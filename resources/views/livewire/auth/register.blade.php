<div class="w-full max-w-sm mx-auto">

    {{-- Brand --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold">FreelanceFlow</h1>
        <p class="mt-1 text-sm text-gray-500">Create your account</p>
    </div>

    {{-- Card --}}
    <flux:card class="p-6 space-y-5">

        {{-- Name --}}
        <flux:field>
            <flux:label>Full name</flux:label>
            <flux:input wire:model="name" type="text" placeholder="John Doe" autofocus autocomplete="name" />
            <flux:error name="name" />
        </flux:field>

        {{-- Email --}}
        <flux:field>
            <flux:label>Email address</flux:label>
            <flux:input wire:model="email" type="email" placeholder="you@example.com" autocomplete="email" />
            <flux:error name="email" />
        </flux:field>

        {{-- Password --}}
        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input wire:model="password" type="password" placeholder="Min. 8 characters"
                autocomplete="new-password" />
            <flux:error name="password" />
        </flux:field>

        {{-- Confirm Password --}}
        <flux:field>
            <flux:label>Confirm password</flux:label>
            <flux:input wire:model="password_confirmation" type="password" placeholder="Repeat password"
                autocomplete="new-password" />
            <flux:error name="password_confirmation" />
        </flux:field>

        {{-- Submit --}}
        <flux:button wire:click="register" wire:loading.attr="disabled" variant="primary" class="w-full">
            <span wire:loading.remove>Create account</span>
            <span wire:loading>Creating account...</span>
        </flux:button>

    </flux:card>

    {{-- Login link --}}
    <p class="mt-4 text-center text-sm text-gray-500">
        Already have an account?
        <a href="{{ route('login') }}" class="text-indigo-600 hover:underline font-medium">Sign in</a>
    </p>

</div>