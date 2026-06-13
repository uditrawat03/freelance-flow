<div class="mx-auto w-full max-w-md">
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-foreground">Two-factor authentication</h1>
        <p class="mt-2 text-sm font-medium text-muted">
            {{ $usingRecoveryCode ? 'Enter one of your recovery codes.' : 'Enter the 6-digit code from your authenticator app.' }}
        </p>
    </div>

    <flux:card class="space-y-5 rounded-lg border border-border bg-surface p-6 shadow-lifted">
        <flux:field>
            <flux:label>{{ $usingRecoveryCode ? 'Recovery code' : 'Authentication code' }}</flux:label>
            <flux:input
                wire:model="code"
                wire:keydown.enter="verify"
                type="{{ $usingRecoveryCode ? 'text' : 'tel' }}"
                placeholder="{{ $usingRecoveryCode ? 'XXXXX-XXXXX' : '000000' }}"
                autocomplete="one-time-code"
                inputmode="{{ $usingRecoveryCode ? 'text' : 'numeric' }}"
                autofocus
            />
            <flux:error name="code" />
        </flux:field>

        <flux:button wire:click="verify" wire:loading.attr="disabled" variant="primary" class="w-full">
            <span wire:loading.remove>Verify</span>
            <span wire:loading>Verifying...</span>
        </flux:button>

        <button type="button" wire:click="toggleRecoveryCode" class="w-full text-center text-sm font-semibold text-primary hover:text-primary-hover">
            {{ $usingRecoveryCode ? 'Use an authenticator code' : 'Use a recovery code' }}
        </button>
    </flux:card>

    <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-muted hover:text-secondary">
            Sign out and try again
        </button>
    </form>
</div>
