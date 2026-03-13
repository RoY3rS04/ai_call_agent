<?php

namespace Database\Factories;

use App\Enums\Enums\CallRoles;
use App\Models\Call;
use App\Models\CallMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallMessage>
 */
class CallMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'call_id' => Call::inRandomOrder()->first()->id,
            'role' => $this->faker->randomElement(CallRoles::cases()),
            'content' => $this->faker->realText(),
        ];
    }
}
