<?php

namespace Database\Factories;

use App\Models\Circle;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventCircleFactory extends Factory
{
    public function definition(): array
    {
        return ['event_id' => Event::factory(), 'circle_id' => Circle::factory(), 'display_name' => fake()->company(), 'booth' => fake()->optional()->bothify('A-##'), 'map_image_path' => null, 'map_x' => fake()->numberBetween(0, 1000), 'map_y' => fake()->numberBetween(0, 1000)];
    }
}
