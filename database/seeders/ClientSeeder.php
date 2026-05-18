<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        // Wipe existing clients before seeding
        Client::truncate();

        $user = User::first();

        $workspace = $user->currentWorkspace();

        $activeClients = Client::factory()->count(30)->active()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $inactiveClients = Client::factory()->count(10)->inactive()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        
        $leadClients = Client::factory()->count(10)->lead()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

         // Seed projects for active clients only
        // Each active client gets 1–4 projects
        $activeClients->each(function (Client $client) use ($user, $workspace) {
            $count = fake()->numberBetween(1, 4);
            Project::factory()->count($count)->create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
            ]);
        });

        // A few completed projects for some inactive clients
        $inactiveClients->take(5)->each(function (Client $client) use ($user, $workspace) {
            Project::factory()->count(2)->completed()->create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
            ]);
        });


        $this->command->info('✓ Seeded 50 clients and ~90 projects');
    }
}
