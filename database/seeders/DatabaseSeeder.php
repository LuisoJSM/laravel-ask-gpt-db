<?php

namespace Database\Seeders;

use App\Models\ContactForm;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create 50 users
        $users = User::factory(50)->create();

        // Create orders with past dates (50 orders)
        Order::factory(50)->past()->create([
            'user_id' => fn() => $users->random()->id,
        ]);

        // Create orders with future dates (30 orders)
        Order::factory(30)->future()->create([
            'user_id' => fn() => $users->random()->id,
        ]);

        // Create recently completed orders (20 orders)
        Order::factory(20)->recentlyCompleted()->create([
            'user_id' => fn() => $users->random()->id,
        ]);

        // Create contact forms

        // 1. This week's contact forms (50)
        ContactForm::factory(50)->thisWeek()->create();

        // 2. Contact forms from last week (50)
        ContactForm::factory(50)->lastWeek()->create();

        // 3. Older contact forms (50)
        ContactForm::factory(50)->create([
            'created_at' => now()->subDays(rand(15, 60)),
            'is_responded' => rand(0, 10) > 3, // 70% chance of being answered
        ]);
    }
}
