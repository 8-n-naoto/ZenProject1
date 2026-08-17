<?php

namespace Database\Factories;

use App\Models\EventCircle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SharedPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return ['event_id' => null, 'event_circle_id' => EventCircle::factory(), 'created_by' => User::factory(), 'note' => fake()->optional()->sentence()];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($sharedPurchase) {
            $sharedPurchase->event_id = EventCircle::query()->findOrFail($sharedPurchase->event_circle_id)->event_id;
        });
    }
}
