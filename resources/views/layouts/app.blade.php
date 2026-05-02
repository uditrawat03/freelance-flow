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
<body class="min-h-screen font-sans antialiased">

    @include('partials.navbar')

    <div class="flex mt-16">
        @auth
            @include('partials.sidebar')
        @endauth


        <main class="flex-1 p-6">
            @hasSection('content')
                @yield('content')
            @else
                {{ $slot ?? '' }}
            @endif
        </main>
    </div>

    {{-- Required: Flux JS + Livewire assets --}}
    @fluxScripts
</body>
</html>
