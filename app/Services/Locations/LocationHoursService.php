<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Models\Location;
use App\Models\LocationSpecialHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Opening hours and special hours of a location. The panel validates the
 * form; the rules here are re-checked on the server whatever the client sent:
 *
 * - weekly rows: ISO day 1–7, valid HH:MM times, opens ≠ closes, no two
 *   intervals of the same day overlap (closes < opens = open past midnight);
 * - special days: either closed all day (one row, no times) or one or more
 *   non-overlapping intervals; a date can't be both.
 *
 * Validation error keys are relative to the submitted data (`opening_hours.0.closes_at`,
 * `closes_at`, …).
 */
final class LocationHoursService
{
    private const string TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    /**
     * Replaces the weekly hours with the given rows.
     *
     * @param  array<int|string, mixed>  $rows  [['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '22:00'], …]
     *
     * @throws ValidationException
     */
    public function syncOpeningHours(Location $location, array $rows): void
    {
        $clean = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $day = filter_var($row['day_of_week'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
            $opens = $this->time($row['opens_at'] ?? null);
            $closes = $this->time($row['closes_at'] ?? null);

            if ($day === false) {
                $errors["opening_hours.{$index}.day_of_week"] = __('panels.app.hours.errors.day');
            }
            if ($opens === null) {
                $errors["opening_hours.{$index}.opens_at"] = __('panels.app.hours.errors.time');
            }
            if ($closes === null) {
                $errors["opening_hours.{$index}.closes_at"] = __('panels.app.hours.errors.time');
            }
            if ($day === false || $opens === null || $closes === null) {
                continue;
            }
            if ($opens === $closes) {
                $errors["opening_hours.{$index}.closes_at"] = __('panels.app.hours.errors.same_time');

                continue;
            }

            foreach ($clean as $otherIndex => $other) {
                if ($other['day_of_week'] === $day && $this->overlaps($opens, $closes, $other['opens_at'], $other['closes_at'])) {
                    $errors["opening_hours.{$index}.opens_at"] = __('panels.app.hours.errors.overlap');

                    continue 2;
                }
            }

            $clean[$index] = ['day_of_week' => $day, 'opens_at' => $opens, 'closes_at' => $closes];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($location, $clean): void {
            $location->openingHours()->delete();

            foreach ($clean as $row) {
                $location->openingHours()->create($row);
            }
        });

        $location->unsetRelation('openingHours');
    }

    /**
     * Creates or updates one special-hours row.
     *
     * @param  array<string, mixed>  $data  date, is_closed, opens_at, closes_at
     *
     * @throws ValidationException
     */
    public function saveSpecialHour(Location $location, array $data, ?LocationSpecialHour $existing = null): LocationSpecialHour
    {
        if ($existing !== null && (int) $existing->location_id !== (int) $location->getKey()) {
            throw new InvalidArgumentException('The special hours row belongs to another location.');
        }

        $date = $this->date($data['date'] ?? null)
            ?? throw ValidationException::withMessages(['date' => __('panels.app.hours.errors.date')]);
        $isClosed = filter_var($data['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $opens = $isClosed ? null : $this->time($data['opens_at'] ?? null);
        $closes = $isClosed ? null : $this->time($data['closes_at'] ?? null);

        if (! $isClosed) {
            $errors = array_filter([
                'opens_at' => $opens === null ? __('panels.app.hours.errors.time') : null,
                'closes_at' => $closes === null ? __('panels.app.hours.errors.time') : null,
            ]);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($opens === $closes) {
                throw ValidationException::withMessages(['closes_at' => __('panels.app.hours.errors.same_time')]);
            }
        }

        return DB::transaction(function () use ($location, $existing, $date, $isClosed, $opens, $closes): LocationSpecialHour {
            $sameDate = $location->specialHours()
                ->whereDate('date', $date->toDateString())
                ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->getKey()))
                ->lockForUpdate()
                ->get();

            if ($isClosed && $sameDate->isNotEmpty()) {
                throw ValidationException::withMessages(['is_closed' => __('panels.app.hours.errors.closed_conflict')]);
            }

            if (! $isClosed) {
                if ($sameDate->contains('is_closed', true)) {
                    throw ValidationException::withMessages(['date' => __('panels.app.hours.errors.already_closed')]);
                }

                foreach ($sameDate as $other) {
                    if ($this->overlaps($opens, $closes, $this->time($other->opens_at), $this->time($other->closes_at))) {
                        throw ValidationException::withMessages(['opens_at' => __('panels.app.hours.errors.overlap')]);
                    }
                }
            }

            $row = $existing ?? $location->specialHours()->make();
            $row->fill([
                'date' => $date->toDateString(),
                'is_closed' => $isClosed,
                'opens_at' => $opens,
                'closes_at' => $closes,
            ])->save();

            return $row;
        });
    }

    public function deleteSpecialHour(LocationSpecialHour $row): void
    {
        $row->delete();
    }

    /**
     * Normalises "8:00", "08:00" or "08:00:00" to "08:00"; null when invalid.
     */
    private function time(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\d:/', $value) === 1) {
            $value = '0'.$value;
        }

        return preg_match(self::TIME_PATTERN, $value) === 1 ? substr($value, 0, 5) : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return CarbonImmutable::createFromDate((int) $parts[1], (int) $parts[2], (int) $parts[3])->startOfDay();
    }

    /**
     * Intervals on the same day, in minutes; an interval that closes at or
     * before it opens runs past midnight.
     */
    private function overlaps(string $opensA, string $closesA, string $opensB, string $closesB): bool
    {
        [$startA, $endA] = $this->range($opensA, $closesA);
        [$startB, $endB] = $this->range($opensB, $closesB);

        return $startA < $endB && $startB < $endA;
    }

    /**
     * @return array{int, int}
     */
    private function range(string $opens, string $closes): array
    {
        $start = $this->minutes($opens);
        $end = $this->minutes($closes);

        return [$start, $end <= $start ? $end + 1440 : $end];
    }

    private function minutes(string $time): int
    {
        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }
}
