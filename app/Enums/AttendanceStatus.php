<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            AttendanceStatus::Present => 'Present',
            AttendanceStatus::Absent => 'Absent',
            AttendanceStatus::Late => 'Late',
            AttendanceStatus::Excused => 'Excused',
        };
    }

    public function countsAsPresent(): bool
    {
        return match ($this) {
            AttendanceStatus::Present, AttendanceStatus::Late => true,
            AttendanceStatus::Absent, AttendanceStatus::Excused => false,
        };
    }

    public function color(): string
    {
        return match ($this) {
            AttendanceStatus::Present => 'green',
            AttendanceStatus::Absent => 'red',
            AttendanceStatus::Late => 'yellow',
            AttendanceStatus::Excused => 'blue',
        };
    }
}
