<?php

namespace App\Concerns;

use App\Enums\AttendanceStatus;
use Illuminate\Support\Collection;

trait CalculatesAttendancePercentage
{
    public function calculatePercentage(Collection $records): float
    {
        $countable = $records->filter(
            fn ($record) => $record->status !== AttendanceStatus::Excused
        );

        if ($countable->isEmpty()) {
            return 100.0;
        }

        $present = $countable->filter(
            fn ($record) => $record->status->countsAsPresent()
        );

        return round(($present->count() / $countable->count()) * 100, 2);
    }

    public function isBelowThreshold(Collection $records, float $threshold): bool
    {
        return $this->calculatePercentage($records) < $threshold;
    }
}
