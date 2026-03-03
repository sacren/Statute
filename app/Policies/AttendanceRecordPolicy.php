<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AttendanceRecord $attendanceRecord): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $attendanceRecord->classRoom->teachers()->where('users.id', $user->id)->exists();
        }

        if ($user->isStudent()) {
            return $attendanceRecord->student_id === $user->id;
        }

        if ($user->isParent()) {
            return $user->children()->where('users.id', $attendanceRecord->student_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            $isOwnClass = $attendanceRecord->classRoom->teachers()->where('users.id', $user->id)->exists();
            $isToday = Carbon::parse($attendanceRecord->date)->isToday();

            return $isOwnClass && $isToday;
        }

        return false;
    }

    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->isAdmin();
    }
}
