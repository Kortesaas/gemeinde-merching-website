<?php

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Department;
use App\Models\Document;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Location;
use App\Models\Media;
use App\Models\NavigationItem;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Person;
use App\Models\PublicNotice;
use App\Models\Service;
use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use App\Services\Content\DocumentStorage;
use App\Services\Content\MediaStorage;
use App\Services\Content\ProposalService;
use App\Services\Content\RevisionService;
use App\Services\Routing\RouteManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

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
            $query = $class::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withTrashed();
            }
            $model = $query->find($id);
            if ($model !== null) {
                if (! str_starts_with((string) ($model->getAttributes()['title'] ?? $model->getAttributes()['name'] ?? $model->getAttributes()['display_name'] ?? $model->getAttributes()['label'] ?? ''), 'Browser Test')) {
                    throw new RuntimeException('Refusing cleanup of a non-fixture record.');
                }
                if ($model instanceof Media || $model instanceof Document) {
                    Storage::disk((string) config('uploads.disk'))->delete($model->file_path);
                }
                if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                    $model->forceDelete();
                } else {
                    $model->delete();
                }
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
    $createdFiles = [];
    $user = User::factory()->withTwoFactor()->create(['name' => 'Browser Test Editor', 'email' => 'parity-browser@example.test']);
    $user->givePermissionTo(Permission::all());
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
    app(MediaStorage::class)->attach($media, UploadedFile::fake()->image('browser-test.png', 1600, 900));
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
    for ($i = 1; $i <= 5; $i++) {
        $service->fees()->create(['description' => 'Browser Test Gebühr für eine außergewöhnlich lange Verwaltungsleistungsbeschreibung '.$i, 'amount' => 12.5 + $i, 'sort_order' => $i]);
    }
    $publish($service);
    app(RouteManager::class)->assign($service, '/browser-test-service');

    $department = Department::create(['name' => 'Browser Test Department', 'is_active' => true, 'phone' => '0123 456', 'location_id' => $location->id]);
    $person = Person::create(['display_name' => 'Browser Test Contact', 'last_name' => 'Test', 'is_active' => true, 'phone' => '0123 789']);
    $service->departments()->attach($department->id);
    $service->contacts()->attach($person->id);
    $article = Article::create(['title' => 'Browser Test Meldung mit einem ausgesprochen langen deutschen Verwaltungsinformationstitel', 'summary' => 'Synthetischer Teaser für den Layouttest.', 'body' => "## Informationen\nEin synthetischer Inhalt für die visuelle Prüfung."]);
    $article->media()->attach($media->id);
    $publish($article);
    app(RouteManager::class)->assign($article, '/browser-test-article');
    $event = Event::create(['title' => 'Browser Test Event', 'starts_at' => now()->addDays(2), 'location_id' => $location->id, 'operational_status' => 'cancelled', 'schedule_notice' => 'Synthetischer Hinweis zur Terminänderung.']);
    $publish($event);
    app(RouteManager::class)->assign($event, '/browser-test-event');
    $document = new Document(['title' => 'Browser Test Document', 'year' => 2026]);
    app(DocumentStorage::class)->attach($document, UploadedFile::fake()->createWithContent('browser-test.pdf', "%PDF-1.4\nSynthetic browser fixture"));
    $publish($document);
    $service->documents()->attach($document->id, ['slot' => 'formulare']);
    $notice = PublicNotice::create(['title' => 'Browser Test Notice', 'body' => 'Synthetische amtliche Information.']);
    $notice->documents()->attach($document->id, ['slot' => 'anhaenge']);
    $publish($notice);
    app(RouteManager::class)->assign($notice, '/browser-test-notice');
    $nav = NavigationItem::create(['label' => 'Browser Test Navigation', 'menu' => 'main', 'public_route_id' => $page->canonicalRoute->id, 'is_active' => true]);
    $child = NavigationItem::create(['label' => 'Browser Test Service Link', 'menu' => 'main', 'parent_id' => $nav->id, 'public_route_id' => $service->canonicalRoute->id, 'is_active' => true]);
    $shortcut = NavigationItem::create(['label' => 'Browser Test Shortcut', 'menu' => 'service', 'public_route_id' => $service->canonicalRoute->id, 'is_active' => true]);
    $revision = app(RevisionService::class)->record($page, $user, 'Browser Test initial revision');
    $proposal = app(ProposalService::class)->create($page, $user);
    $extraRecords = [];
    for ($i = 1; $i <= 6; $i++) {
        $extraPerson = Person::create(['display_name' => 'Browser Test Kontakt für sehr lange Zuständigkeitsbezeichnungen '.$i, 'last_name' => 'Test', 'is_active' => true, 'responsibilities' => 'Synthetische Verwaltungsaufgaben', 'phone' => '0123 000']);
        $service->contacts()->attach($extraPerson->id);
        $extraRecords[] = [Person::class, $extraPerson->id];
        $extraDocument = new Document(['title' => 'Browser Test Ausführliches Formular für Verwaltungsleistungsinformationen '.$i]);
        app(DocumentStorage::class)->attach($extraDocument, UploadedFile::fake()->createWithContent('browser-test-sehr-lange-deutsche-formular-und-dateinamenbeschreibung-'.$i.'.pdf', "%PDF-1.4\nSynthetic fixture"));
        $createdFiles[] = $extraDocument->file_path;
        $publish($extraDocument);
        $service->documents()->attach($extraDocument->id, ['slot' => 'formulare']);
        $extraRecords[] = [Document::class, $extraDocument->id];
    }
    $organization = Organization::create(['name' => 'Browser Test Verein mit einem sehr langen deutschen Organisationsnamen für Verwaltungsangelegenheiten', 'type' => 'verein', 'is_active' => true]);
    $extraRecords[] = [Organization::class, $organization->id];
    $notice->forceFill(['expires_at' => now()->subHour()])->save();
    $records = [[NavigationItem::class, $child->id], [NavigationItem::class, $nav->id], [NavigationItem::class, $shortcut->id], [PublicNotice::class, $notice->id], [Event::class, $event->id], [Article::class, $article->id], [Page::class, $page->id], [Service::class, $service->id], [Gallery::class, $gallery->id], [Media::class, $media->id], [Department::class, $department->id], [Person::class, $person->id], [Location::class, $location->id], [Document::class, $document->id]];

    $records = [...$records, ...$extraRecords];
    file_put_contents($manifest, json_encode(['records' => $records, 'session_id' => $session->getId(), 'user_id' => $user->id], JSON_THROW_ON_ERROR));
    DB::commit();
    echo json_encode(['cookieName' => $cookieName, 'cookie' => $cookie, 'pageId' => $page->id, 'articleId' => $article->id, 'eventId' => $event->id, 'serviceId' => $service->id, 'mediaId' => $media->id, 'proposalId' => $proposal->id, 'revisionNumber' => $revision?->revision_number], JSON_THROW_ON_ERROR);

} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    if (isset($media) && $media instanceof Media && isset($media->file_path)) {
        Storage::disk((string) config('uploads.disk'))->delete($media->file_path);
    }
    if (isset($document) && $document instanceof Document && isset($document->file_path)) {
        Storage::disk((string) config('uploads.disk'))->delete($document->file_path);
    }
    foreach (($createdFiles ?? []) as $createdFile) {
        Storage::disk((string) config('uploads.disk'))->delete($createdFile);
    }
    fwrite(STDERR, 'Local browser fixtures failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
