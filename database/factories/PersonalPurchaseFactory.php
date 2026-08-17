<?php

namespace Database\Factories;

use App\Models\EventProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return ['event_id' => null, 'event_product_id' => EventProduct::factory(), 'user_id' => User::factory(), 'planned_quantity' => fake()->numberBetween(1, 10)];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($personalPurchase) {
            $personalPurchase->event_id = EventProduct::query()->findOrFail($personalPurchase->event_product_id)->event_id;
        });
    }
}
