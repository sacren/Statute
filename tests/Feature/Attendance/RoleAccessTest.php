<?php

use App\Models\ClassRoom;
use App\Models\User;

it('redirects guests to login for all attendance routes', function (string $url): void {
    $this->get($url)->assertRedirectToRoute('login');
})->with([
    'admin classes' => fn () => route('attendance.admin.classes'),
    'admin users' => fn () => route('attendance.admin.users'),
    'admin reports' => fn () => route('attendance.admin.reports'),
    'admin alerts' => fn () => route('attendance.admin.alerts'),
    'my classes' => fn () => route('attendance.my-classes'),
    'my attendance' => fn () => route('attendance.my-attendance'),
    'my children' => fn () => route('attendance.my-children'),
]);

it('allows admins to access admin-only routes', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('attendance.admin.classes'))->assertSuccessful();
    $this->actingAs($admin)->get(route('attendance.admin.users'))->assertSuccessful();
    $this->actingAs($admin)->get(route('attendance.admin.reports'))->assertSuccessful();
    $this->actingAs($admin)->get(route('attendance.admin.alerts'))->assertSuccessful();
});

it('blocks non-admins from admin routes', function (string $role): void {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('attendance.admin.classes'))->assertForbidden();
    $this->actingAs($user)->get(route('attendance.admin.users'))->assertForbidden();
    $this->actingAs($user)->get(route('attendance.admin.reports'))->assertForbidden();
    $this->actingAs($user)->get(route('attendance.admin.alerts'))->assertForbidden();
})->with(['teacher', 'student', 'asParent']);

it('allows admin and teacher to access my-classes route', function (string $factory): void {
    $user = User::factory()->{$factory}()->create();

    $this->actingAs($user)->get(route('attendance.my-classes'))->assertSuccessful();
})->with(['admin', 'teacher']);

it('blocks students and parents from my-classes route', function (string $factory): void {
    $user = User::factory()->{$factory}()->create();

    $this->actingAs($user)->get(route('attendance.my-classes'))->assertForbidden();
})->with(['student', 'asParent']);

it('allows students to access their own attendance', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('attendance.my-attendance'))->assertSuccessful();
});

it('blocks non-students from my-attendance route', function (string $factory): void {
    $user = User::factory()->{$factory}()->create();

    $this->actingAs($user)->get(route('attendance.my-attendance'))->assertForbidden();
})->with(['admin', 'teacher', 'asParent']);

it('allows parents to access my-children route', function (): void {
    $parent = User::factory()->asParent()->create();

    $this->actingAs($parent)->get(route('attendance.my-children'))->assertSuccessful();
});

it('blocks non-parents from my-children route', function (string $factory): void {
    $user = User::factory()->{$factory}()->create();

    $this->actingAs($user)->get(route('attendance.my-children'))->assertForbidden();
})->with(['admin', 'teacher', 'student']);

it('allows admin and teacher to access roll-call', function (string $factory): void {
    $classRoom = ClassRoom::factory()->create();
    $user = User::factory()->{$factory}()->create();

    if ($factory === 'teacher') {
        $classRoom->teachers()->attach($user->id);
    }

    $this->actingAs($user)->get(route('attendance.roll-call', $classRoom))->assertSuccessful();
})->with(['admin', 'teacher']);
