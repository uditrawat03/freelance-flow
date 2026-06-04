<div>

    {{-- Page header --}}
    <x-page-header 
        title="Dashboard" 
        subtitle="Welcome back, {{ auth()->user()->name }}. Here's your business overview." 
    />

    {{-- Overdue alert banner --}}
    @if ($overdue['invoices']->isNotEmpty() || $overdue['projects']->isNotEmpty())
        <x-alert 
            type="danger" 
            dismissible
            class="mb-6"
        >
            <div class="flex items-start gap-3">
                <div>
                    <strong>Attention needed:</strong>
                    <p class="mt-1 text-sm">
                        @if ($overdue['invoices']->isNotEmpty())
                            {{ $overdue['invoices']->count() }} overdue {{ Str::plural('invoice', $overdue['invoices']->count()) }}
                        @endif
                        @if ($overdue['invoices']->isNotEmpty() && $overdue['projects']->isNotEmpty())
                            and
                        @endif
                        @if ($overdue['projects']->isNotEmpty())
                            {{ $overdue['projects']->count() }} overdue {{ Str::plural('project', $overdue['projects']->count()) }}
                        @endif
                    </p>
                    @if ($overdue['invoices']->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($overdue['invoices'] as $inv)
                                <a href="{{ route('invoices.download', $inv) }}"
                                   class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                    {{ $inv->number }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </x-alert>
    @endif


    {{-- Stats grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

        {{-- Active clients card --}}
        <x-card class="group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-label text-gray-500">Active clients</p>
                    <div class="mt-3 flex items-baseline gap-2">
                        <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_clients']) }}</p>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Total across all workspaces</p>
                </div>
                <div class="p-3 bg-indigo-100 rounded-2xl group-hover:bg-indigo-200 transition-colors flex-shrink-0">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm6-8a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
        </x-card>

        {{-- Active projects card --}}
        <x-card class="group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-label text-gray-500">Active projects</p>
                    <div class="mt-3 flex items-baseline gap-2">
                        <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['active_projects']) }}</p>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">In progress</p>
                </div>
                <div class="p-3 bg-amber-100 rounded-2xl group-hover:bg-amber-200 transition-colors flex-shrink-0">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
        </x-card>

        {{-- Overdue invoices card --}}
        <x-card class="group hover:shadow-md transition-shadow {{ $stats['overdue_invoices'] > 0 ? 'border-red-200 bg-red-50' : '' }}">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-label {{ $stats['overdue_invoices'] > 0 ? 'text-red-600' : 'text-gray-500' }}">Overdue invoices</p>
                    <div class="mt-3 flex items-baseline gap-2">
                        <p class="text-3xl font-bold {{ $stats['overdue_invoices'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ number_format($stats['overdue_invoices']) }}
                        </p>
                    </div>
                    <p class="mt-2 text-xs {{ $stats['overdue_invoices'] > 0 ? 'text-red-500' : 'text-gray-400' }}">
                        {{ $stats['overdue_invoices'] > 0 ? 'Needs immediate attention' : 'All clear' }}
                    </p>
                </div>
                <div class="p-3 {{ $stats['overdue_invoices'] > 0 ? 'bg-red-200 group-hover:bg-red-300' : 'bg-green-100 group-hover:bg-green-200' }} rounded-2xl transition-colors flex-shrink-0">
                    <svg class="w-6 h-6 {{ $stats['overdue_invoices'] > 0 ? 'text-red-600' : 'text-green-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </x-card>

        {{-- Revenue this month card --}}
        <x-card class="group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-label text-gray-500">Revenue this month</p>
                    <div class="mt-3 flex items-baseline gap-2">
                        <p class="text-3xl font-bold text-gray-900">₹{{ number_format($stats['revenue_this_month'], 0) }}</p>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Current month</p>
                </div>
                <div class="p-3 bg-green-100 rounded-2xl group-hover:bg-green-200 transition-colors flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </x-card>

        {{-- Total revenue card --}}
        <x-card class="group hover:shadow-md transition-shadow border-indigo-200 bg-gradient-to-br from-indigo-50 to-white">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-label text-indigo-600">Total revenue</p>
                    <div class="mt-3 flex items-baseline gap-2">
                        <p class="text-3xl font-bold text-indigo-600">₹{{ number_format($stats['total_revenue'], 0) }}</p>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Lifetime earnings</p>
                </div>
                <div class="p-3 bg-indigo-200 rounded-2xl group-hover:bg-indigo-300 transition-colors flex-shrink-0">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </x-card>

        {{-- Unpaid invoices card --}}
        <x-card class="group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-label text-gray-500">Unpaid invoices</p>
                    <div class="mt-3 flex items-baseline gap-2">
                        <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['unpaid_invoices']) }}</p>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Awaiting payment</p>
                </div>
                <div class="p-3 bg-orange-100 rounded-2xl group-hover:bg-orange-200 transition-colors flex-shrink-0">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m0 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </x-card>

    </div>


    {{-- Charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

        {{-- Revenue chart - takes 2/3 width --}}
        <x-card class="lg:col-span-2">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Revenue Trend</h3>
                    <p class="text-sm text-gray-500 mt-0.5">
                        ₹{{ number_format($revenueChart['total'], 0) }} in <span class="font-medium">{{ match($period) { '3months' => '3 months', '6months' => '6 months', default => '12 months' } }}</span>
                    </p>
                </div>
                {{-- Period selector --}}
                <div class="flex gap-2">
                    @foreach (['3months' => '3M', '6months' => '6M', '12months' => '12M'] as $value => $label)
                        <button
                            wire:click="$set('period', '{{ $value }}')"
                            class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors
                                {{ $period === $value
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-600 bg-gray-100 hover:bg-gray-200' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div wire:ignore>
                <canvas id="revenueChart" height="80"></canvas>
            </div>
        </x-card>

        {{-- Project status doughnut - takes 1/3 width --}}
        <x-card>
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Project Status</h3>
            <div wire:ignore>
                <canvas id="statusChart" height="200"></canvas>
            </div>
            {{-- Legend --}}
            <div class="mt-5 space-y-2.5">
                @php
                    $statusColours = [
                        'draft'     => '#d1d5db',
                        'active'    => '#6366f1',
                        'on_hold'   => '#f59e0b',
                        'completed' => '#10b981',
                        'cancelled' => '#ef4444',
                    ];
                @endphp
                @foreach ($projectStatusData as $status => $count)
                    <div class="flex items-center justify-between text-sm group hover:bg-gray-50 px-2 py-1.5 rounded-lg transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded"
                                 style="background: {{ $statusColours[$status] ?? '#d1d5db' }}"></div>
                            <span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $status) }}</span>
                        </div>
                        <span class="font-semibold text-gray-900">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>

    </div>


    {{-- Recent activity section --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent clients --}}
        <x-card>
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold text-gray-900">Recent Clients</h3>
                <a href="{{ route('clients.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                    View all →
                </a>
            </div>
            @forelse ($recent['clients'] as $client)
                <a href="{{ route('clients.show', $client) }}"
                   class="flex items-center gap-3 py-3 hover:bg-indigo-50 -mx-3 px-3 rounded-lg transition-colors group">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-200 transition-colors">
                        <span class="text-xs font-bold text-indigo-700">{{ $client->initials }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate group-hover:text-indigo-700">{{ $client->name }}</p>
                        <p class="text-xs text-gray-500">{{ $client->created_at->diffForHumans() }}</p>
                    </div>
                    <x-client-status :status="$client->status" class="flex-shrink-0" />
                </a>
            @empty
                <div class="py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm6-8a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p class="text-sm text-gray-500">No clients yet</p>
                </div>
            @endforelse
        </x-card>

        {{-- Recent projects --}}
        <x-card>
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold text-gray-900">Recent Projects</h3>
                <a href="{{ route('projects.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                    New →
                </a>
            </div>
            @forelse ($recent['projects'] as $project)
                <div class="flex items-start justify-between py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-3 px-3 rounded-lg transition-colors group">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 group-hover:text-indigo-700 transition-colors">{{ $project->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $project->client->name }}</p>
                    </div>
                    <div class="flex-shrink-0 ml-3">
                        <x-project-status :status="$project->status" />
                    </div>
                </div>
            @empty
                <div class="py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <p class="text-sm text-gray-500">No projects yet</p>
                </div>
            @endforelse
        </x-card>

        {{-- Recent invoices --}}
        <x-card>
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold text-gray-900">Recent Invoices</h3>
                <a href="{{ route('invoices.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                    View all →
                </a>
            </div>
            @forelse ($recent['invoices'] as $invoice)
                <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-3 px-3 rounded-lg transition-colors group">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 group-hover:text-indigo-700">{{ $invoice->number }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $invoice->client->name }}</p>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ $invoice->formatted_total }}</p>
                            <x-status-badge :status="$invoice->status" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-gray-500">No invoices yet</p>
                </div>
            @endforelse
        </x-card>

    </div>

</div>

{{-- Chart.js initialisation --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Revenue bar chart
    const revenueCtx = document.getElementById('revenueChart');
    let revenueChart = null;

    function initRevenueChart(labels, data) {
        if (revenueChart) {
            revenueChart.destroy();
        }

        revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: data,
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => '₹' + ctx.parsed.y.toLocaleString('en-IN'),
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: {
                        grid: { color: '#f3f4f6' },
                        border: { display: false },
                        ticks: {
                            callback: (val) => '₹' + (val / 1000).toFixed(0) + 'k',
                        }
                    }
                }
            }
        });
    }

    // Project status doughnut chart
    const statusCtx = document.getElementById('statusChart');
    const statusData = @json($projectStatusData);
    const statusColours = {
        draft: '#d1d5db', active: '#6366f1',
        on_hold: '#f59e0b', completed: '#10b981', cancelled: '#ef4444'
    };

    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(statusData).map(s => s.replace('_', ' ')),
            datasets: [{
                data: Object.values(statusData),
                backgroundColor: Object.keys(statusData).map(s => statusColours[s] || '#d1d5db'),
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: { display: false },
            }
        }
    });

    // Init revenue chart with server data
    initRevenueChart(
        @json($revenueChart['labels']),
        @json($revenueChart['data'])
    );

    // Re-init revenue chart when Livewire updates (period changes)
    document.addEventListener('livewire:updated', function () {
        // Give the DOM time to update before reading the wire data
        setTimeout(() => {
            const labels = @json($revenueChart['labels']);
            const data   = @json($revenueChart['data']);
            initRevenueChart(labels, data);
        }, 50);
    });
</script>
@endpush