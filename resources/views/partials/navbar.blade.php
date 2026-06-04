<nav x-data="{ userMenuOpen: false }" @click.away="userMenuOpen = false" class="fixed top-0 left-0 right-0 z-40 border-b border-border bg-surface/95 px-4 py-3 shadow-soft backdrop-blur">
    <div class="max-w-7xl mx-auto flex items-center gap-4">
        <div class="flex items-center gap-4">
            <button @click="$dispatch('toggle-sidebar')" class="rounded-lg p-2 text-muted transition hover:bg-surface-muted hover:text-secondary lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-lg font-semibold text-foreground">
                <svg class="w-6 h-6 text-primary" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                </svg>
                <span class="hidden sm:inline">FreelanceFlow</span>
            </a>
        </div>

        <div class="flex-1 flex items-center justify-center hidden md:flex">
            <div class="w-full max-w-lg">
                <label class="sr-only">Search</label>
                <div class="relative">
                    <input type="search" placeholder="Search clients, projects, invoices..."
                           class="marketplace-field py-2 pl-4 pr-10" />
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 text-muted">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            @auth
                {{-- Workspace switcher --}}
                <div class="hidden sm:block">
                    <livewire:workspace-switcher />
                </div>

                {{-- Notification bell --}}
                <div class="hidden sm:block">
                    <livewire:notification-bell />
                </div>

                {{-- User menu --}}
                <div class="relative">
                    <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2 rounded-lg px-3 py-2 transition-colors hover:bg-surface-muted">
                        <img src="{{ auth()->user()->avatar_url ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode(auth()->user()->name) }}" alt="avatar" class="w-8 h-8 rounded-full" />
                        <span class="hidden text-sm font-semibold text-secondary sm:inline">{{ auth()->user()->name }}</span>
                        <svg class="hidden w-4 h-4 text-muted sm:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                    </button>

                    {{-- Profile dropdown --}}
                    <div x-show="userMenuOpen"
                         x-transition
                         class="absolute right-0 z-50 mt-2 w-56 rounded-lg border border-border bg-surface shadow-lifted">
                        <div class="border-b border-border px-4 py-3">
                            <p class="text-sm font-semibold text-foreground">{{ auth()->user()->name }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ auth()->user()->email }}</p>
                        </div>
                        
                        <div class="py-1">
                            <a href="#" class="flex items-center gap-3 px-4 py-2 text-sm font-medium text-secondary transition-colors hover:bg-surface-muted">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Profile</span>
                            </a>
                            <a href="#" class="flex items-center gap-3 px-4 py-2 text-sm font-medium text-secondary transition-colors hover:bg-surface-muted">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>Settings</span>
                            </a>
                        </div>

                        <div class="border-t border-border py-1">
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-danger transition-colors hover:bg-red-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span>Log out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endauth

            @guest
                <a href="{{ route('login') }}" class="text-sm font-semibold text-secondary hover:text-primary">Log in</a>
                <a href="{{ route('register') }}" class="text-sm font-semibold text-primary hover:text-primary-hover">Register</a>
            @endguest
        </div>
    </div>
</nav>
