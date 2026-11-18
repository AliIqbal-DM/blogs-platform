<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class BlogPagesTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private Tenant $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
    }

    public function test_home_lists_only_published_posts_newest_first_ten_per_page(): void
    {
        $this->acme->run(function () {
            foreach (range(1, 11) as $day) {
                Post::factory()->published()->create([
                    'title' => "Published {$day}",
                    'published_at' => now()->subDays(20 - $day),
                ]);
            }

            Post::factory()->draft()->create(['title' => 'Secret draft']);
            Post::factory()->scheduled()->create(['title' => 'Future post']);
        });

        $this->get(self::ACME.'/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Home')
                ->where('tenant.name', 'Acme')
                ->has('posts.data', 10)
                ->where('posts.total', 11)
                ->where('posts.data.0.title', 'Published 11')
                ->where('posts.data.9.title', 'Published 2')
                ->missing('posts.data.0.body'))
            ->assertDontSee('Secret draft')
            ->assertDontSee('Future post');

        $this->get(self::ACME.'/?page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Published 1'));
    }

    public function test_published_post_page_is_public(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->for(User::firstWhere('email', 'writer@blogs.test'))->create([
            'title' => 'Hello readers',
            'slug' => 'hello-readers',
            'body' => 'Plain body',
        ]));

        $this->get(self::ACME.'/posts/hello-readers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.title', 'Hello readers')
                ->where('post.body', 'Plain body')
                ->where('post.author', 'Demo Writer'));
    }

    public function test_draft_scheduled_and_unknown_posts_return_404(): void
    {
        $this->acme->run(function () {
            Post::factory()->draft()->create(['slug' => 'a-draft']);
            Post::factory()->scheduled()->create(['slug' => 'a-scheduled-post']);
        });

        $this->get(self::ACME.'/posts/a-draft')->assertNotFound();
        $this->get(self::ACME.'/posts/a-scheduled-post')->assertNotFound();
        $this->get(self::ACME.'/posts/does-not-exist')->assertNotFound();
    }

    public function test_post_body_is_never_rendered_as_html(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->create([
            'slug' => 'xss',
            'body' => '<script>alert("pwned")</script>',
        ]));

        $this->get(self::ACME.'/posts/xss')
            ->assertOk()
            ->assertDontSee('<script>alert("pwned")</script>', escape: false)
            ->assertInertia(fn (Assert $page) => $page->where('post.body', '<script>alert("pwned")</script>'));
    }
}
