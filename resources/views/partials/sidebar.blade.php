<aside x-data="{ sidebarOpen: false }" 
       @toggle-sidebar.window="sidebarOpen = ! sidebarOpen"
       class="hidden lg:flex lg:flex-col w-72 h-screen sticky top-16 border-r border-border bg-surface">
    <div class="flex flex-col gap-6 py-6 px-4 overflow-y-auto flex-1">
        {{-- Workspace section --}}
        <div class="px-2">
            <div class="text-xs text-muted font-semibold uppercase tracking-wide mb-3">Workspace</div>
            <livewire:workspace-switcher />
        </div>

        {{-- Main navigation --}}
        <nav class="space-y-1">
            <a href="{{ route('dashboard') }}" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                   {{ request()->routeIs('dashboard') 
                       ? 'bg-primary-soft text-primary-hover shadow-sm' 
                       : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('clients.index') }}" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                   {{ request()->routeIs('clients.*') 
                       ? 'bg-primary-soft text-primary-hover shadow-sm' 
                       : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Clients</span>
            </a>

            <a href="{{ route('projects.create') }}" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                   {{ request()->routeIs('projects.*') 
                       ? 'bg-primary-soft text-primary-hover shadow-sm' 
                       : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 10.26 24 12.52 18 18.77 19.54 28 12 24.27 4.46 28 6 18.77 0 12.52 8.91 10.26 12 2"></polygon>
                </svg>
                <span>Projects</span>
            </a>

            <a href="{{ route('invoices.index') }}" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                   {{ request()->routeIs('invoices.*') 
                       ? 'bg-primary-soft text-primary-hover shadow-sm' 
                       : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                    <polyline points="13 2 13 9 20 9"></polyline>
                    <line x1="9" y1="14" x2="15" y2="14"></line>
                    <line x1="9" y1="18" x2="15" y2="18"></line>
                </svg>
                <span>Invoices</span>
            </a>
        </nav>

        {{-- Divider --}}
        <div class="border-t border-border"></div>

        {{-- Secondary navigation --}}
        <nav class="space-y-1">
            <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-muted hover:text-secondary hover:bg-surface-muted transition-all duration-200">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="12" cy="5" r="1"></circle>
                    <circle cx="12" cy="19" r="1"></circle>
                </svg>
                <span>Reports</span>
            </button>

            <a href="#" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-muted hover:text-secondary hover:bg-surface-muted transition-all duration-200">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                <span>Documentation</span>
            </a>
        </nav>

        {{-- Settings section at bottom --}}
        <div class="mt-auto pt-6 border-t border-border">
            <a href="#" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-muted hover:text-secondary hover:bg-surface-muted transition-all duration-200">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m3.08 3.08l4.24 4.24M1 12h6m6 0h6m-1.78 7.78l-4.24-4.24m-3.08-3.08l-4.24-4.24"></path>
                </svg>
                <span>Settings</span>
            </a>

            <a href="#" 
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-muted hover:text-secondary hover:bg-surface-muted transition-all duration-200">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 6v6l4 2"></path>
                </svg>
                <span>Help & Support</span>
            </a>
        </div>
    </div>
</aside>

{{-- Mobile sidebar drawer --}}
<div x-data="{ sidebarOpen: false }" 
     @toggle-sidebar.window="sidebarOpen = !sidebarOpen"
     class="lg:hidden">
    {{-- Backdrop --}}
    <div x-show="sidebarOpen"
         @click="sidebarOpen = false"
         x-transition
         class="fixed inset-0 bg-black/50 z-30"
         style="display: none;"></div>

    {{-- Drawer --}}
    <aside x-show="sidebarOpen"
           x-transition:enter="transition ease-out duration-200"
           x-transition:leave="transition ease-in duration-200"
           class="fixed left-0 top-16 h-[calc(100vh-64px)] w-72 bg-surface border-r border-border z-40 overflow-y-auto"
           style="display: none;">
        <div class="flex flex-col gap-6 py-6 px-4">
            {{-- Workspace section --}}
            <div class="px-2">
                <div class="text-xs text-muted font-semibold uppercase tracking-wide mb-3">Workspace</div>
                <livewire:workspace-switcher />
            </div>

            {{-- Main navigation --}}
            <nav class="space-y-1">
                <a href="{{ route('dashboard') }}" 
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                       {{ request()->routeIs('dashboard') 
                           ? 'bg-primary-soft text-primary-hover shadow-sm' 
                           : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('clients.index') }}" 
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                       {{ request()->routeIs('clients.*') 
                           ? 'bg-primary-soft text-primary-hover shadow-sm' 
                           : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Clients</span>
                </a>

                <a href="{{ route('projects.create') }}" 
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                       {{ request()->routeIs('projects.*') 
                           ? 'bg-primary-soft text-primary-hover shadow-sm' 
                           : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 10.26 24 12.52 18 18.77 19.54 28 12 24.27 4.46 28 6 18.77 0 12.52 8.91 10.26 12 2"></polygon>
                    </svg>
                    <span>Projects</span>
                </a>

                <a href="{{ route('invoices.index') }}" 
                   @click="sidebarOpen = false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                       {{ request()->routeIs('invoices.*') 
                           ? 'bg-primary-soft text-primary-hover shadow-sm' 
                           : 'text-muted hover:text-secondary hover:bg-surface-muted' }}">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                        <polyline points="13 2 13 9 20 9"></polyline>
                        <line x1="9" y1="14" x2="15" y2="14"></line>
                        <line x1="9" y1="18" x2="15" y2="18"></line>
                    </svg>
                    <span>Invoices</span>
                </a>
            </nav>

            {{-- Divider --}}
            <div class="border-t border-border"></div>

            {{-- Secondary navigation --}}
            <nav class="space-y-1">
                <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-muted hover:text-secondary hover:bg-surface-muted transition-all duration-200">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="12" cy="5" r="1"></circle>
                        <circle cx="12" cy="19" r="1"></circle>
                    </svg>
                    <span>Reports</span>
                </button>

                <a href="#" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-muted hover:text-secondary hover:bg-surface-muted transition-all duration-200">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span>Documentation</span>
                </a>
            </nav>
        </div>
    </aside>
</div>
