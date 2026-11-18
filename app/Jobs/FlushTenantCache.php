<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Removes every cache entry of the tenant, so a tenant recreated with the same id starts empty.
 * Runs while the tenant is being deleted: run() needs the tenant database to still exist.
 */
class FlushTenantCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            // Inside a tenant, the package's CacheManager turns this into a flush of the
            // tenant's own tag (tag_base + id). Outside a tenant it would clear the whole store.
            Cache::flush();
        });
    }
}
