<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware(['role:admin'])->group(function (): void {
        Route::livewire('admin/classes', 'pages::attendance.admin.classes')->name('attendance.admin.classes');
        Route::livewire('admin/classes/{classRoom}', 'pages::attendance.admin.class-detail')->name('attendance.admin.class-detail');
        Route::livewire('admin/users', 'pages::attendance.admin.users')->name('attendance.admin.users');
        Route::livewire('admin/reports', 'pages::attendance.admin.reports')->name('attendance.admin.reports');
        Route::livewire('admin/alerts', 'pages::attendance.admin.alerts')->name('attendance.admin.alerts');
    });

    Route::middleware(['role:admin,teacher'])->group(function (): void {
        Route::livewire('attendance/classes', 'pages::attendance.my-classes')->name('attendance.my-classes');
        Route::livewire('attendance/roll-call/{classRoom}', 'pages::attendance.roll-call')->name('attendance.roll-call');
        Route::livewire('attendance/reports/{classRoom}', 'pages::attendance.class-report')->name('attendance.class-report');
    });

    Route::middleware(['role:student'])->group(function (): void {
        Route::livewire('my-attendance', 'pages::attendance.my-attendance')->name('attendance.my-attendance');
    });

    Route::middleware(['role:parent'])->group(function (): void {
        Route::livewire('my-children', 'pages::attendance.my-children')->name('attendance.my-children');
        Route::livewire('my-children/{student}', 'pages::attendance.child-attendance')->name('attendance.child-attendance');
    });
});
