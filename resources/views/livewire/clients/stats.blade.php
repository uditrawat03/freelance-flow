<div class="grid grid-cols-2 gap-3 mb-6 lg:grid-cols-4">
    @foreach ([
        'total' => ['label' => 'Total clients', 'class' => 'text-foreground'],
        'active' => ['label' => 'Active', 'class' => 'text-success'],
        'inactive' => ['label' => 'Inactive', 'class' => 'text-muted'],
        'lead' => ['label' => 'Leads', 'class' => 'text-warning'],
    ] as $key => $meta)
        <div class="rounded-xl border border-border bg-surface p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $meta['label'] }}</p>
            <p class="mt-2 text-2xl font-bold {{ $meta['class'] }}">{{ $stats[$key] }}</p>
        </div>
    @endforeach
</div>
