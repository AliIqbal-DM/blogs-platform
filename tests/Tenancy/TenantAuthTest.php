<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class TenantAuthTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private const GLOBEX = 'http://globex.blogs.test';

    private const CENTRAL = 'http://blogs.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('database', config('session.driver'), 'Tenancy tests must run with SESSION_DRIVER=database.');

        app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test', brandColor: '#e11d48');
        app(CreateTenant::class)->handle(id: 'globex', name: 'Globex', domain: 'globex.blogs.test');
    }

    public function test_writer_logs_in_on_tenant_domain_and_reaches_dashboard(): void
    {
        $this->get(self::ACME.'/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Login')
                ->where('tenant.id', 'acme')
                ->where('tenant.name', 'Acme')
                ->where('tenant.brand_color', '#e11d48'));

        $this->freshRequestState();

        $this->post(self::ACME.'/login', ['email' => 'writer@blogs.test', 'password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(self::ACME.'/dashboard');

        $this->assertAuthenticated();

        $this->get(self::ACME.'/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Dashboard')
                ->where('auth.user.email', 'writer@blogs.test')
                ->where('tenant.id', 'acme'));
    }

    public function test_guest_is_redirected_to_tenant_login(): void
    {
        $this->get(self::ACME.'/dashboard')->assertRedirect(self::ACME.'/login');
    }

    public function test_writer_can_log_out(): void
    {
        $cookieName = config('session.cookie');

        $cookie = $this->post(self::ACME.'/login', ['email' => 'writer@blogs.test', 'password' => 'password'])
            ->getCookie($cookieName, decrypt: false)
            ->getValue();

        $this->freshRequestState();

        $this->withUnencryptedCookie($cookieName, $cookie)
            ->post(self::ACME.'/logout')
            ->assertRedirect(self::ACME.'/login');

        $this->assertGuest();

        $this->freshRequestState();

        $this->withUnencryptedCookie($cookieName, $cookie)
            ->get(self::ACME.'/dashboard')
            ->assertRedirect(self::ACME.'/login');
    }

    public function test_wrong_password_fails(): void
    {
        $this->post(self::ACME.'/login', ['email' => 'writer@blogs.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_session_cookie_from_one_tenant_is_a_guest_on_another_tenant(): void
    {
        $cookieName = config('session.cookie');

        $login = $this->post(self::ACME.'/login', ['email' => 'writer@blogs.test', 'password' => 'password'])
            ->assertRedirect(self::ACME.'/dashboard');

        // The raw encrypted value, exactly as a browser would send it back.
        $cookie = $login->getCookie($cookieName, decrypt: false)->getValue();

        // Both tenants seed a writer with id 1, so a shared session store would authenticate this replay.
        $this->assertSame(1, Tenant::find('globex')->run(fn () => User::where('email', 'writer@blogs.test')->value('id')));

        $this->freshRequestState();

        $this->withUnencryptedCookie($cookieName, $cookie)
            ->get(self::ACME.'/dashboard')
            ->assertOk();

        $this->freshRequestState();

        $this->withUnencryptedCookie($cookieName, $cookie)
            ->get(self::GLOBEX.'/dashboard')
            ->assertRedirect(self::GLOBEX.'/login');

        $this->assertGuest();
    }

    public function test_central_admin_cannot_log_in_on_tenant_domain(): void
    {
        User::factory()->create(['email' => 'admin@blogs.test', 'password' => 'admin-password']);

        $this->post(self::ACME.'/login', ['email' => 'admin@blogs.test', 'password' => 'admin-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tenant_writer_cannot_log_in_on_central_domain(): void
    {
        $this->post(self::CENTRAL.'/login', ['email' => 'writer@blogs.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
