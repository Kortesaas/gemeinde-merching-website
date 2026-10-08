<?php

namespace Tests\Concerns;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Document;
use App\Models\Page;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Minimal fixtures for content tests (no real personal data).
 */
trait CreatesContent
{
    protected const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    protected function article(array $attributes = [], PublicationStatus $status = PublicationStatus::Draft, ?CarbonImmutable $publishAt = null, ?CarbonImmutable $expiresAt = null): Article
    {
        $article = new Article(['title' => 'Testartikel '.Str::random(6), ...$attributes]);
        $article->forceFill(['status' => $status, 'publish_at' => $publishAt, 'expires_at' => $expiresAt])->save();

        return $article;
    }

    protected function page(array $attributes = [], PublicationStatus $status = PublicationStatus::Published): Page
    {
        $page = new Page(['title' => 'Testseite '.Str::random(6), ...$attributes]);
        $page->forceFill(['status' => $status, 'publish_at' => $status === PublicationStatus::Draft ? null : now()->subDay()])->save();

        return $page;
    }

    protected function document(array $attributes = [], PublicationStatus $status = PublicationStatus::Published): Document
    {
        $path = 'uploads/test/'.Str::lower(Str::random(40)).'.pdf';
        Storage::disk((string) config('uploads.disk'))->put($path, self::PDF);

        $document = new Document(['title' => 'Testdokument '.Str::random(6), ...$attributes]);
        $document->forceFill([
            'file_path' => $path,
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => strlen(self::PDF),
            'sha256' => hash('sha256', self::PDF),
            'status' => $status,
            'publish_at' => $status === PublicationStatus::Draft ? null : now()->subDay(),
        ])->save();

        return $document;
    }

    /**
     * User with exactly the given permissions (no role).
     *
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions): User
    {
        $user = $this->createUser(role: null);
        $user->givePermissionTo(['admin.access', ...$permissions]);

        return $user->refresh();
    }
}
