<?php

namespace Tests\Unit;

use App\Support\SiteTime;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class SiteTimeTest extends TestCase
{
    public function test_internal_time_is_utc_and_site_time_is_berlin(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertSame('Europe/Berlin', SiteTime::timezone());
        $this->assertSame('Europe/Berlin', SiteTime::now()->getTimezone()->getName());
    }

    public function test_site_timezone_is_configurable(): void
    {
        config(['site.timezone' => 'Europe/Vienna']);

        $this->assertSame('Europe/Vienna', SiteTime::fromUtc(CarbonImmutable::now('UTC'))->getTimezone()->getName());
    }

    public function test_display_across_daylight_saving_start(): void
    {
        // 29 March 2026: clocks jump from 02:00 to 03:00 site time.
        $this->assertSame('29.03.2026, 01:59', SiteTime::format(CarbonImmutable::parse('2026-03-29 00:59:00', 'UTC')));
        $this->assertSame('29.03.2026, 03:00', SiteTime::format(CarbonImmutable::parse('2026-03-29 01:00:00', 'UTC')));
        $this->assertSame('', SiteTime::format(null));
    }

    public function test_editor_input_is_stored_as_utc(): void
    {
        $this->assertSame('2026-07-01 08:00:00', SiteTime::fromInput('2026-07-01T10:00')?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 09:00:00', SiteTime::fromInput('2026-12-01T10:00')?->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', SiteTime::fromInput('2026-12-01T10:00')?->getTimezone()->getName());
        $this->assertNull(SiteTime::fromInput(''));
        $this->assertNull(SiteTime::fromInput(null));
    }

    public function test_input_round_trip(): void
    {
        $utc = SiteTime::fromInput('2026-10-25T01:15');

        $this->assertNotNull($utc);
        $this->assertSame('2026-10-25T01:15', SiteTime::toInput($utc));
        $this->assertSame('', SiteTime::toInput(null));
    }

    public function test_nonexistent_time_at_daylight_saving_start_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SiteTime::fromInput('2026-03-29T02:30');
    }

    public function test_malformed_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SiteTime::fromInput('29.03.2026 10:00');
    }

    public function test_repeated_hour_at_daylight_saving_end_uses_later_occurrence(): void
    {
        // 25 October 2026: 02:00–03:00 site time occurs twice.
        $this->assertTrue(SiteTime::isAmbiguous('2026-10-25T02:30'));
        $this->assertFalse(SiteTime::isAmbiguous('2026-10-25T03:30'));
        $this->assertFalse(SiteTime::isAmbiguous('2026-07-01T02:30'));

        // Later occurrence = standard time (UTC+1) -> 01:30 UTC, never too early.
        $this->assertSame('2026-10-25 01:30:00', SiteTime::fromInput('2026-10-25T02:30')?->format('Y-m-d H:i:s'));
        // Unambiguous neighbours are unaffected.
        $this->assertSame('2026-10-24 23:30:00', SiteTime::fromInput('2026-10-25T01:30')?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 02:30:00', SiteTime::fromInput('2026-10-25T03:30')?->format('Y-m-d H:i:s'));
    }

    public function test_publication_window_comparison_is_independent_of_dst(): void
    {
        // Scheduled for 03:00 site time on the DST-start day = 01:00 UTC.
        $publishAt = SiteTime::fromInput('2026-03-29T03:00');
        $this->assertNotNull($publishAt);

        $this->travelTo(CarbonImmutable::parse('2026-03-29 00:59:59', 'UTC'));
        $this->assertFalse($publishAt->lessThanOrEqualTo(now()));

        $this->travelTo(CarbonImmutable::parse('2026-03-29 01:00:00', 'UTC'));
        $this->assertTrue($publishAt->lessThanOrEqualTo(now()));
    }
}
