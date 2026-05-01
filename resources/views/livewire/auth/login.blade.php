<div class="w-full max-w-sm mx-auto">

    {{-- Logo / Brand --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold ">FreelanceFlow</h1>
        <p class="mt-1 text-sm text-gray-500">Sign in to your account</p>
    </div>

    {{-- Card --}}
    <flux:card class="p-6 space-y-5">

        {{-- Email --}}
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

        {{-- Password --}}
        <flux:field>
            <div class="flex items-center justify-between">
                <flux:label>Password</flux:label>
                <a href="#" class="text-xs text-indigo-600 hover:underline">Forgot password?</a>
            </div>
            <flux:input
                wire:model="password"
                type="password"
                placeholder="••••••••"
                autocomplete="current-password"
            />
            <flux:error name="password" />
        </flux:field>

        {{-- Remember me --}}
        <div class="flex items-center gap-2">
            <flux:checkbox wire:model="remember" id="remember" />
            <flux:label for="remember" class="text-sm font-normal">Remember me</flux:label>
        </div>

        {{-- Submit --}}
        <flux:button wire:click="login" wire:loading.attr="disabled" variant="primary" class="w-full">
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in...</span>
        </flux:button>

    </flux:card>

    {{-- Register link --}}
    <p class="mt-4 text-center text-sm text-gray-500">
        Don't have an account?
        <a href="{{ route('register') }}" class="text-indigo-600 hover:underline font-medium">Create one</a>
    </p>

</div>
