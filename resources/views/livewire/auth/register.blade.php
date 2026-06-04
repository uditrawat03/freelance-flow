<div class="mx-auto w-full max-w-md">
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-foreground">FreelanceFlow</h1>
        <p class="mt-2 text-sm font-medium text-muted">Create your account</p>
    </div>

    <flux:card class="space-y-5 rounded-lg border border-border bg-surface p-6 shadow-lifted">
        <flux:field>
            <flux:label>Full name</flux:label>
            <flux:input wire:model="name" type="text" placeholder="John Doe" autofocus autocomplete="name" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email address</flux:label>
            <flux:input wire:model="email" type="email" placeholder="you@example.com" autocomplete="email" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input wire:model="password" type="password" placeholder="Min. 8 characters" autocomplete="new-password" />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>Confirm password</flux:label>
            <flux:input wire:model="password_confirmation" type="password" placeholder="Repeat password" autocomplete="new-password" />
            <flux:error name="password_confirmation" />
        </flux:field>

        <flux:button wire:click="register" wire:loading.attr="disabled" variant="primary" class="w-full">
            <span wire:loading.remove>Create account</span>
            <span wire:loading>Creating account...</span>
        </flux:button>
    </flux:card>

    <p class="mt-5 text-center text-sm font-medium text-muted">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-primary hover:text-primary-hover">Sign in</a>
    </p>
</div>
