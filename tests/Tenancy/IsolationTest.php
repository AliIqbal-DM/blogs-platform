<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The tenant isolation checklist. Every check first shows the owner reaching its own data,
 * so none of them can pass just because nothing works.
 *
 * Covered in the class named next to each area:
 * - posts (database): PostIsolationTest
 * - images (storage): TenantAssetIsolationTest, CoverImageTest
 * - cache: HomepageCacheTest (isolation, invalidation per tenant, recreated tenant)
 * - session replay between tenants: TenantAuthTest::test_session_cookie_from_one_tenant_is_a_guest_on_another_tenant
 * - scheduled publishing: ScheduledPublishingTest (isolation and the queued worker run)
 * - routing and central/tenant session replay: this class
 */
class IsolationTest extends TenantTestCase
{
    private const CENTRAL = 'http://blogs.test';

    private const ACME = 'http://acme.blogs.test';

    private Tenant $acme;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
        $this->acme->run(fn () => Post::factory()->published()->create(['slug' => 'hello']));
        $this->admin = User::factory()->create(['email' => 'admin@blogs.test', 'password' => 'admin-password']);
    }

    public function test_an_unknown_domain_returns_404_while_a_registered_tenant_resolves(): void
    {
        $this->get(self::ACME.'/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('tenant.id', 'acme'));

        foreach (['http://nobody.blogs.test/', 'http://nobody.blogs.test/posts/hello', 'http://acme.example.com/'] as $url) {
            $this->freshRequestState();
            $this->get($url)->assertNotFound();
        }
    }

    public function test_tenant_routes_are_unreachable_on_the_central_domain(): void
    {
        $this->get(self::ACME.'/posts/hello')->assertOk();
        $this->actingAsWriter($this->acme)->get(self::ACME.'/dashboard/posts')->assertOk();
        $this->freshRequestState();
        $this->get(self::ACME.'/login')->assertInertia(fn (Assert $page) => $page->component('Tenant/Login'));

        $this->freshRequestState();
        $this->get(self::CENTRAL.'/posts/hello')->assertNotFound();
        $this->actingAs($this->admin)->get(self::CENTRAL.'/dashboard/posts')->assertNotFound();
        $this->actingAs($this->admin)->get(self::CENTRAL.'/dashboard/posts/create')->assertNotFound();

        $this->freshRequestState();
        $this->get(self::CENTRAL.'/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_central_admin_routes_are_unreachable_on_tenant_domains(): void
    {
        foreach (['/tenants', '/tenants/create', '/profile'] as $path) {
            $this->freshRequestState();
            $this->actingAs($this->admin)->get(self::CENTRAL.$path)->assertOk();
        }

        foreach (['/tenants', '/tenants/create', '/profile'] as $path) {
            $this->freshRequestState();
            $this->actingAs($this->admin)->get(self::ACME.$path)->assertNotFound();
        }

        $this->freshRequestState();
        $this->actingAs($this->admin)
            ->post(self::ACME.'/tenants', ['name' => 'Sneaky', 'domain' => 'sneaky.blogs.test'])
            ->assertNotFound();
        $this->assertNull(Tenant::find('sneaky'));
    }

    public function test_a_central_admin_session_is_a_guest_on_tenant_domains(): void
    {
        $cookie = $this->loginCookie(self::CENTRAL, 'admin@blogs.test', 'admin-password');

        // The admin (central id 1) and acme's writer (tenant id 1) share an id, so a shared store would log the replay in.
        $this->assertSame($this->admin->id, $this->acme->run(fn () => User::where('email', 'writer@blogs.test')->value('id')));

        $this->freshRequestState();
        $this->withUnencryptedCookie(config('session.cookie'), $cookie)->get(self::CENTRAL.'/tenants')->assertOk();

        $this->freshRequestState();
        $this->withUnencryptedCookie(config('session.cookie'), $cookie)
            ->get(self::ACME.'/dashboard')
            ->assertRedirect(self::ACME.'/login');
        $this->assertGuest();
    }

    public function test_a_tenant_writer_session_is_a_guest_on_the_central_domain(): void
    {
        $cookie = $this->loginCookie(self::ACME, 'writer@blogs.test', 'password');

        $this->freshRequestState();
        $this->withUnencryptedCookie(config('session.cookie'), $cookie)->get(self::ACME.'/dashboard')->assertOk();

        $this->freshRequestState();
        $this->withUnencryptedCookie(config('session.cookie'), $cookie)
            ->get(self::CENTRAL.'/tenants')
            ->assertRedirect(self::CENTRAL.'/login');
        $this->assertGuest();
    }

    /**
     * The raw encrypted session cookie after logging in, exactly as a browser would send it back.
     */
    private function loginCookie(string $origin, string $email, string $password): string
    {
        $this->freshRequestState();

        return $this->post($origin.'/login', ['email' => $email, 'password' => $password])
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->getCookie(config('session.cookie'), decrypt: false)
            ->getValue();
    }
}
