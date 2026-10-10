<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\BudgetPlan;
use App\Models\CouncilMember;
use App\Models\Media;
use App\Models\User;
use App\Services\Content\BudgetWorkflow;
use App\Services\Content\MediaStorage;
use App\Services\Content\RevisionService;
use App\Services\Routing\RouteManager;
use App\Support\Auth\DevelopmentAccounts;
use Database\Seeders\Demo\DemoFiles;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/** Additive, idempotent local demo: preserves existing content, images and settings. */
class BudgetPortraitDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! DevelopmentAccounts::allowed()) {
            throw new RuntimeException('Nur in der lokalen Entwicklungsumgebung verfügbar.');
        }
        $actor = User::query()->where('email', DevelopmentDemoSeeder::ADMIN_EMAIL)->firstOrFail();
        $workflow = app(BudgetWorkflow::class);
        $workflow->transaction(function () use ($actor, $workflow) {
            foreach ([2026 => true, 2027 => false] as $year => $published) {
                $existing = BudgetPlan::withTrashed()->where('year', $year)->first();
                if ($existing !== null) {
                    if ($existing->description === 'Fiktives Musterpaket zur Demonstration. Keine amtlichen Haushaltsdaten.' && ! $existing->sourceReferences()->exists()) {
                        $existing->sourceReferences()->create(['source_system' => DevelopmentDemoSeeder::SOURCE_SYSTEM, 'source_id' => 'budget-plan-'.$existing->getKey(), 'imported_at' => now()]);
                    }

                    continue;
                }
                $plan = BudgetPlan::create(['year' => $year, 'title' => 'Haushaltsplan '.$year, 'description' => 'Fiktives Musterpaket zur Demonstration. Keine amtlichen Haushaltsdaten.', 'show_components' => true]);
                $plan->sourceReferences()->create(['source_system' => DevelopmentDemoSeeder::SOURCE_SYSTEM, 'source_id' => 'budget-plan-'.$plan->getKey(), 'imported_at' => now()]);
                foreach (['Haushaltssatzung', 'Haushaltsplan', 'Finanzplan und Anlagen'] as $position => $title) {
                    $source = $workflow->upload($plan, UploadedFile::fake()->createWithContent(($position + 1).'-'.$title.'-'.$year.'.pdf', DemoFiles::pdf($title.' '.$year, ['Reihenfolge im Gesamt-PDF: '.($position + 1), 'Demonstrationsdaten, keine amtlichen Zahlen.'])), $actor);
                    $plan->components()->create(['budget_source_id' => $source->getKey(), 'sort_order' => $position]);
                }
                app(RouteManager::class)->assign($plan, '/haushaltsplaene/'.$year);
                app(RevisionService::class)->record($plan, $actor, 'Demo-Paket mit drei Quelldateien');
                if ($published) {
                    $plan->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
                    $workflow->finish($plan, $actor);
                }
            }
            foreach (['Max Mustermann' => 1, 'Petra Musterfrau' => 2] as $name => $variant) {
                $member = CouncilMember::query()->where('title', $name)->first();
                if ($member === null || $member->portrait_id !== null) {
                    continue;
                }
                $title = 'Ratsmitglied – Porträtillustration '.$variant.' (Demo)';
                $media = Media::query()->where('title', $title)->first();
                if ($media === null) {
                    $media = new Media(['title' => $title, 'alt_text' => 'Abstraktes Personenporträt zur Demonstration', 'creator' => 'DevelopmentDemoSeeder', 'copyright' => 'Fiktive lokale Demo-Illustration', 'focal_x' => 50, 'focal_y' => 35]);
                    app(MediaStorage::class)->attach($media, UploadedFile::fake()->createWithContent('ratsmitglied-demo-'.$variant.'.png', DemoFiles::portrait($variant)));
                    $media->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
                }
                $member->update(['portrait_id' => $media->getKey()]);
                app(RevisionService::class)->record($member, $actor, 'Optionales Demo-Porträt zugeordnet');
            }
        });
    }
}
