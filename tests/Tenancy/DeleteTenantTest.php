<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DeleteTenantTest extends TenantTestCase
{
    public function test_deleting_a_tenant_removes_its_database_storage_folder_and_central_logo(): void
    {
        $acme = $this->tenantWithCoverAndLogo('acme');
        $logoFile = base_path('storage/app/public/'.$acme->logo_path);

        $this->assertFileExists($logoFile);
        $this->assertDirectoryExists($this->tenantStoragePath('acme', 'app/public/covers'));

        $acme->delete();

        $this->assertFalse($this->tenantDatabaseExists('acme'));
        $this->assertDirectoryDoesNotExist($this->tenantStoragePath('acme'));
        $this->assertFileDoesNotExist($logoFile);
    }

    public function test_deleting_one_tenant_leaves_other_tenants_files_alone(): void
    {
        $acme = $this->tenantWithCoverAndLogo('acme');
        $globex = $this->tenantWithCoverAndLogo('globex');
        $globexCover = $globex->run(fn () => Post::sole()->cover_image);

        $acme->delete();

        $this->assertFileExists($this->tenantStoragePath('globex', "app/public/{$globexCover}"));
        $this->assertFileExists(base_path('storage/app/public/'.$globex->logo_path));
        $this->assertTrue($this->tenantDatabaseExists('globex'));
    }

    public function test_deleting_a_tenant_without_logo_or_files_works(): void
    {
        $tenant = app(CreateTenant::class)->handle(id: 'bare', name: 'Bare', domain: 'bare.blogs.test');

        $tenant->delete();

        $this->assertNull(Tenant::find('bare'));
        $this->assertDirectoryDoesNotExist($this->tenantStoragePath('bare'));
    }

    /**
     * Uses the real central public disk: the logo is removed again when the tenant is deleted.
     */
    private function tenantWithCoverAndLogo(string $id): Tenant
    {
        $tenant = app(CreateTenant::class)->handle(
            id: $id,
            name: ucfirst($id),
            domain: "{$id}.blogs.test",
            logo: UploadedFile::fake()->image('logo.png', 64, 64),
        );

        $this->actingAsWriter($tenant)
            ->post("http://{$id}.blogs.test/dashboard/posts", [
                'title' => 'With cover',
                'body' => 'Body.',
                'status' => 'draft',
                'cover' => UploadedFile::fake()->image('cover.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->freshRequestState();
        $this->assertTrue(Storage::disk('public')->exists($tenant->logo_path));

        return $tenant;
    }
}
