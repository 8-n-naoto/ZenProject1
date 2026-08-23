<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventDayFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 week', '+1 month');

        return [
            'event_id' => Event::factory(),
            'event_date' => $date->format('Y-m-d'),
            'starts_at' => $date->format('Y-m-d').' 10:00:00',
            'ends_at' => $date->format('Y-m-d').' 16:00:00',
        ];
    }
}
