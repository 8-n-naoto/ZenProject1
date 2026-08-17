<?php

namespace Database\Factories;

use App\Models\EventCircle;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventProductFactory extends Factory
{
    public function definition(): array
    {
        return ['event_id' => null, 'event_circle_id' => EventCircle::factory(), 'product_id' => Product::factory(), 'name' => fake()->words(3, true), 'price' => fake()->numberBetween(100, 10000), 'image_path' => null, 'status' => 'selling'];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($eventProduct) {
            $eventProduct->event_id = EventCircle::query()->findOrFail($eventProduct->event_circle_id)->event_id;
        });
    }
}
