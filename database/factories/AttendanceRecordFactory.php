<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_room_id' => ClassRoom::factory(),
            'student_id' => User::factory()->student(),
            'recorded_by' => User::factory()->teacher(),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(AttendanceStatus::cases())->value,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Absent->value,
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Late->value,
        ]);
    }

    public function excused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Excused->value,
        ]);
    }
}
