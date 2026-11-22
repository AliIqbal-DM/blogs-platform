<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PostStatus;
use App\Jobs\PublishDuePostsJob;
use App\Models\Post;
use App\Models\Tenant;
use App\Support\HomepageCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

class HomepageCacheTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private const GLOBEX = 'http://globex.blogs.test';

    private Tenant $acme;

    private Tenant $globex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
        $this->globex = app(CreateTenant::class)->handle(id: 'globex', name: 'Globex', domain: 'globex.blogs.test');
    }

    public function test_one_tenants_cached_homepage_is_invisible_to_another_tenant(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->create(['title' => 'Acme cached post']));

        $this->homepage(self::ACME)->assertSee('Acme cached post');
        $acmeKey = $this->acme->run(fn () => HomepageCache::key(1));
        $this->assertNotNull($this->acme->run(fn () => Cache::get($acmeKey)));

        $this->assertNull($this->globex->run(fn () => Cache::get($acmeKey)));
        $this->assertNull($this->globex->run(fn () => Cache::get(HomepageCache::VERSION_KEY)));

        $this->homepage(self::GLOBEX)
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 0)->where('posts.total', 0))
            ->assertDontSee('Acme cached post');
    }

    public function test_the_cache_holds_plain_arrays_for_ten_minutes(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->create(['title' => 'Plain']));

        $this->homepage(self::ACME);

        $cached = $this->acme->run(fn () => Cache::get(HomepageCache::key(1)));
        $this->assertIsArray($cached);
        $this->assertIsArray($cached['data'][0]);
        $this->assertSame('Plain', $cached['data'][0]['title']);
        $this->assertSame(
            ['id', 'title', 'slug', 'excerpt', 'author', 'cover_url', 'published_at'],
            array_keys($cached['data'][0]),
        );

        $this->travel(9)->minutes();
        $this->assertNotNull($this->acme->run(fn () => Cache::get(HomepageCache::key(1))));

        $this->travel(2)->minutes();
        $this->assertNull($this->acme->run(fn () => Cache::get(HomepageCache::key(1))));
    }

    public function test_a_second_homepage_request_does_not_query_posts(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->count(3)->create());

        $this->homepage(self::ACME);

        $postQueries = [];
        DB::listen(function ($query) use (&$postQueries) {
            if (str_contains($query->sql, '`posts`')) {
                $postQueries[] = $query->sql;
            }
        });

        $this->homepage(self::ACME)->assertInertia(fn (Assert $page) => $page->has('posts.data', 3));

        $this->assertSame([], $postQueries);
    }

    public function test_pages_past_the_last_page_are_not_cached(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->create());

        $this->homepage(self::ACME.'/?page=999');

        $this->assertNull($this->acme->run(fn () => Cache::get(HomepageCache::key(999))));
    }

    public function test_creating_a_post_invalidates_only_the_current_tenants_homepage(): void
    {
        $this->assertOnlyAcmeInvalidated(
            fn () => Post::factory()->published()->create(['title' => 'Brand new']),
            seeOnAcme: 'Brand new',
        );
    }

    public function test_updating_a_post_invalidates_only_the_current_tenants_homepage(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->published()->create(['title' => 'Old title'])->id);

        $this->assertOnlyAcmeInvalidated(
            fn () => Post::find($id)->update(['title' => 'New title']),
            seeOnAcme: 'New title',
        );
    }

    public function test_unpublishing_a_post_invalidates_only_the_current_tenants_homepage(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->published()->create(['title' => 'Back to draft'])->id);

        $this->assertOnlyAcmeInvalidated(
            fn () => Post::find($id)->update(['status' => PostStatus::Draft]),
            dontSeeOnAcme: 'Back to draft',
        );
    }

    public function test_deleting_a_post_invalidates_only_the_current_tenants_homepage(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->published()->create(['title' => 'Doomed'])->id);

        $this->assertOnlyAcmeInvalidated(fn () => Post::find($id)->delete(), dontSeeOnAcme: 'Doomed');
    }

    public function test_a_post_saved_through_the_writer_form_invalidates_the_homepage(): void
    {
        $this->homepage(self::ACME);
        $before = $this->acme->run(fn () => Cache::get(HomepageCache::VERSION_KEY));
        $this->assertNotNull($before);

        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', ['title' => 'From the form', 'body' => 'Body', 'status' => 'published'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->acme->run(fn () => Cache::get(HomepageCache::VERSION_KEY)));
        $this->homepage(self::ACME)->assertSee('From the form');
    }

    public function test_scheduled_publishing_invalidates_only_the_current_tenants_homepage(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->create([
            'title' => 'Goes live later',
            'status' => PostStatus::Scheduled,
            'publish_at' => now()->addMinutes(5),
        ])->id);

        $this->travel(10)->minutes();

        $this->assertOnlyAcmeInvalidated(function () {
            PublishDuePostsJob::dispatch();
        }, seeOnAcme: 'Goes live later');

        $this->assertSame(PostStatus::Published, $this->acme->run(fn () => Post::find($id)->status));
    }

    public function test_a_recreated_tenant_starts_with_an_empty_cache(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->create(['title' => 'From the old acme']));
        $this->homepage(self::ACME)->assertSee('From the old acme');
        $this->homepage(self::GLOBEX);

        $oldVersion = $this->acme->run(fn () => Cache::get(HomepageCache::VERSION_KEY));
        $oldKey = $this->acme->run(fn () => HomepageCache::key(1));
        $globexKey = $this->globex->run(fn () => HomepageCache::key(1));

        $this->freshRequestState();
        $this->acme->delete();

        $recreated = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme again', domain: 'acme.blogs.test');

        $this->assertNull($recreated->run(fn () => Cache::get(HomepageCache::VERSION_KEY)));
        $this->assertNull($recreated->run(fn () => Cache::get("homepage_posts:{$oldVersion}:page:1")));
        $this->assertNull($recreated->run(fn () => Cache::get($oldKey)));
        $this->assertNotNull($this->globex->run(fn () => Cache::get($globexKey)), 'Deleting acme must not clear globex.');

        $this->homepage(self::ACME)
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 0))
            ->assertDontSee('From the old acme');
    }

    /**
     * Warm both homepages, run $change inside acme, then check acme's entry is gone and globex's is untouched.
     */
    private function assertOnlyAcmeInvalidated(callable $change, ?string $seeOnAcme = null, ?string $dontSeeOnAcme = null): void
    {
        $this->globex->run(fn () => Post::factory()->published()->create(['title' => 'Globex stays cached']));

        $this->homepage(self::ACME);
        $this->homepage(self::GLOBEX);

        $acmeVersion = $this->acme->run(fn () => Cache::get(HomepageCache::VERSION_KEY));
        $globexKey = $this->globex->run(fn () => HomepageCache::key(1));
        $globexCached = $this->globex->run(fn () => Cache::get($globexKey));
        $this->assertNotNull($acmeVersion);
        $this->assertNotNull($globexCached);

        $this->acme->run($change);

        $this->assertNull($this->acme->run(fn () => Cache::get(HomepageCache::VERSION_KEY)));
        $this->assertNotSame("homepage_posts:{$acmeVersion}:page:1", $this->acme->run(fn () => HomepageCache::key(1)));
        $this->assertSame($globexKey, $this->globex->run(fn () => HomepageCache::key(1)));
        $this->assertSame($globexCached, $this->globex->run(fn () => Cache::get($globexKey)));

        $acme = $this->homepage(self::ACME);
        if ($seeOnAcme !== null) {
            $acme->assertSee($seeOnAcme);
        }
        if ($dontSeeOnAcme !== null) {
            $acme->assertDontSee($dontSeeOnAcme);
        }
    }

    private function homepage(string $url): TestResponse
    {
        $this->freshRequestState();

        return $this->get($url)->assertOk();
    }
}
