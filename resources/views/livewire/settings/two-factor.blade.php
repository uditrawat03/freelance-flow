<section class="max-w-3xl space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Two-factor authentication</h2>
            <p class="mt-1 text-sm font-medium text-muted">Protect your account with a time-based code and recovery codes.</p>
        </div>

        <x-badge :variant="$user->hasTwoFactorEnabled() ? 'success' : 'default'">
            {{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Disabled' }}
        </x-badge>
    </div>

    @if ($step === 'idle')
        <flux:card class="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-sm">
            <p class="text-sm text-muted">
                Use an authenticator app such as Google Authenticator, 1Password, Authy, or Microsoft Authenticator.
            </p>

            <flux:button wire:click="beginEnable" variant="primary">
                Enable two-factor authentication
            </flux:button>
        </flux:card>
    @endif

    @if ($step === 'confirming_enable')
        <flux:card class="space-y-6 rounded-lg border border-border bg-surface p-6 shadow-sm">
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-foreground">Scan the QR code</h3>
                <div class="inline-flex rounded-lg border border-border bg-white p-4">
                    {!! $qrCodeSvg !!}
                </div>

                @if ($secret)
                    <p class="max-w-xl text-sm text-muted">
                        Can't scan it? Enter this setup key manually:
                        <code class="rounded bg-surface-muted px-2 py-1 font-mono text-xs text-secondary">{{ $secret }}</code>
                    </p>
                @endif
            </div>

            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-foreground">Confirm setup</h3>
                <flux:field>
                    <flux:label>Authentication code</flux:label>
                    <flux:input
                        wire:model="verificationCode"
                        wire:keydown.enter="confirmEnable"
                        type="tel"
                        placeholder="000000"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                    />
                    <flux:error name="verificationCode" />
                </flux:field>
            </div>

            <div class="flex flex-wrap gap-3">
                <flux:button wire:click="confirmEnable" wire:loading.attr="disabled" variant="primary">
                    Confirm and enable
                </flux:button>
                <flux:button wire:click="cancelEnable" variant="ghost">
                    Cancel
                </flux:button>
            </div>
        </flux:card>
    @endif

    @if ($step === 'enabled')
        <flux:card class="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-foreground">Recovery codes</h3>
                    <p class="mt-1 text-sm text-muted">Each code works once if you lose access to your authenticator app.</p>
                </div>

                <flux:button wire:click="regenerateRecoveryCodes" variant="ghost" size="sm">
                    Regenerate
                </flux:button>
            </div>

            @if ($showRecoveryCodes && count($recoveryCodes))
                <div class="grid gap-2 rounded-lg border border-border bg-surface-muted p-4 sm:grid-cols-2">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <code class="font-mono text-sm font-semibold text-secondary">{{ $recoveryCode }}</code>
                    @endforeach
                </div>
                <p class="text-xs font-semibold text-warning">Save these codes now. They are hidden by default after you leave this page.</p>
            @else
                <flux:button wire:click="$set('showRecoveryCodes', true)" variant="ghost">
                    Show recovery codes
                </flux:button>
            @endif
        </flux:card>

        <flux:card class="space-y-4 rounded-lg border border-danger/20 bg-surface p-6 shadow-sm">
            <div>
                <h3 class="text-sm font-semibold text-foreground">Disable two-factor authentication</h3>
                <p class="mt-1 text-sm text-muted">Enter your password to remove the second factor from your account.</p>
            </div>

            <flux:field>
                <flux:label>Current password</flux:label>
                <flux:input wire:model="confirmPassword" type="password" autocomplete="current-password" />
                <flux:error name="confirmPassword" />
            </flux:field>

            <flux:button wire:click="disable" wire:loading.attr="disabled" variant="danger">
                Disable two-factor authentication
            </flux:button>
        </flux:card>
    @endif
</section>
