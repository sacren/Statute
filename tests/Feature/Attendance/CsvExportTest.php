<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('exports CSV with correct headers and data', function (): void {
    $teacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create(['name' => 'Test Class']);
    $classRoom->teachers()->attach($teacher->id);
    $student = User::factory()->student()->create(['name' => 'Test Student']);
    $classRoom->students()->attach($student->id, ['enrolled_at' => now()->toDateString()]);

    $year = now()->year;
    $month = now()->month;

    AttendanceRecord::factory()->present()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $student->id,
        'date' => Carbon::create($year, $month, 1)->toDateString(),
    ]);

    $component = Livewire::actingAs($teacher)
        ->test('pages::attendance.class-report', ['classRoom' => $classRoom])
        ->set('year', $year)
        ->set('month', $month);

    $response = $component->instance()->exportCsv();

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Class');
    expect($content)->toContain('Student');
    expect($content)->toContain('Attendance %');
    expect($content)->toContain('Test Class');
    expect($content)->toContain('Test Student');
});

it('blocks teacher from exporting CSV of another teacher\'s class', function (): void {
    $teacher = User::factory()->teacher()->create();
    $otherTeacher = User::factory()->teacher()->create();
    $classRoom = ClassRoom::factory()->create();
    $classRoom->teachers()->attach($otherTeacher->id);

    Livewire::actingAs($teacher)
        ->test('pages::attendance.class-report', ['classRoom' => $classRoom])
        ->assertForbidden();
});

it('admin can export CSV for any class', function (): void {
    $admin = User::factory()->admin()->create();
    $classRoom = ClassRoom::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::attendance.class-report', ['classRoom' => $classRoom]);

    $response = $component->instance()->exportCsv();

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('content-disposition'))->toContain('.csv');
});
