<section class="max-w-3xl space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-foreground">{{ __('app.settings.language') }}</h2>
        <p class="mt-1 text-sm font-medium text-muted">{{ __('app.settings.language_subtitle') }}</p>
    </div>

    <flux:card class="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-sm">
        <flux:field>
            <flux:label>{{ __('app.settings.language') }}</flux:label>
            <flux:select wire:model="locale">
                @foreach ($locales as $code => $name)
                    <option value="{{ $code }}">{{ $name }}</option>
                @endforeach
            </flux:select>
            <flux:error name="locale" />
        </flux:field>

        <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary">
            <span wire:loading.remove wire:target="save">{{ __('app.settings.save_language') }}</span>
            <span wire:loading wire:target="save">{{ __('app.actions.saving') }}</span>
        </flux:button>
    </flux:card>
</section>
