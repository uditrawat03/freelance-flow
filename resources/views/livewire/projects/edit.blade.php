<div>
    <x-page-header title="Edit Project" subtitle="Update {{ $project->name }}.">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('projects.analytics', $project) }}" class="text-sm font-semibold text-primary hover:text-primary-hover">
                View analytics
            </a>
            <a href="{{ route('clients.show', $project->client_id) }}" class="text-sm font-semibold text-muted hover:text-primary">
                &larr; Back to client
            </a>
        </div>
    </x-page-header>

    <x-form-card max-width="max-w-5xl">
        <section class="space-y-5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Project details</h2>
                <p class="mt-1 text-sm text-muted">Keep the client, name, and scope aligned with the latest work.</p>
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
                    <flux:input wire:model.live="name" type="text" placeholder="Website redesign" />
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
                <p class="mt-1 text-sm text-muted">Update lifecycle, budget, deadline, and labels.</p>
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

        <section class="space-y-4 border-t border-border pt-5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Attachments</h2>
                <p class="mt-1 text-sm text-muted">Store supporting files for this project.</p>
            </div>

            @if ($attachments->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($attachments as $attachment)
                        <div class="flex flex-col gap-3 rounded-lg border border-border bg-surface-muted p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <div @class([
                                    'flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md',
                                    match ($attachment->extension) {
                                        'pdf' => 'bg-red-100 text-red-600',
                                        'doc', 'docx' => 'bg-blue-100 text-blue-600',
                                        'xls', 'xlsx' => 'bg-green-100 text-green-600',
                                        'png', 'jpg', 'jpeg', 'gif' => 'bg-purple-100 text-purple-600',
                                        'zip' => 'bg-yellow-100 text-yellow-600',
                                        default => 'bg-surface text-muted',
                                    },
                                ])>
                                    <span class="text-xs font-bold uppercase">{{ $attachment->extension }}</span>
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-foreground">
                                        {{ $attachment->original_name }}
                                    </p>
                                    <p class="text-xs text-muted">
                                        {{ $attachment->formatted_size }}
                                        &middot; {{ $attachment->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-3 sm:ml-3">
                                <a
                                    href="{{ route('attachments.download', $attachment) }}"
                                    class="text-xs font-semibold text-primary hover:text-primary-hover"
                                >
                                    Download
                                </a>

                                <button
                                    type="button"
                                    wire:click="confirmDeleteAttachment({{ $attachment->id }})"
                                    class="text-xs font-semibold text-danger hover:text-red-700"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="rounded-lg border border-dashed border-border bg-surface-muted p-4 text-sm text-muted">
                    No files attached yet.
                </p>
            @endif

            <flux:field>
                <flux:label>Upload file</flux:label>
                <input
                    type="file"
                    wire:model="newFile"
                    dusk="project-file"
                    class="block w-full cursor-pointer rounded-lg border border-border bg-surface text-sm text-muted shadow-soft file:mr-4 file:border-0 file:bg-primary-soft file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-primary hover:file:bg-blue-100"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.zip"
                />
                <flux:error name="newFile" />

                @if ($newFile)
                    <div class="mt-3 flex flex-col gap-3 rounded-lg border border-border bg-surface-muted p-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-muted">
                            Ready to upload: <span class="font-semibold text-secondary">{{ $newFile->getClientOriginalName() }}</span>
                            ({{ round($newFile->getSize() / 1024, 1) }} KB)
                        </p>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                wire:click="$set('newFile', null)"
                                class="text-xs font-semibold text-muted hover:text-secondary"
                            >
                                Cancel
                            </button>
                            <flux:button wire:click="uploadFile" wire:loading.attr="disabled" dusk="upload-project-file" size="sm" variant="primary">
                                <span wire:loading.remove wire:target="uploadFile">Upload</span>
                                <span wire:loading wire:target="uploadFile">Uploading...</span>
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:field>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('clients.show', $project->client_id) }}" class="text-center text-sm font-semibold text-muted hover:text-secondary">
                Cancel
            </a>

            <flux:button wire:click="update" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="update">Save changes</span>
                <span wire:loading wire:target="update">Saving...</span>
            </flux:button>
        </div>
    </x-form-card>

    <flux:modal wire:model="confirmingDelete" class="max-w-sm">
        <div class="space-y-4 p-6">
            <h3 class="text-lg font-semibold text-foreground">Remove file?</h3>
            <p class="text-sm text-muted">
                This file will be permanently deleted from FreelanceFlow.
                This cannot be undone.
            </p>
            <div class="flex flex-col-reverse gap-3 sm:flex-row">
                <flux:button wire:click="$set('confirmingDelete', false)" variant="ghost" class="flex-1">
                    Cancel
                </flux:button>
                <flux:button wire:click="deleteAttachment" wire:loading.attr="disabled" variant="danger" class="flex-1">
                    Yes, remove
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
