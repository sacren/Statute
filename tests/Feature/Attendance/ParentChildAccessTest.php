<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('parent can view their linked children', function (): void {
    $parent = User::factory()->asParent()->create();
    $child1 = User::factory()->student()->create(['name' => 'Child One']);
    $child2 = User::factory()->student()->create(['name' => 'Child Two']);

    $parent->children()->attach([$child1->id, $child2->id]);

    $component = Livewire::actingAs($parent)
        ->test('pages::attendance.my-children');

    $children = $component->instance()->children;
    expect($children->pluck('id')->contains($child1->id))->toBeTrue();
    expect($children->pluck('id')->contains($child2->id))->toBeTrue();
});

it('parent cannot access attendance of unlinked student', function (): void {
    $parent = User::factory()->asParent()->create();
    $unlinkedStudent = User::factory()->student()->create();

    Livewire::actingAs($parent)
        ->test('pages::attendance.child-attendance', ['student' => $unlinkedStudent])
        ->assertForbidden();
});

it('parent can access attendance of linked child', function (): void {
    $parent = User::factory()->asParent()->create();
    $child = User::factory()->student()->create();
    $parent->children()->attach($child->id);

    Livewire::actingAs($parent)
        ->test('pages::attendance.child-attendance', ['student' => $child])
        ->assertSuccessful();
});

it('parent sees correct attendance records for linked child', function (): void {
    $parent = User::factory()->asParent()->create();
    $child = User::factory()->student()->create();
    $parent->children()->attach($child->id);

    $classRoom = ClassRoom::factory()->create();
    $classRoom->students()->attach($child->id, ['enrolled_at' => now()->toDateString()]);

    $year = now()->year;
    $month = now()->month;

    AttendanceRecord::factory()->present()->create([
        'class_room_id' => $classRoom->id,
        'student_id' => $child->id,
        'date' => Carbon::create($year, $month, 1)->toDateString(),
    ]);

    $component = Livewire::actingAs($parent)
        ->test('pages::attendance.child-attendance', ['student' => $child])
        ->set('year', $year)
        ->set('month', $month);

    $records = $component->instance()->records;
    expect($records->count())->toBe(1);
    expect($records->first()->student_id)->toBe($child->id);
});

it('parent only sees their own children in my-children list', function (): void {
    $parent = User::factory()->asParent()->create();
    $ownChild = User::factory()->student()->create();
    $otherChild = User::factory()->student()->create();

    $parent->children()->attach($ownChild->id);

    $component = Livewire::actingAs($parent)
        ->test('pages::attendance.my-children');

    $children = $component->instance()->children;
    expect($children->pluck('id')->contains($ownChild->id))->toBeTrue();
    expect($children->pluck('id')->contains($otherChild->id))->toBeFalse();
});
