<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LogicException;

/**
 * Publishes the current tenant's due scheduled posts.
 * Dispatch it inside $tenant->run(): QueueTenancyBootstrapper puts the tenant id in the payload
 * and initializes that tenant again before the worker runs the job.
 */
class PublishDuePostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Due posts can only be published inside a tenant.');
        }

        Post::due()->orderBy('publish_at')->pluck('id')->each(fn (int $id) => Post::publishIfDue($id));
    }
}
