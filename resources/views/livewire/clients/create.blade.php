<div>
    <x-page-header title="Add Client" subtitle="Fill in the details to add a new client.">
        <a href="{{ route('clients.index') }}" class="text-sm font-semibold text-muted hover:text-primary">
            &larr; Back to clients
        </a>
    </x-page-header>

    <x-form-card max-width="max-w-4xl">
        <section class="space-y-5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Client profile</h2>
                <p class="mt-1 text-sm text-muted">Basic identity and contact details for this client.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <flux:field>
                    <flux:label>Full name <span class="text-danger">*</span></flux:label>
                    <flux:input wire:model.live="name" type="text" placeholder="Acme Corp or John Doe" autofocus />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Email address <span class="text-danger">*</span></flux:label>
                    <flux:input wire:model.live="email" type="email" placeholder="hello@acme.com" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>Phone <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                    <flux:input wire:model="phone" type="tel" placeholder="+91 98765 43210" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>Company <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                    <flux:input wire:model="company" type="text" placeholder="Acme Inc." />
                    <flux:error name="company" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>Status <span class="text-danger">*</span></flux:label>
                    <flux:select wire:model="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="lead">Lead</option>
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>
            </div>
        </section>

        <section class="border-t border-border pt-5">
            <flux:field>
                <flux:label>Notes <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                <flux:textarea wire:model="notes" placeholder="Any notes about this client..." rows="4" />
                <flux:error name="notes" />
            </flux:field>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('clients.index') }}" class="text-center text-sm font-semibold text-muted hover:text-secondary">
                Cancel
            </a>

            <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="save">Save client</span>
                <span wire:loading wire:target="save">Saving...</span>
            </flux:button>
        </div>
    </x-form-card>
</div>
