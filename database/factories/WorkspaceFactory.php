<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => str($name . '-' . fake()->unique()->numberBetween(1000, 9999))->slug()->toString(),
            'owner_id' => User::factory(),
            'plan' => 'free',
            'settings' => [],
        ];
    }
}
