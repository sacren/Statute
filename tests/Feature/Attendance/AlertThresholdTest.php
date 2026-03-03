<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('flags students below the threshold', function (): void {
    $admin = User::factory()->admin()->create();
    $classRoom = ClassRoom::factory()->create();
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $year = now()->year;
    $month = now()->month;

    // 1 present, 9 absent = 10% attendance (below 80% threshold)
    AttendanceRecord::factory()->present()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 1)->toDateString(),
    ]);

    foreach (range(2, 10) as $day) {
        AttendanceRecord::factory()->absent()->create([
            'class_room_id' => $classRoom->id,
            'student_id' => $student->id,
            'date' => Carbon::create($year, $month, $day)->toDateString(),
        ]);
    }

    $component = Livewire::actingAs($admin)
        ->test('pages::attendance.admin.alerts')
        ->set('year', $year)
        ->set('month', $month)
        ->set('threshold', 80.0);

    $flagged = $component->instance()->flaggedStudents;
    expect($flagged->where('student.id', $student->id)->isNotEmpty())->toBeTrue();
});

it('does not flag students above the threshold', function (): void {
    $admin = User::factory()->admin()->create();
    $classRoom = ClassRoom::factory()->create();
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $year = now()->year;
    $month = now()->month;

    // 9 present, 1 absent = 90% attendance (above 80% threshold)
    foreach (range(1, 9) as $day) {
        AttendanceRecord::factory()->present()->create([
            'class_room_id' => $classRoom->id,
            'student_id' => $student->id,
            'date' => Carbon::create($year, $month, $day)->toDateString(),
        ]);
    }

    AttendanceRecord::factory()->absent()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 10)->toDateString(),
    ]);

    $component = Livewire::actingAs($admin)
        ->test('pages::attendance.admin.alerts')
        ->set('year', $year)
        ->set('month', $month)
        ->set('threshold', 80.0);

    $flagged = $component->instance()->flaggedStudents;
    expect($flagged->where('student.id', $student->id)->isEmpty())->toBeTrue();
});

it('uses a configurable threshold', function (): void {
    $admin = User::factory()->admin()->create();
    $classRoom = ClassRoom::factory()->create();
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $year = now()->year;
    $month = now()->month;

    // 5 present, 5 absent = 50% attendance
    foreach (range(1, 5) as $day) {
        AttendanceRecord::factory()->present()->create([
            'class_room_id' => $classRoom->id,
            'student_id' => $student->id,
            'date' => Carbon::create($year, $month, $day)->toDateString(),
        ]);
    }

    foreach (range(6, 10) as $day) {
        AttendanceRecord::factory()->absent()->create([
            'class_room_id' => $classRoom->id,
            'student_id' => $student->id,
            'date' => Carbon::create($year, $month, $day)->toDateString(),
        ]);
    }

    $component = Livewire::actingAs($admin)
        ->test('pages::attendance.admin.alerts')
        ->set('year', $year)
        ->set('month', $month);

    // At 60% threshold: 50% is flagged
    $component->set('threshold', 60.0);
    expect($component->instance()->flaggedStudents->where('student.id', $student->id)->isNotEmpty())->toBeTrue();

    // At 40% threshold: 50% is NOT flagged
    $component->set('threshold', 40.0);
    expect($component->instance()->flaggedStudents->where('student.id', $student->id)->isEmpty())->toBeTrue();
});
