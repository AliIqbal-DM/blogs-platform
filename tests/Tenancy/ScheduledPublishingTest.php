<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PostStatus;
use App\Jobs\PublishDuePostsJob;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class ScheduledPublishingTest extends TenantTestCase
{
    private Tenant $acme;

    private Tenant $globex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
        $this->globex = app(CreateTenant::class)->handle(id: 'globex', name: 'Globex', domain: 'globex.blogs.test');
    }

    public function test_command_publishes_due_posts_in_every_tenant_and_leaves_the_rest_alone(): void
    {
        $acme = $this->acme->run(fn () => [
            'due' => $this->scheduledIn(minutes: 5),
            'future' => $this->scheduledIn(minutes: 60 * 24),
            'draft' => Post::factory()->draft()->create()->id,
        ]);
        $globex = $this->globex->run(fn () => [
            'due' => $this->scheduledIn(minutes: 2),
            'future' => $this->scheduledIn(minutes: 60 * 24),
        ]);

        $this->travel(10)->minutes();

        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $this->acme->run(function () use ($acme) {
            $this->assertPublishedAtScheduledTime($acme['due']);
            $this->assertSame(PostStatus::Scheduled, Post::find($acme['future'])->status);
            $this->assertNull(Post::find($acme['future'])->published_at);
            $this->assertSame(PostStatus::Draft, Post::find($acme['draft'])->status);
            $this->assertNull(Post::find($acme['draft'])->published_at);
        });

        $this->globex->run(function () use ($globex) {
            $this->assertPublishedAtScheduledTime($globex['due']);
            $this->assertSame(PostStatus::Scheduled, Post::find($globex['future'])->status);
        });
    }

    public function test_a_due_post_in_one_tenant_is_published_without_touching_another_tenant(): void
    {
        $acmeDue = $this->acme->run(fn () => $this->scheduledIn(minutes: 5));
        $this->globex->run(function () {
            $this->scheduledIn(minutes: 60);
            Post::factory()->published()->create();
            Post::factory()->draft()->create();
        });
        $globexBefore = $this->globex->run(fn () => Post::orderBy('id')->get()->toArray());

        $this->travel(10)->minutes();

        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $this->acme->run(fn () => $this->assertPublishedAtScheduledTime($acmeDue));
        $this->assertSame($globexBefore, $this->globex->run(fn () => Post::orderBy('id')->get()->toArray()));
    }

    public function test_each_tenants_job_only_changes_its_own_database(): void
    {
        $acmeDue = $this->acme->run(fn () => $this->scheduledIn(minutes: 5));
        $globexDue = $this->globex->run(fn () => $this->scheduledIn(minutes: 5));

        $this->travel(10)->minutes();

        $this->acme->run(function () {
            PublishDuePostsJob::dispatch();
        });

        $this->acme->run(fn () => $this->assertPublishedAtScheduledTime($acmeDue));
        $this->globex->run(function () use ($globexDue) {
            $this->assertSame(PostStatus::Scheduled, Post::find($globexDue)->status);
            $this->assertNull(Post::find($globexDue)->published_at);
        });
    }

    public function test_a_queued_job_runs_in_its_tenant_when_a_worker_picks_it_up(): void
    {
        config(['queue.default' => 'database']);
        $this->assertSame(config('tenancy.database.central_connection'), config('queue.connections.database.connection'));
        $acmeDue = $this->acme->run(fn () => $this->scheduledIn(minutes: 5));
        $globexDue = $this->globex->run(fn () => $this->scheduledIn(minutes: 5));

        $this->travel(10)->minutes();

        $this->globex->run(function () {
            PublishDuePostsJob::dispatch();
        });

        // Dispatching inside the tenant wrote to the central jobs table, not the tenant database.
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(1, DB::connection('mysql')->table('jobs')->count());
        $this->assertFalse($this->globex->run(fn () => Schema::hasTable('jobs')));
        $payload = json_decode(DB::connection('mysql')->table('jobs')->value('payload'), true);
        $this->assertSame('globex', $payload['tenant_id']);
        $this->assertSame(PublishDuePostsJob::class, $payload['displayName']);

        // Nothing ran inline.
        $this->assertSame(PostStatus::Scheduled, $this->globex->run(fn () => Post::find($globexDue)->status));

        $tenantDuringJob = 'not run';
        Event::listen(JobProcessing::class, function () use (&$tenantDuringJob) {
            $tenantDuringJob = tenant()?->getTenantKey();
        });

        $this->assertFalse(tenancy()->initialized);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true])
            ->assertSuccessful();

        $this->assertSame('globex', $tenantDuringJob);
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(0, DB::connection('mysql')->table('jobs')->count());
        $this->assertSame(0, DB::connection('mysql')->table('failed_jobs')->count());

        $this->globex->run(fn () => $this->assertPublishedAtScheduledTime($globexDue));
        $this->assertSame(PostStatus::Scheduled, $this->acme->run(fn () => Post::find($acmeDue)->status));
    }

    public function test_running_the_command_twice_publishes_only_once(): void
    {
        $due = $this->acme->run(fn () => $this->scheduledIn(minutes: 5));

        $this->travel(10)->minutes();
        $this->artisan('posts:publish-scheduled')->assertSuccessful();
        $afterFirstRun = $this->acme->run(fn () => Post::find($due)->toArray());

        $this->travel(1)->minutes();
        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $this->acme->run(fn () => $this->assertPublishedAtScheduledTime($due));
        $this->assertSame($afterFirstRun, $this->acme->run(fn () => Post::find($due)->toArray()));
    }

    public function test_a_run_that_overlaps_an_earlier_one_does_not_publish_again(): void
    {
        $due = $this->acme->run(fn () => $this->scheduledIn(minutes: 5));

        $this->travel(10)->minutes();

        $this->acme->run(function () use ($due) {
            $this->assertTrue(Post::publishIfDue($due));
            $published = Post::find($due)->toArray();

            $this->travel(1)->minutes();

            $this->assertFalse(Post::publishIfDue($due));
            $this->assertSame($published, Post::find($due)->toArray());
        });
    }

    public function test_a_tenant_without_due_posts_is_left_unchanged(): void
    {
        $this->globex->run(function () {
            $this->scheduledIn(minutes: 60);
            Post::factory()->published()->create();
            Post::factory()->draft()->create();
        });
        $before = $this->globex->run(fn () => Post::orderBy('id')->get()->toArray());

        $this->travel(10)->minutes();

        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $this->assertSame($before, $this->globex->run(fn () => Post::orderBy('id')->get()->toArray()));
        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
    }

    /**
     * A post scheduled the given number of minutes from now. Must run inside a tenant.
     */
    private function scheduledIn(int $minutes): int
    {
        return Post::factory()->create([
            'status' => PostStatus::Scheduled,
            'publish_at' => now()->addMinutes($minutes)->startOfSecond(),
        ])->id;
    }

    private function assertPublishedAtScheduledTime(int $id): void
    {
        $post = Post::find($id);

        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->publish_at);
        $this->assertTrue($post->published_at->equalTo($post->publish_at));
    }
}
