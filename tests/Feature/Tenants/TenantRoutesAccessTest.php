<?php

namespace Tests\Feature\Tenants;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRoutesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_tenant_list(): void
    {
        $this->get('/tenants')->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login_from_the_create_page(): void
    {
        $this->get('/tenants/create')->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_tenants(): void
    {
        $this->post('/tenants', [
            'name' => 'Acme',
            'domain' => 'acme.blogs.test',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('tenants', 0);
    }
}
