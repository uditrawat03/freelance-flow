<div>
    <x-page-header title="Invoices" subtitle="Manage your invoices and track payments.">
        <a href="{{ route('invoices.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            + New invoice
        </a>
    </x-page-header>

    {{-- Status filter pills --}}
    <div class="flex items-center gap-2 mb-5">
        @foreach (['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
            <button wire:click="$set('status', '{{ $value }}')" class="text-sm px-3 py-1.5 rounded-full border transition-colors
                        {{ $status === $value
            ? 'bg-indigo-600 text-white border-indigo-600'
            : 'text-gray-600 border-gray-200 hover:border-indigo-300 bg-white' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Status filter bar --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6">
        <div class="flex items-center gap-2 flex-wrap">
            @foreach (['' => 'All invoices', 'draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
                <button wire:click="$set('status', '{{ $value }}')" 
                    class="text-sm px-3 py-2 rounded-lg font-medium transition-colors whitespace-nowrap
                        {{ $status === $value
                        ? 'bg-indigo-600 text-white'
                        : 'text-gray-600 bg-gray-100 hover:bg-gray-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Invoice rows --}}
    <div class="space-y-3">
    @forelse ($invoices as $invoice)
        <x-card class="hover:shadow-md transition-shadow group">
            <div class="flex items-center justify-between gap-4">

                {{-- Left: invoice info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 mb-2 flex-wrap">
                        <a href="{{ route('invoices.preview', $invoice) }}" target="_blank" class="font-semibold text-gray-900 text-sm hover:text-indigo-600 transition-colors">
                            {{ $invoice->number }}
                        </a>
                        {{-- Status badge --}}
                        <x-status-badge :status="$invoice->status" />
                        @if ($invoice->is_overdue)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-lg bg-red-100 text-red-700">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 100 2 1 1 0 000-2zM9 9a1 1 0 000 2v4a1 1 0 102 0v-4a1 1 0 00-2-2z" clip-rule="evenodd"/>
                                </svg>
                                Due {{ $invoice->due_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-gray-500">Client</p>
                            <p class="font-medium text-gray-900">{{ $invoice->client->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Dates</p>
                            <p class="text-sm text-gray-700">
                                {{ $invoice->issued_at?->format('M d') ?? 'N/A' }} → {{ $invoice->due_at?->format('M d') ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Right: total + actions --}}
                <div class="flex items-center gap-4 flex-shrink-0">
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Amount</p>
                        <p class="text-lg font-bold text-gray-900">{{ $invoice->formatted_total }}</p>
                    </div>

                    {{-- Quick actions dropdown --}}
                    <div class="flex items-center gap-1">
                        {{-- Generate/Download PDF --}}
                        @if (!$invoice->has_pdf)
                            <button wire:click="confirmGenerate({{ $invoice->id }})"
                                dusk="generate-invoice-pdf-{{ $invoice->id }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-50 transition-colors"
                                title="Generate PDF">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        @else
                            <a href="{{ route('invoices.download', $invoice) }}"
                                dusk="download-invoice-pdf-{{ $invoice->id }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-50 transition-colors"
                                title="Download PDF">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </a>
                        @endif

                        {{-- Mark as sent --}}
                        @if ($invoice->status === 'draft')
                            @can('send invoices')
                                <button wire:click="markSent({{ $invoice->id }})"
                                    wire:confirm="Mark invoice {{ $invoice->number }} as sent?"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-blue-600 hover:bg-blue-50 transition-colors"
                                    title="Mark as sent">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </button>
                            @endcan
                        @endif

                        {{-- Mark as paid --}}
                        @if (in_array($invoice->status, ['sent', 'overdue']))
                            <button wire:click="markPaid({{ $invoice->id }})"
                                wire:confirm="Mark invoice {{ $invoice->number }} as paid?"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-green-600 hover:bg-green-50 transition-colors"
                                title="Mark as paid">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        @endif

                        {{-- Delete --}}
                        @can('delete invoices')
                            <button wire:click="confirmDelete({{ $invoice->id }})"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                                title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        @endcan
                    </div>
                </div>

            </div>
        </x-card>
    @empty
        <x-empty-state 
            message="No invoices yet." 
            cta-text="Create your first invoice"
            :cta-href="route('invoices.create')" />
    @endforelse
    </div>

    {{-- Pagination --}}
    @if ($invoices->hasPages())
        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif

    {{-- Generate PDF confirmation modal --}}
    <flux:modal wire:model="confirmingGenerate" class="max-w-sm">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-white">Generate PDF?</h3>
            <p class="text-sm text-gray-500">
                This will create the invoice PDF and store it on the server.
                You can download or send it to the client afterwards.
            </p>
            <div class="flex items-center gap-3">
                <flux:button wire:click="generatePdf" wire:loading.attr="disabled" dusk="confirm-generate-invoice-pdf" variant="primary" class="flex-1">
                    <span wire:loading.remove wire:target="generatePdf">Generate</span>
                    <span wire:loading wire:target="generatePdf">Generating...</span>
                </flux:button>
                <flux:button wire:click="$set('confirmingGenerate', false)" variant="ghost" class="flex-1">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="confirmingDelete" class="max-w-sm">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-white">Delete invoice?</h3>
            <p class="text-sm text-gray-500">This action cannot be undone.</p>
            <div class="flex items-center gap-3">
                <flux:button wire:click="delete" wire:loading.attr="disabled" variant="danger" class="flex-1">Yes,
                    delete</flux:button>
                <flux:button wire:click="$set('confirmingDelete', false)" variant="ghost" class="flex-1">Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
