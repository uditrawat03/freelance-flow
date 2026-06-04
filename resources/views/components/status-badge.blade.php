@props(['status'])

@php
    $styles = match($status) {
        'active' => 'bg-green-50 text-green-700 border border-green-200',
        'inactive' => 'bg-surface-muted text-secondary border border-border',
        'lead' => 'bg-blue-50 text-blue-700 border border-blue-200',
        'draft' => 'bg-surface-muted text-secondary border border-border',
        'sent' => 'bg-blue-50 text-blue-700 border border-blue-200',
        'paid' => 'bg-green-50 text-green-700 border border-green-200',
        'overdue' => 'bg-red-50 text-red-700 border border-red-200',
        'on_hold' => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
        'completed' => 'bg-green-50 text-green-700 border border-green-200',
        'cancelled' => 'bg-red-50 text-red-700 border border-red-200',
        default => 'bg-surface-muted text-secondary border border-border',
    };

    $labels = match($status) {
        'active' => 'Active',
        'inactive' => 'Inactive',
        'lead' => 'Lead',
        'draft' => 'Draft',
        'sent' => 'Sent',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
@endphp

<span class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold {{ $styles }}">
    {{ $labels }}
</span>
