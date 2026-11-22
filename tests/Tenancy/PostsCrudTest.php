<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

class PostsCrudTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private Tenant $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login_from_posts_dashboard(): void
    {
        $this->get(self::ACME.'/dashboard/posts')->assertRedirect(self::ACME.'/login');
        $this->get(self::ACME.'/dashboard/posts/create')->assertRedirect(self::ACME.'/login');
        $this->post(self::ACME.'/dashboard/posts', $this->validPost())->assertRedirect(self::ACME.'/login');

        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
    }

    public function test_index_lists_every_post_of_the_blog_newest_first(): void
    {
        $this->acme->run(function () {
            Post::factory()->published()->create(['title' => 'Older post', 'created_at' => now()->subDay()]);
            Post::factory()->draft()->for(User::factory())->create(['title' => 'Newer post by another writer']);
        });

        $this->actingAsWriter($this->acme)
            ->get(self::ACME.'/dashboard/posts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Posts/Index')
                ->has('posts', 2)
                ->where('posts.0.title', 'Newer post by another writer')
                ->where('posts.0.status', 'draft')
                ->where('posts.1.title', 'Older post')
                ->where('posts.1.status', 'published'));
    }

    public function test_create_page_renders(): void
    {
        $this->actingAsWriter($this->acme)
            ->get(self::ACME.'/dashboard/posts/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Posts/Create'));
    }

    public function test_writer_creates_a_draft_and_publish_at_is_cleared(): void
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost([
                'status' => 'draft',
                'publish_at' => now()->addDay()->toIso8601String(),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(self::ACME.'/dashboard/posts');

        $post = $this->acme->run(fn () => Post::sole()->toArray());

        $this->assertSame('Hello World', $post['title']);
        $this->assertSame('hello-world', $post['slug']);
        $this->assertSame('draft', $post['status']);
        $this->assertNull($post['publish_at']);
        $this->assertNull($post['published_at']);
        $this->assertSame(
            $this->acme->run(fn () => User::where('email', 'writer@blogs.test')->value('id')),
            $post['user_id'],
        );
    }

    public function test_publishing_sets_published_at_to_now(): void
    {
        Carbon::setTestNow('2026-11-18 12:00:00');

        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost(['status' => 'published']))
            ->assertSessionHasNoErrors();

        $publishedAt = $this->acme->run(fn () => Post::sole()->published_at);

        $this->assertTrue($publishedAt->equalTo('2026-11-18 12:00:00'));
    }

    public function test_scheduling_stores_publish_at_in_utc(): void
    {
        Carbon::setTestNow('2026-11-18 12:00:00');

        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost([
                'status' => 'scheduled',
                'publish_at' => '2026-12-01T10:00:00+05:00',
            ]))
            ->assertSessionHasNoErrors();

        $raw = $this->acme->run(fn () => Post::query()->toBase()->value('publish_at'));

        $this->assertSame('2026-12-01 05:00:00', $raw);
    }

    public function test_scheduling_requires_a_future_publish_at(): void
    {
        $writer = $this->actingAsWriter($this->acme);

        $writer->post(self::ACME.'/dashboard/posts', $this->validPost(['status' => 'scheduled']))
            ->assertSessionHasErrors('publish_at');

        $writer->post(self::ACME.'/dashboard/posts', $this->validPost([
            'status' => 'scheduled',
            'publish_at' => now()->subMinute()->toIso8601String(),
        ]))->assertSessionHasErrors('publish_at');

        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
    }

    public function test_store_validates_input(): void
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', [
                'title' => str_repeat('a', 256),
                'body' => '',
                'status' => 'archived',
                'publish_at' => 'not a date',
            ])
            ->assertSessionHasErrors(['title', 'body', 'status', 'publish_at']);

        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
    }

    public function test_slugs_are_unique_within_the_blog(): void
    {
        $writer = $this->actingAsWriter($this->acme);

        foreach (range(1, 3) as $ignored) {
            $writer->post(self::ACME.'/dashboard/posts', $this->validPost(['title' => 'Hello World']))
                ->assertSessionHasNoErrors();
        }

        $slugs = $this->acme->run(fn () => Post::orderBy('id')->pluck('slug')->all());

        $this->assertSame(['hello-world', 'hello-world-2', 'hello-world-3'], $slugs);
    }

    public function test_edit_page_shows_the_post(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->create(['title' => 'Edit me'])->id);

        $this->actingAsWriter($this->acme)
            ->get(self::ACME."/dashboard/posts/{$id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Posts/Edit')
                ->where('post.id', $id)
                ->where('post.title', 'Edit me'));
    }

    public function test_writer_updates_a_post_and_the_slug_stays_the_same(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->scheduled()->create(['title' => 'Original title'])->id);

        $this->actingAsWriter($this->acme)
            ->put(self::ACME."/dashboard/posts/{$id}", $this->validPost([
                'title' => 'Renamed title',
                'body' => 'New body',
                'status' => 'draft',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(self::ACME.'/dashboard/posts');

        $post = $this->acme->run(fn () => Post::findOrFail($id)->toArray());

        $this->assertSame('Renamed title', $post['title']);
        $this->assertSame('original-title', $post['slug']);
        $this->assertSame('New body', $post['body']);
        $this->assertSame('draft', $post['status']);
        $this->assertNull($post['publish_at']);
    }

    public function test_any_writer_of_the_blog_can_edit_another_writers_post(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->for(User::factory())->create()->id);

        $this->actingAsWriter($this->acme)
            ->put(self::ACME."/dashboard/posts/{$id}", $this->validPost(['title' => 'Team edit']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Team edit', $this->acme->run(fn () => Post::findOrFail($id)->title));
    }

    public function test_writer_deletes_a_post(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->published()->create()->id);

        $this->actingAsWriter($this->acme)
            ->delete(self::ACME."/dashboard/posts/{$id}")
            ->assertRedirect(self::ACME.'/dashboard/posts');

        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
    }

    public function test_status_rules_are_enforced_on_the_model(): void
    {
        $this->acme->run(function () {
            $draft = Post::factory()->create(['status' => PostStatus::Draft, 'publish_at' => now()->addDay()]);
            $this->assertNull($draft->publish_at);

            $published = Post::factory()->create(['status' => PostStatus::Published, 'published_at' => null]);
            $this->assertNotNull($published->published_at);

            $originalPublishedAt = now()->subYear()->startOfSecond();
            $republished = Post::factory()->create(['status' => PostStatus::Published, 'published_at' => $originalPublishedAt]);
            $this->assertTrue($republished->published_at->equalTo($originalPublishedAt));
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPost(array $overrides = []): array
    {
        return [
            'title' => 'Hello World',
            'body' => "First paragraph.\n\nSecond paragraph.",
            'status' => 'draft',
            'publish_at' => null,
            ...$overrides,
        ];
    }
}
