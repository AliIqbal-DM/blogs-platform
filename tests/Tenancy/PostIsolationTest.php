<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

class PostIsolationTest extends TenantTestCase
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

    public function test_post_created_on_one_tenant_never_appears_on_another(): void
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', [
                'title' => 'Acme only',
                'body' => 'Internal to Acme.',
                'status' => 'published',
            ])
            ->assertSessionHasNoErrors();

        $id = $this->acme->run(fn () => Post::where('slug', 'acme-only')->value('id'));

        // Control: the post is visible on its own blog.
        $this->freshRequestState();
        $this->get(self::ACME.'/')
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.slug', 'acme-only'));
        $this->get(self::ACME.'/posts/acme-only')->assertOk();

        $this->freshRequestState();
        $this->get(self::GLOBEX.'/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 0)->where('posts.total', 0))
            ->assertDontSee('Acme only');

        $this->freshRequestState();
        $this->get(self::GLOBEX.'/posts/acme-only')->assertNotFound();

        $globexWriter = $this->actingAsWriter($this->globex);

        $globexWriter->get(self::GLOBEX.'/dashboard/posts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Posts/Index')->has('posts', 0))
            ->assertDontSee('Acme only');

        $globexWriter->get(self::GLOBEX."/dashboard/posts/{$id}/edit")->assertNotFound();
        $globexWriter->delete(self::GLOBEX."/dashboard/posts/{$id}")->assertNotFound();

        $this->assertSame(1, $this->acme->run(fn () => Post::count()));
        $this->assertSame(0, $this->globex->run(fn () => Post::count()));
    }

    public function test_central_list_shows_post_counts_per_tenant(): void
    {
        $this->acme->run(function () {
            Post::factory()->draft()->create();
            Post::factory()->scheduled()->create();
            Post::factory()->published()->count(2)->create();
        });

        $legacy = app(CreateTenant::class)->handle(id: 'legacy', name: 'Legacy', domain: 'legacy.blogs.test');
        $legacy->run(fn () => Schema::drop('posts'));

        $admin = User::factory()->create();

        $tenants = $this->actingAs($admin)
            ->get('http://blogs.test/tenants')
            ->assertOk()
            ->viewData('page')['props']['tenants'];

        $counts = collect($tenants)->pluck('post_count', 'id')->all();

        $this->assertSame(['acme' => 4, 'globex' => 0, 'legacy' => null], [
            'acme' => $counts['acme'],
            'globex' => $counts['globex'],
            'legacy' => $counts['legacy'],
        ]);
    }
}
