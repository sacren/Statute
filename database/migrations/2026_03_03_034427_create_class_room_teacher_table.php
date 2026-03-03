<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_room_teacher', function (Blueprint $table): void {
            $table->foreignId('class_room_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['class_room_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_room_teacher');
    }
};
