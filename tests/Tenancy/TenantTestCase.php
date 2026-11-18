<?php

namespace Tests\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base class for tests that create real tenant databases on MySQL.
 * Run with: php artisan test --configuration=phpunit.tenancy.xml
 */
abstract class TenantTestCase extends TestCase
{
    protected const TEST_DATABASE = 'blogs_central_test';

    protected const TENANT_DATABASE_PREFIX = 'tenant_test_';

    /**
     * Only clean up once we know we are connected to the test database.
     */
    private bool $onTestDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();

        if ($database !== static::TEST_DATABASE) {
            $this->fail(sprintf(
                'Refusing to run tenancy tests against database [%s]; expected [%s]. Run them with --configuration=phpunit.tenancy.xml.',
                $database,
                static::TEST_DATABASE,
            ));
        }

        $this->onTestDatabase = true;

        config(['tenancy.database.prefix' => static::TENANT_DATABASE_PREFIX]);

        $this->dropLeftoverTenantDatabases();
        $this->artisan('migrate:fresh');
    }

    protected function tearDown(): void
    {
        if ($this->onTestDatabase) {
            tenancy()->end();
            Tenant::all()->each->delete();
        }

        parent::tearDown();
    }

    /**
     * Each HTTP request in production starts with a fresh app. In tests the app is shared,
     * so clear the tenant context, resolved guard user and session handler between requests.
     */
    protected function freshRequestState(): void
    {
        tenancy()->end();
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    /**
     * Log in as the writer seeded into the given tenant, starting from a fresh request state.
     */
    protected function actingAsWriter(Tenant $tenant): static
    {
        $this->freshRequestState();

        $writer = $tenant->run(fn () => User::where('email', 'writer@blogs.test')->firstOrFail());

        return $this->actingAs($writer);
    }

    protected function tenantDatabaseExists(string $tenantId): bool
    {
        return DB::table('information_schema.SCHEMATA')
            ->where('SCHEMA_NAME', static::TENANT_DATABASE_PREFIX.$tenantId)
            ->exists();
    }

    /**
     * A crashed run can leave tenant_test_* databases behind, which would make CreateDatabase fail.
     */
    private function dropLeftoverTenantDatabases(): void
    {
        $leftovers = DB::table('information_schema.SCHEMATA')
            ->where('SCHEMA_NAME', 'like', str_replace('_', '\\_', static::TENANT_DATABASE_PREFIX).'%')
            ->pluck('SCHEMA_NAME');

        foreach ($leftovers as $name) {
            DB::statement("DROP DATABASE `{$name}`");
        }
    }
}
