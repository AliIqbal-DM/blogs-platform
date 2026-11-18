<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\HomepageCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Runs after the surrounding transaction commits (e.g. Post::publishIfDue()), so a homepage request
 * in between cannot cache the old list again under the new version.
 */
class PostObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Post $post): void
    {
        HomepageCache::forget();
    }

    public function deleted(Post $post): void
    {
        HomepageCache::forget();
    }
}
