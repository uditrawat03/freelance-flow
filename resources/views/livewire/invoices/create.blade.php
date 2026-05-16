<div>
    <x-page-header title="New Invoice" subtitle="Create a professional invoice for a client.">
        <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back</a>
    </x-page-header>

    <x-form-card max-width="max-w-3xl">

        {{-- Client & Project --}}
        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Client <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model.live="client_id">
                    <option value="">Select client...</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->display_name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="client_id" />
            </flux:field>

            <flux:field>
                <flux:label>Project <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
                <flux:select wire:model="project_id" :disabled="! $client_id">
                    <option value="">No project</option>
                    @foreach ($this->projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="project_id" />
            </flux:field>
        </div>

        {{-- Dates --}}
        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Invoice date</flux:label>
                <flux:input wire:model="issued_at" type="date" />
                <flux:error name="issued_at" />
            </flux:field>
            <flux:field>
                <flux:label>Due date <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
                <flux:input wire:model="due_at" type="date" />
                <flux:error name="due_at" />
            </flux:field>
        </div>

        {{-- Line items --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-gray-700">Line items</label>
            </div>

            {{-- Header row --}}
            <div class="grid grid-cols-12 gap-2 mb-1 px-1">
                <div class="col-span-6 text-xs text-gray-400 font-medium">Description</div>
                <div class="col-span-2 text-xs text-gray-400 font-medium text-right">Qty</div>
                <div class="col-span-3 text-xs text-gray-400 font-medium text-right">Rate (₹)</div>
                <div class="col-span-1"></div>
            </div>

            @foreach ($lineItems as $index => $item)
                <div class="grid grid-cols-12 gap-2 mb-2 items-center" wire:key="line-{{ $index }}">
                    <div class="col-span-6">
                        <flux:input wire:model="lineItems.{{ $index }}.description" type="text"
                            placeholder="e.g. Website Design" />
                        @error("lineItems.{$index}.description")
                            <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-span-2">
                        <flux:input wire:model.live="lineItems.{{ $index }}.quantity" type="number" min="1" step="1" />
                    </div>
                    <div class="col-span-3">
                        <flux:input wire:model.live="lineItems.{{ $index }}.rate" type="number" min="0" step="0.01"
                            placeholder="0.00" />
                    </div>
                    <div class="col-span-1 flex justify-center">
                        @if (count($lineItems) > 1)
                            <button wire:click="removeLineItem({{ $index }})"
                                class="text-gray-300 hover:text-red-400 transition-colors" type="button">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach

            <button wire:click="addLineItem" type="button"
                class="mt-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                + Add line item
            </button>
        </div>

        {{-- Totals --}}
        <div class="border-t border-gray-100 pt-4">
            <div class="ml-auto w-56 space-y-2">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span>₹{{ number_format($this->subtotal, 2) }}</span>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm text-gray-600">GST</span>
                        <div class="w-16">
                            <flux:input wire:model.live="tax_rate" type="number" min="0" max="100" step="0.5" />
                        </div>
                        <span class="text-sm text-gray-400">%</span>
                    </div>
                    <span class="text-sm text-gray-600">₹{{ number_format($this->tax_amount, 2) }}</span>
                </div>

                <div class="flex justify-between text-base font-bold text-white border-t border-gray-200 pt-2">
                    <span>Total</span>
                    <span>₹{{ number_format($this->total, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <flux:field>
            <flux:label>Notes <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:textarea wire:model="notes" placeholder="Payment terms, bank details, thank you message..."
                rows="2" />
        </flux:field>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2">
            <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="save">Create invoice</span>
                <span wire:loading wire:target="save">Creating...</span>
            </flux:button>
            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

    </x-form-card>
</div>