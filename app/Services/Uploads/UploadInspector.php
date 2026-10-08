<?php

namespace App\Services\Uploads;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Validates an uploaded file against config/uploads.php and derives safe
 * storage metadata. The client-supplied filename and MIME type are never
 * trusted: the extension must be allowlisted AND the MIME type detected from
 * the file content must match that extension.
 *
 * Storing the file is up to the caller, e.g.:
 *   Storage::disk(config('uploads.disk'))->putFileAs(dirname($i->storagePath), $file, basename($i->storagePath));
 */
class UploadInspector
{
    /**
     * @throws RejectedUpload
     */
    public function inspect(UploadedFile $file): InspectedUpload
    {
        if (! $file->isValid()) {
            throw new RejectedUpload('Die Datei konnte nicht hochgeladen werden.');
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (int) config('uploads.max_kilobytes') * 1024) {
            throw new RejectedUpload('Die Datei ist leer oder zu groß.');
        }

        /** @var array<string, list<string>> $types */
        $types = config('uploads.types');
        $extension = Str::lower($file->getClientOriginalExtension());

        if (! array_key_exists($extension, $types)) {
            throw new RejectedUpload('Dieser Dateityp ist nicht erlaubt.');
        }

        // Detected from the file content (libmagic), never from the request or
        // the filename.
        $detectedMime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());

        if (! in_array($detectedMime, $types[$extension], true)) {
            throw new RejectedUpload('Der Dateiinhalt passt nicht zur Dateiendung.');
        }

        $storagePath = trim((string) config('uploads.directory'), '/')
            .'/'.now()->format('Y/m')
            .'/'.Str::lower(Str::random(40)).'.'.$extension;

        return new InspectedUpload(
            storagePath: $storagePath,
            mimeType: $detectedMime,
            extension: $extension,
            size: $size,
            sha256: (string) hash_file('sha256', $file->getRealPath()),
            originalName: $this->sanitizeOriginalName($file->getClientOriginalName()),
        );
    }

    private function sanitizeOriginalName(string $name): string
    {
        // Strip paths, control characters and anything that is not a normal
        // filename character; keep umlauts for readable download names.
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN\s._()-]+/u', '_', $name) ?? 'datei';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '', ' .');

        return Str::limit($name === '' ? 'datei' : $name, 200, '');
    }
}
