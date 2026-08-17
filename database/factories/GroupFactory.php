<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'description' => fake()->optional()->sentence(), 'image_path' => null];
    }
}
