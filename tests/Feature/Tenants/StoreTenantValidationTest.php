<?php

namespace Tests\Feature\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every case here fails validation, so no tenant database is ever created.
 */
class StoreTenantValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    private function postTenant(array $overrides = [])
    {
        return $this->from('/tenants/create')->post('/tenants', array_merge([
            'name' => 'Acme',
            'domain' => 'acme.blogs.test',
            'brand_color' => '#e11d48',
        ], $overrides));
    }

    /**
     * Insert a tenant and domain without firing events, so no database is created.
     */
    private function existingTenant(string $id, string $domain): void
    {
        Model::withoutEvents(function () use ($id, $domain) {
            $tenant = Tenant::create(['id' => $id, 'name' => ucfirst($id)]);
            $tenant->domains()->create(['domain' => $domain]);
        });
    }

    public function test_brand_color_must_be_a_six_digit_hex_color(): void
    {
        foreach (['red', '#fff', '#12345g', 'e11d48', '#e11d4800'] as $color) {
            $this->postTenant(['brand_color' => $color])
                ->assertRedirect('/tenants/create')
                ->assertSessionHasErrors('brand_color');
        }

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_svg_logos_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>',
        );

        $this->postTenant(['logo' => $svg])->assertSessionHasErrors('logo');

        $this->assertDatabaseCount('tenants', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_logos_larger_than_two_megabytes_are_rejected(): void
    {
        $logo = UploadedFile::fake()->image('logo.png')->size(2049);

        $this->postTenant(['logo' => $logo])->assertSessionHasErrors('logo');
    }

    public function test_domain_must_be_unique(): void
    {
        $this->existingTenant('existing', 'acme.blogs.test');

        $this->postTenant(['domain' => 'acme.blogs.test'])->assertSessionHasErrors('domain');

        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_domain_cannot_be_a_central_domain(): void
    {
        $this->postTenant(['domain' => 'blogs.test'])->assertSessionHasErrors('domain');

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_domain_must_be_a_lowercase_hostname(): void
    {
        foreach (['Acme.blogs.test', 'not a domain', 'acme', '-acme.blogs.test', 'http://acme.blogs.test'] as $domain) {
            $this->postTenant(['domain' => $domain])->assertSessionHasErrors('domain');
        }

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_name_is_required_and_limited_to_100_characters(): void
    {
        $this->postTenant(['name' => ''])->assertSessionHasErrors('name');
        $this->postTenant(['name' => str_repeat('a', 101)])->assertSessionHasErrors('name');
    }

    public function test_name_must_produce_an_unused_tenant_id(): void
    {
        $this->existingTenant('acme_corp', 'other.blogs.test');

        $this->postTenant(['name' => 'Acme Corp'])->assertSessionHasErrors('name');
        $this->postTenant(['name' => '!!!'])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('tenants', 1);
    }
}
