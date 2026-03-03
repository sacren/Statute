<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassRoom>
 */
class ClassRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2023, 2026);

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'grade_level' => fake()->optional()->randomElement(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']),
            'academic_year' => $year.'-'.($year + 1),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
