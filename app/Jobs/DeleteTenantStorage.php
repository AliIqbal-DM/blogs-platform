<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use LogicException;

/**
 * Removes storage/<suffix_base><tenant id>, the folder FilesystemTenancyBootstrapper gives the tenant.
 * Runs in the central context, after the tenant database is gone, so tenancy cannot be initialized here.
 */
class DeleteTenantStorage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant) {}

    public function handle(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Tenant storage can only be deleted from the central context.');
        }

        $key = (string) $this->tenant->getTenantKey();

        if ($key === '') {
            return;
        }

        File::deleteDirectory(storage_path(config('tenancy.filesystem.suffix_base').$key));
    }
}
