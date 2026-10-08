<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Converts between stored UTC timestamps and the local time of the
 * municipality (config('app.local_timezone'), Europe/Berlin).
 *
 * Store and compare in UTC; convert only at the edges (display, form input).
 */
final class LocalTime
{
    public static function timezone(): string
    {
        return (string) config('app.local_timezone');
    }

    /**
     * UTC (or any) timestamp -> local time, e.g. for display.
     */
    public static function fromUtc(DateTimeInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->setTimezone(self::timezone());
    }

    /**
     * Local wall-clock input (e.g. "2026-03-29 02:30" from a form) -> UTC for storage.
     */
    public static function toUtc(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, self::timezone())->utc();
    }

    public static function format(?DateTimeInterface $value, string $format = 'd.m.Y, H:i'): string
    {
        return $value === null ? '' : self::fromUtc($value)->format($format);
    }
}
