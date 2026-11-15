<?php

declare(strict_types=1);

namespace App\Support\Menu;

use App\Support\Menu\Data\HoursData;
use App\Support\Menu\Data\ItemData;
use App\Support\Menu\Data\LocationInfo;
use App\Support\Menu\Data\MenuSummary;
use App\Support\Menu\Data\SpecialHoursData;
use Carbon\CarbonImmutable;

/**
 * Time-dependent state, computed per request from snapshot data and the
 * clock (never cached, no queries): open now, today's hours, menus being
 * served, effective sold-out.
 */
final class LiveMenuState
{
    private readonly CarbonImmutable $now;

    /** @var list<array{int, int}> today's intervals in minutes (end may exceed 1440) */
    private readonly array $today;

    /** @var list<array{int, int}> yesterday's intervals that run past midnight */
    private readonly array $carryOver;

    public function __construct(private readonly LocationInfo $location, ?CarbonImmutable $now = null)
    {
        $this->now = ($now ?? CarbonImmutable::now())->setTimezone($location->timezone);
        $this->today = $this->intervalsFor($this->now);
        $this->carryOver = array_values(array_filter(
            $this->intervalsFor($this->now->subDay()),
            fn (array $interval): bool => $interval[1] > 1440,
        ));
    }

    public static function for(LocationInfo $location, ?CarbonImmutable $now = null): self
    {
        return new self($location, $now);
    }

    public function isOpenNow(): bool
    {
        $minute = $this->minuteOfDay();

        foreach ($this->today as [$start, $end]) {
            if ($minute >= $start && $minute < $end) {
                return true;
            }
        }

        foreach ($this->carryOver as [, $end]) {
            if ($minute < $end - 1440) {
                return true;
            }
        }

        return false;
    }

    /**
     * Closing time (HH:MM) of the interval open right now.
     */
    public function closesAt(): ?string
    {
        $minute = $this->minuteOfDay();

        foreach ($this->carryOver as [, $end]) {
            if ($minute < $end - 1440) {
                return $this->format($end);
            }
        }

        foreach ($this->today as [$start, $end]) {
            if ($minute >= $start && $minute < $end) {
                return $this->format($end);
            }
        }

        return null;
    }

    /**
     * Next opening time today (HH:MM), if still ahead.
     */
    public function opensAt(): ?string
    {
        $minute = $this->minuteOfDay();

        foreach ($this->today as [$start]) {
            if ($start > $minute) {
                return $this->format($start);
            }
        }

        return null;
    }

    /**
     * Today's hours as "08:00–14:00, 17:00–02:00"; null when closed all day.
     */
    public function todayHours(): ?string
    {
        if ($this->today === []) {
            return null;
        }

        return implode(', ', array_map(fn (array $i): string => $this->format($i[0]).'–'.$this->format($i[1]), $this->today));
    }

    public function todayIsoDay(): int
    {
        return $this->now->isoWeekday();
    }

    /**
     * Whether a menu is being served now (no serving hours = all day).
     */
    public function isServing(MenuSummary $menu): bool
    {
        if ($menu->servingHours === []) {
            return true;
        }

        $minute = $this->minuteOfDay();
        $day = $this->now->isoWeekday();
        $yesterday = $this->now->subDay()->isoWeekday();

        foreach ($menu->servingHours as $hours) {
            [$start, $end] = $this->range($hours);

            if ($hours->day === $day && $minute >= $start && $minute < $end) {
                return true;
            }
            if ($hours->day === $yesterday && $end > 1440 && $minute < $end - 1440) {
                return true;
            }
        }

        return false;
    }

    public function isSoldOut(ItemData $item): bool
    {
        return $item->soldOutIndefinitely
            || ($item->soldOutUntil !== null && $item->soldOutUntil > $this->now->getTimestamp());
    }

    /**
     * Local time (HH:MM) until which an item is sold out, for timed sold-outs.
     */
    public function soldOutUntil(ItemData $item): ?string
    {
        if ($item->soldOutIndefinitely || $item->soldOutUntil === null || ! $this->isSoldOut($item)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($item->soldOutUntil)->setTimezone($this->location->timezone)->format('H:i');
    }

    /**
     * @return list<array{int, int}>
     */
    private function intervalsFor(CarbonImmutable $day): array
    {
        $date = $day->toDateString();
        $special = array_values(array_filter($this->location->specialHours, fn (SpecialHoursData $s): bool => $s->date === $date));

        if ($special !== []) {
            $open = array_filter($special, fn (SpecialHoursData $s): bool => ! $s->isClosed && $s->opens !== null && $s->closes !== null);
            $intervals = array_map(fn (SpecialHoursData $s): array => $this->range(new HoursData($day->isoWeekday(), $s->opens, $s->closes)), $open);
        } else {
            $weekly = array_filter($this->location->weeklyHours, fn (HoursData $h): bool => $h->day === $day->isoWeekday());
            $intervals = array_map(fn (HoursData $h): array => $this->range($h), $weekly);
        }

        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_values($intervals);
    }

    /**
     * @return array{int, int}
     */
    private function range(HoursData $hours): array
    {
        $start = $this->minutes($hours->opens);
        $end = $this->minutes($hours->closes);

        return [$start, $end <= $start ? $end + 1440 : $end];
    }

    private function minutes(string $time): int
    {
        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }

    private function minuteOfDay(): int
    {
        return $this->now->hour * 60 + $this->now->minute;
    }

    private function format(int $minutes): string
    {
        $minutes %= 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
