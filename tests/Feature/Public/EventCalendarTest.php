<?php

namespace Tests\Feature\Public;

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Services\Routing\RouteManager;
use App\Support\Content\EventCalendar;
use App\Support\SiteTime;
use Carbon\CarbonImmutable;
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

    public function test_only_selected_month_is_listed_without_truncation_and_filters_keep_that_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01'));
        for ($i = 1; $i <= 23; $i++) {
            $this->event('Februartermin '.$i, sprintf('2030-02-%02dT18:00', $i));
        }
        $march = $this->event('Märztermin', '2030-03-03T10:00');
        $span = $this->event('Monatsübergreifend', '2030-01-31T10:00', '2030-02-02T12:00');
        $exclusive = $this->event('Nur Januar', '2030-01-31T00:00', '2030-02-01T00:00', allDay: true);
        $this->get('/veranstaltungen?monat=2030-02')->assertOk()
            ->assertSee('24 Veranstaltungen')->assertSee('Februartermin 23')
            ->assertSee($span->title)->assertDontSee($march->title)->assertDontSee($exclusive->title)
            ->assertSee('name="monat" value="2030-02"', false);
        $this->get('/veranstaltungen?monat=2030-03&q=März')->assertOk()->assertSee($march->title)->assertDontSee('Februartermin');
    }

    public function test_long_spanning_events_mark_the_selected_month_even_after_the_first_31_days(): void
    {
        $event = $this->event('Ausstellung', '2031-01-01T10:00', '2031-03-15T18:00');
        $month = EventCalendar::month('2031-02', null);
        $this->assertCount(28, EventCalendar::dates($event, $month));
        $this->assertTrue(EventCalendar::overlaps($event, $month));
        $this->assertFalse(EventCalendar::overlaps($event, EventCalendar::month('2031-04', null)));
    }

    public function test_unspecified_source_time_is_preserved_without_inventing_all_day_or_midnight(): void
    {
        $event = $this->event('Termin ohne feste Uhrzeit', '2031-02-15T00:00');
        $event->update(['time_is_unspecified' => true, 'time_text' => 'wird noch bekanntgegeben', 'auto_archive' => true]);

        $this->get($event->publicPath())->assertOk()
            ->assertSee('wird noch bekanntgegeben')->assertDontSee('00:00 Uhr')->assertDontSee('ganztägig');
        $this->assertSame('2031-02-15', SiteTime::fromUtc($event->expires_at)->toDateString());
        $this->assertSame('23:59:59', SiteTime::fromUtc($event->expires_at)->format('H:i:s'));
    }
}
