<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('shows correct attendance counts for the selected month', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($teacher->id);
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $month = now()->month;
    $year = now()->year;

    AttendanceRecord::factory()->present()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 1)->toDateString(),
    ]);
    AttendanceRecord::factory()->absent()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 2)->toDateString(),
    ]);
    AttendanceRecord::factory()->late()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 3)->toDateString(),
    ]);

    $component = Livewire::actingAs($teacher)
        ->test('pages::attendance.class-report', ['classRoom' => $classRoom])
        ->set('year', $year)
        ->set('month', $month);

    $studentData = $component->instance()->studentsWithRecords->firstWhere('id', $student->id);
    expect($studentData->attendanceRecords->count())->toBe(3);
});

it('excludes excused records from percentage denominator', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($teacher->id);
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $month = now()->month;
    $year = now()->year;

    // 2 present + 1 excused — denominator should be 2, so 100%
    AttendanceRecord::factory()->present()->count(2)->sequence(
        ['date' => Carbon::create($year, $month, 1)->toDateString()],
        ['date' => Carbon::create($year, $month, 2)->toDateString()],
    )->create(['class_room_id' => $classRoom->id, 'student_id' => $student->id]);

    AttendanceRecord::factory()->excused()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 3)->toDateString(),
    ]);

    $component = Livewire::actingAs($teacher)
        ->test('pages::attendance.class-report', ['classRoom' => $classRoom])
        ->set('year', $year)
        ->set('month', $month);

    $studentData = $component->instance()->studentsWithRecords->firstWhere('id', $student->id);
    $percentage = $component->instance()->calculatePercentage($studentData->attendanceRecords);

    expect($percentage)->toBe(100.0);
});

it('scopes records to the selected month', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($teacher->id);
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $thisMonth = now();
    $lastMonth = now()->subMonth();

    AttendanceRecord::factory()->present()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => $lastMonth->startOfMonth()->toDateString(),
    ]);
    AttendanceRecord::factory()->absent()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => $thisMonth->startOfMonth()->toDateString(),
    ]);

    $component = Livewire::actingAs($teacher)
        ->test('pages::attendance.class-report', ['classRoom' => $classRoom])
        ->set('year', $thisMonth->year)
        ->set('month', $thisMonth->month);

    $studentData = $component->instance()->studentsWithRecords->firstWhere('id', $student->id);
    expect($studentData->attendanceRecords->count())->toBe(1);
});

it('returns 100 percent when there are no countable records', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($teacher->id);
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $component = Livewire::actingAs($teacher)
        ->test('pages::attendance.class-report', ['classRoom' => $classRoom]);

    $studentData = $component->instance()->studentsWithRecords->firstWhere('id', $student->id);
    $percentage = $component->instance()->calculatePercentage($studentData->attendanceRecords);

    expect($percentage)->toBe(100.0);
});
