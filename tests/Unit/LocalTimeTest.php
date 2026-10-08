<?php

namespace Tests\Unit;

use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class LocalTimeTest extends TestCase
{
    public function test_application_works_in_utc_and_displays_berlin_time(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Europe/Berlin', LocalTime::timezone());
    }

    public function test_conversion_across_daylight_saving_start(): void
    {
        // 29 March 2026: clocks jump from 02:00 to 03:00 local time.
        $this->assertSame('29.03.2026, 01:59', LocalTime::format(CarbonImmutable::parse('2026-03-29 00:59:00', 'UTC')));
        $this->assertSame('29.03.2026, 03:00', LocalTime::format(CarbonImmutable::parse('2026-03-29 01:00:00', 'UTC')));
    }

    public function test_local_input_is_stored_as_utc(): void
    {
        $this->assertSame('2026-07-01 08:00:00', LocalTime::toUtc('2026-07-01 10:00')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 09:00:00', LocalTime::toUtc('2026-12-01 10:00')->format('Y-m-d H:i:s'));
    }
}
