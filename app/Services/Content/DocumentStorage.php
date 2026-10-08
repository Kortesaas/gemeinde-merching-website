<?php

namespace App\Services\Content;

use App\Exceptions\DomainRuleViolation;
use App\Models\Document;
use App\Services\Uploads\RejectedUpload;
use App\Services\Uploads\UploadInspector;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores and delivers document files on the private disk (outside public/)
 * using the secure upload pipeline. File columns are only set here.
 */
class DocumentStorage
{
    /** MIME types that browsers may display inline; everything else is a download. */
    private const INLINE = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly UploadInspector $inspector) {}

    /**
     * Validate and store an upload for $document (not yet saved). A replaced
     * file is removed after the database transaction commits.
     *
     * @throws DomainRuleViolation
     */
    public function attach(Document $document, UploadedFile $file): void
    {
        try {
            $inspected = $this->inspector->inspect($file);
        } catch (RejectedUpload $e) {
            throw new DomainRuleViolation($e->getMessage(), 'file');
        }

        $this->disk()->putFileAs(dirname($inspected->storagePath), $file, basename($inspected->storagePath));

        $previous = $document->exists ? $document->getOriginal('file_path') : null;

        $document->forceFill([
            'file_path' => $inspected->storagePath,
            'original_filename' => $inspected->originalName,
            'mime_type' => $inspected->mimeType,
            'extension' => $inspected->extension,
            'size_bytes' => $inspected->size,
            'sha256' => $inspected->sha256,
        ]);

        if (is_string($previous) && $previous !== $inspected->storagePath) {
            DB::afterCommit(fn () => $this->disk()->delete($previous));
        }
    }

    /**
     * Physically delete the file of a document that is being force-deleted.
     */
    public function deleteFileAfterCommit(Document $document): void
    {
        $path = $document->file_path;
        DB::afterCommit(fn () => $this->disk()->delete($path));
    }

    public function exists(Document $document): bool
    {
        return $this->disk()->exists($document->file_path);
    }

    /**
     * Response with safe headers; Content-Type comes from the detected MIME type.
     */
    public function response(Document $document): StreamedResponse
    {
        $inline = in_array($document->mime_type, self::INLINE, true);
        $filename = $document->original_filename;
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'dokument.'.$document->extension;

        return new StreamedResponse(function () use ($document) {
            $stream = $this->disk()->readStream($document->file_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Length' => (string) $document->size_bytes,
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $filename, $fallback),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; object-src 'none'; sandbox",
        ]);
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('uploads.disk'));
    }
}
