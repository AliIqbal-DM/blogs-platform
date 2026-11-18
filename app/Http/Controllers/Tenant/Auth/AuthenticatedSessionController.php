<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Auth\AuthenticatedSessionController as BaseAuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Writer login on a tenant domain. Same flow as Breeze, but with tenant pages and tenant.* routes.
 */
class AuthenticatedSessionController extends BaseAuthenticatedSessionController
{
    public function create(): Response
    {
        return Inertia::render('Tenant/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('tenant.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        parent::destroy($request);

        return redirect()->route('tenant.login');
    }
}
