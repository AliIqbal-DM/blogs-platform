<?php

namespace Tests\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Only central admins may manage tenants; any other logged-in central user gets 403.
 */
class TenantAdminAccessTest extends TenantTestCase
{
    private const CENTRAL = 'http://blogs.test';

    private const NEW_TENANT = ['name' => 'Sneaky Corp', 'domain' => 'sneaky.blogs.test'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_non_admin_is_forbidden_from_the_tenant_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(self::CENTRAL.'/tenants')
            ->assertForbidden();
    }

    public function test_non_admin_is_forbidden_from_the_tenant_creation_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(self::CENTRAL.'/tenants/create')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_create_a_tenant(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(self::CENTRAL.'/tenants', self::NEW_TENANT)
            ->assertForbidden();

        $this->assertSame(0, Tenant::count());
        $this->assertDatabaseMissing('domains', ['domain' => 'sneaky.blogs.test']);
        $this->assertFalse($this->tenantDatabaseExists('sneaky_corp'), 'tenant_test_sneaky_corp database was created');
    }

    public function test_admin_can_list_open_the_form_and_create_a_tenant(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(self::CENTRAL.'/tenants')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenants/Index'));

        $this->actingAs($admin)
            ->get(self::CENTRAL.'/tenants/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenants/Create'));

        $this->actingAs($admin)
            ->post(self::CENTRAL.'/tenants', self::NEW_TENANT)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tenants.index'));

        $this->assertNotNull(Tenant::find('sneaky_corp'));
        $this->assertDatabaseHas('domains', ['domain' => 'sneaky.blogs.test', 'tenant_id' => 'sneaky_corp']);
        $this->assertTrue($this->tenantDatabaseExists('sneaky_corp'), 'tenant_test_sneaky_corp database was not created');
    }
}
