<div class="relative" x-data>

    {{-- Bell button with unread badge --}}
    <button wire:click="toggleOpen" dusk="notification-bell" class="relative p-2 text-gray-500 hover:text-gray-700 transition-colors"
        aria-label="Notifications">
        {{-- Bell icon --}}
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        {{-- Unread count badge --}}
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white
                             text-xs font-bold rounded-full flex items-center justify-center"
                dusk="notification-count">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Notification dropdown panel --}}
    @if ($open)
        <div class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50"
            wire:click.outside="$set('open', false)">
            {{-- Panel header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                @if ($notifications->isNotEmpty())
                    <button wire:click="clearAll" class="text-xs text-gray-400 hover:text-gray-600">
                        Clear all
                    </button>
                @endif
            </div>

            {{-- Notification list --}}
            <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                @forelse ($notifications as $notification)
                    @php $data = $notification->data; @endphp
                    <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors
                                        {{ is_null($notification->read_at) ? 'bg-indigo-50/40' : '' }}">

                        {{-- Status indicator dot --}}
                        <div class="flex-shrink-0 mt-1">
                            @if (is_null($notification->read_at))
                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                            @else
                                <div class="w-2 h-2 rounded-full bg-gray-200"></div>
                            @endif
                        </div>

                        {{-- Notification content --}}
                        <div class="flex-1 min-w-0">
                            <a href="{{ $data['url'] ?? '#' }}"
                                class="text-sm text-gray-900 hover:text-indigo-600 line-clamp-2 leading-snug">
                                <strong>{{ $data['project_name'] ?? 'A project' }}</strong>
                                status changed to
                                <strong>{{ $data['status_label'] ?? $data['new_status'] }}</strong>
                            </a>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>

                        {{-- Dismiss button --}}
                        <button wire:click="dismiss('{{ $notification->id }}')"
                            class="flex-shrink-0 text-gray-300 hover:text-gray-500 mt-0.5" aria-label="Dismiss">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        </button>

                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <p class="text-sm text-gray-400">No notifications</p>
                    </div>
                @endforelse
            </div>

        </div>
    @endif

</div>
