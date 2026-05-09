<?php

namespace Database\Seeders;

use App\Models\User;
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
            'name' => 'Demo User',
            'email' => 'demo@freelanceflow.test',
        ]);

         $this->call([
            ClientSeeder::class,
            TagSeeder::class
            // ProjectSeeder::class,   - we add these in Phase 2
            // InvoiceSeeder::class,
        ]);
    }
}
