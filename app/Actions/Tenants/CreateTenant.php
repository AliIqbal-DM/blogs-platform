<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateTenant
{
    /**
     * Create the tenant (which creates and migrates its database), then its domain.
     * If the domain cannot be created, the tenant is deleted again, dropping its database.
     */
    public function handle(
        string $id,
        string $name,
        string $domain,
        ?string $brandColor = null,
        ?UploadedFile $logo = null,
    ): Tenant {
        $logoPath = $logo?->store('logos', 'public');

        $attributes = array_filter([
            'id' => $id,
            'name' => $name,
            'brand_color' => $brandColor,
            'logo_path' => $logoPath,
        ], fn ($value) => $value !== null);

        try {
            $tenant = Tenant::create($attributes);
        } catch (Throwable $e) {
            $this->deleteLogo($logoPath);

            throw $e;
        }

        try {
            $tenant->domains()->create(['domain' => $domain]);
        } catch (Throwable $e) {
            $tenant->delete();
            $this->deleteLogo($logoPath);

            throw $e;
        }

        return $tenant;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
