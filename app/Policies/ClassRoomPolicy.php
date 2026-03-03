<?php

namespace App\Policies;

use App\Models\ClassRoom;
use App\Models\User;

class ClassRoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function view(User $user, ClassRoom $classRoom): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $classRoom->teachers()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ClassRoom $classRoom): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ClassRoom $classRoom): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, ClassRoom $classRoom): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ClassRoom $classRoom): bool
    {
        return $user->isAdmin();
    }
}
