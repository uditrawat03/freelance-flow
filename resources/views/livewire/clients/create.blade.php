<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Add Client</h1>
            <p class="mt-1 text-sm text-gray-500">Fill in the details to add a new client to FreelanceFlow.</p>
        </div>
        <a
            href="{{ route('clients.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700"
        >
            ← Back to clients
        </a>
    </div>

    {{-- Form card --}}
    <flux:card class="max-w-2xl p-6 space-y-5">

        {{-- Name --}}
        <flux:field>
            <flux:label>Full name <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="name"
                type="text"
                placeholder="Acme Corp or John Doe"
                autofocus
            />
            <flux:error name="name" />
        </flux:field>

        {{-- Email --}}
        <flux:field>
            <flux:label>Email address <span class="text-red-500">*</span></flux:label>
            <flux:input
                wire:model.live="email"
                type="email"
                placeholder="hello@acme.com"
            />
            <flux:error name="email" />
        </flux:field>

        {{-- Phone --}}
        <flux:field>
            <flux:label>Phone <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="phone"
                type="tel"
                placeholder="+91 98765 43210"
            />
            <flux:error name="phone" />
        </flux:field>

        {{-- Company --}}
        <flux:field>
            <flux:label>Company <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input
                wire:model="company"
                type="text"
                placeholder="Acme Inc."
            />
            <flux:error name="company" />
        </flux:field>

        {{-- Status --}}
        <flux:field>
            <flux:label>Status <span class="text-red-500">*</span></flux:label>
            <flux:select wire:model="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="lead">Lead</option>
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        {{-- Notes --}}
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
        <div class="flex items-center gap-3 pt-2">
            <flux:button
                wire:click="save"
                wire:loading.attr="disabled"
                variant="primary"
            >
                <span wire:loading.remove wire:target="save">Save client</span>
                <span wire:loading wire:target="save">Saving...</span>
            </flux:button>

            <a
                href="{{ route('clients.index') }}"
                class="text-sm text-gray-500 hover:text-gray-700"
            >
                Cancel
            </a>
        </div>

    </flux:card>
</div>