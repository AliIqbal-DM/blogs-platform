<?php

namespace Tests\Tenancy;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoTenantsSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoTenantsSeederTest extends TenantTestCase
{
    /**
     * Logos go to the central public disk. Point it at a test folder so dev demo logos are never touched.
     */
    private string $centralPublic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralPublic = storage_path('framework/testing/demo-central-public');
        config(['filesystems.disks.public.root' => $this->centralPublic]);
        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem)->deleteDirectory($this->centralPublic);
    }

    public function test_database_seeder_creates_the_admin_and_two_demo_tenants(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'admin@blogs.test', 'is_admin' => true]);
        $this->assertEqualsCanonicalizing(['acme', 'globex'], Tenant::pluck('id')->all());

        foreach (['acme' => '#e11d48', 'globex' => '#2563eb'] as $id => $color) {
            $tenant = Tenant::find($id);

            $this->assertSame([$id.'.blogs.test'], $tenant->domains->pluck('domain')->all());
            $this->assertSame($color, $tenant->brand_color);
            $this->assertSame("logos/demo-{$id}.png", $tenant->logo_path);
            $this->assertNotFalse(getimagesize($this->centralPublic."/logos/demo-{$id}.png"));
            $this->assertTrue($this->tenantDatabaseExists($id), "tenant_test_{$id} was not created");

            $tenant->run(function () use ($id) {
                $this->assertSame(1, User::where('email', 'writer@blogs.test')->count());
                $this->assertSame(7, Post::count());
                $this->assertSame(5, Post::where('status', PostStatus::Published)->count());
                $this->assertSame(1, Post::where('status', PostStatus::Draft)->count());
                $this->assertTrue(Post::where('status', PostStatus::Scheduled)->sole()->publish_at->isFuture());
                $this->assertSame(0, Post::whereNull('user_id')->count());

                $covers = Post::whereNotNull('cover_image')->pluck('cover_image');
                $this->assertGreaterThanOrEqual(2, $covers->count());
                foreach ($covers as $cover) {
                    $this->assertFileExists($this->tenantStoragePath($id, "app/public/{$cover}"));
                }

                $this->assertStringNotContainsString('<', Post::pluck('body')->implode(''), "{$id} bodies must be plain text");
            });
        }

        $acmeTitles = $this->tenantTitles('acme');
        $this->assertSame([], array_intersect($acmeTitles, $this->tenantTitles('globex')));
    }

    public function test_seeding_again_replaces_the_demo_tenants(): void
    {
        $this->seed(DemoTenantsSeeder::class);
        $oldCover = Tenant::find('acme')->run(fn () => Post::whereNotNull('cover_image')->value('cover_image'));

        $this->seed(DemoTenantsSeeder::class);

        $this->assertSame(2, Tenant::count());
        $this->assertSame(7, Tenant::find('acme')->run(fn () => Post::count()));
        $this->assertSame(7, Tenant::find('globex')->run(fn () => Post::count()));
        $this->assertFileDoesNotExist($this->tenantStoragePath('acme', "app/public/{$oldCover}"));
    }

    public function test_seeding_after_migrate_fresh_removes_the_leftover_databases_files_and_cache(): void
    {
        $this->seed(DemoTenantsSeeder::class);
        $oldCover = Tenant::find('acme')->run(fn () => Post::whereNotNull('cover_image')->value('cover_image'));
        Tenant::find('acme')->run(fn () => Cache::forever('left-behind', 'stale'));
        tenancy()->end();

        // Drops the central tables only: the tenant databases and storage folders are left behind.
        $this->artisan('migrate:fresh');
        $this->assertSame(0, Tenant::count());
        $this->assertTrue($this->tenantDatabaseExists('acme'));
        $this->assertFileExists($this->tenantStoragePath('acme', "app/public/{$oldCover}"));

        $this->seed(DemoTenantsSeeder::class);

        $this->assertSame(7, Tenant::find('acme')->run(fn () => Post::count()));
        $this->assertFileDoesNotExist($this->tenantStoragePath('acme', "app/public/{$oldCover}"));
        $this->assertNull(Tenant::find('acme')->run(fn () => Cache::get('left-behind')));
    }

    public function test_demo_seeding_refuses_to_run_in_production(): void
    {
        $this->seed(DemoTenantsSeeder::class);
        $this->app['env'] = 'production';

        try {
            // Called directly: db:seed would stop at its own production prompt before reaching the seeder.
            app(DemoTenantsSeeder::class)->run();
            $this->fail('Demo seeding ran in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('production', $e->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(2, Tenant::count());
        $this->assertTrue($this->tenantDatabaseExists('acme'));
    }

    /**
     * @return list<string>
     */
    private function tenantTitles(string $id): array
    {
        return Tenant::find($id)->run(fn () => Post::pluck('title')->all());
    }
}
