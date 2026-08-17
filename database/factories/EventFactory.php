<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+1 month');

        return [
            'group_id' => Group::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(3, true),
            'venue_name' => fake()->company(),
            'venue_address' => fake()->address(),
            'description' => fake()->sentence(),
            'image_path' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+1 day'),
            'fixed_at' => null,
            'status' => 'preparation',
        ];
    }
}
