<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::first() ?? User::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $wallet = Wallet::first() ?? Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 100000.00
        ]);
    }
}
