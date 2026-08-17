<?php

namespace Database\Factories;

use App\Models\EventProduct;
use App\Models\SharedPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

class SharedPurchaseItemFactory extends Factory
{
    public function definition(): array
    {
        return ['shared_purchase_id' => SharedPurchase::factory(), 'event_product_id' => EventProduct::factory(), 'planned_quantity' => fake()->numberBetween(1, 10)];
    }
}
