<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'FreelanceFlow')</title>

    {{-- Inter font --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Required for Flux dark mode support --}}
    @fluxAppearance
</head>
<body class="min-h-screen font-sans antialiased bg-gray-50">

    @include('partials.navbar')

    <div class="flex pt-16">
        @auth
            @include('partials.sidebar')
        @endauth

        <main class="flex-1 min-h-[calc(100vh-64px)]">
            <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
                {{-- Flash message --}}
                <x-flash-message />
                
                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot ?? '' }}
                @endif
            </div>
        </main>
    </div>

    {{-- Notification toast — listens for dispatch('notify') events --}}
    <livewire:notification />

    @stack('scripts')

    {{-- Required: Flux JS + Livewire assets --}}
    @fluxScripts
</body>
</html>
