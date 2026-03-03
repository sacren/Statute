<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';
    case Parent = 'parent';

    public function label(): string
    {
        return match ($this) {
            UserRole::Admin => 'Admin',
            UserRole::Teacher => 'Teacher',
            UserRole::Student => 'Student',
            UserRole::Parent => 'Parent',
        };
    }
}
