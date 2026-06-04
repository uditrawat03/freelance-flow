<div class="mx-auto w-full max-w-md">
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-foreground">FreelanceFlow</h1>
        <p class="mt-2 text-sm font-medium text-muted">Sign in to your account</p>
    </div>

    <flux:card class="space-y-5 rounded-lg border border-border bg-surface p-6 shadow-lifted">
        <flux:field>
            <flux:label>Email address</flux:label>
            <flux:input
                wire:model="email"
                type="email"
                placeholder="you@example.com"
                autofocus
                autocomplete="email"
            />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <div class="flex items-center justify-between gap-3">
                <flux:label>Password</flux:label>
                <a href="#" class="text-xs font-semibold text-primary hover:text-primary-hover">Forgot password?</a>
            </div>
            <flux:input
                wire:model="password"
                type="password"
                placeholder="Password"
                autocomplete="current-password"
            />
            <flux:error name="password" />
        </flux:field>

        <label for="remember" class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-secondary">
            <flux:checkbox wire:model="remember" id="remember" />
            <span>Remember me</span>
        </label>

        <flux:button wire:click="login" wire:loading.attr="disabled" variant="primary" class="w-full">
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in...</span>
        </flux:button>
    </flux:card>

    <p class="mt-5 text-center text-sm font-medium text-muted">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-semibold text-primary hover:text-primary-hover">Create one</a>
    </p>
</div>
