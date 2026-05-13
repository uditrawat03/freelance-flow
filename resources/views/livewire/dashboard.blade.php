<div>

    {{-- Page header --}}
    <x-page-header title="Dashboard" subtitle="Your FreelanceFlow business at a glance." />

    {{-- Overdue alert banner --}}
    @if ($overdue['invoices']->isNotEmpty() || $overdue['projects']->isNotEmpty())
        <div class="mb-6 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-lg">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="text-sm font-medium text-red-800">
                    Attention needed:
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
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($overdue['invoices'] as $inv)
                        <a href="{{ route('invoices.download', $inv) }}"
                           class="text-xs text-red-700 underline hover:text-red-900">
                            {{ $inv->number }} ({{ $inv->client->name }})
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Active clients</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_clients']) }}</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Active projects</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['active_projects']) }}</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5
                    {{ $stats['overdue_invoices'] > 0 ? 'border-red-200 bg-red-50' : '' }}">
            <p class="text-xs font-medium {{ $stats['overdue_invoices'] > 0 ? 'text-red-400' : 'text-gray-400' }} uppercase tracking-wide">
                Overdue invoices
            </p>
            <p class="text-3xl font-bold {{ $stats['overdue_invoices'] > 0 ? 'text-red-600' : 'text-gray-900' }} mt-1">
                {{ number_format($stats['overdue_invoices']) }}
            </p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Revenue this month</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">
                ₹{{ number_format($stats['revenue_this_month'], 0) }}
            </p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Total revenue</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1">
                ₹{{ number_format($stats['total_revenue'], 0) }}
            </p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Unpaid invoices</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['unpaid_invoices']) }}</p>
        </div>

    </div>

    {{-- Charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">

        {{-- Revenue chart - takes 2/3 width --}}
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Revenue</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        ₹{{ number_format($revenueChart['total'], 0) }} total in period
                    </p>
                </div>
                {{-- Period selector --}}
                <div class="flex gap-1">
                    @foreach (['3months' => '3M', '6months' => '6M', '12months' => '12M'] as $value => $label)
                        <button
                            wire:click="$set('period', '{{ $value }}')"
                            class="text-xs px-2.5 py-1 rounded-md font-medium transition-colors
                                {{ $period === $value
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-500 hover:bg-gray-100' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div wire:ignore>
                <canvas id="revenueChart" height="80"></canvas>
            </div>
        </div>

        {{-- Project status doughnut - takes 1/3 width --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Project status</h3>
            <div wire:ignore>
                <canvas id="statusChart" height="160"></canvas>
            </div>
            {{-- Legend --}}
            <div class="mt-4 space-y-1.5">
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
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-sm"
                                 style="background: {{ $statusColours[$status] ?? '#d1d5db' }}"></div>
                            <span class="text-gray-600">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                        </div>
                        <span class="font-medium text-gray-900">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Recent activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Recent clients --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Recent clients</h3>
                <a href="{{ route('clients.index') }}" class="text-xs text-indigo-600 hover:underline">View all</a>
            </div>
            @forelse ($recent['clients'] as $client)
                <a href="{{ route('clients.show', $client) }}"
                   class="flex items-center gap-3 py-2 hover:bg-gray-50 -mx-2 px-2 rounded-lg transition-colors">
                    <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-semibold text-indigo-700">{{ $client->initials }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $client->name }}</p>
                        <p class="text-xs text-gray-400">{{ $client->created_at->diffForHumans() }}</p>
                    </div>
                    <x-client-status :status="$client->status" />
                </a>
            @empty
                <p class="text-sm text-gray-400">No clients yet.</p>
            @endforelse
        </div>

        {{-- Recent projects --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Recent projects</h3>
            </div>
            @forelse ($recent['projects'] as $project)
                <div class="flex items-start justify-between py-2 border-b border-gray-50 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $project->name }}</p>
                        <p class="text-xs text-gray-400">{{ $project->client->name }}</p>
                    </div>
                    <x-project-status :status="$project->status" />
                </div>
            @empty
                <p class="text-sm text-gray-400">No projects yet.</p>
            @endforelse
        </div>

        {{-- Recent invoices --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Recent invoices</h3>
            </div>
            @forelse ($recent['invoices'] as $invoice)
                <div class="flex items-start justify-between py-2 border-b border-gray-50 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900">{{ $invoice->number }}</p>
                        <p class="text-xs text-gray-400">{{ $invoice->client->name }}</p>
                    </div>
                    <div class="text-right ml-3 flex-shrink-0">
                        <p class="text-sm font-medium text-gray-900">{{ $invoice->formatted_total }}</p>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full
                            {{ match($invoice->status) {
                                'paid'    => 'bg-green-100 text-green-700',
                                'sent'    => 'bg-blue-100 text-blue-700',
                                'overdue' => 'bg-red-100 text-red-700',
                                default   => 'bg-gray-100 text-gray-600',
                            } }}">
                            {{ $invoice->status_label }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">No invoices yet.</p>
            @endforelse
        </div>

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