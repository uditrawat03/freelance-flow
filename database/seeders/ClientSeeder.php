<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@freelanceflow.test')->firstOrFail();
        $workspace = Workspace::where('slug', 'demo-agency')->firstOrFail();

        $featuredClients = collect([
            ['name' => 'Aarav Mehta', 'email' => 'aarav@northstar-retail.test', 'company' => 'Northstar Retail', 'status' => 'active', 'phone' => '+91 98765 10001'],
            ['name' => 'Maya Iyer', 'email' => 'maya@bluepeak-studio.test', 'company' => 'Bluepeak Studio', 'status' => 'active', 'phone' => '+91 98765 10002'],
            ['name' => 'Rohan Shah', 'email' => 'rohan@finwise-labs.test', 'company' => 'Finwise Labs', 'status' => 'active', 'phone' => '+91 98765 10003'],
            ['name' => 'Neha Kapoor', 'email' => 'neha@greenleaf-cafe.test', 'company' => 'Greenleaf Cafe', 'status' => 'active', 'phone' => '+91 98765 10004'],
            ['name' => 'Vikram Rao', 'email' => 'vikram@orbit-legal.test', 'company' => 'Orbit Legal', 'status' => 'inactive', 'phone' => '+91 98765 10005'],
            ['name' => 'Sara Thomas', 'email' => 'sara@craftlane.test', 'company' => 'Craftlane', 'status' => 'lead', 'phone' => '+91 98765 10006'],
        ])->map(fn (array $client) => Client::factory()->create([
            ...$client,
            'notes' => 'Seeded demo client with realistic project and invoice history.',
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]));

        $activeClients = Client::factory()->count(24)->active()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

        $inactiveClients = Client::factory()->count(8)->inactive()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

        $leadClients = Client::factory()->count(12)->lead()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

        $featuredClients->merge($activeClients)->each(function (Client $client) use ($user, $workspace): void {
            Project::factory()->count(fake()->numberBetween(2, 5))->create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
            ]);
        });

        $inactiveClients->each(function (Client $client) use ($user, $workspace): void {
            Project::factory()->count(fake()->numberBetween(1, 3))->completed()->create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
            ]);
        });

        $leadClients->take(6)->each(function (Client $client) use ($user, $workspace): void {
            Project::factory()->count(1)->create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'status' => 'draft',
                'budget' => fake()->randomFloat(2, 15000, 90000),
                'deadline' => now()->addDays(fake()->numberBetween(30, 120)),
            ]);
        });

        $this->command->info('Seeded demo clients and project pipeline.');
    }
}
