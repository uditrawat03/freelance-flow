<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'layouts.inertia';

    public function share(Request $request): array
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->getLocale(),
                ] : null,
            ],
            'current_route' => $request->route()?->getName(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'navigation' => [
                'dashboard' => route('dashboard'),
                'clients' => route('clients.index'),
                'invoices' => route('invoices.index'),
                'settings' => route('settings.index'),
            ],
            'workspace' => $workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ] : null,
        ];
    }
}
