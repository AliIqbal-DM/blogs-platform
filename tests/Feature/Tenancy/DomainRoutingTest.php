<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_tenant_domain_returns_not_found(): void
    {
        $this->get('http://nobody.blogs.test/')->assertNotFound();
    }

    public function test_central_domain_does_not_serve_tenant_routes(): void
    {
        $this->get('http://blogs.test/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Welcome'))
            ->assertDontSee('The id of the current tenant is');
    }
}
