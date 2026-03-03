<?php

use App\Models\ClassRoom;
use App\Models\User;
use Livewire\Livewire;

it('admin can view all classes', function (): void {
    $admin = User::factory()->admin()->create();
    ClassRoom::factory()->count(3)->create();

    Livewire::actingAs($admin)
        ->test('pages::attendance.admin.classes')
        ->assertSee(ClassRoom::first()->name);
});

it('admin can create a class room', function (): void {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::attendance.admin.classes')
        ->call('openCreateModal')
        ->set('name', 'Math 101')
        ->set('academicYear', '2025-2026')
        ->call('createClassRoom');

    expect(ClassRoom::where('name', 'Math 101')->exists())->toBeTrue();
});

it('admin can update a class room', function (): void {
    $admin = User::factory()->admin()->create();
    $classRoom = ClassRoom::factory()->create(['name' => 'Old Name']);

    Livewire::actingAs($admin)
        ->test('pages::attendance.admin.classes')
        ->call('openEditModal', $classRoom->id)
        ->set('name', 'New Name')
        ->call('updateClassRoom');

    expect($classRoom->fresh()->name)->toBe('New Name');
});

it('admin can delete a class room', function (): void {
    $admin = User::factory()->admin()->create();
    $classRoom = ClassRoom::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::attendance.admin.classes')
        ->call('deleteClassRoom', $classRoom->id);

    expect(ClassRoom::find($classRoom->id))->toBeNull();
});

it('non-admin cannot access admin classes page', function (string $factory): void {
    $user = User::factory()->{$factory}()->create();

    Livewire::actingAs($user)
        ->test('pages::attendance.admin.classes')
        ->assertForbidden();
})->with(['teacher', 'student', 'asParent']);

it('admin can assign teachers and enroll students when creating', function (): void {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    Livewire::actingAs($admin)
        ->test('pages::attendance.admin.classes')
        ->call('openCreateModal')
        ->set('name', 'Science 101')
        ->set('academicYear', '2025-2026')
        ->set('selectedTeacherIds', [$teacher->id])
        ->set('selectedStudentIds', [$student->id])
        ->call('createClassRoom');

    $classRoom = ClassRoom::where('name', 'Science 101')->first();
    expect($classRoom->teachers->pluck('id')->contains($teacher->id))->toBeTrue();
    expect($classRoom->students->pluck('id')->contains($student->id))->toBeTrue();
});
