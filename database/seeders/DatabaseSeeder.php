<?php

namespace Database\Seeders;

use App\Models\Call;
use App\Models\CallMessage;
use App\Models\Customer;
use App\Models\User;
use Database\Factories\CallFactory;
use Database\Factories\CallMessageFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Customer::factory(20)->create();
        Call::factory(50)->create();
        CallMessage::factory(200)->create();
    }
}
