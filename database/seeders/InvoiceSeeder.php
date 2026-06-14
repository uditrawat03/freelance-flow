<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    private int $sequence = 1;

    public function run(): void
    {
        $user = User::where('email', 'demo@freelanceflow.test')->firstOrFail();
        $workspace = Workspace::where('slug', 'demo-agency')->firstOrFail();

        Project::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->with('client')
            ->get()
            ->each(function (Project $project) use ($user, $workspace): void {
                $count = match ($project->status) {
                    'completed' => fake()->numberBetween(2, 5),
                    'active' => fake()->numberBetween(1, 4),
                    'on_hold' => fake()->numberBetween(1, 3),
                    'draft' => fake()->boolean(35) ? 1 : 0,
                    default => fake()->numberBetween(0, 2),
                };

                for ($i = 0; $i < $count; $i++) {
                    $status = $this->statusForProject($project->status);
                    $issuedAt = $status === 'draft'
                        ? null
                        : now()->subDays(fake()->numberBetween(5, 330));
                    $dueAt = $issuedAt?->copy()->addDays(fake()->numberBetween(7, 45));
                    $paidAt = $status === 'paid'
                        ? $issuedAt?->copy()->addDays(fake()->numberBetween(2, 35))
                        : null;

                    if ($status === 'overdue') {
                        $dueAt = now()->subDays(fake()->numberBetween(2, 45));
                    }

                    $this->createInvoice($project->client, $project, $user, $workspace, $status, $issuedAt, $dueAt, $paidAt);
                }
            });

        Client::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->inRandomOrder()
            ->limit(12)
            ->get()
            ->each(function (Client $client) use ($user, $workspace): void {
                $status = fake()->randomElement(['draft', 'sent', 'paid']);
                $issuedAt = $status === 'draft' ? null : now()->subDays(fake()->numberBetween(2, 90));
                $dueAt = $status === 'draft' ? null : now()->addDays(fake()->numberBetween(7, 30));
                $paidAt = $status === 'paid' ? now()->subDays(fake()->numberBetween(1, 30)) : null;

                $this->createInvoice($client, null, $user, $workspace, $status, $issuedAt, $dueAt, $paidAt);
            });

        $this->command->info('Seeded invoices across paid, sent, draft, and overdue states.');
    }

    private function statusForProject(string $projectStatus): string
    {
        return match ($projectStatus) {
            'completed' => fake()->randomElement(['paid', 'paid', 'paid', 'sent']),
            'active' => fake()->randomElement(['paid', 'sent', 'sent', 'overdue', 'draft']),
            'on_hold' => fake()->randomElement(['sent', 'overdue', 'draft']),
            'draft' => 'draft',
            default => fake()->randomElement(['paid', 'sent']),
        };
    }

    private function createInvoice(
        Client $client,
        ?Project $project,
        User $user,
        Workspace $workspace,
        string $status,
        mixed $issuedAt,
        mixed $dueAt,
        mixed $paidAt,
    ): void {
        $subtotal = fake()->randomFloat(2, 8000, 180000);
        $taxRate = fake()->randomElement([0, 5, 12, 18]);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = $subtotal + $taxAmount;

        Invoice::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'project_id' => $project?->id,
            'number' => sprintf('DEMO-INV-%s-%03d', now()->year, $this->sequence++),
            'status' => $status,
            'notes' => fake()->boolean(45) ? fake()->sentence() : null,
            'line_items' => [
                [
                    'description' => fake()->randomElement([
                        'Discovery and planning',
                        'Design and prototyping',
                        'Frontend implementation',
                        'Backend development',
                        'Content production',
                        'QA and launch support',
                    ]),
                    'quantity' => 1,
                    'rate' => $subtotal,
                    'amount' => $subtotal,
                ],
            ],
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'issued_at' => $status === 'draft' ? null : $issuedAt,
            'due_at' => $status === 'draft' ? null : $dueAt,
            'paid_at' => $status === 'paid' ? $paidAt : null,
            'pdf_path' => fake()->boolean(60) ? 'invoices/demo-invoice.pdf' : null,
            'created_at' => $issuedAt ?? now()->subDays(fake()->numberBetween(1, 45)),
            'updated_at' => now()->subDays(fake()->numberBetween(0, 15)),
        ]);
    }
}
