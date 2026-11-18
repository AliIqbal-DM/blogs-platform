<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Tenant\BlogController;
use App\Http\Controllers\Tenant\PostController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::name('tenant.')->group(function () {
        Route::get('/', [BlogController::class, 'home'])->name('home');
        Route::get('posts/{slug}', [BlogController::class, 'show'])->name('posts.show');

        Route::middleware('guest')->group(function () {
            Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('login', [AuthenticatedSessionController::class, 'store']);
        });

        Route::middleware('auth')->group(function () {
            Route::get('dashboard', fn () => Inertia::render('Tenant/Dashboard'))->name('dashboard');
            Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        });
    });
});
