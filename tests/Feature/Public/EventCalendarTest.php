<?php

namespace Tests\Feature\Public;

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Services\Routing\RouteManager;
use App\Support\Content\EventCalendar;
use App\Support\SiteTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $title, string $startLocal, ?string $endLocal = null, bool $allDay = false): Event
    {
        $event = Event::create(['title' => $title, 'starts_at' => SiteTime::fromInput($startLocal), 'ends_at' => $endLocal ? SiteTime::fromInput($endLocal) : null, 'all_day' => $allDay]);
        $event->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($event, '/termin-'.$event->id);

        return $event;
    }

    public function test_dates_cover_multi_day_and_all_day_events_in_site_time(): void
    {
        $evening = $this->event('Abendtermin', '2030-03-10T23:30');
        $market = $this->event('Markt', '2030-12-05T00:00', '2030-12-07T00:00', allDay: true);

        // 23:30 Berlin is still 10 March although it is 22:30 UTC.
        $this->assertSame(['2030-03-10'], EventCalendar::dates($evening));
        $this->assertSame(['2030-12-05', '2030-12-06'], EventCalendar::dates($market));
    }

    public function test_listing_shows_month_grid_linked_to_list_entries(): void
    {
        $start = SiteTime::now()->addDays(3)->setTime(18, 0);
        $event = $this->event('Kalendertest', $start->format('Y-m-d\TH:i'));
        $month = $start->locale('de')->translatedFormat('F Y');

        $this->get('/veranstaltungen')->assertOk()
            ->assertSee('id="kalender"', false)
            ->assertSee('data-cal-date="'.$start->toDateString().'"', false)
            ->assertSee('href="#termin-'.$event->id.'"', false)
            ->assertSee('id="termin-'.$event->id.'" data-event-dates="'.$start->toDateString().'"', false)
            ->assertSee($month);
    }

    public function test_other_months_are_reachable_without_javascript(): void
    {
        $this->get('/veranstaltungen?monat=2031-02')->assertOk()->assertSee('Februar 2031')->assertSee('monat=2031-03', false)->assertSee('monat=2031-01', false);
        $this->get('/veranstaltungen?monat=2031-13')->assertStatus(302);
    }
}
