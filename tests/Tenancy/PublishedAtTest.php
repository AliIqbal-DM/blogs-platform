<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * published_at is the moment the post most recently became visible to readers.
 */
class PublishedAtTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private Tenant $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
        Carbon::setTestNow('2026-11-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_draft_to_published_sets_published_at_to_now(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->draft()->create()->id);
        Carbon::setTestNow('2026-11-22 12:30:00');

        $this->update($id, ['status' => 'published'])->assertSessionHasNoErrors();

        $this->assertPublishedAt($id, '2026-11-22 12:30:00');
    }

    public function test_scheduled_to_published_by_the_job_sets_published_at_to_the_scheduled_time(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->scheduled()->create(['publish_at' => '2026-11-22 13:00:00'])->id);
        Carbon::setTestNow('2026-11-22 13:04:00');

        $this->assertTrue($this->acme->run(fn () => Post::publishIfDue($id)));

        $this->assertPublishedAt($id, '2026-11-22 13:00:00');
    }

    public function test_scheduled_to_published_through_the_form_sets_published_at_to_now(): void
    {
        $id = $this->acme->run(fn () => Post::factory()->scheduled()->create(['publish_at' => '2026-11-29 09:00:00'])->id);
        Carbon::setTestNow('2026-11-22 12:10:00');

        $this->update($id, ['status' => 'published'])->assertSessionHasNoErrors();

        $this->assertPublishedAt($id, '2026-11-22 12:10:00');
    }

    public function test_a_plain_edit_of_a_published_post_keeps_published_at(): void
    {
        $id = $this->publishedPost('2026-11-20 08:00:00');
        Carbon::setTestNow('2026-11-22 12:45:00');

        $this->update($id, ['status' => 'published', 'body' => 'Edited body'])->assertSessionHasNoErrors();
        $this->acme->run(fn () => Post::findOrFail($id)->update(['title' => 'Edited on the model']));

        $this->assertPublishedAt($id, '2026-11-20 08:00:00');
    }

    public function test_published_to_draft_clears_published_at(): void
    {
        $id = $this->publishedPost('2026-11-20 08:00:00');

        $this->update($id, ['status' => 'draft'])->assertSessionHasNoErrors();

        $this->assertSame([PostStatus::Draft, null], $this->state($id));
    }

    public function test_published_to_scheduled_clears_published_at(): void
    {
        $id = $this->publishedPost('2026-11-20 08:00:00');

        $this->update($id, ['status' => 'scheduled', 'publish_at' => '2026-11-25T09:00:00.000Z'])
            ->assertSessionHasNoErrors();

        $this->assertSame([PostStatus::Scheduled, null], $this->state($id));
    }

    public function test_republishing_after_a_draft_gives_a_new_published_at(): void
    {
        $id = $this->publishedPost('2026-11-20 08:00:00');

        $this->update($id, ['status' => 'draft'])->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-22 15:00:00');
        $this->update($id, ['status' => 'published'])->assertSessionHasNoErrors();

        $this->assertPublishedAt($id, '2026-11-22 15:00:00');
    }

    public function test_rescheduled_post_published_by_the_job_gets_the_new_scheduled_time(): void
    {
        $id = $this->publishedPost('2026-11-20 08:00:00');

        $this->update($id, ['status' => 'scheduled', 'publish_at' => '2026-11-22T13:00:00.000Z'])
            ->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-22 13:01:00');
        $this->acme->run(fn () => Post::publishIfDue($id));

        $this->assertPublishedAt($id, '2026-11-22 13:00:00');
    }

    public function test_the_rule_also_applies_to_saves_outside_the_form(): void
    {
        $id = $this->publishedPost('2026-11-20 08:00:00');

        $this->acme->run(fn () => Post::findOrFail($id)->update(['status' => PostStatus::Draft]));
        $this->assertSame([PostStatus::Draft, null], $this->state($id));

        Carbon::setTestNow('2026-11-22 16:00:00');
        $this->acme->run(fn () => Post::findOrFail($id)->update(['status' => PostStatus::Published]));
        $this->assertPublishedAt($id, '2026-11-22 16:00:00');
    }

    public function test_factory_states_follow_the_rule(): void
    {
        $this->acme->run(function () {
            $this->assertNull(Post::factory()->create()->published_at);
            $this->assertNull(Post::factory()->draft()->create()->published_at);
            $this->assertNull(Post::factory()->scheduled()->create()->published_at);
            $this->assertTrue(Post::factory()->published()->create()->published_at->isPast());
            $this->assertNull(Post::factory()->published()->draft()->create()->published_at);
        });
    }

    public function test_a_republished_post_moves_to_the_top_of_the_homepage(): void
    {
        $id = $this->publishedPost('2026-11-10 08:00:00', 'Comes back');
        $this->publishedPost('2026-11-21 08:00:00', 'Newer post');

        $this->homepage()->assertSeeInOrder(['Newer post', 'Comes back']);

        $this->update($id, ['status' => 'draft', 'title' => 'Comes back'])->assertSessionHasNoErrors();
        $this->homepage()->assertDontSee('Comes back');

        Carbon::setTestNow('2026-11-22 18:00:00');
        $this->update($id, ['status' => 'published', 'title' => 'Comes back'])->assertSessionHasNoErrors();
        $this->homepage()->assertSeeInOrder(['Comes back', 'Newer post']);
    }

    private function publishedPost(string $publishedAt, string $title = 'Published post'): int
    {
        return $this->acme->run(fn () => Post::factory()->create([
            'title' => $title,
            'status' => PostStatus::Published,
            'published_at' => $publishedAt,
        ])->id);
    }

    private function assertPublishedAt(int $id, string $expected): void
    {
        [$status, $publishedAt] = $this->state($id);

        $this->assertSame(PostStatus::Published, $status);
        $this->assertSame($expected, $publishedAt);
    }

    /**
     * Status and published_at, read inside the tenant so no model leaves its context.
     *
     * @return array{PostStatus, ?string}
     */
    private function state(int $id): array
    {
        return $this->acme->run(function () use ($id) {
            $post = Post::findOrFail($id);

            return [$post->status, $post->published_at?->format('Y-m-d H:i:s')];
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(int $id, array $overrides): TestResponse
    {
        return $this->actingAsWriter($this->acme)->put(self::ACME."/dashboard/posts/{$id}", [
            'title' => 'Published post',
            'body' => 'Body.',
            'publish_at' => null,
            ...$overrides,
        ]);
    }

    private function homepage(): TestResponse
    {
        $this->freshRequestState();

        return $this->get(self::ACME.'/')->assertOk();
    }
}
