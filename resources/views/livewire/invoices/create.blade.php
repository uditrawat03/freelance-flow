<div>
    <x-page-header title="New Invoice" subtitle="Create a professional invoice for a client.">
        <a href="{{ route('invoices.index') }}" class="text-sm font-semibold text-muted hover:text-primary">
            &larr; Back
        </a>
    </x-page-header>

    <x-form-card max-width="max-w-5xl">
        <section class="space-y-5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Invoice setup</h2>
                <p class="mt-1 text-sm text-muted">Choose the client, project, and dates for this invoice.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <flux:field>
                    <flux:label>Client <span class="text-danger">*</span></flux:label>
                    <flux:select wire:model.live="client_id">
                        <option value="">Select client...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->display_name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="client_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Project <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                    <flux:select wire:model="project_id" :disabled="! $client_id">
                        <option value="">No project</option>
                        @foreach ($this->projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="project_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Invoice date</flux:label>
                    <flux:input wire:model="issued_at" type="date" />
                    <flux:error name="issued_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Due date <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                    <flux:input wire:model="due_at" type="date" />
                    <flux:error name="due_at" />
                </flux:field>
            </div>
        </section>

        <section class="space-y-4 border-t border-border pt-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-foreground">Line items</h2>
                    <p class="mt-1 text-sm text-muted">Add billable work, quantities, and rates.</p>
                </div>

                <button
                    wire:click="addLineItem"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold text-primary shadow-soft transition hover:border-primary hover:bg-primary-soft"
                >
                    + Add line item
                </button>
            </div>

            <div class="hidden grid-cols-12 gap-3 px-1 text-xs font-semibold uppercase tracking-wide text-muted md:grid">
                <div class="col-span-6">Description</div>
                <div class="col-span-2 text-right">Qty</div>
                <div class="col-span-3 text-right">Rate (INR)</div>
                <div class="col-span-1"></div>
            </div>

            <div class="space-y-3">
                @foreach ($lineItems as $index => $item)
                    <div class="rounded-lg border border-border bg-surface-muted p-3" wire:key="line-{{ $index }}">
                        <div class="grid gap-3 md:grid-cols-12 md:items-start">
                            <div class="md:col-span-6">
                                <label class="mb-1 block text-xs font-semibold text-muted md:hidden">Description</label>
                                <flux:input
                                    wire:model="lineItems.{{ $index }}.description"
                                    type="text"
                                    placeholder="e.g. Website Design"
                                />
                                @error("lineItems.{$index}.description")
                                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-muted md:hidden">Qty</label>
                                <flux:input wire:model.live="lineItems.{{ $index }}.quantity" type="number" min="1" step="1" />
                            </div>

                            <div class="md:col-span-3">
                                <label class="mb-1 block text-xs font-semibold text-muted md:hidden">Rate (INR)</label>
                                <flux:input wire:model.live="lineItems.{{ $index }}.rate" type="number" min="0" step="0.01" placeholder="0.00" />
                            </div>

                            <div class="flex justify-end md:col-span-1 md:pt-2">
                                @if (count($lineItems) > 1)
                                    <button
                                        wire:click="removeLineItem({{ $index }})"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted transition hover:bg-red-50 hover:text-danger"
                                        type="button"
                                        aria-label="Remove line item"
                                    >
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 border-t border-border pt-5 lg:grid-cols-[1fr_20rem]">
            <flux:field>
                <flux:label>Notes <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                <flux:textarea wire:model="notes" placeholder="Payment terms, bank details, thank you message..." rows="5" />
            </flux:field>

            <div class="rounded-lg border border-border bg-surface-muted p-4 shadow-soft">
                <h2 class="text-sm font-semibold text-foreground">Invoice total</h2>

                <div class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm text-muted">
                        <span>Subtotal</span>
                        <span class="font-semibold text-secondary">INR {{ number_format($this->subtotal, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-muted">GST</span>
                            <div class="w-20">
                                <flux:input wire:model.live="tax_rate" type="number" min="0" max="100" step="0.5" />
                            </div>
                            <span class="text-sm text-muted">%</span>
                        </div>
                        <span class="text-sm font-semibold text-secondary">INR {{ number_format($this->tax_amount, 2) }}</span>
                    </div>

                    <div class="flex justify-between border-t border-border pt-3 text-base font-bold text-foreground">
                        <span>Total</span>
                        <span>INR {{ number_format($this->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('invoices.index') }}" class="text-center text-sm font-semibold text-muted hover:text-secondary">
                Cancel
            </a>

            <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="save">Create invoice</span>
                <span wire:loading wire:target="save">Creating...</span>
            </flux:button>
        </div>
    </x-form-card>
</div>
