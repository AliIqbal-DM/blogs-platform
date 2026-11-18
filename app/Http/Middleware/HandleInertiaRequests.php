<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'tenant' => fn () => $this->tenant(),
        ];
    }

    /**
     * @return array{id: string, name: ?string, brand_color: ?string, logo_url: ?string}|null
     */
    private function tenant(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();
        $brandColor = $tenant->brand_color;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'brand_color' => is_string($brandColor) && preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor) ? $brandColor : null,
            'logo_url' => $tenant->logo_url,
        ];
    }
}
