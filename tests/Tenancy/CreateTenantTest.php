<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

class CreateTenantTest extends TenantTestCase
{
    public function test_admin_can_create_a_tenant_with_domain_and_database(): void
    {
        // Keep a handle on the fake: ending tenancy after the tenant database is migrated resets the disk.
        $disk = Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/tenants', [
                'name' => 'Acme Corp',
                'domain' => 'acme.blogs.test',
                'brand_color' => '#e11d48',
                'logo' => UploadedFile::fake()->image('logo.png', 64, 64),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tenants.index'));

        $tenant = Tenant::find('acme_corp');

        $this->assertNotNull($tenant);
        $this->assertSame('Acme Corp', $tenant->name);
        $this->assertSame('#e11d48', $tenant->brand_color);
        $this->assertDatabaseHas('domains', ['domain' => 'acme.blogs.test', 'tenant_id' => 'acme_corp']);
        $this->assertTrue($this->tenantDatabaseExists('acme_corp'), 'tenant_test_acme_corp database was not created');

        $disk->assertExists($tenant->logo_path);
        $this->assertStringStartsWith('logos/', $tenant->logo_path);
        $this->assertSame('http://blogs.test/storage/'.$tenant->logo_path, $tenant->logo_url);

        $this->actingAs($admin)
            ->get('/tenants')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenants/Index')
                ->has('tenants', 1)
                ->where('tenants.0.id', 'acme_corp')
                ->where('tenants.0.domains.0', 'acme.blogs.test')
                ->where('tenants.0.brand_color', '#e11d48'));
    }

    public function test_tenant_is_rolled_back_when_its_domain_cannot_be_created(): void
    {
        $existing = Tenant::create(['id' => 'existing', 'name' => 'Existing']);
        $existing->domains()->create(['domain' => 'taken.blogs.test']);

        try {
            app(CreateTenant::class)->handle(
                id: 'rollback',
                name: 'Rollback',
                domain: 'taken.blogs.test',
            );
            $this->fail('Expected the duplicate domain to throw.');
        } catch (DomainOccupiedByOtherTenantException) {
            // Expected: stancl refuses to save a domain owned by another tenant.
        }

        $this->assertNull(Tenant::find('rollback'));
        $this->assertFalse($this->tenantDatabaseExists('rollback'), 'tenant_test_rollback database was not dropped');
        $this->assertTrue($this->tenantDatabaseExists('existing'));
    }
}
