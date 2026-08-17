<?php

namespace Database\Factories;

use App\Models\PersonalPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseResultFactory extends Factory
{
    public function definition(): array
    {
        return ['personal_purchase_id' => PersonalPurchase::factory(), 'shared_purchase_item_id' => null, 'event_product_id' => null, 'purchase_assignee_user_id' => User::factory(), 'planned_quantity' => 1, 'purchased_quantity' => 1, 'unit_price' => fake()->numberBetween(100, 10000), 'status' => 'completed'];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($purchaseResult) {
            $purchase = PersonalPurchase::query()->findOrFail($purchaseResult->personal_purchase_id);
            $purchaseResult->event_product_id = $purchase->event_product_id;
            $purchaseResult->planned_quantity = $purchase->planned_quantity;
        });
    }
}
