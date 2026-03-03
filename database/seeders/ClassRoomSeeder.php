<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassRoomSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::factory(3)->teacher()->create();
        $students = User::factory(10)->student()->create();

        ClassRoom::factory(3)->create()->each(function (ClassRoom $classRoom, int $index) use ($teachers, $students): void {
            $classRoom->teachers()->attach($teachers[$index]->id);

            $classRoom->students()->attach(
                $students->pluck('id'),
                ['enrolled_at' => now()->startOfYear()->toDateString()]
            );
        });
    }
}
