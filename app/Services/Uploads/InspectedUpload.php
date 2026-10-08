<?php

namespace App\Services\Uploads;

/**
 * Result of a successful upload inspection. Only these values may be used for
 * storage and delivery – never the client-supplied name or MIME type.
 */
final readonly class InspectedUpload
{
    public function __construct(
        /** Random storage path relative to the uploads disk, e.g. "uploads/2026/10/<random>.pdf". */
        public string $storagePath,
        /** MIME type detected from the file content. */
        public string $mimeType,
        /** Normalised extension from the allowlist. */
        public string $extension,
        public int $size,
        /** SHA-256 of the content (duplicate detection, integrity). */
        public string $sha256,
        /** Sanitised original filename – metadata for display/download names only. */
        public string $originalName,
    ) {}
}
