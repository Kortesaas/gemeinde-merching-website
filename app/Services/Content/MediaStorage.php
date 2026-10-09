<?php

namespace App\Services\Content;

use App\Exceptions\DomainRuleViolation;
use App\Models\Media;
use App\Services\Uploads\RejectedUpload;
use App\Services\Uploads\UploadInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStorage
{
    public function __construct(private readonly UploadInspector $inspector) {}

    public function attach(Media $media, UploadedFile $file): void
    {
        try {
            $i = $this->inspector->inspect($file);
        } catch (RejectedUpload $e) {
            throw new DomainRuleViolation($e->getMessage(), 'file');
        }
        $bytes = file_get_contents($file->getPathname());
        if ($bytes === false) {
            throw new DomainRuleViolation('Die Datei konnte nicht gelesen werden.', 'file');
        }
        $width = $height = null;
        if (str_starts_with($i->mimeType, 'image/')) {
            $dimensions = @getimagesize($file->getPathname());
            if ($dimensions === false || (int) config('uploads.max_image_pixels') < $dimensions[0] * $dimensions[1]) {
                throw new DomainRuleViolation('Das Bild ist ungültig oder hat zu viele Bildpunkte.', 'file');
            }
            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                throw new DomainRuleViolation('Das Bild kann nicht sicher verarbeitet werden.', 'file');
            }
            [$width,$height] = $dimensions;
            // Re-encode to strip EXIF/GPS and non-image payloads before delivery.
            imagealphablending($image, false);
            imagesavealpha($image, true);
            ob_start();
            $ok = match ($i->mimeType) {
                'image/jpeg' => imagejpeg($image, null, 90), 'image/png' => imagepng($image), 'image/webp' => imagewebp($image, null, 90), default => false
            };
            $encoded = ob_get_clean();
            if (! $ok || $encoded === false) {
                throw new DomainRuleViolation('Das Bild konnte nicht verarbeitet werden.', 'file');
            }
            $bytes = $encoded;
        }
        if (! Storage::disk((string) config('uploads.disk'))->put($i->storagePath, $bytes)) {
            throw new DomainRuleViolation('Die Datei konnte nicht gespeichert werden.', 'file');
        }
        $previous = $media->exists ? $media->getOriginal('file_path') : null;
        $media->forceFill(['file_path' => $i->storagePath, 'original_filename' => $i->originalName, 'mime_type' => $i->mimeType, 'extension' => $i->extension, 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'width' => $width, 'height' => $height]);
        if (is_string($previous)) {
            DB::afterCommit(fn () => Storage::disk((string) config('uploads.disk'))->delete($previous));
        }
    }

    public function deleteFileAfterCommit(Media $media): void
    {
        $path = $media->file_path;
        DB::afterCommit(fn () => Storage::disk((string) config('uploads.disk'))->delete($path));
    }

    public function response(Media $media, ?int $width = null): StreamedResponse
    {
        $disk = Storage::disk((string) config('uploads.disk'));
        abort_unless($disk->exists($media->file_path), 404);

        if ($width !== null && in_array($width, [480, 960, 1440], true) && $media->isImage() && $media->width > $width) {
            $image = imagecreatefromstring((string) $disk->get($media->file_path));
            abort_if($image === false, 404);
            $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));
            $scaled = imagescale($image, $width, $height);
            abort_if($scaled === false, 404);
            imagesavealpha($scaled, true);

            return new StreamedResponse(function () use ($scaled) {
                imagewebp($scaled, null, 82);
            }, 200, [
                'Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'no-store',
            ]);
        }

        return $disk->response($media->file_path, 'medium.'.$media->extension, [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $media->isImage() ? 'inline' : 'attachment');
    }
}
