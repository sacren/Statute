<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceRecordSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = AttendanceStatus::cases();
        $classRooms = ClassRoom::with('students', 'teachers')->get();
        $today = Carbon::today();

        foreach ($classRooms as $classRoom) {
            $teacher = $classRoom->teachers->first();

            foreach ($classRoom->students as $student) {
                for ($daysBack = 29; $daysBack >= 0; $daysBack--) {
                    $date = $today->copy()->subDays($daysBack);

                    AttendanceRecord::create([
                        'class_room_id' => $classRoom->id,
                        'student_id' => $student->id,
                        'recorded_by' => $teacher?->id,
                        'date' => $date->toDateString(),
                        'status' => fake()->randomElement($statuses)->value,
                        'notes' => null,
                    ]);
                }
            }
        }
    }
}
