<div>
    <x-page-header title="Invoices" subtitle="Manage your invoices and track payments.">
        <a href="{{ route('invoices.create') }}"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors">
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

    {{-- Invoice rows --}}
    @forelse ($invoices as $invoice)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-2">
            <div class="flex items-start justify-between">

                {{-- Left: invoice info --}}
                <div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-gray-900 text-sm">{{ $invoice->number }}</span>
                        {{-- Status badge --}}
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full
                                {{ match ($invoice->status) {
            'paid' => 'bg-green-100 text-green-700',
            'sent' => 'bg-blue-100 text-blue-700',
            'overdue' => 'bg-red-100 text-red-700',
            'draft' => 'bg-gray-100 text-gray-600',
            default => 'bg-gray-100 text-gray-600',
        } }}">
                            {{ $invoice->status_label }}
                        </span>
                        @if ($invoice->is_overdue)
                            <span class="text-xs text-red-500 font-medium">
                                Due {{ $invoice->due_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $invoice->client->name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $invoice->issued_at ? $invoice->issued_at->format('M d, Y') : 'No date' }}
                        @if ($invoice->due_at)
                            · Due {{ $invoice->due_at->format('M d, Y') }}
                        @endif
                    </p>
                </div>

                {{-- Right: total + actions --}}
                <div class="flex items-center gap-4">
                    <span class="text-base font-bold text-gray-900">{{ $invoice->formatted_total }}</span>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2">

                        {{-- Generate PDF --}}
                        @if (!$invoice->has_pdf)
                            <button wire:click="confirmGenerate({{ $invoice->id }})"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium border border-indigo-200 px-2.5 py-1 rounded-md hover:bg-indigo-50 transition-colors">
                                Generate PDF
                            </button>
                        @else
                            <a href="{{ route('invoices.download', $invoice) }}"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                Download PDF
                            </a>
                            <a href="{{ route('invoices.preview', $invoice) }}" target="_blank"
                                class="text-xs text-gray-500 hover:text-gray-700">
                                Preview
                            </a>
                        @endif

                        {{-- Mark as sent --}}
                        @if ($invoice->status === 'draft')
                            @can('send invoices')
                                <button wire:click="markSent({{ $invoice->id }})"
                                    wire:confirm="Mark invoice {{ $invoice->number }} as sent?"
                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Mark sent
                                </button>
                            @endcan
                        @endif

                        {{-- Mark as paid --}}
                        @if (in_array($invoice->status, ['sent', 'overdue']))
                            <button wire:click="markPaid({{ $invoice->id }})"
                                wire:confirm="Mark invoice {{ $invoice->number }} as paid?"
                                class="text-xs text-green-600 hover:text-green-800 font-medium">
                                Mark paid
                            </button>
                            <a href="{{ route('invoices.pay', $invoice) }}" target="_blank"
                                class="text-xs text-purple-600 hover:text-purple-800 font-medium">
                                Pay link
                            </a>
                        @endif

                        {{-- Delete --}}
                        @can('delete invoices')
                            <button wire:click="confirmDelete({{ $invoice->id }})"
                                class="text-xs text-red-400 hover:text-red-600">
                                Delete
                            </button>
                        @endcan

                    </div>
                </div>

            </div>
        </div>
    @empty
        <x-empty-state message="No invoices yet." cta-text="Create your first invoice"
            :cta-href="route('invoices.create')" />
    @endforelse

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
                <flux:button wire:click="generatePdf" wire:loading.attr="disabled" variant="primary" class="flex-1">
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