<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CircleFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company(), 'website_url' => fake()->url(), 'map_image_path' => null, 'map_x' => fake()->numberBetween(0, 1000), 'map_y' => fake()->numberBetween(0, 1000), 'description' => fake()->sentence()];
    }
}
