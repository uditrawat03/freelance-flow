<div>
    <x-page-header title="Edit Client" subtitle="Update {{ $client->name }}'s details.">
        <a href="{{ route('clients.index') }}" class="text-sm font-semibold text-muted hover:text-primary">
            &larr; Back to clients
        </a>
    </x-page-header>

    <x-form-card max-width="max-w-4xl">
        <section class="space-y-5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Client profile</h2>
                <p class="mt-1 text-sm text-muted">Keep contact, company, and relationship status up to date.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <flux:field>
                    <flux:label>Full name <span class="text-danger">*</span></flux:label>
                    <flux:input wire:model.live="name" type="text" placeholder="Acme Corp or John Doe" />
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

        <div class="flex flex-col gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @can('delete', $client)
                    <flux:button wire:click="confirmDelete" variant="danger" size="sm">
                        Delete client
                    </flux:button>
                @endcan
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('clients.index') }}" class="text-center text-sm font-semibold text-muted hover:text-secondary">
                    Cancel
                </a>

                <flux:button wire:click="update" wire:loading.attr="disabled" variant="primary">
                    <span wire:loading.remove wire:target="update">Save changes</span>
                    <span wire:loading wire:target="update">Saving...</span>
                </flux:button>
            </div>
        </div>
    </x-form-card>

    <flux:modal wire:model="confirmingDelete" class="max-w-sm">
        <div class="space-y-4 p-6">
            <div>
                <h3 class="text-lg font-semibold text-foreground">Delete client?</h3>
                <p class="mt-1 text-sm text-muted">
                    Are you sure you want to remove <strong>{{ $client->name }}</strong>?
                    This action can be undone by an administrator.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row">
                <flux:button wire:click="$set('confirmingDelete', false)" variant="ghost" class="flex-1">
                    Cancel
                </flux:button>

                <flux:button wire:click="delete" wire:loading.attr="disabled" variant="danger" class="flex-1">
                    <span wire:loading.remove wire:target="delete">Yes, delete</span>
                    <span wire:loading wire:target="delete">Deleting...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
