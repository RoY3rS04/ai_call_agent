<?php

namespace Database\Factories;

use App\Enums\CallStatus;
use App\Models\Call;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Call>
 */
class CallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'twilio_call_sid' => $this->faker->randomNumber(),
            'start_time' => $this->faker->dateTimeBetween(now(), now()),
            'end_time' => $this->faker->dateTimeBetween(\Illuminate\Support\now()->addSecond(), now()->addDay()),
            'duration' => $this->faker->time(),
            'status' => $this->faker->randomElement(CallStatus::cases()),
        ];
    }
}
