<?php

namespace App\Support\Content;

use App\Models\Event;
use App\Support\SiteTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Month grid for the event listing (Monday first, six weeks, site time zone). Multi-day
 * events appear on every day they cover. Pure presentation of public events
 * the listing already shows; nothing is cached.
 */
final class EventCalendar
{
    /** Maximum number of days a single event marks, to keep odd data harmless. */
    private const MAX_SPAN_DAYS = 31;

    /**
     * @param  Collection<int, Event>  $events
     * @return array{month: CarbonImmutable, weeks: list<list<array{date: string, day: int, inMonth: bool, today: bool, events: list<Event>}>>, previous: string, next: string, count: int}
     */
    public static function build(Collection $events, CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();
        $byDate = [];
        foreach ($events as $event) {
            foreach (self::dates($event, $month) as $date) {
                $byDate[$date][] = $event;
            }
        }
        $today = SiteTime::now()->toDateString();
        $cursor = $month->startOfWeek(CarbonImmutable::MONDAY);
        $weeks = [];
        $count = 0;
        // Always six weeks: the grid keeps its height when switching months.
        for ($row = 0; $row < 6; $row++) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->toDateString();
                $inMonth = $cursor->month === $month->month;
                $dayEvents = $inMonth ? ($byDate[$date] ?? []) : [];
                $count += count($dayEvents);
                $week[] = ['date' => $date, 'day' => $cursor->day, 'inMonth' => $inMonth, 'today' => $date === $today, 'events' => $dayEvents];
                $cursor = $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return ['month' => $month, 'weeks' => $weeks, 'previous' => $month->subMonth()->format('Y-m'), 'next' => $month->addMonth()->format('Y-m'), 'count' => $count];
    }

    /**
     * Calendar days (Y-m-d, site time) an event covers.
     *
     * @return list<string>
     */
    public static function dates(Event $event, ?CarbonImmutable $month = null): array
    {
        $start = SiteTime::fromUtc($event->starts_at)->startOfDay();
        $end = $event->ends_at !== null ? SiteTime::fromUtc($event->ends_at) : $start;
        // All-day events end at midnight of the following day.
        if ($event->all_day && $event->ends_at !== null && $end->format('H:i') === '00:00' && $end->greaterThan($start)) {
            $end = $end->subSecond();
        }
        if ($month !== null) {
            $start = $start->max($month->startOfMonth());
            $end = $end->min($month->endOfMonth());
            if ($start->greaterThan($end)) {
                return [];
            }
        }
        $dates = [];
        for ($day = $start; $day->lessThanOrEqualTo($end) && count($dates) < self::MAX_SPAN_DAYS; $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates === [] ? [$start->toDateString()] : $dates;
    }

    public static function overlaps(Event $event, CarbonImmutable $month): bool
    {
        return self::dates($event, $month) !== [];
    }

    /** Month to show: an explicit "YYYY-MM", else the month of the first listed event, else now. */
    public static function month(?string $requested, ?Event $first): CarbonImmutable
    {
        if ($requested !== null && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $requested, $m) === 1) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, SiteTime::timezone()) ?? SiteTime::now()->startOfMonth();
        }

        return ($first !== null ? SiteTime::fromUtc($first->starts_at) : SiteTime::now())->startOfMonth();
    }
}
