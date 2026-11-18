<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The public homepage's post list, cached page by page.
 *
 * Must run inside a tenant: CacheTenancyBootstrapper tags every key with the current tenant,
 * so the same key names never collide between blogs.
 *
 * Page keys include a version token. Invalidating forgets the token, which makes every cached
 * page unreachable at once; a request that was building a page from old data writes it under
 * the old token, where nobody reads it, and it expires.
 */
class HomepageCache
{
    public const VERSION_KEY = 'homepage_posts:version';

    public const TTL_MINUTES = 10;

    public static function key(int $page): string
    {
        $version = Cache::get(static::VERSION_KEY);

        if (! is_string($version)) {
            $version = Str::random(16);
            Cache::forever(static::VERSION_KEY, $version);
        }

        return "homepage_posts:{$version}:page:{$page}";
    }

    /**
     * The cached page, or the result of $build (a paginator array) which is cached when the page exists.
     *
     * @param  Closure(): array{last_page: int}  $build
     */
    public static function posts(int $page, Closure $build): array
    {
        $key = static::key($page);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $posts = $build();

        // Arbitrary ?page= values past the end are not stored, so they cannot fill the cache.
        if ($page <= max(1, $posts['last_page'])) {
            Cache::put($key, $posts, now()->addMinutes(static::TTL_MINUTES));
        }

        return $posts;
    }

    public static function forget(): void
    {
        Cache::forget(static::VERSION_KEY);
    }
}
