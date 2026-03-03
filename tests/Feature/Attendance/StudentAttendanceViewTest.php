<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('student sees only their own attendance records', function (): void {
    $classRoom = ClassRoom::factory()->create();
    $student = User::factory()->student()->create();
    $otherStudent = User::factory()->student()->create();

    $classRoom->students()->attach([$student->id, $otherStudent->id], ['enrolled_at' => now()->toDateString()]);

    $year = now()->year;
    $month = now()->month;

    AttendanceRecord::factory()->present()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 1)->toDateString(),
    ]);

    AttendanceRecord::factory()->absent()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $otherStudent->id,
        'date' => Carbon::create($year, $month, 1)->toDateString(),
    ]);

    $component = Livewire::actingAs($student)
        ->test('pages::attendance.my-attendance')
        ->set('year', $year)
        ->set('month', $month);

    $records = $component->instance()->records;

    expect($records->where('student_id', $student->id)->count())->toBe(1);
    expect($records->where('student_id', $otherStudent->id)->count())->toBe(0);
});

it('calculates correct attendance percentage for student', function (): void {
    $classRoom = ClassRoom::factory()->create();
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $year = now()->year;
    $month = now()->month;

    // 3 present, 1 absent = 75%
    foreach (range(1, 3) as $day) {
        AttendanceRecord::factory()->present()->create([
            'class_room_id' => $classRoom->id,
            'student_id' => $student->id,
            'date' => Carbon::create($year, $month, $day)->toDateString(),
        ]);
    }

    AttendanceRecord::factory()->absent()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 4)->toDateString(),
    ]);

    $component = Livewire::actingAs($student)
        ->test('pages::attendance.my-attendance')
        ->set('year', $year)
        ->set('month', $month);

    $percentage = $component->instance()->attendancePercentage;

    expect($percentage)->toBe(75.0);
});

it('shows 100 percent when student has no countable records', function (): void {
    $student = User::factory()->student()->create();

    $component = Livewire::actingAs($student)
        ->test('pages::attendance.my-attendance');

    expect($component->instance()->attendancePercentage)->toBe(100.0);
});
