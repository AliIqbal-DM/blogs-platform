<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Logos live on the central public disk, so this must not run while a tenant's disk is active.
 */
class DeleteTenantLogo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant) {}

    public function handle(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Tenant logos can only be deleted from the central context.');
        }

        if ($this->tenant->logo_path) {
            Storage::disk('public')->delete($this->tenant->logo_path);
        }
    }
}
