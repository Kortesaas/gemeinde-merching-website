<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Converts between internal UTC timestamps and the site time zone
 * (config('site.timezone'), SITE_TIMEZONE, default Europe/Berlin).
 *
 * Rule: everything is stored and compared in UTC (database, publish_at,
 * expires_at, audit log). Every date/time a citizen or editor sees or enters
 * goes through this class – display, form input, scheduled publishing/expiry.
 */
final class SiteTime
{
    /** Value format of <input type="datetime-local">. */
    public const INPUT_FORMAT = 'Y-m-d\TH:i';

    public static function timezone(): string
    {
        return (string) config('site.timezone');
    }

    /**
     * Current wall-clock time in the site time zone (for display only).
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /**
     * Stored (UTC) timestamp -> site time zone.
     */
    public static function fromUtc(DateTimeInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->setTimezone(self::timezone());
    }

    /**
     * Format a stored timestamp for citizens/editors, e.g. "29.03.2026, 03:00".
     */
    public static function format(?DateTimeInterface $value, string $format = 'd.m.Y, H:i'): string
    {
        return $value === null ? '' : self::fromUtc($value)->format($format);
    }

    /**
     * Format with German month/day names, e.g. "Freitag, 9. Oktober 2026".
     */
    public static function formatLocalized(?DateTimeInterface $value, string $format = 'j. F Y'): string
    {
        if ($value === null) {
            return '';
        }
        /** @var CarbonImmutable $local */
        $local = self::fromUtc($value)->locale((string) config('app.locale', 'de'));

        return $local->translatedFormat($format);
    }

    /**
     * Stored timestamp -> value for <input type="datetime-local">.
     */
    public static function toInput(?DateTimeInterface $value): string
    {
        return $value === null ? '' : self::fromUtc($value)->format(self::INPUT_FORMAT);
    }

    /**
     * Editor input from <input type="datetime-local"> ("2026-03-29T10:00",
     * site time zone) -> UTC instant for storage (e.g. publish_at, expires_at).
     *
     * Wall-clock times that do not exist (the skipped hour when clocks go
     * forward) are rejected instead of being silently shifted. For the
     * repeated hour when clocks go back, the LATER occurrence (standard time)
     * is used deterministically – a scheduled publication therefore never goes
     * live before the intended time. Forms can warn editors via isAmbiguous().
     *
     * @throws InvalidArgumentException for malformed or non-existent local times
     */
    public static function fromInput(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $local = CarbonImmutable::createFromFormat('!'.self::INPUT_FORMAT, $value, self::timezone());

        if (! $local instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Ungültiges Datum oder ungültige Uhrzeit.');
        }

        if ($local->format(self::INPUT_FORMAT) !== $value) {
            throw new InvalidArgumentException('Diese Uhrzeit existiert wegen der Zeitumstellung nicht.');
        }

        return self::laterOccurrence($local->utc(), $value);
    }

    /**
     * True if the local wall-clock time occurs twice (clocks go back).
     */
    public static function isAmbiguous(string $value): bool
    {
        $utc = self::fromInput($value);

        return $utc !== null && self::wallClock($utc->subHour()) === trim($value);
    }

    private static function laterOccurrence(CarbonImmutable $utc, string $wallClock): CarbonImmutable
    {
        return self::wallClock($utc->addHour()) === $wallClock ? $utc->addHour() : $utc;
    }

    private static function wallClock(CarbonImmutable $utc): string
    {
        return $utc->setTimezone(new DateTimeZone(self::timezone()))->format(self::INPUT_FORMAT);
    }
}
