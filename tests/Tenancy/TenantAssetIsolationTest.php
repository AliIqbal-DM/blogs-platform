<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Post;
use App\Models\Tenant;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

class TenantAssetIsolationTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private const GLOBEX = 'http://globex.blogs.test';

    private Tenant $acme;

    private Tenant $globex;

    private string $acmeCover;

    private string $globexCover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
        $this->globex = app(CreateTenant::class)->handle(id: 'globex', name: 'Globex', domain: 'globex.blogs.test');

        // Both tenants get a cover, so globex has a storage root and every check in the asset controller runs.
        $this->acmeCover = $this->uploadCover($this->acme, self::ACME);
        $this->globexCover = $this->uploadCover($this->globex, self::GLOBEX);
    }

    public function test_each_cover_is_served_on_its_own_tenant(): void
    {
        $this->freshRequestState();
        $this->get(self::ACME."/tenancy/assets/{$this->acmeCover}")->assertOk();

        $this->freshRequestState();
        $this->get(self::GLOBEX."/tenancy/assets/{$this->globexCover}")->assertOk();
    }

    public function test_cover_url_of_one_tenant_returns_404_on_another_tenants_domain(): void
    {
        $this->assertAssetHidden(
            self::GLOBEX."/tenancy/assets/{$this->acmeCover}",
            'Accessing a nonexistent file',
        );
        $this->assertAssetHidden(
            self::ACME."/tenancy/assets/{$this->globexCover}",
            'Accessing a nonexistent file',
        );
    }

    public function test_cover_url_returns_404_on_a_tenant_that_never_uploaded_anything(): void
    {
        app(CreateTenant::class)->handle(id: 'initech', name: 'Initech', domain: 'initech.blogs.test');

        $this->assertAssetHidden(
            "http://initech.blogs.test/tenancy/assets/{$this->acmeCover}",
            "Storage root doesn't exist",
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function traversalPaths(): array
    {
        $acmeFolder = static::TENANT_STORAGE_SUFFIX.'acme';

        return [
            'plain ../' => ["../../../{$acmeFolder}/app/public/{cover}"],
            'encoded ../' => ["%2e%2e%2f%2e%2e%2f%2e%2e%2f{$acmeFolder}%2fapp%2fpublic%2f{cover}"],
            'mixed encoding' => ["..%2f..%2f../{$acmeFolder}/app/public/{cover}"],
            'via covers/' => ["covers/../../../../{$acmeFolder}/app/public/{cover}"],
        ];
    }

    #[DataProvider('traversalPaths')]
    public function test_path_traversal_cannot_read_another_tenants_file(string $path): void
    {
        $path = str_replace('{cover}', $this->acmeCover, $path);

        $this->assertFileExists($this->tenantStoragePath('acme', "app/public/{$this->acmeCover}"));

        $this->assertAssetHidden(
            self::GLOBEX."/tenancy/assets/{$path}",
            'Accessing a file outside the storage root',
        );
    }

    public function test_path_traversal_cannot_read_central_files(): void
    {
        $this->assertAssetHidden(self::GLOBEX.'/tenancy/assets/../../../../.env', 'Accessing a file outside the storage root');
        $this->assertAssetHidden(self::GLOBEX.'/tenancy/assets/../../../../composer.json', 'Accessing a file outside the storage root');
    }

    public function test_asset_route_is_not_served_on_the_central_domain(): void
    {
        $this->freshRequestState();

        $this->get("http://blogs.test/tenancy/assets/{$this->acmeCover}")->assertNotFound();
    }

    private function uploadCover(Tenant $tenant, string $origin): string
    {
        $this->actingAsWriter($tenant)
            ->post("{$origin}/dashboard/posts", [
                'title' => 'Post with cover',
                'body' => 'Body.',
                'status' => 'published',
                'cover' => UploadedFile::fake()->image('cover.png'),
            ])
            ->assertSessionHasNoErrors();

        return $tenant->run(fn () => Post::sole()->cover_image);
    }

    /**
     * Asserts the package rejects the path for the expected reason, and that the visitor gets a 404.
     */
    private function assertAssetHidden(string $url, string $reason): void
    {
        $this->freshRequestState();
        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->get($url),
            Exception::class,
            $reason,
        );
        $this->withExceptionHandling();

        $this->freshRequestState();
        $this->getOutsideUnitTestMode($url)->assertNotFound();
    }

    /**
     * Under unit tests TenantAssetsController throws instead of aborting with 404,
     * so the production response is only visible with the environment switched.
     */
    private function getOutsideUnitTestMode(string $url): TestResponse
    {
        $this->app['env'] = 'production';

        try {
            return $this->get($url);
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
