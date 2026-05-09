<div>
    <x-page-header title="Edit Project" subtitle="Update {{ $project->name }}.">
        <a href="{{ route('clients.show', $project->client_id) }}" class="text-sm text-gray-500 hover:text-gray-700">
            &larr; Back to client
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
            <flux:input wire:model.live="name" type="text" placeholder="Website redesign" />
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

        <div class="border-t border-gray-100 pt-5">
            <h3 class="text-sm font-medium text-gray-900 mb-3">Attachments</h3>

            @if ($attachments->isNotEmpty())
                <div class="space-y-2 mb-4">
                    @foreach ($attachments as $attachment)
                        <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center gap-3 min-w-0">
                                <div @class([
                                    'w-8 h-8 rounded flex items-center justify-center flex-shrink-0',
                                    match ($attachment->extension) {
                                        'pdf' => 'bg-red-100 text-red-600',
                                        'doc', 'docx' => 'bg-blue-100 text-blue-600',
                                        'xls', 'xlsx' => 'bg-green-100 text-green-600',
                                        'png', 'jpg', 'jpeg', 'gif' => 'bg-purple-100 text-purple-600',
                                        'zip' => 'bg-yellow-100 text-yellow-600',
                                        default => 'bg-gray-100 text-gray-600',
                                    },
                                ])>
                                    <span class="text-xs font-bold uppercase">{{ $attachment->extension }}</span>
                                </div>

                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $attachment->original_name }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        {{ $attachment->formatted_size }}
                                        &middot; {{ $attachment->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 shrink-0 ml-3">
                                <a
                                    href="{{ route('attachments.download', $attachment) }}"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                >
                                    Download
                                </a>

                                <button
                                    type="button"
                                    wire:click="confirmDeleteAttachment({{ $attachment->id }})"
                                    class="text-xs text-red-500 hover:text-red-700"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mb-4 text-sm text-gray-500">No files attached yet.</p>
            @endif

            <flux:field>
                <flux:label>Upload file</flux:label>
                <input
                    type="file"
                    wire:model="newFile"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.zip"
                />
                <flux:error name="newFile" />

                @if ($newFile)
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <p class="text-xs text-gray-600">
                            Ready to upload: <span class="font-medium">{{ $newFile->getClientOriginalName() }}</span>
                            ({{ round($newFile->getSize() / 1024, 1) }} KB)
                        </p>
                        <flux:button wire:click="uploadFile" wire:loading.attr="disabled" size="sm" variant="primary">
                            <span wire:loading.remove wire:target="uploadFile">Upload</span>
                            <span wire:loading wire:target="uploadFile">Uploading...</span>
                        </flux:button>
                        <button
                            type="button"
                            wire:click="$set('newFile', null)"
                            class="text-xs text-gray-400 hover:text-gray-600"
                        >
                            Cancel
                        </button>
                    </div>
                @endif
            </flux:field>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <flux:button wire:click="update" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="update">Save changes</span>
                <span wire:loading wire:target="update">Saving...</span>
            </flux:button>

            <a href="{{ route('clients.show', $project->client_id) }}" class="text-sm text-gray-500 hover:text-gray-700">
                Cancel
            </a>
        </div>
    </x-form-card>

    <flux:modal wire:model="confirmingDelete" class="max-w-sm">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-900">Remove file?</h3>
            <p class="text-sm text-gray-500">
                This file will be permanently deleted from FreelanceFlow.
                This cannot be undone.
            </p>
            <div class="flex items-center gap-3">
                <flux:button wire:click="deleteAttachment" wire:loading.attr="disabled" variant="danger" class="flex-1">
                    Yes, remove
                </flux:button>
                <flux:button wire:click="$set('confirmingDelete', false)" variant="ghost" class="flex-1">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
