<flux:field>
    <flux:label>Tags <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
    <div class="flex flex-wrap gap-2 mt-1">
        @foreach ($tags as $tag)
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="checkbox" wire:model="selectedTags" value="{{ $tag->id }}"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                    style="background-color: {{ $tag->colour }}22; color: {{ $tag->colour }}">
                    {{ $tag->name }}
                </span>
            </label>
        @endforeach
    </div>
    <flux:error name="selectedTags" />
</flux:field>