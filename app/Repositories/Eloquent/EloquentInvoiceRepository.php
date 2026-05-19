<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function paginate(string $status = '', int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('client')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): ?Invoice
    {
        return Invoice::find($id);
    }

    public function findOrFail(int $id): Invoice
    {
        return Invoice::findOrFail($id);
    }

    public function create(array $data): Invoice
    {
        $data['number'] = Invoice::generateNumber();
        $invoice = Invoice::create($data);
        $invoice->recalculate();
        return $invoice;
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);
        $invoice->recalculate();
        return $invoice->fresh();
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    public function totalRevenue(): float
    {
        return (float) Invoice::paid()->sum('total');
    }

    public function revenueByMonth(int $months = 12): array
    {
        $labels = [];
        $data   = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date     = now()->subMonths($i);
            $labels[] = $date->format('M Y');
            $data[]   = (float) Invoice::paid()
                ->whereMonth('paid_at', $date->month)
                ->whereYear('paid_at', $date->year)
                ->sum('total');
        }

        return ['labels' => $labels, 'data' => $data];
    }

    public function overdueInvoices(): Collection
    {
        return Invoice::overdue()->with('client')->latest('due_at')->get();
    }
}