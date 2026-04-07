<?php

namespace Database\Seeders;

use App\Enums\MeetingStatus;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'check-calendar',
            'manage-calls',
            'manage-customers',
            'manage-companies',
            'manage-meetings',
            'manage-users',
            'manage-roles',
            'manage-permissions',
        ])->mapWithKeys(function (string $permissionName): array {
            return [
                $permissionName => Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => config('auth.defaults.guard'),
                ]),
            ];
        });

        $roles = collect([
            'admin' => [
                'manage-calls',
                'manage-customers',
                'manage-companies',
                'manage-meetings',
                'manage-users',
                'manage-roles',
                'manage-permissions',
                'check-calendar',
            ],
            'marketing' => [
                'check-calendar',
                'manage-meetings',
                'manage-customers',
                'manage-companies',
            ],
            'sales' => [
                'manage-calls',
                'manage-customers',
                'manage-companies',
                'manage-meetings',
            ],
            'support' => [
                'manage-calls',
                'manage-customers',
            ],
        ])->mapWithKeys(function (array $rolePermissions, string $roleName) use ($permissions): array {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => config('auth.defaults.guard'),
            ]);

            $role->syncPermissions(
                collect($rolePermissions)->map(fn (string $permissionName) => $permissions[$permissionName])
            );

            return [$roleName => $role];
        });

        $adminUser = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
        ]);
        $adminUser->syncRoles([$roles['admin']]);

        $marketingUsers = collect([
            ['name' => 'Marketing Manager', 'email' => 'marketing.manager@example.com'],
            ['name' => 'Marketing Specialist', 'email' => 'marketing.specialist@example.com'],
            ['name' => 'Calendar Coordinator', 'email' => 'calendar.coordinator@example.com'],
        ])->map(function (array $attributes) use ($roles, $permissions): User {
            $user = User::factory()->create($attributes + [
                'google_calendar_id' => 'primary',
            ]);

            $user->syncRoles([$roles['marketing']]);

            if ($attributes['email'] === 'calendar.coordinator@example.com') {
                $user->givePermissionTo($permissions['check-calendar']);
            }

            return $user;
        });

        $salesUsers = collect([
            ['name' => 'Sales Lead', 'email' => 'sales.lead@example.com'],
            ['name' => 'Sales Representative', 'email' => 'sales.rep@example.com'],
        ])->map(function (array $attributes) use ($roles): User {
            $user = User::factory()->create($attributes);
            $user->syncRoles([$roles['sales']]);

            return $user;
        });

        $supportUser = User::factory()->create([
            'name' => 'Support Agent',
            'email' => 'support@example.com',
        ]);
        $supportUser->syncRoles([$roles['support']]);

        User::factory(6)->create()->each(function (User $user) use ($roles): void {
            $user->syncRoles([$roles->random()]);
        });

        Company::factory(15)->create();
        Customer::factory(30)->create();
        Call::factory(60)->create();
        CallMessage::factory(240)->create();

        if (class_exists(Meeting::class)) {
            $customers = Customer::query()->inRandomOrder()->limit(20)->get();

            foreach ($customers as $customer) {
                $call = $customer->calls()->inRandomOrder()->first();
                $marketingUser = $marketingUsers->random();
                $start = now()->addDays(fake()->numberBetween(1, 21))->setTime(fake()->randomElement([9, 10, 11, 13, 14, 15, 16]), fake()->randomElement([0, 30]));

                Meeting::create([
                    'customer_id' => $customer->getKey(),
                    'company_id' => $customer->company_id,
                    'call_id' => $call?->getKey(),
                    'marketing_user_id' => $marketingUser->getKey(),
                    'start_time' => $start,
                    'end_time' => $start->copy()->addMinutes(30),
                    'timezone' => $customer->timezone,
                    'status' => fake()->randomElement([
                        MeetingStatus::PENDING,
                        MeetingStatus::CONFIRMED,
                        MeetingStatus::COMPLETED,
                    ]),
                    'confirmed_at' => fake()->boolean(70) ? now()->subDays(fake()->numberBetween(0, 7)) : null,
                    'source' => fake()->randomElement(['ai_call', 'manual']),
                    'notes' => fake()->boolean(50) ? fake()->sentence() : null,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
