<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'FreelanceFlow')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    @include('partials.navbar')

    <div class="flex items-center mt-16 z-0">
        @include('partials.sidebar')

        <main class="px-4 py-8 h-screen w-full">
            @yield('content')
        </main>
    </div>

</body>

</html>