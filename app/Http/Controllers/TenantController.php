<?php

namespace App\Http\Controllers;

use App\Actions\Tenants\CreateTenant;
use App\Http\Requests\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::with('domains')
            ->latest()
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'logo_url' => $tenant->logo_url,
                'brand_color' => $tenant->brand_color,
                'domains' => $tenant->domains->pluck('domain'),
                'created_at' => $tenant->created_at?->toDateString(),
            ]);

        return Inertia::render('Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenants/Create', [
            'defaultBrandColor' => '#4f46e5',
        ]);
    }

    public function store(StoreTenantRequest $request, CreateTenant $createTenant): RedirectResponse
    {
        $tenant = $createTenant->handle(
            id: $request->tenantId(),
            name: $request->validated('name'),
            domain: $request->validated('domain'),
            brandColor: $request->validated('brand_color'),
            logo: $request->file('logo'),
        );

        return redirect()
            ->route('tenants.index')
            ->with('success', "Blog \"{$tenant->name}\" created.");
    }
}
