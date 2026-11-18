<?php

namespace App\Http\Middleware;

use App\Support\BrandColor;
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
     * theme.brand is the brand color or the default, theme.brand_foreground the readable text color on it.
     *
     * @return array{id: string, name: ?string, brand_color: ?string, logo_url: ?string, theme: array{brand: string, brand_foreground: string}}|null
     */
    private function tenant(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();
        $brand = BrandColor::orDefault($tenant->brand_color);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'brand_color' => BrandColor::valid($tenant->brand_color),
            'logo_url' => $tenant->logo_url,
            'theme' => [
                'brand' => $brand,
                'brand_foreground' => BrandColor::foreground($brand),
            ],
        ];
    }
}
