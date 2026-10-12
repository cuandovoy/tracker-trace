<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => fn () => ['user' => $request->user()?->only('id', 'name', 'email', 'role'), 'company' => $request->user()?->company?->only('id', 'name', 'timezone')],
            'flash' => fn () => ['success' => $request->session()->get('success'), 'warning' => $request->session()->get('warning')],
        ];
    }
}
