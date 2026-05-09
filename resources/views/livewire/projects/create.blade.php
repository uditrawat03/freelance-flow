<div>
    <x-page-header title="Add Project" subtitle="Create a project and connect it to a client.">
        <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
            &larr; Back to clients
        </a>
    </x-page-header>

    <x-form-card>
        <flux:field>
            <flux:label>Client <span class="text-red-500">*</span></flux:label>
            <flux:select wire:model="selectedClientId">
                <option value="">Select a client</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </flux:select>
            <flux:error name="selectedClientId" />
        </flux:field>

        <flux:field>
            <flux:label>Project name <span class="text-red-500">*</span></flux:label>
            <flux:input wire:model.live="name" type="text" placeholder="Website redesign" autofocus />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Description <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:textarea
                wire:model="description"
                placeholder="Scope, goals, or notes for this project..."
                rows="4"
            />
            <flux:error name="description" />
        </flux:field>

        <div class="grid gap-5 sm:grid-cols-2">
            <flux:field>
                <flux:label>Status <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="status">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="on_hold">On hold</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </flux:select>
                <flux:error name="status" />
            </flux:field>

            <flux:field>
                <flux:label>Budget <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
                <flux:input wire:model="budget" type="number" min="0" step="0.01" placeholder="50000" />
                <flux:error name="budget" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Deadline <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:input wire:model="deadline" type="date" />
            <flux:error name="deadline" />
        </flux:field>

        <flux:field>
            <flux:label>Tags <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <div class="flex flex-wrap gap-2 mt-1">
                @forelse ($tags as $tag)
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="selectedTags"
                            value="{{ $tag->id }}"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span
                            class="text-xs font-medium px-2 py-0.5 rounded-full"
                            style="background-color: {{ $tag->colour }}22; color: {{ $tag->colour }}"
                        >
                            {{ $tag->name }}
                        </span>
                    </label>
                @empty
                    <p class="text-sm text-gray-500">No tags available yet.</p>
                @endforelse
            </div>
            <flux:error name="selectedTags" />
        </flux:field>

        <div class="flex items-center gap-3 pt-2">
            <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="save">Save project</span>
                <span wire:loading wire:target="save">Saving...</span>
            </flux:button>

            <a
                href="{{ $selectedClientId ? route('clients.show', $selectedClientId) : route('clients.index') }}"
                class="text-sm text-gray-500 hover:text-gray-700"
            >
                Cancel
            </a>
        </div>
    </x-form-card>
</div>
