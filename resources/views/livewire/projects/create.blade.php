<div>
    <x-page-header title="Add Project" subtitle="Create a project and connect it to a client.">
        <a href="{{ route('clients.index') }}" class="text-sm font-semibold text-muted hover:text-primary">
            &larr; Back to clients
        </a>
    </x-page-header>

    <x-form-card max-width="max-w-5xl">
        <section class="space-y-5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Project details</h2>
                <p class="mt-1 text-sm text-muted">Name the work, choose the client, and describe the scope.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <flux:field>
                    <flux:label>Client <span class="text-danger">*</span></flux:label>
                    <flux:select wire:model="selectedClientId">
                        <option value="">Select a client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selectedClientId" />
                </flux:field>

                <flux:field>
                    <flux:label>Project name <span class="text-danger">*</span></flux:label>
                    <flux:input wire:model.live="name" type="text" placeholder="Website redesign" autofocus />
                    <flux:error name="name" />
                </flux:field>

                <flux:field class="lg:col-span-2">
                    <flux:label>Description <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                    <flux:textarea wire:model="description" placeholder="Scope, goals, or notes for this project..." rows="4" />
                    <flux:error name="description" />
                </flux:field>
            </div>
        </section>

        <section class="space-y-5 border-t border-border pt-5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Planning</h2>
                <p class="mt-1 text-sm text-muted">Track status, commercial value, deadline, and project labels.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <flux:field>
                    <flux:label>Status <span class="text-danger">*</span></flux:label>
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
                    <flux:label>Budget <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                    <flux:input wire:model="budget" type="number" min="0" step="0.01" placeholder="50000" />
                    <flux:error name="budget" />
                </flux:field>

                <flux:field>
                    <flux:label>Deadline <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                    <flux:input wire:model="deadline" type="date" />
                    <flux:error name="deadline" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Tags <span class="text-muted text-xs font-normal">(optional)</span></flux:label>
                <div class="mt-2 flex flex-wrap gap-2 rounded-lg border border-border bg-surface-muted p-3">
                    @forelse ($tags as $tag)
                        <label class="group inline-flex cursor-pointer items-center gap-2 rounded-md border border-border bg-surface px-2.5 py-2 shadow-soft transition hover:border-primary">
                            <input
                                type="checkbox"
                                wire:model="selectedTags"
                                value="{{ $tag->id }}"
                                class="h-4 w-4 rounded border-border text-primary focus:ring-primary/15"
                            />
                            <span
                                class="rounded-md px-2 py-0.5 text-xs font-semibold"
                                style="background-color: {{ $tag->colour }}22; color: {{ $tag->colour }}"
                            >
                                {{ $tag->name }}
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-muted">No tags available yet.</p>
                    @endforelse
                </div>
                <flux:error name="selectedTags" />
            </flux:field>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-end">
            <a
                href="{{ $selectedClientId ? route('clients.show', $selectedClientId) : route('clients.index') }}"
                class="text-center text-sm font-semibold text-muted hover:text-secondary"
            >
                Cancel
            </a>

            <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="save">Save project</span>
                <span wire:loading wire:target="save">Saving...</span>
            </flux:button>
        </div>
    </x-form-card>
</div>
