<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Edit Client</h1>
            <p class="mt-1 text-sm text-gray-500">Update {{ $client->name }}'s details.</p>
        </div>
        <a
            href="{{ route('clients.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700"
        >
            ← Back to clients
        </a>
    </div>

    {{-- Edit form --}}
    <flux:card class="max-w-2xl p-6 space-y-5">

        <flux:field>
            <flux:label>Full name <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="name"
                type="text"
                placeholder="Acme Corp or John Doe"
            />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email address <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="email"
                type="email"
                placeholder="hello@acme.com"
            />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>Phone <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="phone"
                type="tel"
                placeholder="+91 98765 43210"
            />
            <flux:error name="phone" />
        </flux:field>

        <flux:field>
            <flux:label>Company <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="company"
                type="text"
                placeholder="Acme Inc."
            />
            <flux:error name="company" />
        </flux:field>

        <flux:field>
            <flux:label>Status <span class="text-red-500">*</span></flux:label>
            <flux:select wire:model="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="lead">Lead</option>
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        <flux:field>
            <flux:label>Notes <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:textarea
                wire:model="notes"
                placeholder="Any notes about this client..."
                rows="3"
            />
            <flux:error name="notes" />
        </flux:field>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2">
            <div class="flex items-center gap-3">
                <flux:button
                    wire:click="update"
                    wire:loading.attr="disabled"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="update">Save changes</span>
                    <span wire:loading wire:target="update">Saving...</span>
                </flux:button>

                <a
                    href="{{ route('clients.index') }}"
                    class="text-sm text-gray-500 hover:text-gray-700"
                >
                    Cancel
                </a>
            </div>

            {{-- Delete trigger --}}
            <flux:button
                wire:click="confirmDelete"
                variant="danger"
                size="sm"
            >
                Delete client
            </flux:button>
        </div>

    </flux:card>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="confirmingDelete" class="max-w-sm">
        <div class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold">Delete client?</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Are you sure you want to remove <strong>{{ $client->name }}</strong>?
                    This action can be undone by an administrator.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <flux:button
                    wire:click="delete"
                    wire:loading.attr="disabled"
                    variant="danger"
                    class="flex-1"
                >
                    <span wire:loading.remove wire:target="delete">Yes, delete</span>
                    <span wire:loading wire:target="delete">Deleting...</span>
                </flux:button>

                <flux:button
                    wire:click="$set('confirmingDelete', false)"
                    variant="ghost"
                    class="flex-1"
                >
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>