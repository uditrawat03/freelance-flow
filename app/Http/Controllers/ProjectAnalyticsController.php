<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectAnalyticsController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load(['client:id,name', 'tags:id,name,colour']);

        $invoiceQuery = $project->invoices();
        $invoiceCount = (clone $invoiceQuery)->count();
        $totalInvoiced = (float) (clone $invoiceQuery)->sum('total');
        $totalPaid = (float) (clone $invoiceQuery)->where('status', 'paid')->sum('total');
        $totalOutstanding = (float) (clone $invoiceQuery)
            ->whereIn('status', ['sent', 'overdue'])
            ->sum('total');

        $invoiceLimit = 50;
        $invoices = (clone $invoiceQuery)
            ->select(['id', 'project_id', 'number', 'status', 'total', 'issued_at', 'due_at'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit($invoiceLimit)
            ->get();

        return Inertia::render('Projects/Analytics', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'budget' => $project->budget,
                'formatted_budget' => $this->formatCurrency($project->budget),
                'deadline' => $project->deadline?->toDateString(),
                'client_id' => $project->client_id,
                'client' => [
                    'id' => $project->client->id,
                    'name' => $project->client->name,
                ],
                'tags' => $project->tags->map(fn ($tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'colour' => $tag->colour,
                ])->values(),
                'urls' => [
                    'client' => route('clients.show', $project->client),
                    'edit' => route('projects.edit', $project),
                ],
            ],
            'invoices' => $invoices->map(fn ($invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'total' => $invoice->total,
                'formatted_total' => $this->formatCurrency($invoice->total),
                'issued_at' => $invoice->issued_at?->toDateString(),
                'due_at' => $invoice->due_at?->toDateString(),
            ])->values(),
            'stats' => [
                'total_invoiced' => $this->formatCurrency($totalInvoiced),
                'total_paid' => $this->formatCurrency($totalPaid),
                'total_outstanding' => $this->formatCurrency($totalOutstanding),
                'outstanding_amount' => $totalOutstanding,
                'invoice_count' => $invoiceCount,
                'has_more_invoices' => $invoiceCount > $invoiceLimit,
            ],
        ]);
    }

    private function formatCurrency(float|int|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return 'No budget set';
        }

        return 'INR '.number_format((float) $amount, 2);
    }
}
