<?php

namespace Database\Seeders;

use App\Contracts\Proposable;
use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Enums\AccessibilityStatus;
use App\Enums\AlertSeverity;
use App\Enums\CategoryContext;
use App\Enums\EventOperationalStatus;
use App\Enums\LocationType;
use App\Enums\NavigationMenu;
use App\Enums\OnlineServiceMode;
use App\Enums\OrganizationType;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Committee;
use App\Models\ContactRoute;
use App\Models\ContentProposal;
use App\Models\CouncilMember;
use App\Models\CouncilTerm;
use App\Models\Department;
use App\Models\Document;
use App\Models\Event;
use App\Models\ExternalResource;
use App\Models\Gallery;
use App\Models\LifeSituation;
use App\Models\Location;
use App\Models\Media;
use App\Models\NavigationItem;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Person;
use App\Models\PublicNotice;
use App\Models\PublicRoute;
use App\Models\SearchSynonym;
use App\Models\Service;
use App\Models\SiteAlert;
use App\Models\SiteSettings;
use App\Models\Tag;
use App\Models\User;
use App\Services\Authorization\RoleSynchronizer;
use App\Services\Content\DocumentStorage;
use App\Services\Content\MediaStorage;
use App\Services\Content\ProposalService;
use App\Services\Content\RevisionService;
use App\Services\Routing\RedirectManager;
use App\Services\Routing\RouteManager;
use App\Services\Search\SearchIndexer;
use App\Support\Auth\DevelopmentAccounts;
use App\Support\Authorization\Role;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEVELOPMENT-ONLY demonstration content for visual and editorial review.
 *
 * Every name, address, phone number, fee, date and text created here is
 * fictional sample content ("Musterinhalt"). It is NOT information about the
 * Gemeinde Merching and must never reach a production database.
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DevelopmentDemoSeeder
 *
 * The seeder refuses to run outside local/development/testing environments
 * and on a database that already contains editorial content. Demo records
 * are tagged with the source system "development-demo".
 */
class DevelopmentDemoSeeder extends Seeder
{
    public const SOURCE_SYSTEM = 'development-demo';

    /**
     * Local-only full administrator for trying the CMS. The reserved
     * demo.localhost domain is refused outside local environments
     * (User model, login, deploy:check); see docs/local-development.md.
     */
    public const ADMIN_EMAIL = 'admin@'.DevelopmentAccounts::DOMAIN;

    public const ADMIN_PASSWORD = 'Merching-Demo-2026';

    /** Base32 TOTP secret for any authenticator app (local demo only). */
    public const ADMIN_TOTP_SECRET = 'MERCHINGDEMOVERWALTUNGLOKAL23456';

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Model> */
    private array $r = [];

    private CarbonImmutable $now;

    /**
     * Typed access to a record created earlier in this run.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function ref(string $key, string $class): Model
    {
        $model = $this->r[$key] ?? null;
        if (! $model instanceof $class) {
            throw new RuntimeException('Demo record '.$key.' is missing.');
        }

        return $model;
    }

    public function run(): void
    {
        if (! DevelopmentAccounts::allowed()) {
            throw new RuntimeException('DevelopmentDemoSeeder darf nur in lokalen Entwicklungsumgebungen laufen.');
        }
        if (Page::withTrashed()->exists() || Article::withTrashed()->exists() || Service::withTrashed()->exists()) {
            throw new RuntimeException('Die Datenbank enthält bereits Inhalte. Demo-Daten nur in eine frische Entwicklungsdatenbank laden (php artisan migrate:fresh --seed).');
        }

        $this->now = CarbonImmutable::now((string) config('site.timezone'));
        app(RoleSynchronizer::class)->sync();

        DB::transaction(function () {
            $this->users();
            Auth::setUser($this->users['redaktion']);
            $this->taxonomy();
            $this->externalResources();
            $this->locations();
            $this->departmentsAndPeople();
            $this->contactRoutes();
            $this->settings();
            $this->media();
            SiteSettings::query()->whereKey(1)->update(['homepage_media_id' => $this->ref('img.rathaus', Media::class)->getKey()]);
            $this->documents();
            $this->organizations();
            $this->services();
            $this->lifeSituations();
            $this->articles();
            $this->events();
            $this->notices();
            $this->pages();
            $this->council();
            $this->alertsAndSynonyms();
            $this->navigation();
            $this->redirects();
            $this->history();
            $this->proposals();
            $this->qualityExamples();
        });
        Auth::forgetUser();

        app(SearchIndexer::class)->rebuild();
        $this->command->info('Demo-Inhalte angelegt. Alle Angaben sind fiktiv und nur für die Entwicklung bestimmt.');
    }

    // ------------------------------------------------------------------ helpers

    /** Berlin wall-clock time relative to today, stored as UTC. */
    private function at(int $days, string $time = '00:00'): CarbonImmutable
    {
        return $this->now->addDays($days)->setTimeFromTimeString($time)->utc();
    }

    private function publish(Model $model, int $daysAgo = 30, ?int $expiresInDays = null): Model
    {
        $model->forceFill([
            'status' => PublicationStatus::Published,
            'publish_at' => $this->now->subDays($daysAgo)->setTime(8, 0)->utc(),
            'expires_at' => $expiresInDays === null ? null : $this->now->addDays($expiresInDays)->setTime(23, 59)->utc(),
        ])->save();
        $this->tag($model);

        return $model;
    }

    private function tag(Model $model): void
    {
        if (method_exists($model, 'sourceReferences') && ! $model->sourceReferences()->exists()) {
            $model->sourceReferences()->create(['source_system' => self::SOURCE_SYSTEM, 'source_id' => $model->getMorphClass().'-'.$model->getKey(), 'imported_at' => now()]);
        }
    }

    private function route(Routable&Model $model, ?string $path = null): void
    {
        $routes = app(RouteManager::class);
        $routes->assign($model, $path ?? $routes->suggestPath($model));
    }

    private function category(CategoryContext $context, string $name): Category
    {
        return Category::query()->where('context', $context->value)->where('name', $name)->firstOrFail();
    }

    private function phone(int $extension): string
    {
        // Numbers reserved by the Bundesnetzagentur for fiction (089 99998 xxx).
        return '089 99998 '.str_pad((string) $extension, 3, '0', STR_PAD_LEFT);
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    // ------------------------------------------------------------------ users

    private function users(): void
    {
        $admin = User::create(['name' => 'Demo-Administration', 'email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD, 'is_active' => true]);
        $admin->forceFill(['password_changed_at' => now(), 'two_factor_secret' => self::ADMIN_TOTP_SECRET, 'two_factor_confirmed_at' => now()])->save();
        $admin->assignRole(Role::Administrator->value);
        $this->users['admin'] = $admin;

        $domain = '@'.DevelopmentAccounts::DOMAIN;
        $accounts = [
            'redaktion' => ['Sabine Probe (Demo)', 'sabine.probe'.$domain, Role::Chefredaktion],
            'fachbereich' => ['Petra Beispiel (Demo)', 'petra.beispiel'.$domain, Role::Fachbereichsredaktion],
            'termine' => ['Tobias Muster (Demo)', 'tobias.muster'.$domain, Role::Veranstaltungsredaktion],
            'pruefung' => ['Jana Exempel (Demo)', 'jana.exempel'.$domain, Role::Reviewer],
        ];
        foreach ($accounts as $key => [$name, $email, $role]) {
            // Random, never displayed password: only the documented admin has local credentials.
            $user = User::create(['name' => $name, 'email' => $email, 'password' => Str::password(48), 'is_active' => true]);
            $user->assignRole($role->value);
            $this->users[$key] = $user;
        }
    }

    // ------------------------------------------------------------------ taxonomy

    private function taxonomy(): void
    {
        $categories = [
            CategoryContext::Article->value => ['Aus dem Rathaus', 'Bauen und Verkehr', 'Kinder und Familie', 'Umwelt und Natur', 'Vereinsleben'],
            CategoryContext::Event->value => ['Kultur', 'Sport und Freizeit', 'Sitzungen', 'Vereine', 'Senioren'],
            CategoryContext::Document->value => ['Formulare und Anträge', 'Satzungen und Ortsrecht', 'Haushalt und Finanzen', 'Bauleitplanung', 'Merkblätter'],
            CategoryContext::PublicNotice->value => ['Amtliche Bekanntmachungen', 'Bauleitplanung', 'Sitzungen des Gemeinderats', 'Wahlen'],
            CategoryContext::Service->value => ['Meldewesen und Ausweise', 'Bauen und Wohnen', 'Steuern und Abgaben', 'Familie und Soziales', 'Abfall und Umwelt', 'Ordnung und Verkehr', 'Standesamt'],
            CategoryContext::Organization->value => ['Sport', 'Kultur und Brauchtum', 'Soziales und Familie', 'Natur und Garten', 'Gastronomie', 'Handwerk und Handel'],
        ];
        foreach ($categories as $context => $names) {
            foreach ($names as $index => $name) {
                Category::create(['context' => $context, 'name' => $name, 'slug' => Str::slug($name), 'sort_order' => $index]);
            }
        }
        foreach (['Spielplatz', 'Baustelle', 'Umleitung', 'Ehrenamt', 'Kinderbetreuung', 'Haushalt', 'Klimaschutz', 'Seefest'] as $name) {
            Tag::create(['name' => $name, 'slug' => Str::slug($name)]);
        }
    }

    // ------------------------------------------------------------------ external resources

    private function externalResources(): void
    {
        $resources = [
            'portal' => ['Online-Anträge im Beispiel-Serviceportal', 'https://www.example.org/serviceportal/merching-demo', 'portal', 'Beispiel-Serviceportal (Demo)', 'Zentrales Portal für Online-Anträge. In dieser Demonstration führt der Link zu einer Beispieladresse.', 'Sie verlassen die Website der Gemeinde. Es gilt die Datenschutzerklärung des Portalbetreibers.'],
            'ausweis' => ['Termin für Personalausweis online vereinbaren', 'https://www.example.org/termine/ausweis-demo', 'online_service', 'Beispiel-Terminportal (Demo)', 'Online-Terminvereinbarung für das Bürgerbüro.', 'Externer Dienst. Für die Terminbuchung werden Name und E-Mail-Adresse an den Anbieter übermittelt.'],
            'hundesteuer' => ['Hund online anmelden', 'https://www.example.org/antraege/hundesteuer-demo', 'online_service', 'Beispiel-Serviceportal (Demo)', 'Online-Anmeldung eines Hundes zur Hundesteuer.', 'Externer Dienst mit eigener Datenschutzerklärung.'],
            'sperrmuell' => ['Sperrmüll online anmelden', 'https://www.example.org/abfall/sperrmuell-demo', 'online_service', 'Beispiel-Abfallportal des Landkreises (Demo)', 'Anmeldung der Sperrmüllabholung beim zuständigen Entsorger.', null],
            'fuehrungszeugnis' => ['Führungszeugnis online beantragen', 'https://www.example.org/bund/fuehrungszeugnis-demo', 'online_service', 'Beispiel-Bundesportal (Demo)', 'Online-Antrag mit Online-Ausweisfunktion.', 'Für den Online-Antrag benötigen Sie einen Personalausweis mit aktivierter Online-Ausweisfunktion.'],
            'briefwahl' => ['Briefwahlunterlagen online anfordern', 'https://www.example.org/wahlen/briefwahl-demo', 'form', 'Beispiel-Wahlportal (Demo)', 'Antrag auf Wahlschein und Briefwahlunterlagen.', null],
            'bayernatlas' => ['BayernAtlas – Karten und Luftbilder', 'https://geoportal.bayern.de/bayernatlas', 'map', 'Landesamt für Digitalisierung, Breitband und Vermessung', 'Amtliche Karten, Luftbilder und Flurstücke in Bayern.', 'Externe Kartenanwendung. Sie wird erst nach Klick geöffnet.'],
            'osm_rathaus' => ['Lage des Rathauses in OpenStreetMap', 'https://www.openstreetmap.org/#map=17/48.2470/10.9860', 'map', 'OpenStreetMap', null, 'Externe Karte. Beim Öffnen werden Daten an OpenStreetMap übertragen.'],
            'osm_see' => ['Badeplatz in OpenStreetMap anzeigen', 'https://www.openstreetmap.org/#map=16/48.2550/10.9700', 'map', 'OpenStreetMap', null, 'Externe Karte. Beim Öffnen werden Daten an OpenStreetMap übertragen.'],
            'badewasser' => ['Badegewässerqualität in Bayern', 'https://www.lgl.bayern.de/gesundheit/hygiene/wasser/badewasser/', 'information', 'Bayerisches Landesamt für Gesundheit und Lebensmittelsicherheit', 'Aktuelle Messwerte und Informationen zur Badegewässerqualität.', null],
            'ratsinfo' => ['Ratsinformationssystem (Beispiel)', 'https://www.example.org/ratsinfo/merching-demo', 'information', 'Beispiel-Ratsinformationsdienst (Demo)', 'Sitzungstermine, Tagesordnungen und Niederschriften.', null],
            'landratsamt' => ['Landratsamt – Kfz-Zulassung', 'https://www.example.org/landratsamt/kfz-demo', 'authority', 'Beispiel-Landratsamt (Demo)', 'Zuständig für Kfz-Zulassung und Führerscheine.', null],
        ];
        foreach ($resources as $key => [$title, $url, $type, $provider, $description, $privacy]) {
            $resource = ExternalResource::create(['title' => $title, 'url' => $url, 'type' => $type, 'provider_name' => $provider, 'description' => $description, 'privacy_note' => $privacy]);
            $this->publish($resource, 120);
            $this->r['link.'.$key] = $resource;
        }
    }

    // ------------------------------------------------------------------ locations

    private function locations(): void
    {
        $locations = [
            'rathaus' => ['Rathaus', LocationType::Administration, 'Sitz der Gemeindeverwaltung mit Bürgerbüro, Bauamt und Standesamt.', 'Musterstraße 1', "Mo–Fr 08:00–12:00 Uhr\nDo zusätzlich 14:00–18:00 Uhr\nMittwochnachmittag geschlossen", 'Stufenloser Zugang über den Seiteneingang. Aufzug zu allen Etagen, barrierefreie Toilette im Erdgeschoss.', 'osm_rathaus', 48.247, 10.986, 100],
            'bauhof' => ['Bauhof', LocationType::Facility, 'Gemeindlicher Bauhof für Straßenunterhalt, Grünpflege und Winterdienst.', 'Gewerbering 12', "Mo–Do 07:00–16:00 Uhr\nFr 07:00–12:00 Uhr", null, null, null, null, 140],
            'wertstoffhof' => ['Wertstoffhof', LocationType::Recycling, 'Annahme von Wertstoffen, Grüngut und Elektrokleingeräten aus privaten Haushalten.', 'Am Wertstoffhof 3', "Di 16:00–19:00 Uhr\nFr 14:00–17:00 Uhr\nSa 09:00–12:00 Uhr", 'Ebenerdiges Gelände mit Schotterflächen. Container teilweise nur über Stufen erreichbar.', null, null, null, 150],
            'halle' => ['Mehrzweckhalle', LocationType::Venue, 'Halle für Vereinssport, Versammlungen und Veranstaltungen mit bis zu 400 Plätzen.', 'Schulweg 5', null, 'Stufenloser Haupteingang, barrierefreie Toilette, induktive Höranlage im großen Saal.', null, null, null, 160],
            'see' => ['Badeplatz am Seeufer', LocationType::Leisure, 'Liegewiese mit Badesteg, Spielplatz und Kiosk in der Badesaison.', 'Seeweg', "Frei zugänglich\nKiosk Mai–September täglich 11:00–19:00 Uhr (bei gutem Wetter)", 'Befestigter Weg bis zur Liegewiese, Zugang zum Wasser über Kiesstrand.', 'osm_see', 48.255, 10.970, 170],
            'buecherei' => ['Gemeindebücherei', LocationType::Facility, 'Bücher, Hörbücher, Zeitschriften und Spiele für alle Altersgruppen.', 'Kirchplatz 2', "Di 15:00–18:00 Uhr\nDo 09:00–11:00 und 15:00–19:00 Uhr\nSa 10:00–12:00 Uhr", 'Eingang mit Rampe, Medien teilweise in hohen Regalen.', null, null, null, 180],
            'feuerwehr' => ['Feuerwehrhaus', LocationType::Facility, 'Standort der Freiwilligen Feuerwehr.', 'Florianstraße 8', null, null, null, null, null, 190],
            'friedhof' => ['Gemeindefriedhof', LocationType::Facility, 'Friedhof mit Aussegnungshalle.', 'Friedhofsweg 1', "April–September 07:00–20:00 Uhr\nOktober–März 08:00–17:00 Uhr", 'Hauptwege befestigt, Aussegnungshalle stufenlos erreichbar.', null, null, null, 200],
        ];
        foreach ($locations as $key => [$name, $type, $description, $street, $hours, $access, $map, $lat, $lng, $phone]) {
            $location = Location::create(['name' => $name, 'type' => $type, 'description' => $description, 'street' => $street, 'postal_code' => '86504', 'city' => 'Merching', 'phone' => $this->phone($phone), 'opening_hours' => $hours, 'accessibility_note' => $access, 'map_resource_id' => $map ? $this->ref('link.'.$map, ExternalResource::class)->getKey() : null, 'latitude' => $lat, 'longitude' => $lng, 'is_active' => true, 'sort_order' => count($this->r)]);
            $this->tag($location);
            $this->r['loc.'.$key] = $location;
        }
        $this->route($this->ref('loc.rathaus', Location::class), '/rathaus');
        $this->route($this->ref('loc.wertstoffhof', Location::class), '/wertstoffhof');
        $this->route($this->ref('loc.see', Location::class), '/freizeit/badeplatz');
    }

    // ------------------------------------------------------------------ departments & people

    private function departmentsAndPeople(): void
    {
        $departments = [
            'zentral' => ['Hauptverwaltung', 'Hauptamt', 'Zentrale Anlaufstelle für allgemeine Anliegen, Sitzungsdienst und Personal.', 10, 'poststelle', 'rathaus', "Mo–Fr 08:00–12:00 Uhr\nDo zusätzlich 14:00–18:00 Uhr"],
            'buergerbuero' => ['Bürgerbüro', null, 'Meldewesen, Ausweise und Pässe, Führungszeugnisse, Fundsachen und Gewerbe.', 20, 'buergerbuero', 'rathaus', "Mo–Fr 08:00–12:00 Uhr\nDo zusätzlich 14:00–18:00 Uhr\nTermine nach Vereinbarung"],
            'finanzen' => ['Kämmerei und Steueramt', 'Kämmerei', 'Gemeindehaushalt, Steuern, Gebühren und Beiträge.', 30, 'kaemmerei', 'rathaus', null],
            'bauamt' => ['Bauamt', null, 'Bauanträge, Bauleitplanung, gemeindliche Liegenschaften und Straßen.', 40, 'bauamt', 'rathaus', "Mo, Di, Fr 08:00–12:00 Uhr\nDo 14:00–18:00 Uhr\nMittwoch geschlossen"],
            'ordnung' => ['Ordnungsamt', null, 'Öffentliche Sicherheit, Veranstaltungen, Verkehr und Gewerbeangelegenheiten.', 50, 'ordnungsamt', 'rathaus', null],
            'standesamt' => ['Standesamt', null, 'Geburten, Eheschließungen und Sterbefälle.', 60, 'standesamt', 'rathaus', 'Termine nur nach Vereinbarung'],
            'bauhof' => ['Bauhof', null, 'Straßenunterhalt, Grünpflege, Winterdienst und Sperrmüllfragen.', 70, 'bauhof', 'bauhof', "Mo–Do 07:00–16:00 Uhr\nFr 07:00–12:00 Uhr"],
        ];
        foreach ($departments as $key => [$name, $short, $description, $phone, $mail, $location, $hours]) {
            $department = Department::create(['name' => $name, 'short_name' => $short, 'description' => $description, 'phone' => $this->phone($phone), 'email' => $mail.'@merching.example', 'location_id' => $this->ref('loc.'.$location, Location::class)->getKey(), 'opening_hours' => $hours, 'is_active' => true, 'sort_order' => $phone]);
            $this->tag($department);
            $this->r['dep.'.$key] = $department;
        }
        $this->route($this->ref('dep.buergerbuero', Department::class), '/rathaus/buergerbuero');
        $this->route($this->ref('dep.bauamt', Department::class), '/rathaus/bauamt');

        $people = [
            'mustermann' => ['Frau', null, 'Erika', 'Mustermann', 'Leitung Bürgerbüro', "Pass- und Ausweiswesen\nMeldewesen\nFührungszeugnisse", 21, 'EG 03', 'Mo–Fr vormittags, Do auch nachmittags', ['buergerbuero' => 'Leitung']],
            'muster' => ['Herr', null, 'Max', 'Muster', 'Sachbearbeitung Bürgerbüro', "An-, Um- und Abmeldungen\nFundsachen\nGewerbeanmeldungen", 22, 'EG 04', 'Mo–Fr vormittags', ['buergerbuero' => null]],
            'beispiel' => ['Frau', null, 'Anna', 'Beispiel', 'Sachbearbeitung Steuern und Abgaben', "Hundesteuer\nGrundsteuer\nWasser- und Abwassergebühren", 31, '1. OG 12', 'Mo, Di, Do vormittags', ['finanzen' => null]],
            'probe' => ['Herr', null, 'Jonas', 'Probe', 'Kämmerer', 'Haushalt, Finanzplanung und Beteiligungen', 30, '1. OG 10', null, ['finanzen' => 'Leitung']],
            'platzhalter' => ['Frau', 'Dr.', 'Maximiliane', 'Musterfrau-Beispielhausen', 'Leitung Bauamt und Geschäftsleitung Bauleitplanung, Liegenschaftsverwaltung und kommunaler Hochbau', "Bauanträge und Bauvoranfragen\nBauleitplanung und Flächennutzungsplan\nGemeindliche Liegenschaften\nStraßenausbau und Erschließung\nDenkmalschutz in Abstimmung mit der unteren Denkmalschutzbehörde", 41, '2. OG 21', 'Termine nach Vereinbarung', ['bauamt' => 'Leitung']],
            'exempel' => ['Herr', null, 'Lukas', 'Exempel', 'Sachbearbeitung Bauamt', 'Bauanträge, Hausnummern, Aufgrabungen', 42, '2. OG 22', 'Mo, Di, Fr vormittags', ['bauamt' => null]],
            'demo' => ['Frau', null, 'Julia', 'Demo', 'Sachbearbeitung Ordnungsamt', "Veranstaltungen und Plakatierung\nVerkehrsrechtliche Anordnungen\nHunde und Fundtiere", 51, 'EG 07', null, ['ordnung' => null]],
            'vorlage' => ['Herr', null, 'Stefan', 'Vorlage', 'Standesbeamter', 'Eheschließungen, Geburten, Sterbefälle, Namensrecht', 61, 'EG 09', 'Termine nach Vereinbarung', ['standesamt' => 'Leitung']],
            'entwurf' => ['Frau', null, 'Miriam', 'Entwurf', 'Standesbeamtin', 'Eheschließungen und Urkunden', 62, 'EG 09', 'Di und Do vormittags', ['standesamt' => null]],
            'muster2' => ['Herr', null, 'Thomas', 'Mustermeier', 'Leitung Bauhof', 'Winterdienst, Grünpflege, Sperrmüllfragen', 71, null, 'Mo–Do 07:00–15:00 Uhr', ['bauhof' => 'Leitung']],
            'zentrale' => [null, null, null, null, 'Telefonzentrale', 'Allgemeine Auskünfte und Weitervermittlung', 10, 'EG Empfang', 'Zu den Öffnungszeiten des Rathauses', ['zentral' => null]],
            'sitzung' => ['Frau', null, 'Carla', 'Beispielmann', 'Sitzungsdienst und Personal', "Sitzungen des Gemeinderats\nAusschüsse\nPersonalangelegenheiten", 12, '1. OG 01', null, ['zentral' => null]],
        ];
        $order = 0;
        foreach ($people as $key => [$salutation, $academic, $first, $last, $job, $responsibilities, $phone, $room, $availability, $memberships]) {
            $person = Person::create(['salutation' => $salutation, 'academic_title' => $academic, 'first_name' => $first, 'last_name' => $last ?? 'Telefonzentrale', 'display_name' => $key === 'zentrale' ? 'Telefonzentrale Rathaus' : null, 'job_title' => $job, 'responsibilities' => $responsibilities, 'phone' => $this->phone($phone), 'email' => $first ? Str::slug($first.'.'.$last, '.').'@merching.example' : 'zentrale@merching.example', 'room' => $room, 'availability' => $availability, 'is_active' => true, 'sort_order' => $order++]);
            foreach ($memberships as $department => $function) {
                $this->ref('dep.'.$department, Department::class)->people()->attach($person->getKey(), ['function_label' => $function, 'sort_order' => $order]);
            }
            $this->tag($person);
            $this->r['person.'.$key] = $person;
        }
        // Former employee: kept for history, never shown publicly.
        $former = Person::create(['salutation' => 'Herr', 'first_name' => 'Klaus', 'last_name' => 'Ehemalig', 'job_title' => 'Ehemalige Sachbearbeitung', 'is_active' => false]);
        $this->tag($former);
    }

    private function contactRoutes(): void
    {
        $routes = [
            'allgemein' => ['Allgemeine Anfrage an das Rathaus', 'Für alle Anliegen, die Sie keinem Bereich zuordnen können.', 'zentral'],
            'buergerbuero' => ['Bürgerbüro, Ausweise und Meldewesen', 'Fragen zu An- und Ummeldung, Ausweisen und Führungszeugnissen.', 'buergerbuero'],
            'bauamt' => ['Bauen und Bauleitplanung', 'Fragen zu Bauanträgen, Bebauungsplänen und Grundstücken.', 'bauamt'],
            'website' => ['Hinweis zur Website', 'Fehler, veraltete Angaben oder Barrieren auf dieser Website melden.', 'zentral'],
        ];
        $order = 0;
        foreach ($routes as $key => [$label, $explanation, $department]) {
            $this->r['contact.'.$key] = ContactRoute::create(['label' => $label, 'explanation' => $explanation, 'recipients' => ['demo-'.$key.'@merching.example'], 'department_id' => $this->ref('dep.'.$department, Department::class)->getKey(), 'is_active' => true, 'sort_order' => $order++]);
        }
    }

    private function settings(): void
    {
        $settings = SiteSettings::query()->firstOrNew(['id' => 1]);
        $settings->forceFill([
            'id' => 1,
            'municipality_name' => 'Gemeinde Merching',
            'town_hall_location_id' => $this->ref('loc.rathaus', Location::class)->getKey(),
            'central_department_id' => $this->ref('dep.zentral', Department::class)->getKey(),
            'central_contact_route_id' => $this->ref('contact.website', ContactRoute::class)->getKey(),
            'works_department_id' => $this->ref('dep.bauhof', Department::class)->getKey(),
            'recycling_location_id' => $this->ref('loc.wertstoffhof', Location::class)->getKey(),
            'postal_address' => "Gemeinde Merching (Demo)\nMusterstraße 1\n86504 Merching",
            'legal_contact' => 'Demonstrationsangabe – vor Veröffentlichung durch geprüfte Angaben ersetzen.',
            'default_seo_title' => 'Gemeinde Merching',
            'default_meta_description' => 'Demonstrationsinhalt: Bürgerservice, Aktuelles und Veranstaltungen der Gemeinde Merching.',
        ])->save();
    }

    // ------------------------------------------------------------------ media & documents

    private function media(): void
    {
        $images = [
            'rathaus' => ['Rathaus (Illustration)', 'Illustration eines dreistöckigen Rathauses mit Uhrengiebel und Blumenkästen an den Fenstern', 'Das Rathaus – Demonstrationsbild', 50, 40],
            'see' => ['Badesee (Illustration)', 'Illustration eines Sees mit Segelbooten vor einem Waldsaum', 'Blick über den See – Demonstrationsbild', 50, 55],
            'fest' => ['Festzelt am Abend (Illustration)', 'Illustration eines Festzelts mit Lichterkette in der Abenddämmerung', null, 45, 50],
            'spielplatz' => ['Spielschiff (Illustration)', 'Illustration eines hölzernen Spielschiffs mit Segel auf einem Sandspielplatz', 'Das neue Spielschiff – Demonstrationsbild', 50, 65],
            'baustelle' => ['Straßenbaustelle (Illustration)', 'Illustration einer gesperrten Straße mit rot-weißen Absperrungen und Umleitungsschild', null, 40, 60],
            'maibaum' => ['Maibaum (Illustration)', 'Illustration eines weiß-blau gestreiften Maibaums mit Zunftschildern', null, 50, 30],
            'feuerwehr' => ['Feuerwehrhaus (Illustration)', 'Illustration eines Feuerwehrhauses mit drei roten Toren', null, 50, 55],
            'herbst' => ['Herbstlicher Feldweg (Illustration)', 'Illustration eines Feldwegs zwischen abgeernteten Feldern mit herbstlichen Bäumen', null, 50, 60],
            'markt' => ['Wochenmarkt (Illustration)', 'Illustration dreier Marktstände mit gestreiften Markisen und Obst', null, 50, 60],
            'radweg' => ['Radweg (Illustration)', 'Illustration eines Radwegs durch Felder mit einer radfahrenden Person', null, 52, 60],
            'winter' => ['Winterdienst (Illustration, klein)', 'Kleine Illustration eines verschneiten Hauses', null, 50, 50],
            'grusswort' => ['Grußwort (Platzhalter-Silhouette)', 'Neutrale Silhouette als Platzhalter für ein Bild zum Grußwort', null, 50, 40],
        ];
        foreach ($images as $key => [$title, $alt, $caption, $fx, $fy]) {
            $media = new Media(['title' => $title, 'alt_text' => $alt, 'caption' => $caption, 'copyright' => 'Demo-Illustration (frei erfunden)', 'creator' => 'DevelopmentDemoSeeder', 'focal_x' => $fx, 'focal_y' => $fy]);
            app(MediaStorage::class)->attach($media, $this->upload($key.'.jpg', (string) file_get_contents(database_path('demo/media/'.$key.'.jpg'))));
            $media->save();
            $this->publish($media, 60);
            $this->r['img.'.$key] = $media;
        }
        // Decorative image (empty alternative text on purpose).
        $decor = new Media(['title' => 'Dekorative Herbstfläche', 'is_decorative' => true, 'copyright' => 'Demo-Illustration (frei erfunden)']);
        app(MediaStorage::class)->attach($decor, $this->upload('herbst-dekor.jpg', (string) file_get_contents(database_path('demo/media/herbst.jpg'))));
        $decor->save();
        $this->publish($decor, 60);

        $galleries = [
            'seefest' => ['Seefest – Impressionen (Demo)', 'Bildergalerie zur Demonstration der Galerie-Darstellung.', ['fest', 'see', 'markt', 'maibaum', 'spielplatz', 'radweg']],
            'ort' => ['Ortsansichten (Demo)', 'Illustrierte Ansichten zur Demonstration unterschiedlicher Bildformate.', ['rathaus', 'feuerwehr', 'herbst', 'maibaum', 'winter']],
        ];
        foreach ($galleries as $key => [$title, $description, $items]) {
            $gallery = Gallery::create(['title' => $title, 'description' => $description]);
            foreach ($items as $index => $image) {
                $gallery->items()->create(['media_id' => $this->ref('img.'.$image, Media::class)->getKey(), 'caption' => $index === 0 ? 'Bildunterschrift aus der Galerie (überschreibt die zentrale Unterschrift)' : null, 'sort_order' => $index]);
            }
            $this->publish($gallery, 40);
            $this->r['gallery.'.$key] = $gallery;
        }
        $this->route($this->ref('gallery.ort', Gallery::class), '/galerie/ortsansichten');
    }

    private function documents(): void
    {
        $text = fn (string $topic) => [
            'Dieses Dokument ist ein Platzhalter für die Gestaltungsprüfung der neuen Website.',
            'Thema: '.$topic.'.',
            'Es enthält keine rechtsverbindlichen Angaben, Fristen, Gebühren oder Formularinhalte.',
            'Vor dem Livegang werden alle Musterdokumente durch geprüfte Originaldateien der Gemeinde ersetzt.',
        ];
        $documents = [
            'hundesteuer_formular' => ['Anmeldung eines Hundes zur Hundesteuer', 'formulare', 'Formulare und Anträge', 'Anmeldeformular-Hundesteuer.pdf', AccessibilityStatus::Accessible, 2026, null, 0],
            'hundesteuer_abmeldung' => ['Abmeldung eines Hundes', 'formulare', 'Formulare und Anträge', 'Abmeldeformular-Hundesteuer.pdf', AccessibilityStatus::NotChecked, 2026, null, 0],
            'satzung_2021' => ['Hundesteuersatzung (Fassung 2021)', 'satzung', 'Satzungen und Ortsrecht', 'Hundesteuersatzung-2021.pdf', AccessibilityStatus::NotAccessible, 2021, '2021-01-01', 40],
            'satzung_2026' => ['Hundesteuersatzung (Fassung 2026)', 'satzung', 'Satzungen und Ortsrecht', 'Hundesteuersatzung-2026.pdf', AccessibilityStatus::Accessible, 2026, '2026-01-01', 40],
            'vollmacht' => ['Vollmacht zur Abholung eines Personalausweises', 'formulare', 'Formulare und Anträge', 'Vollmacht-Abholung-Ausweisdokument.pdf', AccessibilityStatus::PartiallyAccessible, 2025, null, 0],
            'meldeschein' => ['Meldeschein für die Anmeldung einer Wohnung', 'formulare', 'Formulare und Anträge', 'Meldeschein-Anmeldung.pdf', AccessibilityStatus::NotChecked, 2026, null, 0],
            'wohnungsgeber' => ['Wohnungsgeberbestätigung', 'formulare', 'Formulare und Anträge', 'Wohnungsgeberbestaetigung.docx', AccessibilityStatus::NotChecked, 2026, null, 0],
            'sperrmuell' => ['Merkblatt Sperrmüll und Elektrogeräte', 'merkblatt', 'Merkblätter', 'Merkblatt-Sperrmuell.pdf', AccessibilityStatus::Accessible, 2026, null, 0],
            'abfallkalender' => ['Abfallkalender 2026', 'merkblatt', 'Merkblätter', 'Abfallkalender-2026-Merching-alle-Ortsteile.pdf', AccessibilityStatus::NotChecked, 2026, null, 180],
            'haushalt' => ['Haushaltssatzung und Haushaltsplan 2026 mit Finanzplan und Investitionsprogramm', 'haushalt', 'Haushalt und Finanzen', 'Haushaltssatzung-und-Haushaltsplan-2026-mit-Finanzplan-Investitionsprogramm-und-Stellenplan-endgueltige-Fassung.pdf', AccessibilityStatus::NotAccessible, 2026, '2026-03-15', 900],
            'bplan' => ['Bebauungsplan Nr. 27 „Am Mühlbach“ – Planzeichnung', 'bplan', 'Bauleitplanung', 'BP27-Am-Muehlbach-Planzeichnung.pdf', AccessibilityStatus::NotAccessible, 2026, '2026-07-01', 1500],
            'bplan_text' => ['Bebauungsplan Nr. 27 „Am Mühlbach“ – Begründung (barrierefreie Textfassung)', 'bplan', 'Bauleitplanung', 'BP27-Am-Muehlbach-Begruendung-barrierefrei.pdf', AccessibilityStatus::Accessible, 2026, '2026-07-01', 60],
            'bauantrag' => ['Checkliste Bauantrag', 'merkblatt', 'Merkblätter', 'Checkliste-Bauantrag.pdf', AccessibilityStatus::Accessible, 2025, null, 0],
            'amtsblatt' => ['Amtsblatt Nr. 18/2026 (Musterausgabe)', 'notice', 'Formulare und Anträge', 'Amtsblatt-18-2026.pdf', AccessibilityStatus::NotChecked, 2026, null, 300],
            'sitzung' => ['Tagesordnung der Gemeinderatssitzung (Muster)', 'notice', 'Formulare und Anträge', 'Tagesordnung-Gemeinderat.pdf', AccessibilityStatus::Accessible, 2026, null, 0],
            'wahl' => ['Wahlbekanntmachung (Muster)', 'notice', 'Formulare und Anträge', 'Wahlbekanntmachung-Muster.pdf', AccessibilityStatus::NotChecked, 2026, null, 0],
            'badeordnung' => ['Badeordnung für den Badeplatz', 'merkblatt', 'Merkblätter', 'Badeordnung.pdf', AccessibilityStatus::PartiallyAccessible, 2024, null, 0],
            'veranstaltung' => ['Anzeige einer öffentlichen Veranstaltung', 'formulare', 'Formulare und Anträge', 'Anzeige-Veranstaltung.pdf', AccessibilityStatus::Accessible, 2026, null, 0],
            'gewerbe' => ['Gewerbeanmeldung', 'formulare', 'Formulare und Anträge', 'Gewerbeanmeldung-GewA1.pdf', AccessibilityStatus::NotChecked, 2026, null, 0],
            'kita' => ['Anmeldung Kinderbetreuung', 'formulare', 'Formulare und Anträge', 'Anmeldung-Kinderbetreuung-2027.pdf', AccessibilityStatus::Accessible, 2026, null, 0],
            'geburt' => ['Merkblatt Geburt beurkunden', 'merkblatt', 'Merkblätter', 'Merkblatt-Geburtsbeurkundung.pdf', AccessibilityStatus::AlternativeProvided, 2025, null, 0],
        ];
        foreach ($documents as $key => [$title, $kind, $category, $filename, $status, $year, $date, $padding]) {
            $document = new Document(['title' => $title, 'description' => $kind === 'formulare' ? 'Musterformular zur Demonstration. Ausgefüllt im Rathaus abgeben oder per Post senden.' : null, 'category_id' => $this->category(CategoryContext::Document, $category)->getKey(), 'year' => $year, 'document_date' => $date, 'valid_from' => $kind === 'satzung' ? $date : null, 'accessibility_status' => $status === AccessibilityStatus::AlternativeProvided ? AccessibilityStatus::NotChecked : $status, 'accessibility_notes' => $status === AccessibilityStatus::NotAccessible ? 'Die Planzeichnung ist eine grafische Darstellung. Eine Textfassung ist auf Anfrage erhältlich.' : null]);
            $bytes = str_ends_with($filename, '.docx') ? DemoFiles::docx($title, $text($title)) : DemoFiles::pdf($title, $text($title), $padding);
            app(DocumentStorage::class)->attach($document, $this->upload($filename, $bytes));
            $document->save();
            $this->publish($document, $key === 'satzung_2021' ? 1700 : 50);
            $this->r['doc.'.$key] = $document;
        }
        // Supersession and accessible alternative relations.
        $this->ref('doc.satzung_2026', Document::class)->forceFill(['replaces_document_id' => $this->ref('doc.satzung_2021', Document::class)->getKey()])->save();
        $this->ref('doc.geburt', Document::class)->forceFill(['accessibility_status' => AccessibilityStatus::AlternativeProvided, 'accessible_alternative_id' => $this->ref('doc.bauantrag', Document::class)->getKey()])->save();
        $this->ref('doc.bplan', Document::class)->forceFill(['accessible_alternative_id' => $this->ref('doc.bplan_text', Document::class)->getKey(), 'accessibility_status' => AccessibilityStatus::AlternativeProvided])->save();
        $this->route($this->ref('doc.satzung_2026', Document::class), '/wp-content/uploads/2026/01/hundesteuersatzung.pdf');
    }

    // ------------------------------------------------------------------ organizations

    private function organizations(): void
    {
        /** @var list<array{0:string,1:OrganizationType,2:string,3:string,4:?string,5:?string}> $organizations */
        $organizations = [
            ['Sportverein Grün-Weiß e. V.', OrganizationType::Club, 'Sport', 'Fußball, Turnen, Tischtennis und Gymnastik für alle Altersgruppen.', 'Vorstand', 'Sportplatzweg 4'],
            ['Blaskapelle Harmonie e. V.', OrganizationType::Club, 'Kultur und Brauchtum', 'Blasmusik bei Festen, Kirchenkonzert im Advent und Jugendausbildung.', 'Vorsitz', null],
            ['Trachtenverein Edelweiß e. V.', OrganizationType::Club, 'Kultur und Brauchtum', 'Brauchtumspflege, Volkstanz und Kindertrachtengruppe.', null, null],
            ['Gartenbauverein Blütenfreunde e. V.', OrganizationType::Club, 'Natur und Garten', 'Obstbaumschnittkurse, Pflanzenbörse und Ortsverschönerung.', 'Vorsitz', null],
            ['Seniorentreff Lebensfreude', OrganizationType::Club, 'Soziales und Familie', 'Wöchentlicher Treff, Ausflüge und Fahrdienst für ältere Menschen.', null, 'Kirchplatz 2'],
            ['Elterninitiative Kinderwelt e. V.', OrganizationType::Club, 'Soziales und Familie', 'Krabbelgruppen, Kinderkleiderbasar und Ferienprogramm.', null, null],
            ['Schützengesellschaft Tell e. V.', OrganizationType::Club, 'Sport', 'Luftgewehr- und Luftpistolenschießen, Jugendtraining ab 12 Jahren.', null, 'Schützenweg 1'],
            ['Imkerverein Bienenfleiß e. V.', OrganizationType::Club, 'Natur und Garten', 'Imkerkurse und Lehrbienenstand.', null, null],
            ['Gasthaus Zur Linde (Beispielbetrieb)', OrganizationType::Gastronomy, 'Gastronomie', 'Bayerische Küche, Biergarten und Saal für Feiern.', null, 'Dorfstraße 10'],
            ['Bäckerei Mustermann (Beispielbetrieb)', OrganizationType::Business, 'Handwerk und Handel', 'Backwaren aus eigener Herstellung.', null, 'Dorfstraße 3'],
            ['Interessengemeinschaft für die Pflege und Weiterentwicklung des gemeindlichen Vereins- und Kulturlebens e. V.', OrganizationType::Other, 'Kultur und Brauchtum', 'Beispiel für einen besonders langen Organisationsnamen.', null, null],
        ];
        foreach ($organizations as $index => [$name, $type, $category, $description, $contact, $street]) {
            $organization = Organization::create(['name' => $name, 'type' => $type, 'category_id' => $this->category(CategoryContext::Organization, $category)->getKey(), 'description' => $description, 'contact_name' => $contact, 'street' => $street, 'postal_code' => $street ? '86504' : null, 'city' => $street ? 'Merching' : null, 'phone' => $index < 6 ? $this->phone(300 + $index) : null, 'email' => $index < 8 ? 'verein'.($index + 1).'@beispielverein.example' : null, 'website' => $index < 4 ? 'https://www.example.org/verein-'.($index + 1) : null, 'is_active' => true, 'sort_order' => $index]);
            if ($index === 0) {
                $organization->links()->create(['label' => 'Trainingszeiten (Beispiel)', 'url' => 'https://www.example.org/verein-1/training', 'sort_order' => 0]);
            }
            $this->tag($organization);
            $this->r['org.'.$index] = $organization;
        }
        $this->route($this->ref('org.0', Organization::class), '/vereine/sportverein-gruen-weiss');
    }

    // ------------------------------------------------------------------ services

    private function services(): void
    {
        /** @var array<string, array<string, mixed>> $services */
        $services = [
            'personalausweis' => [
                'title' => 'Personalausweis beantragen', 'sort' => null, 'category' => 'Meldewesen und Ausweise',
                'summary' => 'Den Personalausweis beantragen Sie persönlich im Bürgerbüro. Bitte vereinbaren Sie vorher einen Termin.',
                'body' => "Deutsche ab 16 Jahren müssen einen gültigen Personalausweis oder Reisepass besitzen. Den Antrag stellen Sie **persönlich** im Bürgerbüro Ihres Hauptwohnsitzes.\n\nDer Ausweis wird zentral hergestellt. Sie erhalten nach einigen Wochen einen Brief mit der PIN und holen den Ausweis anschließend im Bürgerbüro ab.",
                'prerequisites' => "Sie sind deutsche Staatsangehörige oder deutscher Staatsangehöriger.\nSie haben Ihren Hauptwohnsitz in Merching.\nSie erscheinen persönlich zur Antragstellung.",
                'required' => "Bisheriger Personalausweis oder Reisepass\nEin aktuelles biometrisches Passfoto (digital oder Papierabzug)\nBei Kindern unter 16 Jahren: Zustimmung aller Sorgeberechtigten\nBei Namensänderung: Heirats- oder Geburtsurkunde",
                'duration' => 'Ca. 3–4 Wochen (Beispielangabe)',
                'notice' => 'Musterinhalt: Gebühren und Fristen in dieser Demonstration sind nicht verbindlich.',
                'mode' => OnlineServiceMode::Appointment, 'online' => 'ausweis', 'departments' => ['buergerbuero'], 'contacts' => ['mustermann', 'muster'],
                'aliases' => ['Perso', 'Ausweis', 'Identitätskarte'],
                'fees' => [['Personen ab 24 Jahren', 'Gültigkeit 10 Jahre', 37.00, null], ['Personen unter 24 Jahren', 'Gültigkeit 6 Jahre', 22.80, null], ['Vorläufiger Personalausweis', null, 10.00, 'Nur bei dringendem Bedarf'], ['Nachträgliche Änderung der Anschrift', null, null, 'Gebührenfrei'], ['Antrag außerhalb der Öffnungszeiten', 'Auf Wunsch', 13.00, 'Musterbetrag']],
                'documents' => ['formulare' => ['vollmacht']], 'links' => ['links' => ['portal']],
            ],
            'reisepass' => ['title' => 'Reisepass beantragen', 'category' => 'Meldewesen und Ausweise', 'summary' => 'Reisepässe werden im Bürgerbüro beantragt und zentral hergestellt.', 'required' => "Bisheriges Ausweisdokument\nBiometrisches Passfoto", 'duration' => 'Ca. 4–6 Wochen, Express ca. 3 Werktage (Beispielangabe)', 'mode' => OnlineServiceMode::Appointment, 'online' => 'ausweis', 'departments' => ['buergerbuero'], 'contacts' => ['mustermann'], 'aliases' => ['Pass', 'Kinderreisepass'], 'fees' => [['Reisepass ab 24 Jahren', null, 70.00, null], ['Reisepass unter 24 Jahren', null, 37.50, null], ['Expresszuschlag', null, 32.00, null]]],
            'anmeldung' => ['title' => 'Wohnsitz anmelden', 'category' => 'Meldewesen und Ausweise', 'summary' => 'Nach dem Einzug in eine Wohnung in Merching melden Sie sich innerhalb von zwei Wochen im Bürgerbüro an.', 'body' => "Die Anmeldung ist für alle Personen erforderlich, die in eine Wohnung in Merching einziehen.\n\n- Meldeschein ausfüllen oder vor Ort ausfüllen lassen\n- Wohnungsgeberbestätigung des Vermieters mitbringen\n- Ausweisdokumente aller einziehenden Personen vorlegen", 'required' => "Personalausweis oder Reisepass\nWohnungsgeberbestätigung", 'duration' => 'Sofort bei Vorsprache', 'mode' => OnlineServiceMode::Unavailable, 'departments' => ['buergerbuero'], 'contacts' => ['muster'], 'aliases' => ['Ummeldung', 'Umzug', 'Zuzug', 'Meldebescheinigung'], 'fees' => [['Anmeldung', null, null, 'Gebührenfrei']], 'documents' => ['formulare' => ['meldeschein', 'wohnungsgeber']]],
            'abmeldung' => ['title' => 'Wohnsitz abmelden', 'category' => 'Meldewesen und Ausweise', 'summary' => 'Eine Abmeldung ist nur beim Wegzug ins Ausland oder bei Aufgabe einer Nebenwohnung nötig.', 'mode' => OnlineServiceMode::Information, 'online' => 'portal', 'departments' => ['buergerbuero'], 'contacts' => ['muster']],
            'fuehrungszeugnis' => ['title' => 'Führungszeugnis beantragen', 'category' => 'Meldewesen und Ausweise', 'summary' => 'Das Führungszeugnis beantragen Sie im Bürgerbüro oder online beim Bundesamt für Justiz.', 'required' => 'Personalausweis oder Reisepass', 'duration' => 'Ca. 2 Wochen (Beispielangabe)', 'mode' => OnlineServiceMode::Application, 'online' => 'fuehrungszeugnis', 'departments' => ['buergerbuero'], 'contacts' => ['mustermann'], 'aliases' => ['Polizeiliches Führungszeugnis'], 'fees' => [['Führungszeugnis', null, 13.00, null], ['Für ehrenamtliche Tätigkeit', 'Mit Bestätigung der Einrichtung', null, 'Gebührenfrei']]],
            'hundesteuer' => ['title' => 'Hund anmelden (Hundesteuer)', 'sort' => 'Hundesteuer', 'category' => 'Steuern und Abgaben', 'summary' => 'Wer in Merching einen Hund hält, meldet ihn innerhalb von zwei Wochen zur Hundesteuer an und erhält eine Hundemarke.', 'body' => 'Die Hundesteuer ist eine gemeindliche Steuer. Die Höhe richtet sich nach der Hundesteuersatzung.', 'prerequisites' => 'Sie halten einen Hund, der älter als vier Monate ist.', 'required' => "Ausgefülltes Anmeldeformular\nBei Ermäßigung: Nachweis (z. B. Rettungshundeprüfung)", 'duration' => 'Ca. 1 Woche', 'mode' => OnlineServiceMode::Application, 'online' => 'hundesteuer', 'departments' => ['finanzen'], 'contacts' => ['beispiel'], 'aliases' => ['Hundemarke', 'Hund ummelden'], 'fees' => [['Erster Hund', 'pro Jahr', 60.00, 'Musterbetrag'], ['Zweiter und jeder weitere Hund', 'pro Jahr', 90.00, 'Musterbetrag'], ['Kampfhund', 'pro Jahr', 600.00, 'Musterbetrag'], ['Ersatz-Hundemarke', null, 5.00, null]], 'documents' => ['formulare' => ['hundesteuer_formular', 'hundesteuer_abmeldung'], 'merkblaetter' => ['satzung_2026']]],
            'grundsteuer' => ['title' => 'Grundsteuer', 'category' => 'Steuern und Abgaben', 'summary' => 'Informationen zu Grundsteuerbescheid, Fälligkeit und Hebesatz.', 'mode' => OnlineServiceMode::NotSpecified, 'departments' => ['finanzen'], 'contacts' => ['beispiel']],
            'sperrmuell' => ['title' => 'Sperrmüll und Elektrogeräte abholen lassen', 'sort' => 'Sperrmüll', 'category' => 'Abfall und Umwelt', 'summary' => 'Sperrmüll und große Elektrogeräte werden nach Anmeldung beim Entsorger abgeholt.', 'mode' => OnlineServiceMode::Application, 'online' => 'sperrmuell', 'departments' => ['bauhof'], 'contacts' => ['muster2'], 'aliases' => ['Sperrmüllkarte', 'Kühlschrank entsorgen', 'Müll'], 'documents' => ['merkblaetter' => ['sperrmuell', 'abfallkalender']]],
            'wertstoffhof' => ['title' => 'Wertstoffhof nutzen', 'category' => 'Abfall und Umwelt', 'summary' => 'Öffnungszeiten und angenommene Wertstoffe am Wertstoffhof.', 'mode' => OnlineServiceMode::NotSpecified, 'departments' => ['bauhof'], 'aliases' => ['Wertstoffsammelstelle', 'Grüngut']],
            'bauantrag' => ['title' => 'Bauantrag stellen', 'category' => 'Bauen und Wohnen', 'summary' => 'Bauanträge reichen Sie bei der Gemeinde ein. Sie werden geprüft und an das Landratsamt weitergeleitet.', 'body' => "Für die meisten Bauvorhaben ist eine Baugenehmigung erforderlich. Das Bauamt berät Sie gerne vor der Antragstellung.\n\nIn dieser Demonstration fehlen bewusst Angaben zu Gebühren.", 'prerequisites' => 'Der Antrag wird von einer bauvorlageberechtigten Person (z. B. Architektin) unterschrieben.', 'required' => "Bauantragsformular\nLageplan\nBauzeichnungen\nBaubeschreibung\nStatische Berechnungen (bei Bedarf)", 'duration' => 'Abhängig vom Vorhaben, in der Regel 1–3 Monate', 'mode' => OnlineServiceMode::Information, 'online' => 'portal', 'departments' => ['bauamt'], 'contacts' => ['platzhalter', 'exempel'], 'aliases' => ['Baugenehmigung', 'Bauvoranfrage'], 'documents' => ['merkblaetter' => ['bauantrag']], 'links' => ['links' => ['bayernatlas']]],
            'hausnummer' => ['title' => 'Hausnummer beantragen', 'category' => 'Bauen und Wohnen', 'summary' => 'Für Neubauten vergibt das Bauamt eine Hausnummer.', 'mode' => OnlineServiceMode::Unavailable, 'departments' => ['bauamt'], 'contacts' => ['exempel'], 'fees' => [['Hausnummernvergabe', null, 25.00, 'Musterbetrag']]],
            'gewerbe' => ['title' => 'Gewerbe anmelden', 'category' => 'Ordnung und Verkehr', 'summary' => 'Wer ein Gewerbe beginnt, ummeldet oder aufgibt, zeigt dies dem Bürgerbüro an.', 'required' => 'Personalausweis, ggf. Handelsregisterauszug und Erlaubnisse', 'mode' => OnlineServiceMode::Application, 'online' => 'portal', 'departments' => ['buergerbuero'], 'contacts' => ['muster'], 'aliases' => ['Gewerbeabmeldung', 'Gewerbeummeldung', 'Selbstständigkeit'], 'fees' => [['Gewerbeanmeldung', null, 26.00, null], ['Gewerbeummeldung', null, 20.00, null], ['Gewerbeabmeldung', null, 15.00, null]], 'documents' => ['formulare' => ['gewerbe']]],
            'veranstaltung' => ['title' => 'Veranstaltung anzeigen', 'category' => 'Ordnung und Verkehr', 'summary' => 'Öffentliche Veranstaltungen zeigen Sie rechtzeitig beim Ordnungsamt an.', 'mode' => OnlineServiceMode::Unavailable, 'departments' => ['ordnung'], 'contacts' => ['demo'], 'documents' => ['formulare' => ['veranstaltung']], 'aliases' => ['Festzelt', 'Plakatierung']],
            'fundsachen' => ['title' => 'Fundsache abgeben oder suchen', 'sort' => 'Fundbüro', 'category' => 'Ordnung und Verkehr', 'summary' => 'Das Fundbüro im Bürgerbüro nimmt Fundsachen entgegen und gibt sie an Eigentümer zurück.', 'mode' => OnlineServiceMode::NotSpecified, 'departments' => ['buergerbuero'], 'contacts' => ['muster'], 'aliases' => ['Fundbüro', 'Verloren']],
            'briefwahl' => ['title' => 'Briefwahl beantragen', 'category' => 'Meldewesen und Ausweise', 'summary' => 'Wahlschein und Briefwahlunterlagen können Sie schriftlich, persönlich oder online beantragen.', 'mode' => OnlineServiceMode::Application, 'online' => 'briefwahl', 'departments' => ['zentral'], 'contacts' => ['sitzung'], 'aliases' => ['Wahlschein']],
            'geburt' => ['title' => 'Geburt beurkunden', 'category' => 'Standesamt', 'summary' => 'Die Geburt eines Kindes wird beim Standesamt des Geburtsortes beurkundet.', 'mode' => OnlineServiceMode::Unavailable, 'departments' => ['standesamt'], 'contacts' => ['vorlage', 'entwurf'], 'documents' => ['merkblaetter' => ['geburt']], 'fees' => [['Geburtsurkunde', 'erste Ausfertigung', 12.00, null], ['Weitere Ausfertigung', null, 6.00, null]]],
            'eheschliessung' => ['title' => 'Eheschließung anmelden', 'category' => 'Standesamt', 'summary' => 'Die Eheschließung melden Sie gemeinsam beim Standesamt an. Trauungen sind im Trauzimmer des Rathauses möglich.', 'mode' => OnlineServiceMode::Appointment, 'online' => 'portal', 'departments' => ['standesamt'], 'contacts' => ['vorlage', 'entwurf'], 'aliases' => ['Heiraten', 'Trauung', 'Hochzeit'], 'fees' => [['Anmeldung der Eheschließung', null, 55.00, null], ['Trauung außerhalb der Dienstzeit', 'Samstag', 120.00, 'Musterbetrag'], ['Eheurkunde', null, 12.00, null]]],
            'sterbefall' => ['title' => 'Sterbefall beurkunden', 'category' => 'Standesamt', 'summary' => 'Ein Sterbefall muss spätestens am dritten Werktag beim Standesamt angezeigt werden. Meist übernimmt dies das Bestattungsunternehmen.', 'mode' => OnlineServiceMode::Unavailable, 'departments' => ['standesamt'], 'contacts' => ['vorlage']],
            'kinderbetreuung' => ['title' => 'Kinderbetreuungsplatz anmelden', 'sort' => 'Kita-Platz', 'category' => 'Familie und Soziales', 'summary' => 'Die Anmeldung für Krippe, Kindergarten und Hort erfolgt einmal jährlich für das folgende Betreuungsjahr.', 'mode' => OnlineServiceMode::Information, 'online' => 'portal', 'departments' => ['zentral'], 'contacts' => ['sitzung'], 'aliases' => ['Kita', 'Kindergarten', 'Krippe', 'Hort'], 'documents' => ['formulare' => ['kita']]],
            'wasserzaehler' => ['title' => 'Wasserzählerstand melden', 'category' => 'Steuern und Abgaben', 'summary' => 'Den Zählerstand melden Sie einmal jährlich zum Jahresende.', 'mode' => OnlineServiceMode::Application, 'online' => 'portal', 'departments' => ['finanzen'], 'contacts' => ['beispiel'], 'aliases' => ['Wasseruhr']],
            'kfz' => ['title' => 'Fahrzeug zulassen', 'category' => 'Ordnung und Verkehr', 'summary' => 'Für die Kfz-Zulassung ist das Landratsamt zuständig, nicht die Gemeinde.', 'mode' => OnlineServiceMode::Information, 'online' => 'landratsamt', 'aliases' => ['Auto anmelden', 'Kennzeichen', 'Zulassungsstelle']],
            'grundstuecksentwaesserung' => ['title' => 'Grundstücksentwässerungsanlage genehmigen lassen', 'category' => 'Bauen und Wohnen', 'summary' => 'Beispiel für einen langen Leistungsnamen mit zusammengesetzten Wörtern wie Grundstücksentwässerungsanlagengenehmigung.', 'mode' => OnlineServiceMode::NotSpecified, 'departments' => ['bauamt'], 'contacts' => ['exempel']],
        ];
        $order = 0;
        foreach ($services as $key => $data) {
            $service = Service::create(['title' => $data['title'], 'sort_title' => $data['sort'] ?? null, 'summary' => $data['summary'], 'body' => $data['body'] ?? null, 'category_id' => $this->category(CategoryContext::Service, $data['category'])->getKey(), 'online_service_resource_id' => isset($data['online']) ? $this->ref('link.'.$data['online'], ExternalResource::class)->getKey() : null, 'sort_order' => $order++, 'prerequisites' => $data['prerequisites'] ?? null, 'required_items' => $data['required'] ?? null, 'processing_duration' => $data['duration'] ?? null, 'important_notice' => $data['notice'] ?? null, 'online_service_mode' => $data['mode']]);
            foreach ($data['departments'] ?? [] as $index => $department) {
                $service->departments()->attach($this->ref('dep.'.$department, Department::class)->getKey(), ['sort_order' => $index]);
            }
            foreach ($data['contacts'] ?? [] as $index => $person) {
                $service->contacts()->attach($this->ref('person.'.$person, Person::class)->getKey(), ['sort_order' => $index]);
            }
            foreach ($data['aliases'] ?? [] as $alias) {
                $service->aliases()->create(['alias' => $alias]);
            }
            foreach ($data['fees'] ?? [] as $index => [$description, $context, $amount, $note]) {
                $service->fees()->create(['description' => $description, 'context' => $context, 'amount' => $amount, 'note' => $note, 'sort_order' => $index]);
            }
            foreach ($data['documents'] ?? [] as $slot => $keys) {
                foreach ($keys as $index => $document) {
                    $service->documents()->attach($this->ref('doc.'.$document, Document::class)->getKey(), ['slot' => $slot, 'sort_order' => $index]);
                }
            }
            foreach ($data['links'] ?? [] as $slot => $keys) {
                foreach ($keys as $index => $link) {
                    $service->externalResources()->attach($this->ref('link.'.$link, ExternalResource::class)->getKey(), ['slot' => $slot, 'sort_order' => $index]);
                }
            }
            $this->publish($service, 90);
            $this->route($service);
            $this->r['svc.'.$key] = $service;
        }
        $related = ['personalausweis' => ['reisepass', 'anmeldung', 'fuehrungszeugnis'], 'reisepass' => ['personalausweis'], 'anmeldung' => ['abmeldung', 'personalausweis', 'hundesteuer'], 'hundesteuer' => ['anmeldung'], 'sperrmuell' => ['wertstoffhof'], 'bauantrag' => ['hausnummer', 'grundstuecksentwaesserung'], 'geburt' => ['kinderbetreuung', 'personalausweis'], 'eheschliessung' => ['anmeldung', 'personalausweis']];
        foreach ($related as $key => $others) {
            foreach ($others as $index => $other) {
                $this->ref('svc.'.$key, Service::class)->relatedServices()->attach($this->ref('svc.'.$other, Service::class)->getKey(), ['sort_order' => $index]);
            }
        }
        // Content blocks on the flagship service page.
        $this->blocks($this->ref('svc.personalausweis', Service::class), [
            ['callout', 'Termin vereinbaren', "Bitte buchen Sie vorab einen Termin. So vermeiden Sie Wartezeiten.\n\nOhne Termin ist eine Vorsprache nur donnerstags von 14 bis 18 Uhr möglich."],
            ['accordion', 'Was gilt für Kinder unter 16 Jahren?', 'Kinder können einen Personalausweis erhalten. Beide Sorgeberechtigten müssen zustimmen; das Kind muss bei der Antragstellung anwesend sein.'],
            ['accordion', 'Kann jemand anderes den Ausweis für mich abholen?', 'Ja, mit einer schriftlichen Vollmacht und dem Ausweisdokument der bevollmächtigten Person.'],
            ['accordion', 'Was tun bei Verlust oder Diebstahl?', 'Melden Sie den Verlust sofort im Bürgerbüro oder bei der Polizei. Die Online-Ausweisfunktion lässt sich rund um die Uhr über den Sperrnotruf sperren.'],
        ]);
    }

    // ------------------------------------------------------------------ life situations

    private function lifeSituations(): void
    {
        $situations = [
            'umzug' => ['Umzug nach Merching', 'Was Sie nach dem Einzug erledigen sollten – von der Anmeldung bis zur Hundesteuer.', ['anmeldung', 'personalausweis', 'hundesteuer', 'wasserzaehler', 'kinderbetreuung'], 'Herzlich willkommen! Diese Übersicht hilft Ihnen, nach dem Umzug an alles zu denken.'],
            'geburt' => ['Geburt eines Kindes', 'Beurkundung, Ausweis und Kinderbetreuung rund um die Geburt.', ['geburt', 'reisepass', 'kinderbetreuung'], null],
            'heiraten' => ['Heiraten', 'Anmeldung der Eheschließung, Trauung und Namensänderung.', ['eheschliessung', 'personalausweis', 'anmeldung'], null],
            'bauen' => ['Bauen und Renovieren', 'Von der Bauvoranfrage bis zur Hausnummer.', ['bauantrag', 'hausnummer', 'grundstuecksentwaesserung'], null],
            'hund' => ['Hund anschaffen', 'Hundesteuer, Hundemarke und Regeln für Hundehalter.', ['hundesteuer'], null],
            'todesfall' => ['Todesfall', 'Was Angehörige jetzt erledigen müssen.', ['sterbefall'], null],
            'gewerbe' => ['Selbstständig machen', 'Gewerbe anmelden und Genehmigungen einholen.', ['gewerbe', 'veranstaltung'], null],
        ];
        $order = 0;
        foreach ($situations as $key => [$title, $summary, $services, $body]) {
            $situation = LifeSituation::create(['title' => $title, 'summary' => $summary, 'body' => $body, 'sort_order' => $order++]);
            foreach ($services as $index => $service) {
                $situation->services()->attach($this->ref('svc.'.$service, Service::class)->getKey(), ['sort_order' => $index]);
            }
            $this->publish($situation, 80);
            $this->route($situation);
            $this->r['life.'.$key] = $situation;
        }
        $this->ref('life.umzug', LifeSituation::class)->documents()->attach($this->ref('doc.meldeschein', Document::class)->getKey(), ['slot' => 'dokumente', 'sort_order' => 0]);
        $this->ref('life.umzug', LifeSituation::class)->externalResources()->attach($this->ref('link.portal', ExternalResource::class)->getKey(), ['slot' => 'links', 'sort_order' => 0]);
    }

    // ------------------------------------------------------------------ articles

    private function articles(): void
    {
        $articles = [
            'sperrung' => ['Vollsperrung der Seestraße vom 12. bis 30. Oktober', 'Wegen der Erneuerung der Fahrbahndecke ist die Seestraße zwischen Kirchplatz und Ortsausgang voll gesperrt.', "Die Gemeinde erneuert die Fahrbahndecke der Seestraße. Während der Arbeiten ist die Straße zwischen Kirchplatz und Ortsausgang **voll gesperrt**.\n\nEine Umleitung ist ausgeschildert. Anlieger erreichen ihre Grundstücke nach Absprache mit der Baufirma.\n\n*Musterinhalt zur Demonstration.*", 'Bauen und Verkehr', ['Baustelle', 'Umleitung'], 'baustelle', 2, true],
            'spielschiff' => ['Neues Spielschiff am Badeplatz eingeweiht', 'Am Badeplatz steht jetzt ein Spielschiff aus Holz – gebaut mit viel ehrenamtlicher Unterstützung.', null, 'Kinder und Familie', ['Spielplatz', 'Ehrenamt'], 'spielplatz', 5, false],
            'haushalt' => ['Gemeinderat beschließt Haushalt 2026', 'Der Gemeinderat hat die Haushaltssatzung mit einem Investitionsvolumen von rund 4,2 Mio. Euro beschlossen (Beispielwert).', "In seiner Sitzung hat der Gemeinderat die Haushaltssatzung für das Jahr 2026 beschlossen.\n\nSchwerpunkte sind die Sanierung der Mehrzweckhalle, der Ausbau der Kinderbetreuung und Straßenbaumaßnahmen.\n\n*Alle Beträge sind Beispielwerte.*", 'Aus dem Rathaus', ['Haushalt'], null, 9, false],
            'papiertonne' => ['Landkreis übernimmt die Papiertonne ab Januar', 'Die Erfassung der Papiertonnen erfolgt künftig durch den Landkreis. Für Haushalte ändert sich wenig.', 'Ab Januar übernimmt der Landkreis die Abfuhr der Papiertonne. Die Abfuhrtermine finden Sie im neuen Abfallkalender.', 'Umwelt und Natur', [], 'herbst', 14, false],
            'feuerwehr' => ['Freiwillige Feuerwehr sucht Verstärkung – Infoabend im Feuerwehrhaus', null, 'Die Freiwillige Feuerwehr lädt alle Interessierten zu einem Informationsabend ein. Vorkenntnisse sind nicht nötig.', 'Vereinsleben', ['Ehrenamt'], 'feuerwehr', 18, false],
            'bodenrichtwerte' => ['Bodenrichtwerte zum 1. Januar veröffentlicht', 'Der Gutachterausschuss hat die neuen Bodenrichtwerte ermittelt.', null, 'Bauen und Verkehr', [], null, 25, false],
            'kita' => ['Anmeldung für Krippe, Kindergarten und Hort für das Betreuungsjahr 2027/2028', 'Die Anmeldewoche findet im Januar statt. Formulare stehen bereits zum Download bereit.', null, 'Kinder und Familie', ['Kinderbetreuung'], null, 31, false],
            'klima' => ['Klimaschutzkonzept: Bürgerinnen und Bürger können Ideen einreichen', 'Bis Ende November können Vorschläge für das gemeindliche Klimaschutzkonzept eingereicht werden.', null, 'Umwelt und Natur', ['Klimaschutz'], 'radweg', 38, false],
            'markt' => ['Wochenmarkt zieht auf den Kirchplatz um', null, 'Ab November findet der Wochenmarkt immer freitags auf dem Kirchplatz statt.', 'Aus dem Rathaus', [], 'markt', 45, false],
            'ehrenamt' => ['Ehrenamtskarte jetzt auch in Merching erhältlich', 'Engagierte erhalten Vergünstigungen bei zahlreichen Partnern im Landkreis.', null, 'Vereinsleben', ['Ehrenamt'], null, 60, false],
            'grundsteuerreformumsetzungsinformationsveranstaltung' => ['Grundsteuerreformumsetzungsinformationsveranstaltung im Rathaussitzungssaal', 'Beispiel für einen Titel mit einem sehr langen deutschen Kompositum.', null, 'Aus dem Rathaus', [], null, 70, false],
            'winterdienst' => ['Winterdienst: Räum- und Streupflicht für Anlieger', 'Hinweise zur Räum- und Streupflicht auf Gehwegen.', null, 'Bauen und Verkehr', [], 'winter', 300, false],
        ];
        foreach ($articles as $key => [$title, $summary, $body, $category, $tags, $image, $daysAgo, $featured]) {
            $article = Article::create(['title' => $title, 'summary' => $summary, 'body' => $body, 'category_id' => $this->category(CategoryContext::Article, $category)->getKey(), 'department_id' => $key === 'sperrung' ? $this->ref('dep.bauamt', Department::class)->getKey() : null, 'author_name' => $key === 'spielschiff' ? 'Redaktion (Demo)' : null, 'is_featured' => $featured]);
            foreach ($tags as $tag) {
                $article->tags()->attach(Tag::query()->where('name', $tag)->value('id'));
            }
            if ($image) {
                $article->media()->attach($this->ref('img.'.$image, Media::class)->getKey(), ['sort_order' => 0]);
            }
            $this->publish($article, $daysAgo);
            $this->route($article);
            $this->r['art.'.$key] = $article;
        }
        $this->ref('art.sperrung', Article::class)->contacts()->attach($this->ref('person.platzhalter', Person::class)->getKey(), ['sort_order' => 0]);
        $this->ref('art.haushalt', Article::class)->documents()->attach($this->ref('doc.haushalt', Document::class)->getKey(), ['slot' => 'anhaenge', 'sort_order' => 0]);
        $this->ref('art.kita', Article::class)->documents()->attach($this->ref('doc.kita', Document::class)->getKey(), ['slot' => 'anhaenge', 'sort_order' => 0]);
        $this->ref('art.papiertonne', Article::class)->documents()->attach($this->ref('doc.abfallkalender', Document::class)->getKey(), ['slot' => 'anhaenge', 'sort_order' => 0]);
        $this->ref('art.bodenrichtwerte', Article::class)->externalResources()->attach($this->ref('link.bayernatlas', ExternalResource::class)->getKey(), ['slot' => 'links', 'sort_order' => 0]);

        // Article composed of controlled blocks (image, gallery, text, contact, related event …).
        $this->blocks($this->ref('art.spielschiff', Article::class), [
            ['text', null, 'Das neue Spielschiff am Badeplatz ist eingeweiht. Kinder können jetzt an Deck klettern, rutschen und im Sand spielen.'],
            ['heading', 'Gebaut mit Unterstützung aus dem Ort', null, 2],
            ['text', null, "Mitglieder mehrerer Vereine haben beim Aufbau geholfen. Die Gemeinde dankt allen Beteiligten.\n\n- Planung: Bauamt\n- Aufbau: Bauhof und Ehrenamtliche\n- Bepflanzung: Gartenbauverein"],
            ['gallery', 'seefest'],
            ['contact', 'muster2'],
        ]);

        // Expired article (public archive) and a scheduled one.
        $archived = Article::create(['title' => 'Sommerferienprogramm 2025: Anmeldung gestartet', 'summary' => 'Archivbeispiel: Diese Meldung ist abgelaufen und erscheint im öffentlichen Archiv.', 'category_id' => $this->category(CategoryContext::Article, 'Kinder und Familie')->getKey()]);
        $this->publish($archived, 400, -300);
        $this->route($archived);
        $scheduled = Article::create(['title' => 'Neue Öffnungszeiten der Gemeindebücherei ab November', 'summary' => 'Geplante Veröffentlichung: erscheint erst in einigen Tagen.', 'category_id' => $this->category(CategoryContext::Article, 'Aus dem Rathaus')->getKey()]);
        $this->publish($scheduled, -3);
        $this->route($scheduled);
        $draft = Article::create(['title' => 'Entwurf: Rückblick auf das Seefest', 'summary' => 'Noch nicht veröffentlicht.', 'category_id' => $this->category(CategoryContext::Article, 'Vereinsleben')->getKey()]);
        $this->tag($draft);
        $this->r['art.draft'] = $draft;
    }

    // ------------------------------------------------------------------ events

    private function events(): void
    {
        /** @var array<string, array{0:string,1:int,2:string,3:string,4:bool,5:?string,6:?string,7:?int,8:string,9:?string,10:?string,11:?string}> $events */
        $events = [
            'flohmarkt' => ['Kinderflohmarkt', 3, '09:00', '13:00', false, 'halle', null, 5, 'Vereine', 'Spielzeug, Kinderkleidung und Bücher. Verkäuferinnen und Verkäufer melden sich bitte vorab an.', null, null],
            'gemeinderat' => ['Öffentliche Sitzung des Gemeinderats', 6, '19:30', '22:00', false, 'rathaus', null, null, 'Sitzungen', 'Die Tagesordnung wird eine Woche vorher bekannt gemacht.', null, null],
            'weinfest' => ['Weinfest des Trachtenvereins', 8, '19:00', '23:30', false, null, 'Trachtenheim, Schützenweg 1', 2, 'Kultur', 'Weine, Brotzeiten und Live-Musik.', null, 'https://www.example.org/verein-3/weinfest'],
            'konzert' => ['Herbstkonzert der Blaskapelle', 13, '18:00', '20:00', false, 'halle', null, 1, 'Kultur', 'Konzert mit Werken von Klassik bis Moderne. Eintritt frei, Spenden erbeten.', null, null],
            'buergerversammlung' => ['Bürgerversammlung', 20, '19:30', '21:30', false, 'halle', null, null, 'Sitzungen', 'Der Bürgermeister berichtet über das vergangene Jahr. Anschließend können Fragen gestellt werden.', null, null],
            'seniorennachmittag' => ['Seniorennachmittag mit Kaffee und Kuchen', 24, '14:30', '17:00', false, 'buecherei', null, 4, 'Senioren', null, null, null],
            'christkindlmarkt' => ['Christkindlmarkt auf dem Kirchplatz', 55, '00:00', '00:00', true, null, 'Kirchplatz', null, 'Kultur', 'Zwei Tage Christkindlmarkt mit Ständen der örtlichen Vereine.', null, null],
            'lauf' => ['Volkslauf rund um den See', 34, '10:00', '13:00', false, 'see', null, 0, 'Sport und Freizeit', 'Läufe über 5 und 10 km sowie ein Bambinilauf.', null, null],
            'obstbaum' => ['Obstbaumschnittkurs', 41, '09:00', '12:00', false, null, 'Streuobstwiese am Ortsrand', 3, 'Vereine', null, null, null],
        ];
        foreach ($events as $key => [$title, $days, $start, $end, $allDay, $location, $venue, $organization, $category, $description, $notice, $url]) {
            $event = Event::create(['title' => $title, 'description' => $description, 'starts_at' => $this->at($days, $start), 'ends_at' => $allDay ? $this->at($days + 1, '00:00') : $this->at($days, $end), 'all_day' => $allDay, 'location_id' => $location ? $this->ref('loc.'.$location, Location::class)->getKey() : null, 'venue' => $venue, 'organization_id' => $organization !== null ? $this->ref('org.'.$organization, Organization::class)->getKey() : null, 'category_id' => $this->category(CategoryContext::Event, $category)->getKey(), 'schedule_notice' => $notice, 'url' => $url, 'auto_archive' => true]);
            $this->publish($event, 20);
            $this->route($event);
            $this->r['evt.'.$key] = $event;
        }
        $this->ref('evt.gemeinderat', Event::class)->forceFill(['contact_person_id' => $this->ref('person.sitzung', Person::class)->getKey()])->save();
        $this->ref('evt.lauf', Event::class)->forceFill(['registration_url' => 'https://www.example.org/verein-1/volkslauf-anmeldung'])->save();
        $this->ref('evt.gemeinderat', Event::class)->documents()->attach($this->ref('doc.sitzung', Document::class)->getKey(), ['slot' => 'anhaenge', 'sort_order' => 0]);

        // Cancelled and rescheduled examples.
        $this->ref('evt.seniorennachmittag', Event::class)->forceFill(['operational_status' => EventOperationalStatus::Cancelled, 'schedule_notice' => 'Die Veranstaltung muss leider entfallen. Ein Ersatztermin wird bekannt gegeben.'])->save();
        $this->ref('evt.flohmarkt', Event::class)->forceFill(['schedule_notice' => 'Neuer Termin: Der Flohmarkt wurde um eine Woche verschoben und beginnt jetzt bereits um 9 Uhr.'])->save();

        // Past events (public archive).
        foreach ([['Seefest mit Feuerwerk', -40, 'see', 'Kultur'], ['Ferienprogramm: Waldtag', -60, null, 'Sport und Freizeit']] as $index => [$title, $days, $location, $category]) {
            $event = Event::create(['title' => $title, 'description' => 'Archivbeispiel einer vergangenen Veranstaltung.', 'starts_at' => $this->at($days, '17:00'), 'ends_at' => $this->at($days, '23:00'), 'location_id' => $location ? $this->ref('loc.'.$location, Location::class)->getKey() : null, 'venue' => $location ? null : 'Treffpunkt Parkplatz Schulweg', 'category_id' => $this->category(CategoryContext::Event, $category)->getKey(), 'auto_archive' => true]);
            $this->publish($event, 90 - $days);
            $this->route($event);
            $this->r['evt.past'.$index] = $event;
        }
    }

    // ------------------------------------------------------------------ public notices

    private function notices(): void
    {
        $notices = [
            'amtsblatt' => ['Amtsblatt Nr. 18/2026 der Regierung (Musterausgabe)', 'Hinweis auf die Veröffentlichung des Amtsblatts.', 'Amtliche Bekanntmachungen', 7, ['bekanntmachung' => ['amtsblatt']]],
            'sitzung' => ['Bekanntmachung der Gemeinderatssitzung', 'Tagesordnung der nächsten öffentlichen Sitzung des Gemeinderats.', 'Sitzungen des Gemeinderats', 2, ['bekanntmachung' => ['sitzung']]],
            'bplan' => ['Bebauungsplan Nr. 27 „Am Mühlbach“ – Öffentliche Auslegung', 'Der Entwurf liegt vom 15. Oktober bis 15. November öffentlich aus. Stellungnahmen sind während dieser Frist möglich.', 'Bauleitplanung', 10, ['bekanntmachung' => ['bplan'], 'anlagen' => ['bplan_text']]],
            'haushalt' => ['Haushaltssatzung 2026 – Bekanntmachung', 'Die Haushaltssatzung tritt mit Beginn des Haushaltsjahres in Kraft.', 'Amtliche Bekanntmachungen', 40, ['bekanntmachung' => ['haushalt']]],
            'wahl' => ['Wahlbekanntmachung (Muster)', 'Musterbeispiel einer Wahlbekanntmachung mit Anlage.', 'Wahlen', 15, ['bekanntmachung' => ['wahl']]],
            'hundesteuer' => ['Satzung zur Änderung der Hundesteuersatzung', 'Die geänderte Satzung tritt am 1. Januar in Kraft.', 'Amtliche Bekanntmachungen', 280, ['bekanntmachung' => ['satzung_2026']]],
        ];
        foreach ($notices as $key => [$title, $summary, $category, $daysAgo, $documents]) {
            $notice = PublicNotice::create(['title' => $title, 'summary' => $summary, 'body' => $key === 'bplan' ? "Der Gemeinderat hat in seiner Sitzung den Entwurf des Bebauungsplans gebilligt.\n\nDie Unterlagen können während der Auslegungsfrist im Bauamt (Zimmer 2. OG 21) und online eingesehen werden.\n\n*Musterinhalt – keine amtliche Bekanntmachung.*" : null, 'category_id' => $this->category(CategoryContext::PublicNotice, $category)->getKey(), 'published_on' => $this->now->subDays($daysAgo)->toDateString()]);
            foreach ($documents as $slot => $keys) {
                foreach ($keys as $index => $document) {
                    $notice->documents()->attach($this->ref('doc.'.$document, Document::class)->getKey(), ['slot' => $slot, 'sort_order' => $index]);
                }
            }
            $this->publish($notice, $daysAgo, $key === 'sitzung' ? 7 : null);
            $this->route($notice);
            $this->r['notice.'.$key] = $notice;
        }
        $this->ref('notice.hundesteuer', PublicNotice::class)->services()->attach($this->ref('svc.hundesteuer', Service::class)->getKey());
        $this->ref('notice.bplan', PublicNotice::class)->services()->attach($this->ref('svc.bauantrag', Service::class)->getKey());

        $expired = PublicNotice::create(['title' => 'Bekanntmachung über die Auslegung des Wählerverzeichnisses (abgelaufen)', 'summary' => 'Archivbeispiel einer abgelaufenen Bekanntmachung.', 'category_id' => $this->category(CategoryContext::PublicNotice, 'Wahlen')->getKey(), 'published_on' => $this->now->subDays(200)->toDateString()]);
        $this->publish($expired, 200, -150);
        $this->route($expired);
        $scheduled = PublicNotice::create(['title' => 'Bekanntmachung zur Grundsteuer (geplant)', 'summary' => 'Wird in einigen Tagen veröffentlicht.', 'category_id' => $this->category(CategoryContext::PublicNotice, 'Amtliche Bekanntmachungen')->getKey(), 'published_on' => $this->now->addDays(5)->toDateString()]);
        $this->publish($scheduled, -5);
        $this->route($scheduled);
    }

    // ------------------------------------------------------------------ pages

    private function pages(): void
    {
        $listing = [
            '/buergerservice' => ['Bürgerservice', 'Leistungen finden, Unterlagen vorbereiten und die zuständige Stelle erreichen.', 'Viele Anliegen erledigen Sie direkt im Rathaus, einige auch online. Wählen Sie eine Leistung aus oder suchen Sie nach Ihrem Anliegen.'],
            '/buergerservice/a-z' => ['Bürgerservice von A bis Z', 'Alle Leistungen der Gemeindeverwaltung in alphabetischer Reihenfolge.', null],
            '/aktuelles' => ['Aktuelles', 'Meldungen aus Rathaus und Gemeinde.', null],
            '/veranstaltungen' => ['Veranstaltungen', 'Termine von Gemeinde, Vereinen und Einrichtungen.', null],
            '/bekanntmachungen' => ['Bekanntmachungen', 'Amtliche Bekanntmachungen der Gemeinde.', 'Amtliche Bekanntmachungen werden zusätzlich an den Amtstafeln am Rathaus ausgehängt. *Musterinhalt.*'],
            '/dokumente' => ['Formulare und Dokumente', 'Formulare, Satzungen, Merkblätter und Pläne zum Herunterladen.', null],
            '/verzeichnisse' => ['Ansprechpersonen und Einrichtungen', 'Ämter, Ansprechpersonen, Vereine und Einrichtungen in Merching.', null],
        ];
        foreach ($listing as $path => [$title, $summary, $body]) {
            $page = Page::create(['title' => $title, 'summary' => $summary, 'body' => $body]);
            $this->publish($page, 100);
            $this->route($page, $path);
            $this->r['page.'.$path] = $page;
        }

        $pages = [
            'rathaus' => ['Rathaus und Politik', 'Gemeindeverwaltung, Gemeinderat und Ortsrecht.', 'Hier finden Sie Informationen zur Gemeindeverwaltung, zum Gemeinderat und zu den Satzungen der Gemeinde.', '/rathaus-und-politik', 'zentral'],
            'leben' => ['Leben in Merching', 'Freizeit, Familie, Vereine und Einrichtungen.', 'Merching bietet Familien, Vereinen und Erholungssuchenden vielfältige Angebote. *Musterinhalt.*', '/leben', null],
            'bauen' => ['Bauen und Wirtschaft', 'Bauleitplanung, Bauanträge und Gewerbe.', 'Informationen für Bauherrinnen, Bauherren und Gewerbetreibende.', '/bauen-und-wirtschaft', 'bauamt'],
            'ortsrecht' => ['Ortsrecht und Satzungen', 'Geltende Satzungen und Verordnungen der Gemeinde.', 'Die folgenden Satzungen sind Musterdokumente. Ältere Fassungen bleiben im Archiv abrufbar.', '/ortsrecht', 'zentral'],
            'barrierefreiheit' => ['Erklärung zur Barrierefreiheit (Platzhalter)', null, "Platzhalterseite für die Gestaltungsprüfung.\n\nDie verbindliche Erklärung zur Barrierefreiheit wird vor dem Livegang von der Gemeinde erstellt und geprüft.", '/barrierefreiheit', null],
            'datenschutz' => ['Datenschutzerklärung (Platzhalter)', null, 'Platzhalterseite für die Gestaltungsprüfung. Der geprüfte Text wird vor dem Livegang eingefügt.', '/datenschutz', null],
            'impressum' => ['Impressum (Platzhalter)', null, 'Platzhalterseite für die Gestaltungsprüfung. Die Pflichtangaben werden vor dem Livegang ergänzt.', '/impressum', null],
            'kinderbetreuung' => ['Kinderbetreuung', 'Krippe, Kindergarten, Hort und Tagespflege in Merching.', "In Merching gibt es Betreuungsangebote für Kinder ab einem Jahr bis zum Ende der Grundschulzeit.\n\n*Musterinhalt zur Demonstration.*", '/leben/kinderbetreuung', 'zentral'],
        ];
        foreach ($pages as $key => [$title, $summary, $body, $path, $department]) {
            $page = Page::create(['title' => $title, 'summary' => $summary, 'body' => $body, 'department_id' => $department ? $this->ref('dep.'.$department, Department::class)->getKey() : null]);
            $this->publish($page, 100);
            $this->route($page, $path);
            $this->r['page.'.$key] = $page;
        }
        $this->ref('page.ortsrecht', Page::class)->documents()->attach($this->ref('doc.satzung_2026', Document::class)->getKey(), ['slot' => 'downloads', 'group_label' => '2026', 'sort_order' => 0]);
        $this->ref('page.ortsrecht', Page::class)->documents()->attach($this->ref('doc.haushalt', Document::class)->getKey(), ['slot' => 'downloads', 'group_label' => '2026', 'sort_order' => 1]);
        $this->ref('page.ortsrecht', Page::class)->documents()->attach($this->ref('doc.satzung_2021', Document::class)->getKey(), ['slot' => 'anlagen', 'group_label' => 'Frühere Fassungen', 'sort_order' => 0]);
        $this->ref('page.bauen', Page::class)->externalResources()->attach($this->ref('link.bayernatlas', ExternalResource::class)->getKey(), ['slot' => 'links', 'sort_order' => 0]);
        $this->ref('page.bauen', Page::class)->externalResources()->attach($this->ref('link.portal', ExternalResource::class)->getKey(), ['slot' => 'online-dienste', 'sort_order' => 0]);
        $this->ref('page.kinderbetreuung', Page::class)->contacts()->attach($this->ref('person.sitzung', Person::class)->getKey(), ['sort_order' => 0]);

        $greeting = Page::create(['title' => 'Grußwort des Ersten Bürgermeisters', 'summary' => 'Musterinhalt: ein Grußwort zur Demonstration der Startseite.', 'body' => "Liebe Mitbürgerinnen und Mitbürger,\n\nherzlich willkommen auf der neuen Website der Gemeinde. Hier finden Sie Leistungen, Formulare, Termine und Ansprechpersonen an einem Ort.\n\n*Musterinhalt – dieser Text und die genannte Person sind frei erfunden.*"]);
        $this->publish($greeting, 100);
        $this->route($greeting, '/rathaus-und-politik/grusswort');
        SiteSettings::query()->whereKey(1)->update([
            'greeting_text' => 'Liebe Mitbürgerinnen, liebe Mitbürger, ich freue mich sehr, dass Sie uns auf dem digitalen Weg besuchen.',
            'greeting_name' => 'Max Mustermann',
            'greeting_role' => 'Erster Bürgermeister (Demo)',
            'greeting_page_id' => $greeting->getKey(),
            'greeting_media_id' => $this->ref('img.grusswort', Media::class)->getKey(),
        ]);

        // A page that exercises every supported content block.
        $page = Page::create(['title' => 'Freizeit am See', 'summary' => 'Baden, Spielen und Erholen am Badeplatz – mit allen Informationen zu Badeordnung, Veranstaltungen und Anfahrt.', 'department_id' => $this->ref('dep.ordnung', Department::class)->getKey()]);
        $this->publish($page, 30);
        $this->route($page, '/leben/freizeit-am-see');
        $this->r['page.see'] = $page;
        $this->blocks($page, [
            ['text', null, 'Der Badeplatz am Seeufer ist im Sommer ein beliebter Treffpunkt für Familien. Liegewiese, Badesteg und Spielplatz sind frei zugänglich. *Musterinhalt zur Demonstration aller Inhaltsbausteine.*'],
            ['image', 'see'],
            ['heading', 'Baden und Wasserqualität', null, 2],
            ['text', null, "Das Baden erfolgt auf eigene Gefahr. Eine Badeaufsicht gibt es nur an Wochenenden in den Sommerferien.\n\n1. Bitte beachten Sie die Badeordnung.\n2. Glas ist auf der Liegewiese nicht erlaubt.\n3. Hunde sind im Badebereich nicht gestattet."],
            ['callout', 'Hinweis zur Wasserqualität', 'Die Wasserqualität wird während der Badesaison regelmäßig vom Gesundheitsamt überprüft. Bei Blaualgen wird an den Zugängen informiert.'],
            ['external', 'badewasser'],
            ['downloads', 'badeordnung'],
            ['heading', 'Spielplatz und Kiosk', null, 3],
            ['text', null, 'Der Spielplatz mit dem neuen Spielschiff eignet sich für Kinder von 3 bis 12 Jahren.'],
            ['gallery', 'seefest'],
            ['heading', 'Häufige Fragen', null, 2],
            ['accordion', 'Gibt es Parkplätze?', 'Ja, am Seeweg gibt es rund 80 Parkplätze. An Sommerwochenenden empfehlen wir die Anreise mit dem Fahrrad.'],
            ['accordion', 'Darf gegrillt werden?', 'Grillen ist nur auf dem ausgewiesenen Grillplatz erlaubt.'],
            ['heading', 'Veranstaltungen am See', null, 2],
            ['events', 'lauf'],
            ['events', 'seniorennachmittag'],
            ['heading', 'Anfahrt', null, 2],
            ['location', 'see'],
            ['heading', 'Ansprechpersonen', null, 2],
            ['department', 'ordnung'],
            ['contact', 'demo'],
            ['services', 'veranstaltung'],
        ]);
    }

    /** @param list<array{0:string,1:string|null,2?:string|null,3?:int}> $blocks */
    private function blocks(Page|Article|Service $owner, array $blocks): void
    {
        $references = ['image' => ['media_id', 'img.'], 'gallery' => ['gallery_id', 'gallery.'], 'downloads' => ['document_id', 'doc.'], 'contact' => ['person_id', 'person.'], 'department' => ['department_id', 'dep.'], 'services' => ['service_id', 'svc.'], 'events' => ['event_id', 'evt.'], 'external' => ['external_resource_id', 'link.'], 'location' => ['location_id', 'loc.']];
        foreach ($blocks as $index => $block) {
            [$type, $value] = $block;
            $row = ['type' => $type, 'sort_order' => $index];
            if (isset($references[$type])) {
                $row[$references[$type][0]] = $this->ref($references[$type][1].$value, Model::class)->getKey();
            } else {
                $row['heading'] = $value;
                $row['text'] = $block[2] ?? null;
                $row['heading_level'] = $block[3] ?? null;
            }
            if ($type === 'heading') {
                $row['heading'] = $value;
                $row['text'] = null;
            }
            $owner->blocks()->create($row);
        }
    }

    // ------------------------------------------------------------------ council

    private function council(): void
    {
        $current = CouncilTerm::create(['title' => 'Gemeinderat 2026–2032', 'description' => 'Der Gemeinderat besteht in dieser Demonstration aus dem Ersten Bürgermeister und 16 Gemeinderatsmitgliedern. Alle Namen sind frei erfunden.', 'starts_on' => '2026-05-01', 'ends_on' => '2032-04-30', 'is_historical' => false]);
        $previous = CouncilTerm::create(['title' => 'Gemeinderat 2020–2026', 'description' => 'Historische Wahlperiode (Demonstration).', 'starts_on' => '2020-05-01', 'ends_on' => '2026-04-30', 'is_historical' => true]);
        $names = [
            ['Max Mustermann', 'Erster Bürgermeister', 'Bürgerliste Beispiel'], ['Petra Musterfrau', 'Zweite Bürgermeisterin', 'Wählergemeinschaft Muster'], ['Hans Beispielmann', 'Dritter Bürgermeister', 'Freie Liste Demo'],
            ['Andrea Probst', 'Gemeinderätin', 'Bürgerliste Beispiel'], ['Bernd Vorlagenberger', 'Gemeinderat', 'Bürgerliste Beispiel'], ['Claudia Entwurfsmann', 'Gemeinderätin', 'Bürgerliste Beispiel'], ['Dieter Platzmann', 'Gemeinderat', 'Bürgerliste Beispiel'],
            ['Eva Mustergruber', 'Gemeinderätin', 'Wählergemeinschaft Muster'], ['Florian Testmeier', 'Gemeinderat', 'Wählergemeinschaft Muster'], ['Gabriele Exemplar', 'Gemeinderätin', 'Wählergemeinschaft Muster'], ['Heinrich Musterhuber', 'Gemeinderat', 'Wählergemeinschaft Muster'],
            ['Ingrid Beispielhofer', 'Gemeinderätin', 'Freie Liste Demo'], ['Johann Demowski', 'Gemeinderat', 'Freie Liste Demo'], ['Katrin Probemann', 'Gemeinderätin', 'Freie Liste Demo'],
            ['Ludwig Fiktiv', 'Gemeinderat', 'Junge Liste Muster'], ['Monika Erfunden', 'Gemeinderätin', 'Junge Liste Muster'], ['Norbert Annahme', 'Gemeinderat', 'Junge Liste Muster'],
        ];
        $members = [];
        foreach ($names as $index => [$name, $role, $group]) {
            $member = CouncilMember::create(['title' => $name, 'description' => $index === 0 ? 'Fiktive Person zur Demonstration.' : null]);
            $this->publish($member, 160);
            $current->memberships()->create(['council_member_id' => $member->getKey(), 'role' => $role, 'grouping' => $group, 'sort_order' => $index]);
            if ($index % 3 !== 2) {
                $previous->memberships()->create(['council_member_id' => $member->getKey(), 'role' => $index === 0 ? 'Gemeinderat' : $role, 'grouping' => $group, 'sort_order' => $index]);
            }
            $members[] = $member;
        }
        $committees = [
            ['Bau- und Umweltausschuss', 'Berät Bauanträge, Bauleitplanung und Umweltthemen.', [0, 2, 4, 7, 9, 12, 15]],
            ['Finanz- und Rechnungsprüfungsausschuss', 'Prüft die Jahresrechnung und berät den Haushalt.', [1, 3, 8, 11, 14]],
            ['Kultur-, Sport- und Sozialausschuss', null, [0, 5, 6, 10, 13, 16]],
        ];
        foreach ($committees as $index => [$title, $description, $memberIndexes]) {
            $committee = Committee::create(['title' => $title, 'description' => $description, 'council_term_id' => $current->getKey(), 'sort_order' => $index]);
            foreach ($memberIndexes as $order => $memberIndex) {
                $committee->committeeMemberships()->create(['council_member_id' => $members[$memberIndex]->getKey(), 'role' => $order === 0 ? 'Vorsitz' : 'Mitglied', 'sort_order' => $order]);
            }
            $this->publish($committee, 150);
        }
        $this->publish($current, 150);
        $this->publish($previous, 2000);
        $this->route($current, '/gemeinderat');
        $this->route($previous, '/gemeinderat/2020-2026');
        $this->r['council.current'] = $current;
    }

    // ------------------------------------------------------------------ alerts, synonyms, navigation, redirects

    private function alertsAndSynonyms(): void
    {
        $alert = SiteAlert::create(['title' => 'Vollsperrung der Seestraße', 'body' => 'Bis voraussichtlich Ende Oktober ist die Seestraße zwischen Kirchplatz und Ortsausgang gesperrt.', 'severity' => AlertSeverity::Warning, 'link_url' => rtrim((string) config('app.url'), '/').$this->ref('art.sperrung', Article::class)->publicPath(), 'link_label' => 'Umleitung ansehen']);
        $this->publish($alert, 2, 10);

        foreach ([['Perso', 'Personalausweis; Ausweis'], ['Müll', 'Abfall; Sperrmüll; Wertstoffhof'], ['Hund', 'Hundesteuer; Hundemarke'], ['Ummelden', 'Wohnsitz anmelden; Anmeldung'], ['Baugenehmigung', 'Bauantrag'], ['Kita', 'Kinderbetreuung; Kindergarten'], ['Hochzeit', 'Eheschließung; Heiraten']] as [$phrase, $alternatives]) {
            SearchSynonym::create(['phrase' => $phrase, 'alternatives' => $alternatives, 'is_active' => true]);
        }
    }

    private function navigation(): void
    {
        $route = fn (string $key) => PublicRoute::query()->where('routable_type', $this->ref($key, Model::class)->getMorphClass())->where('routable_id', $this->ref($key, Model::class)->getKey())->where('is_canonical', true)->value('id');
        $item = function (NavigationMenu $menu, string $label, ?string $key, ?int $parent = null, int $order = 0, ?string $resource = null) use ($route): NavigationItem {
            return NavigationItem::create(['menu' => $menu, 'label' => $label, 'public_route_id' => $key ? $route($key) : null, 'external_resource_id' => $resource ? $this->ref('link.'.$resource, ExternalResource::class)->getKey() : null, 'parent_id' => $parent, 'sort_order' => $order, 'is_active' => true]);
        };
        $main = [
            ['Bürgerservice', 'page./buergerservice', [['Leistungen von A bis Z', 'page./buergerservice/a-z'], ['Umzug nach Merching', 'life.umzug'], ['Geburt eines Kindes', 'life.geburt'], ['Bauen und Renovieren', 'life.bauen'], ['Formulare und Dokumente', 'page./dokumente'], ['Online-Anträge', null, 'portal'], ['Ansprechpersonen', 'page./verzeichnisse']]],
            ['Rathaus & Politik', 'page.rathaus', [['Gemeinderat', 'council.current'], ['Bekanntmachungen', 'page./bekanntmachungen'], ['Bürgerbüro', 'dep.buergerbuero'], ['Bauamt', 'dep.bauamt'], ['Ortsrecht und Satzungen', 'page.ortsrecht'], ['Rathaus und Öffnungszeiten', 'loc.rathaus']]],
            ['Aktuelles', 'page./aktuelles', [['Meldungen', 'page./aktuelles'], ['Veranstaltungen', 'page./veranstaltungen'], ['Bekanntmachungen', 'page./bekanntmachungen']]],
            ['Leben & Freizeit', 'page.leben', [['Freizeit am See', 'page.see'], ['Kinderbetreuung', 'page.kinderbetreuung'], ['Vereine', 'page./verzeichnisse'], ['Wertstoffhof', 'loc.wertstoffhof'], ['Ortsansichten', 'gallery.ort']]],
            ['Bauen & Wirtschaft', 'page.bauen', [['Bauantrag stellen', 'svc.bauantrag'], ['Gewerbe anmelden', 'svc.gewerbe'], ['Bebauungspläne', 'notice.bplan']]],
        ];
        foreach ($main as $index => [$label, $key, $children]) {
            $parent = $item(NavigationMenu::Main, $label, $key, null, $index);
            foreach ($children as $childIndex => $child) {
                $item(NavigationMenu::Main, $child[0], $child[1], $parent->getKey(), $childIndex, $child[2] ?? null);
            }
        }
        foreach ([['Personalausweis und Reisepass', 'svc.personalausweis'], ['Wohnsitz anmelden', 'svc.anmeldung'], ['Hundesteuer', 'svc.hundesteuer'], ['Sperrmüll anmelden', 'svc.sperrmuell'], ['Führungszeugnis', 'svc.fuehrungszeugnis'], ['Formulare', 'page./dokumente'], ['Bauantrag', 'svc.bauantrag'], ['Wertstoffhof', 'loc.wertstoffhof']] as $index => [$label, $key]) {
            $item(NavigationMenu::Service, $label, $key, null, $index);
        }
        $footer = [
            ['Bürgerservice', 'page./buergerservice', [['Leistungen A–Z', 'page./buergerservice/a-z'], ['Formulare', 'page./dokumente'], ['Online-Anträge', null, 'portal'], ['Ansprechpersonen', 'page./verzeichnisse']]],
            ['Aktuelles', 'page./aktuelles', [['Meldungen', 'page./aktuelles'], ['Veranstaltungen', 'page./veranstaltungen'], ['Bekanntmachungen', 'page./bekanntmachungen']]],
            ['Rechtliches', 'page.impressum', [['Barrierefreiheit', 'page.barrierefreiheit'], ['Datenschutz', 'page.datenschutz'], ['Impressum', 'page.impressum']]],
        ];
        foreach ($footer as $index => [$label, $key, $children]) {
            $parent = $item(NavigationMenu::Footer, $label, $key, null, $index);
            foreach ($children as $childIndex => $child) {
                $item(NavigationMenu::Footer, $child[0], $child[1], $parent->getKey(), $childIndex, $child[2] ?? null);
            }
        }
    }

    private function redirects(): void
    {
        $manager = app(RedirectManager::class);
        $manager->save(['source_path' => '/buergerservice-online/', 'destination' => '/buergerservice', 'status_code' => 301, 'notes' => 'Demo: frühere Adresse des Bürgerservice']);
        $manager->save(['source_path' => '/aktuelles/archiv-2019', 'destination' => '/aktuelles', 'status_code' => 301, 'notes' => 'Demo: frühere Archivseite']);
        $manager->save(['source_path' => '/alte-umfrage', 'status_code' => 410, 'notes' => 'Demo: dauerhaft entfernte Seite']);
    }

    // ------------------------------------------------------------------ revisions & proposals

    private function history(): void
    {
        $revisions = app(RevisionService::class);
        $editors = [$this->users['redaktion'], $this->users['fachbereich'], $this->users['termine']];
        foreach ($this->r as $model) {
            if ($model instanceof Revisionable) {
                $revisions->record($model, $editors[$model->getKey() % 3], 'Ersterfassung (Demo)');
            }
        }
        // A short, realistic history on the flagship service.
        $service = $this->ref('svc.personalausweis', Service::class);
        $service->forceFill(['processing_duration' => 'Ca. 2–3 Wochen (Beispielangabe)'])->save();
        $revisions->record($service, $this->users['fachbereich'], 'Bearbeitungsdauer aktualisiert');
        $service->forceFill(['processing_duration' => 'Ca. 3–4 Wochen (Beispielangabe)', 'important_notice' => 'Musterinhalt: Gebühren und Fristen in dieser Demonstration sind nicht verbindlich.'])->save();
        $revisions->record($service, $this->users['redaktion'], 'Hinweis ergänzt, Bearbeitungsdauer korrigiert');
        $article = $this->ref('art.sperrung', Article::class);
        $article->forceFill(['summary' => $article->summary.' Die Umleitung erfolgt über die Kreisstraße.'])->save();
        $revisions->record($article, $this->users['fachbereich'], 'Umleitung ergänzt');
    }

    private function proposals(): void
    {
        $proposals = app(ProposalService::class);
        $propose = function (Model $record, User $author, array $attributes, string $summary, int $hoursAgo) use ($proposals): ContentProposal {
            /** @var Model&Proposable $record */
            $proposal = $proposals->create($record, $author);
            $payload = $proposal->payload;
            foreach ($attributes as $key => $value) {
                $payload['attributes'][$key] = $value;
            }
            $proposal->update(['payload' => $payload, 'summary' => $summary]);
            $proposals->submit($proposal, $author);
            $proposal->forceFill(['created_at' => now()->subHours($hoursAgo + 1), 'submitted_at' => now()->subHours($hoursAgo)])->save();

            return $proposal;
        };
        $propose($this->ref('evt.flohmarkt', Event::class), $this->users['termine'], ['schedule_notice' => 'Neuer Termin: Der Flohmarkt findet eine Woche später statt. Beginn bereits um 8:30 Uhr.', 'remarks' => 'Standgebühr 5 Euro (Beispiel).'], 'Termin geändert, Standgebühr ergänzt', 2);
        $propose($this->ref('notice.amtsblatt', PublicNotice::class), $this->users['fachbereich'], ['summary' => 'Das Amtsblatt Nr. 18/2026 ist erschienen und liegt im Rathaus zur Einsicht aus.'], 'Kurztext präzisiert', 20);
        $propose($this->ref('art.spielschiff', Article::class), $this->users['fachbereich'], ['summary' => 'Am Badeplatz steht jetzt ein Spielschiff aus Holz – gebaut von Bauhof, Vereinen und vielen Ehrenamtlichen.'], 'Dank an Ehrenamtliche ergänzt', 30);

        // Conflict: the live record changes the same field after the proposal was created.
        $conflict = $propose($this->ref('svc.personalausweis', Service::class), $this->users['fachbereich'], ['summary' => 'Den Personalausweis beantragen Sie persönlich im Bürgerbüro – am schnellsten mit Online-Termin.'], 'Hinweis auf Online-Termin', 50);
        $live = $this->ref('svc.personalausweis', Service::class);
        $live->forceFill(['summary' => 'Den Personalausweis beantragen Sie persönlich im Bürgerbüro. Bitte bringen Sie ein aktuelles biometrisches Foto mit.'])->save();
        app(RevisionService::class)->record($live, $this->users['redaktion'], 'Kurzbeschreibung direkt angepasst');
        unset($conflict);

        // Rejected and draft proposals for the history views.
        $rejected = $proposals->create($this->ref('page.see', Page::class), $this->users['termine']);
        $payload = $rejected->payload;
        $payload['attributes']['summary'] = 'Alles rund um den See!!!';
        $rejected->update(['payload' => $payload, 'summary' => 'Kurztext verkürzt']);
        $proposals->submit($rejected, $this->users['termine']);
        $proposals->reject($rejected, $this->users['redaktion'], 'Bitte sachlich formulieren und die Ausrufezeichen entfernen.');

        $draft = $proposals->create($this->ref('svc.hundesteuer', Service::class), $this->users['fachbereich']);
        $payload = $draft->payload;
        $payload['attributes']['processing_duration'] = 'Ca. 3 Werktage';
        $draft->update(['payload' => $payload, 'summary' => 'Bearbeitungsdauer (Entwurf)']);
    }

    private function qualityExamples(): void
    {
        // Media without alternative text (blocked from publication, shown on the dashboard).
        $media = new Media(['title' => 'Pressefoto ohne Alternativtext', 'copyright' => 'Demo-Illustration (frei erfunden)']);
        app(MediaStorage::class)->attach($media, $this->upload('pressefoto.jpg', (string) file_get_contents(database_path('demo/media/markt.jpg'))));
        $media->save();

        // Draft with a skipped heading level and a draft service without its online link.
        $page = Page::create(['title' => 'Entwurf: Seite mit Qualitätsproblemen', 'summary' => 'Demonstriert Fehler und Warnungen der Qualitätsprüfung.']);
        $page->blocks()->create(['type' => 'heading', 'heading' => 'Abschnitt', 'heading_level' => 2, 'sort_order' => 0]);
        $page->blocks()->create(['type' => 'heading', 'heading' => 'Übersprungene Ebene', 'heading_level' => 4, 'sort_order' => 1]);
        $page->blocks()->create(['type' => 'text', 'text' => 'Hier klicken für weitere Informationen.', 'sort_order' => 2]);
        $this->tag($page);
        $service = Service::create(['title' => 'Entwurf: Parkausweis für Anwohner', 'summary' => 'Entwurf ohne verknüpften Online-Dienst.', 'online_service_mode' => OnlineServiceMode::Application, 'category_id' => $this->category(CategoryContext::Service, 'Ordnung und Verkehr')->getKey()]);
        $this->tag($service);
        app(RevisionService::class)->record($page, $this->users['fachbereich'], 'Entwurf angelegt');
    }
}
