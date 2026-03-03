<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Livewire\Livewire;

it('creates attendance records on save', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($teacher->id);
    $students = User::factory()->student()->count(3)->create();
    $classRoom->students()->attach($students->pluck('id'), ['enrolled_at' => now()->toDateString()]);

    $statusMap = $students->mapWithKeys(fn ($s) => [$s->id => AttendanceStatus::Present->value])->toArray();

    Livewire::actingAs($teacher)
        ->test('pages::attendance.roll-call', ['classRoom' => $classRoom])
        ->set('statusMap', $statusMap)
        ->call('saveAttendance');

    expect(AttendanceRecord::where('class_room_id', $classRoom->id)->count())->toBe(3);
});

it('updates not duplicates records on re-save (unique constraint)', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($teacher->id);
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $statusMap = [$student->id => AttendanceStatus::Present->value];

    $component = Livewire::actingAs($teacher)
        ->test('pages::attendance.roll-call', ['classRoom' => $classRoom]);

    $component->set('statusMap', $statusMap)->call('saveAttendance');
    $component->set('statusMap', [$student->id => AttendanceStatus::Absent->value])->call('saveAttendance');

    expect(AttendanceRecord::where('class_room_id', $classRoom->id)->where('student_id', $student->id)->count())->toBe(1);
    expect(AttendanceRecord::where('class_room_id', $classRoom->id)->where('student_id', $student->id)->first()->status)
        ->toBe(AttendanceStatus::Absent);
});

it('blocks teacher from saving attendance on another teacher\'s class', function (): void {
    $teacher = User::factory()->teacher()->create();
    $otherTeacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($otherTeacher->id);
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    Livewire::actingAs($teacher)
        ->test('pages::attendance.roll-call', ['classRoom' => $classRoom])
        ->assertForbidden();
});

it('loads existing attendance records when date changes', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($teacher->id);
    $student = User::factory()->student()->create();
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $date = now()->subDay()->toDateString();

    AttendanceRecord::create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'recorded_by' => $teacher->id,
        'date' => $date,
        'status' => AttendanceStatus::Late->value,
        'notes' => null,
    ]);

    Livewire::actingAs($teacher)
        ->test('pages::attendance.roll-call', ['classRoom' => $classRoom])
        ->set('selectedDate', $date)
        ->assertSet("statusMap.{$student->id}", AttendanceStatus::Late->value);
});
