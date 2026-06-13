<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 5000, 100000);
        $taxRate = 18.0;
        $taxAmount = $subtotal * ($taxRate / 100);

        return [
            'client_id' => Client::factory(),
            'number' => 'INV-' . now()->year . '-' . str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'status' => fake()->randomElement(['draft', 'sent', 'paid']),
            'line_items' => [
                [
                    'description' => fake()->words(3, true),
                    'quantity' => 1,
                    'rate' => $subtotal,
                ],
            ],
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'issued_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'due_at' => fake()->dateTimeBetween('now', '+30 days'),
            'paid_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'issued_at' => null,
            'due_at' => null,
            'paid_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'issued_at' => now()->subDays(5),
            'due_at' => now()->addDays(25),
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'issued_at' => now()->subDays(10),
            'due_at' => now()->addDays(20),
            'paid_at' => now()->subDays(2),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'issued_at' => now()->subDays(35),
            'due_at' => now()->subDays(5),
            'paid_at' => null,
        ]);
    }
}
