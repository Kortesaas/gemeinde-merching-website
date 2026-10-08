<?php

namespace Tests\Feature\Content;

use App\Enums\PublicationState;
use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Article;
use App\Models\Event;
use App\Models\PublicNotice;
use App\Models\SiteAlert;
use App\Services\Content\PublicationService;
use App\Support\SiteTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class PublicationLifecycleTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_draft_is_never_public(): void
    {
        $draft = $this->article(publishAt: now()->subDay());

        $this->assertSame(PublicationState::Draft, $draft->publicationState());
        $this->assertFalse($draft->isPubliclyReachable());
        $this->assertSame(0, Article::visible()->count());
        $this->assertSame(0, Article::publicArchive()->count());
    }

    public function test_scheduled_item_becomes_public_by_timestamp_without_cron(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01 08:00:00', 'UTC'));
        $article = $this->article(status: PublicationStatus::Published, publishAt: CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC'));

        $this->assertSame(PublicationState::Scheduled, $article->publicationState());
        $this->assertSame(0, Article::visible()->count());

        $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC'));

        $this->assertSame(PublicationState::Published, $article->fresh()?->publicationState());
        $this->assertSame(1, Article::visible()->count());
        // The stored status never changed – no job was needed.
        $this->assertSame(PublicationStatus::Published, $article->fresh()?->status);
    }

    public function test_expired_item_leaves_current_listings_but_stays_published_and_archived(): void
    {
        $article = $this->article(status: PublicationStatus::Published, publishAt: now()->subDays(10), expiresAt: now()->subMinute());

        $this->assertSame(PublicationState::Expired, $article->publicationState());
        $this->assertSame(0, Article::visible()->count());
        $this->assertSame(1, Article::publicArchive()->count());
        $this->assertSame(PublicationStatus::Published, $article->fresh()?->status, 'Expired content must not become a draft.');
    }

    public function test_expiry_happens_exactly_at_expires_at(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01 12:00:00', 'UTC'));
        $this->article(status: PublicationStatus::Published, publishAt: now()->subDay(), expiresAt: CarbonImmutable::parse('2026-06-01 12:00:01', 'UTC'));

        $this->assertSame(1, Article::visible()->count());
        $this->travelTo(CarbonImmutable::parse('2026-06-01 12:00:01', 'UTC'));
        $this->assertSame(0, Article::visible()->count());
    }

    public function test_archived_item_is_not_current_but_in_public_archive(): void
    {
        $article = $this->article(status: PublicationStatus::Published, publishAt: now()->subDays(3));
        app(PublicationService::class)->apply($article, PublicationStatus::Archived, $article->publish_at, null);
        $article->save();

        $this->assertSame(PublicationState::Archived, $article->publicationState());
        $this->assertNotNull($article->archived_at);
        $this->assertSame(0, Article::visible()->count());
        $this->assertSame(1, Article::publicArchive()->count());
        $this->assertTrue($article->isPubliclyReachable(), 'Articles keep a public archive.');
    }

    public function test_expired_notice_stays_reachable_in_archive_but_page_does_not(): void
    {
        $notice = new PublicNotice(['title' => 'Bekanntmachung']);
        $notice->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDays(20), 'expires_at' => now()->subDay()])->save();
        $page = $this->page();
        $page->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertTrue($notice->isPubliclyReachable());
        $this->assertFalse($page->fresh()?->isPubliclyReachable());
    }

    public function test_dst_start_publication_is_exact(): void
    {
        // 03:00 site time on 29.03.2026 (clocks jump 02:00 → 03:00) = 01:00 UTC.
        $publishAt = SiteTime::fromInput('2026-03-29T03:00');
        $this->article(status: PublicationStatus::Published, publishAt: $publishAt);

        $this->travelTo(CarbonImmutable::parse('2026-03-29 00:59:59', 'UTC'));
        $this->assertSame(0, Article::visible()->count());
        $this->travelTo(CarbonImmutable::parse('2026-03-29 01:00:00', 'UTC'));
        $this->assertSame(1, Article::visible()->count());
    }

    public function test_dst_end_repeated_hour_never_publishes_early(): void
    {
        // 02:30 occurs twice on 25.10.2026; the later occurrence (01:30 UTC) is used.
        $this->article(status: PublicationStatus::Published, publishAt: SiteTime::fromInput('2026-10-25T02:30'));

        $this->travelTo(CarbonImmutable::parse('2026-10-25 00:30:00', 'UTC')); // first 02:30
        $this->assertSame(0, Article::visible()->count());
        $this->travelTo(CarbonImmutable::parse('2026-10-25 01:30:00', 'UTC')); // second 02:30
        $this->assertSame(1, Article::visible()->count());
    }

    public function test_transition_rules(): void
    {
        $service = app(PublicationService::class);
        $draft = $this->article();

        $this->assertSame('content.published', $service->apply($draft, PublicationStatus::Published, null, null));
        $this->assertNotNull($draft->publish_at, 'Publishing without date publishes now.');

        $archived = $this->article();
        $this->expectException(DomainRuleViolation::class);
        $service->apply($archived, PublicationStatus::Archived, null, null); // draft → archived is not allowed
    }

    public function test_publication_end_before_start_is_rejected(): void
    {
        $this->expectException(DomainRuleViolation::class);

        app(PublicationService::class)->apply($this->article(), PublicationStatus::Published, now(), now()->subHour());
    }

    public function test_event_auto_archive_derives_expiry_from_event_end(): void
    {
        $event = new Event(['title' => 'Dorffest', 'starts_at' => SiteTime::fromInput('2026-07-04T18:00'), 'ends_at' => SiteTime::fromInput('2026-07-04T23:00'), 'auto_archive' => true]);
        $event->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();

        $this->assertSame('2026-07-04 21:00:00', $event->expires_at?->format('Y-m-d H:i:s'));

        $allDay = new Event(['title' => 'Markt', 'starts_at' => SiteTime::fromInput('2026-07-05T00:00'), 'all_day' => true, 'auto_archive' => true]);
        $allDay->forceFill(['status' => PublicationStatus::Draft])->save();
        // End of 05.07. in Europe/Berlin (UTC+2) = 21:59:59 UTC.
        $this->assertSame('2026-07-05 21:59:59', $allDay->expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_site_alert_uses_the_same_lifecycle(): void
    {
        $alert = new SiteAlert(['title' => 'Straßensperrung', 'body' => 'Hauptstraße gesperrt', 'severity' => 'warning']);
        $alert->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->addHour(), 'expires_at' => now()->addDay()])->save();

        $this->assertSame(0, SiteAlert::visible()->count());
        $this->travel(2)->hours();
        $this->assertSame(1, SiteAlert::visible()->count());
        $this->travel(1)->days();
        $this->assertSame(0, SiteAlert::visible()->count());
    }
}
