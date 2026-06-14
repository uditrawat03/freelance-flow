<div
    x-data
    x-on:keydown.ctrl.k.window.prevent="$wire.openModal()"
    x-on:keydown.meta.k.window.prevent="$wire.openModal()"
>
    <flux:button wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal" variant="primary" size="sm">
        Quick invoice
    </flux:button>

    <div
        wire:loading.flex
        wire:target="save"
        class="mt-3 items-center gap-2 rounded-lg border border-primary-soft bg-primary-soft px-3 py-2 text-sm font-medium text-primary"
    >
        <span class="h-2 w-2 rounded-full bg-primary"></span>
        Creating invoice...
    </div>

    <flux:modal wire:model="open" class="max-w-md">
        <div class="space-y-4 p-6">
            <h3 class="text-lg font-semibold text-foreground">Quick invoice</h3>

            <flux:field>
                <flux:label>Client</flux:label>
                <flux:select wire:model="client_id">
                    <option value="">Select client...</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="client_id" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:input wire:model="description" type="text" maxlength="255" placeholder="Website design" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>Amount</flux:label>
                <flux:input wire:model="amount" type="number" min="1" step="0.01" placeholder="50000" />
                <flux:error name="amount" />
            </flux:field>

            <div class="flex items-center gap-3 pt-2">
                <flux:button wire:click="save" wire:loading.attr="disabled" wire:target="save" variant="primary" class="flex-1">
                    <span wire:loading.remove wire:target="save">Create</span>
                    <span wire:loading wire:target="save">Creating...</span>
                </flux:button>
                <flux:button wire:click="$set('open', false)" variant="ghost" class="flex-1">Cancel</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
