<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
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

         $user = User::factory()->create([
            'name'     => 'Demo User',
            'email'    => 'demo@freelanceflow.test',
            'password' => bcrypt('password'),
        ]);

        // Create a default workspace for the demo user
        $workspace = Workspace::create([
            'name'     => 'Demo Agency',
            'slug'     => 'demo-agency',
            'owner_id' => $user->id,
            'plan'     => 'pro',
        ]);

        // Attach user as owner
        $workspace->users()->attach($user->id, ['role' => 'owner']);

         $this->call([
            RoleAndPermissionSeeder::class,
            ClientSeeder::class,
            TagSeeder::class
            // ProjectSeeder::class,   - we add these in Phase 2
            // InvoiceSeeder::class,
        ]);
    }
}
