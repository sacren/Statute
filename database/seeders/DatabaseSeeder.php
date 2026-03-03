<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory()->teacher()->create([
            'name' => 'Teacher User',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password'),
        ]);

        $student = User::factory()->student()->create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
        ]);

        $parent = User::factory()->asParent()->create([
            'name' => 'Parent User',
            'email' => 'parent@example.com',
            'password' => Hash::make('password'),
        ]);

        $parent->children()->attach($student->id);

        $this->call([
            ClassRoomSeeder::class,
            AttendanceRecordSeeder::class,
        ]);
    }
}
