<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Pick a consistent company name and derive the email from it
        $company = fake()->company();
        $domain = str(fake()->domainWord())->slug() . '.com';

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->boolean(70)   // 70% of clients have a phone
                ? fake()->phoneNumber()
                : null,
            'company' => fake()->boolean(80)   // 80% belong to a company
                ? $company
                : null,
            'notes' => fake()->boolean(40)   // 40% have notes
                ? fake()->sentences(2, true)
                : null,
            'status' => fake()->randomElement(['active', 'active', 'active', 'inactive', 'lead']),
        ];
    }

    
    // Only active clients
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    // Only inactive clients
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    // Only leads — no company, always has a phone
    public function lead(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'lead',
            'company' => null,
            'phone' => fake()->phoneNumber(),
        ]);
    }

    // Client with no optional fields — bare minimum record
    public function minimal(): static
    {
        return $this->state(fn(array $attributes) => [
            'phone' => null,
            'company' => null,
            'notes' => null,
        ]);
    }
}
