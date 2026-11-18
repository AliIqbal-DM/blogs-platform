<?php

namespace App\Console\Commands;

use App\Jobs\PublishDuePostsJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Queue a job per tenant that publishes its scheduled posts once their time has come';

    public function handle(): int
    {
        $count = 0;

        foreach (Tenant::query()->lazyById(100) as $tenant) {
            // Not an arrow function: a returned PendingDispatch would only queue the job
            // after run() has ended tenancy, so the payload would carry no tenant id.
            $tenant->run(function () {
                PublishDuePostsJob::dispatch();
            });

            $this->line("Queued publishing for tenant [{$tenant->getTenantKey()}].", verbosity: 'v');
            $count++;
        }

        $this->info("Queued publishing for {$count} tenant(s).");

        return self::SUCCESS;
    }
}
