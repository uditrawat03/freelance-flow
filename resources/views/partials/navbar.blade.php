<nav class="bg-black  px-6 py-3 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-lg font-bold">
        FreelanceFlow
    </a>

    <div class="flex items-center gap-4">
        @auth
            <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-red-500 transition">
                    Log out
                </button>
            </form>
        @endauth

        @guest
            <a href="{{ route('login') }}" class="text-sm text-white hover:text-blue-600">Log in</a>
            <a href="{{ route('register') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Register</a>
        @endguest
    </div>
</nav>
