<div>
    {{-- Page header --}}
    <x-page-header title="Add Client" subtitle="Fill in the details to add a new client.">
        <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
            ← Back to clients
        </a>
    </x-page-header>

    {{-- Form card --}}
    <x-form-card>

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

    </x-form-card>
</div>