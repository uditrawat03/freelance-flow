<?php

namespace App\GraphQL\Mutations;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CreateInvoice
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Invoice
    {
        $input = Validator::make($args['input'], [
            'client_id' => ['required', Rule::exists(Client::class, 'id')],
            'project_id' => ['nullable', Rule::exists(Project::class, 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'line_items' => ['required', 'array', 'min:1', 'max:100'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'line_items.*.rate' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ])->validate();

        if (isset($input['project_id'])) {
            Project::query()
                ->whereKey($input['project_id'])
                ->where('client_id', $input['client_id'])
                ->firstOrFail();
        }

        return $this->invoiceService->create([
            'client_id' => $input['client_id'],
            'project_id' => $input['project_id'] ?? null,
            'notes' => $input['notes'] ?? null,
            'tax_rate' => $input['tax_rate'] ?? config('freelanceflow.invoice.default_tax_rate', 18.0),
            'issued_at' => $input['issued_at'] ?? now()->toDateString(),
            'due_at' => $input['due_at'] ?? null,
            'line_items' => $input['line_items'],
            'status' => 'draft',
        ])->load(['client', 'project']);
    }
}
