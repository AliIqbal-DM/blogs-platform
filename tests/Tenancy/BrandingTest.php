<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

class BrandingTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private const GLOBEX = 'http://globex.blogs.test';

    private Tenant $acme;

    private Tenant $globex;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->acme = app(CreateTenant::class)->handle(
            id: 'acme',
            name: 'Acme',
            domain: 'acme.blogs.test',
            brandColor: '#e11d48',
            logo: UploadedFile::fake()->image('acme.png', 64, 64),
        );
        $this->globex = app(CreateTenant::class)->handle(
            id: 'globex',
            name: 'Globex',
            domain: 'globex.blogs.test',
            brandColor: '#facc15',
            logo: UploadedFile::fake()->image('globex.png', 64, 64),
        );

        foreach ([$this->acme, $this->globex] as $tenant) {
            $tenant->run(fn () => Post::factory()->published()->create(['slug' => 'hello']));
        }
    }

    public function test_public_pages_use_their_own_tenants_branding_only(): void
    {
        foreach (['/', '/posts/hello'] as $path) {
            $this->freshRequestState();
            $this->assertBrandedAs($this->get(self::ACME.$path), $this->acme, $this->globex);

            $this->freshRequestState();
            $this->assertBrandedAs($this->get(self::GLOBEX.$path), $this->globex, $this->acme);
        }
    }

    public function test_writer_pages_use_their_own_tenants_branding_only(): void
    {
        $this->freshRequestState();
        $this->assertBrandedAs($this->get(self::ACME.'/login'), $this->acme, $this->globex);

        foreach (['/dashboard', '/dashboard/posts', '/dashboard/posts/create'] as $path) {
            $this->assertBrandedAs($this->actingAsWriter($this->acme)->get(self::ACME.$path), $this->acme, $this->globex);
            $this->assertBrandedAs($this->actingAsWriter($this->globex)->get(self::GLOBEX.$path), $this->globex, $this->acme);
        }
    }

    public function test_text_on_the_brand_color_is_white_or_dark_depending_on_its_luminance(): void
    {
        $this->get(self::ACME.'/')->assertInertia(fn (Assert $page) => $page
            ->where('tenant.theme.brand', '#e11d48')
            ->where('tenant.theme.brand_foreground', '#ffffff'));

        $this->freshRequestState();
        $this->get(self::GLOBEX.'/')->assertInertia(fn (Assert $page) => $page
            ->where('tenant.theme.brand', '#facc15')
            ->where('tenant.theme.brand_foreground', '#18181b'));
    }

    public function test_an_invalid_brand_color_falls_back_to_the_default(): void
    {
        $this->acme->update(['brand_color' => 'red;background:url(x)']);

        $this->get(self::ACME.'/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.brand_color', null)
                ->where('tenant.theme.brand', '#4f46e5')
                ->where('tenant.theme.brand_foreground', '#ffffff'))
            ->assertDontSee('red;background', escape: false);
    }

    private function assertBrandedAs(TestResponse $response, Tenant $own, Tenant $other): void
    {
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.id', $own->id)
                ->where('tenant.name', $own->name)
                ->where('tenant.logo_url', $own->logo_url)
                ->where('tenant.theme.brand', $own->brand_color))
            ->assertSee(basename($own->logo_path))
            ->assertDontSee(basename($other->logo_path))
            ->assertDontSee($other->brand_color)
            ->assertDontSee($other->name);
    }
}
