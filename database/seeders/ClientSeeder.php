<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        // Wipe existing clients before seeding
        Client::truncate();

        Client::factory()->count(30)->active()->create();
        Client::factory()->count(10)->inactive()->create();
        Client::factory()->count(10)->lead()->create();

        $this->command->info('✓ Seeded 50 clients');
    }
}
