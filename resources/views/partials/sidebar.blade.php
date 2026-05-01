<aside class="w-72 py-8 shadow h-screen text-black">
    <ul class="flex flex-col px-4 gap-4">
        <li class="{{ request()->routeIs('clients.*') ? 'active' : '' }} hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white">
            <a href="{{ route('clients.index') }}">Clients</a>
        </li>
        <li class="{{ request()->routeIs('projects.*') ? 'active' : '' }} hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white">
            <a href="#">Projects</a>
        </li>
        <li class="hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white"><a href="#">Invoices</a></li>
        <li class="hover:bg-blue-300 px-2 py-1 rounded-lg hover:text-white"><a href="#">Dashboard</a></li>
    </ul>
</aside>