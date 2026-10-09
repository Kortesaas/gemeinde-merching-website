<?php

use App\Enums\PublicationStatus;
use App\Models\Gallery;
use App\Models\Location;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use App\Services\Content\MediaStorage;
use App\Services\Routing\RouteManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Disposable synthetic fixtures for local Docker browser checks only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Browser fixtures require the local environment.');
}

try {
    $manifest = storage_path('app/private/parity-browser-fixtures.json');
    $cleanup = function () use ($manifest): void {
        if (! is_file($manifest)) {
            return;
        }
        $data = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
        foreach ($data['records'] as [$class, $id]) {
            $alias = (new $class)->getMorphClass();
            // Includes fixtures already deleted by an interrupted cleanup attempt.
            DB::table('content_revisions')->where('revisionable_type', $alias)->where('revisionable_id', $id)->delete();
            DB::table('content_proposals')->where('proposable_type', $alias)->where('proposable_id', $id)->delete();
            $model = $class::withTrashed()->find($id);
            if ($model !== null) {
                if (! str_starts_with((string) ($model->getAttributes()['title'] ?? $model->getAttributes()['name'] ?? ''), 'Browser Test')) {
                    throw new RuntimeException('Refusing cleanup of a non-fixture record.');
                }
                if ($model instanceof Media) {
                    Storage::disk((string) config('uploads.disk'))->delete($model->file_path);
                }
                $model->forceDelete();
            }
        }
        DB::table('sessions')->where('id', $data['session_id'])->delete();
        User::query()->whereKey($data['user_id'])->delete();
        unlink($manifest);
    };
    $cleanup();
    if (($argv[1] ?? '') === 'cleanup') {
        exit;
    }

    DB::beginTransaction();
    $user = User::factory()->withTwoFactor()->create(['name' => 'Browser Test Editor', 'email' => 'parity-browser@example.test']);
    $user->givePermissionTo(['admin.access', 'page.view', 'page.create', 'page.edit', 'page.publish', 'page.archive', 'page.delete']);
    $session = app('session')->driver();
    $session->start();
    $session->put([
        app('auth')->guard('web')->getName() => $user->id,
        AdminAuthenticator::AUTHENTICATED_AT => now()->getTimestamp(),
        AdminAuthenticator::MFA_VERIFIED => true,
    ]);
    $session->save();
    $cookieName = config('session.cookie');
    $cookie = app('encrypter')->encrypt(CookieValuePrefix::create($cookieName, app('encrypter')->getKey()).$session->getId(), false);

    $publish = fn ($model) => $model->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
    $media = new Media(['title' => 'Browser Test Image', 'alt_text' => 'Synthetisches Galeriebild']);
    app(MediaStorage::class)->attach($media, UploadedFile::fake()->image('browser-test.png', 320, 180));
    $publish($media);
    $gallery = Gallery::create(['title' => 'Browser Test Gallery']);
    $gallery->items()->create(['media_id' => $media->id, 'caption' => 'Testbildunterschrift', 'sort_order' => 0]);
    $publish($gallery);
    $location = Location::create(['name' => 'Browser Test Location', 'type' => 'veranstaltungsort', 'is_active' => true, 'accessibility_note' => 'Stufenlos erreichbar']);
    $page = Page::create(['title' => 'Browser Test Composition']);
    $page->blocks()->create(['type' => 'heading', 'heading' => 'Testabschnitt', 'heading_level' => 2, 'sort_order' => 0]);
    $page->blocks()->create(['type' => 'text', 'text' => 'Ein synthetischer Textabschnitt.', 'sort_order' => 1]);
    $page->blocks()->create(['type' => 'gallery', 'gallery_id' => $gallery->id, 'sort_order' => 2]);
    $page->blocks()->create(['type' => 'accordion', 'heading' => 'Testinformation aufklappen', 'text' => 'Zusätzliche Testinformation.', 'sort_order' => 3]);
    $page->blocks()->create(['type' => 'location', 'location_id' => $location->id, 'sort_order' => 4]);
    $publish($page);
    app(RouteManager::class)->assign($page, '/browser-test-composition');
    $service = Service::create(['title' => 'Browser Test Service', 'required_items' => 'Synthetischer Nachweis', 'online_service_mode' => 'unavailable']);
    $service->fees()->create(['description' => 'Testgebühr', 'amount' => 12.5, 'sort_order' => 0]);
    $publish($service);
    app(RouteManager::class)->assign($service, '/browser-test-service');

    file_put_contents($manifest, json_encode(['records' => [[Page::class, $page->id], [Service::class, $service->id], [Gallery::class, $gallery->id], [Media::class, $media->id], [Location::class, $location->id]], 'session_id' => $session->getId(), 'user_id' => $user->id], JSON_THROW_ON_ERROR));
    DB::commit();
    echo json_encode(['cookieName' => $cookieName, 'cookie' => $cookie, 'pageId' => $page->id], JSON_THROW_ON_ERROR);

} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    if (isset($media) && $media instanceof Media && isset($media->file_path)) {
        Storage::disk((string) config('uploads.disk'))->delete($media->file_path);
    }
    fwrite(STDERR, 'Local browser fixtures failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
