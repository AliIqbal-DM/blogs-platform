<?php

namespace Database\Seeders;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PostStatus;
use App\Jobs\FlushTenantCache;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Support\PlaceholderPng;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The acme and globex demo blogs. Safe to run again: the demo tenants are removed first,
 * including what migrate:fresh leaves behind (tenant databases, storage folders, logos, cache).
 */
class DemoTenantsSeeder extends Seeder
{
    private const TENANTS = [
        'acme' => ['name' => 'Acme Journal', 'domain' => 'acme.blogs.test', 'color' => '#e11d48'],
        'globex' => ['name' => 'Globex Notes', 'domain' => 'globex.blogs.test', 'color' => '#2563eb'],
    ];

    public function run(): void
    {
        $this->refuseInProduction();

        foreach (self::TENANTS as $id => $demo) {
            $this->removeLeftovers($id);

            $tenant = app(CreateTenant::class)->handle(
                id: $id,
                name: $demo['name'],
                domain: $demo['domain'],
                brandColor: $demo['color'],
            );

            // Cache entries are namespaced by tenant id, so a previous tenant with this id may have left some.
            FlushTenantCache::dispatchSync($tenant);

            $tenant->update(['logo_path' => $this->storeLogo($id, $demo['color'], strtoupper($id[0]))]);
            $tenant->run(fn () => $this->seedPosts($id, $demo['color']));
        }
    }

    private function refuseInProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoTenantsSeeder deletes tenant databases and refuses to run in production.');
        }
    }

    /**
     * Only the demo ids are touched. An existing tenant is deleted normally; without a tenant row
     * (after migrate:fresh) its database, storage folder and logo are removed directly.
     */
    private function removeLeftovers(string $id): void
    {
        $this->refuseInProduction();

        if ($tenant = Tenant::find($id)) {
            $tenant->delete();
        }

        $orphan = (new Tenant)->forceFill(['id' => $id]);
        $manager = $orphan->database()->manager();

        if ($manager->databaseExists($orphan->database()->getName())) {
            $manager->deleteDatabase($orphan);
        }

        File::deleteDirectory(storage_path(config('tenancy.filesystem.suffix_base').$id));
        Storage::disk('public')->delete($this->logoPath($id));
    }

    private function logoPath(string $id): string
    {
        return "logos/demo-{$id}.png";
    }

    /**
     * Logos live on the central public disk under a fixed name, so a later run can find and replace them.
     */
    private function storeLogo(string $id, string $color, string $letter): string
    {
        Storage::disk('public')->put($this->logoPath($id), PlaceholderPng::make(128, 128, $color, $letter));

        return $this->logoPath($id);
    }

    /**
     * Runs inside the tenant: posts go to its database and covers to its own public disk.
     */
    private function seedPosts(string $id, string $color): void
    {
        $writer = User::where('email', 'writer@blogs.test')->firstOrFail();

        foreach ($this->posts()[$id] as $index => [$title, $body, $state, $withCover]) {
            $factory = Post::factory()->for($writer);

            $attributes = match ($state) {
                'draft' => ['status' => PostStatus::Draft],
                'scheduled' => ['status' => PostStatus::Scheduled, 'publish_at' => now()->addDays(3)->setTime(9, 0)],
                default => ['status' => PostStatus::Published, 'published_at' => now()->subDays(3 + $index * 4)->setTime(9, 30)],
            };

            $factory->create([
                ...$attributes,
                'title' => $title,
                'body' => $body,
                'cover_image' => $withCover ? $this->storeCover($color) : null,
            ]);
        }
    }

    private function storeCover(string $color): string
    {
        $path = 'covers/'.Str::random(40).'.png';
        Storage::disk('public')->put($path, PlaceholderPng::make(1200, 675, $color));

        return $path;
    }

    /**
     * Title, plain-text body, state (published, draft or scheduled) and whether the post has a cover.
     *
     * @return array<string, list<array{string, string, string, bool}>>
     */
    private function posts(): array
    {
        return [
            'acme' => [
                ['How we cut packaging waste by a third', "Last spring we looked at every box that leaves our warehouse. Most were bigger than they needed to be.\n\nWe now ship in four standard sizes and fill gaps with recycled paper instead of plastic. Returns for damage did not go up.", 'published', true],
                ['Inside the new Tucson assembly line', "The line opened in March and builds our compact anvils end to end.\n\nThe biggest change is the order of the stations: finishing now happens before inspection, so defects are caught in one pass.", 'published', true],
                ['What our support team learned from 10,000 tickets', "We tagged a year of support tickets by topic. Almost half were about the same three questions.\n\nThose answers are now on the product pages, and first replies are down to under two hours.", 'published', false],
                ['Meet the people who test our rockets', "Our test crew works in the desert, three hours from the nearest town.\n\nThey run each model through heat, dust and a hundred launches before it goes on sale.", 'published', true],
                ['Spring catalogue: five products we are proud of', "This season brings a lighter roller skate, a quieter magnet and three other updates.\n\nEvery product page now lists what changed since the last version.", 'published', false],
                ['Draft: our 2027 sustainability goals', "Working notes for the sustainability report. Numbers still to be confirmed by finance.\n\nGoals so far: renewable power at both plants and no single-use plastic in shipping.", 'draft', false],
                ['Coming soon: the Acme repair programme', "From next month you can send any Acme product back for repair instead of replacing it.\n\nWe will publish prices and turnaround times on the day the programme opens.", 'scheduled', false],
            ],
            'globex' => [
                ['Our first year on the new data platform', "A year ago we moved every team to one shared data platform.\n\nReports that used to take a week now refresh every night, and we retired eleven spreadsheets.", 'published', true],
                ['Notes from the Cypress Creek energy pilot', "The pilot powers two office floors from rooftop panels and a battery in the basement.\n\nOn sunny days the floors ran without the grid from ten in the morning until six.", 'published', true],
                ['How we run blameless incident reviews', "After every outage we write down what happened, in order, without naming who pressed which button.\n\nThe goal is to fix the system, not the person. The template is at the end of this post.", 'published', false],
                ['Hiring: what to expect in our interviews', "Our process has three conversations and one short take-home task.\n\nWe pay for the take-home time and send feedback to every candidate, whatever the result.", 'published', true],
                ['Five things we changed after the customer survey', "Nine hundred customers answered our survey this autumn.\n\nThe top request was clearer invoices, so that is the first change, shipping this month.", 'published', false],
                ['Draft: roadmap for the next two quarters', "Internal draft. Themes: faster onboarding, better reporting, fewer manual steps for operations.\n\nNeeds a review from each team lead before it goes out.", 'draft', false],
                ['Announcing the Globex developer day', "On the first Friday of next month our engineers open the office to local developers.\n\nThere will be short talks, a hack session and a long lunch. Details to follow.", 'scheduled', false],
            ],
        ];
    }
}
