<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $status   = fake()->randomElement(['draft', 'draft', 'active', 'active', 'active', 'on_hold', 'completed', 'cancelled']);
        $deadline = in_array($status, ['active', 'on_hold'])
            ? fake()->dateTimeBetween('now', '+6 months')
            : (fake()->boolean(50) ? fake()->dateTimeBetween('-3 months', '+3 months') : null);

        return [
            'client_id'   => Client::factory(), // creates a new client if not specified
            'name'        => fake()->randomElement([
                'Website Redesign',
                'Mobile App Development',
                'Brand Identity',
                'SEO Campaign',
                'E-commerce Platform',
                'CRM Integration',
                'Social Media Strategy',
                'Annual Report Design',
                'API Development',
                'Marketing Dashboard',
            ]) . ' — ' . fake()->company(),
            'description' => fake()->boolean(70) ? fake()->paragraph() : null,
            'status'      => $status,
            'budget'      => fake()->boolean(80)
                ? fake()->randomFloat(2, 5000, 250000)
                : null,
            'deadline'    => $deadline,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'   => 'completed',
            'deadline' => fake()->dateTimeBetween('-6 months', '-1 week'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status'   => 'active',
            'deadline' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}