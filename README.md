# Blogs: a white-label multi-tenant blogging platform

Blogs is a white-label blogging platform built as a DataMoon internship project. A central admin creates tenants from one central site. Each tenant gets its own blog on its own subdomain (`acme.blogs.test`), its own MySQL database, its own uploaded files and its own cache namespace, plus its own logo and brand colour. Writers sign in on their tenant's subdomain to write, schedule and publish posts. Readers see the published posts on the tenant's homepage. One tenant can never see another tenant's posts, images, cache entries or sessions.

## Stack

| Part | Version |
| --- | --- |
| PHP | 8.2 to 8.4 (developed on 8.4) |
| Laravel | 11.x |
| stancl/tenancy | 3.x (database per tenant, tenants identified by domain) |
| Inertia + React (Laravel Breeze) | Inertia 2, React 18 |
| Tailwind CSS / Vite | 3.4 / 6 |
| MySQL | central database plus one database per tenant |
| Redis | cache store (tag-capable, needed for per-tenant cache) |
| PHPUnit | 11 |

Laravel 11 is end-of-life. The project brief requires it, so it is not upgraded. Composer reports security advisories for this version. They are ignored by advisory ID under `config.policy.advisories.ignore-id` in `composer.json`, with a note that this is a local learning app only. Do not deploy this to production as it stands.

## Prerequisites

- [Laravel Herd](https://herd.laravel.com) with PHP 8.2, 8.3 or 8.4
- Composer
- Node.js and npm
- MySQL (Herd Pro, DBngin or Homebrew)
- Redis (Herd Pro, DBngin or Homebrew)

## Setup

```bash
git clone <repository-url> blogs
cd blogs

composer install
npm install
npm run build

cp .env.example .env
php artisan key:generate
```

Check the `DB_*` and `REDIS_*` values in `.env` against your local MySQL and Redis. Then create the central database and the test database:

```bash
mysql -u root -e "CREATE DATABASE blogs_central; CREATE DATABASE blogs_central_test;"
```

Migrate and seed. The seeder creates the central admin and the two demo tenants. It also creates each tenant's database (`tenant_acme`, `tenant_globex`) and sample posts:

```bash
php artisan migrate --seed
php artisan storage:link
```

Link the site in Herd so `blogs.test` and every `*.blogs.test` subdomain resolve to this app:

```bash
herd link blogs
```

To reset the demo data later, run `php artisan migrate:fresh --seed`. You can run it as often as you like. Before recreating the demo tenants, the demo seeder removes what is left of them: their `tenant_<id>` databases, `storage/tenant<id>` folders, logos and cache entries. It only touches the `acme` and `globex` ids, and it refuses to run when `APP_ENV=production`.

## Demo sites and credentials

| Site | URL | Brand colour | Sign in with |
| --- | --- | --- | --- |
| Central admin | http://blogs.test | | `admin@blogs.test` |
| Acme Journal | http://acme.blogs.test | `#e11d48` | `writer@blogs.test` at `/login` |
| Globex Notes | http://globex.blogs.test | `#2563eb` | `writer@blogs.test` at `/login` |

The password for every demo account is `password` unless you set `ADMIN_PASSWORD` or `WRITER_PASSWORD` in `.env`. These credentials are for local development only. Each tenant has its own `writer@blogs.test` in its own database, so signing in on acme does not sign you in on globex.

Tenant management is for central admins only. The seeded `admin@blogs.test` has the `is_admin` flag; any other central user who signs in gets 403 on the tenant list and the create-tenant pages.

Each demo tenant gets seven posts: five published (three with cover images), one draft and one scheduled three days ahead. Logos and covers are placeholder PNGs drawn at seed time, so no image binaries are committed.

## Scheduler and queue worker

Scheduled posts are published by `posts:publish-scheduled`, which runs every minute. For each tenant it dispatches a `PublishDuePostsJob` to the database queue. Run both of these in separate terminals:

```bash
php artisan schedule:work
php artisan queue:work
```

## Tests

There are two suites:

```bash
# Unit and feature tests on in-memory SQLite (no MySQL needed)
php artisan test

# Tenancy tests against real MySQL databases (needs blogs_central_test)
vendor/bin/phpunit --configuration=phpunit.tenancy.xml
```

The tenancy suite refuses to run against any database other than `blogs_central_test`. It creates and drops `tenant_test_*` databases and `storage/tenant_test_*` folders, so it never touches your local demo data. `tests/Tenancy/IsolationTest.php` lists which test proves each isolation guarantee.

## Architecture

**Central and tenant.** The central app on `blogs.test` owns the `tenants` and `domains` tables, the central admin user and the tenant management screens (`routes/web.php`, wrapped in `Route::domain()` for each central domain). Tenant routes (`routes/tenant.php`) go through `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains`. An unknown subdomain returns 404, tenant pages are unreachable on the central domain, and central admin pages are unreachable on tenant domains. Tenant tables have no `tenant_id` column; isolation comes from each tenant having its own database.

**Bootstrappers that matter** (`config/tenancy.php`):

- `DatabaseTenancyBootstrapper` switches the default connection to the tenant's database (`tenant_<id>`). Users, posts and sessions all live there.
- `CacheTenancyBootstrapper` tags every cache call with `tenant<id>`, so a tenant's cache keys cannot collide with another tenant's. When a tenant is deleted, its tagged cache is flushed (`FlushTenantCache`).
- `FilesystemTenancyBootstrapper` points the `local` and `public` disks at `storage/tenant<id>/...`. Cover images are stored there and served through `/tenancy/assets/...`.
- `QueueTenancyBootstrapper` adds the tenant id to every job dispatched in tenant context and re-initialises that tenant when the job runs.

**Where things are scoped:**

| Concern | Where it lives |
| --- | --- |
| Posts, writer accounts | the tenant database `tenant_<id>` |
| Sessions | `sessions` table in the database of the site you signed in on (database driver) |
| Cache | Redis, tagged per tenant (cached homepage pages, invalidated by `PostObserver`) |
| Cover images | `storage/tenant<id>/app/public/covers` |
| Tenant logos | central public disk, `storage/app/public/logos`, served from `/storage` |
| Queued jobs | `jobs` table in the central database, carrying the tenant id in the payload |

Tenant lifecycle is handled by job pipelines in `app/Providers/TenancyServiceProvider.php`. Creating a tenant creates, migrates and seeds its database. Deleting one flushes its cache, then drops its database and removes its storage folder and logo.

## Known limitations and follow-ups

- Laravel 11 is end-of-life and has open advisories. Upgrading is out of scope for the brief.
- Tenant creation runs synchronously inside the admin's request. Many tenants, or slow migrations, would need queued provisioning with a status screen.
- Each tenant has one seeded writer. There is no tenant-side user management, invitation or password reset yet.
- Only `*.blogs.test` subdomains are supported. There are no custom domains or HTTPS setup.
- Redis is used only through the cache. The Redis tenancy bootstrapper is off, so any future direct `Redis::` call would not be tenant-scoped.
- Post bodies are plain text. There is no rich-text editor, and there are no comments, tags or search.
- Scheduled posts go live up to a minute late, and only while both the scheduler and a queue worker are running.
- `migrate:fresh` drops only the central tables. The demo seeder cleans up the demo tenant ids, but databases and storage folders of other tenants you created by hand are left behind and must be dropped manually.

## What I learned

When there are multiple clients whom we need to serve from a single codebase, we use multitenancy. Clients can be put in a single db or each one can get their own db. You maintain it through the central db. Each user gets their own domain which cant be accessed from the central domain. We are making a new db for every tenant. So we get proper isolation. But the cost is that whenever the schema changes, the migrations have to run on every tenants db.
