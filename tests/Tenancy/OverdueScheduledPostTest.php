<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * A scheduled post whose time has passed but which the publish job has not picked up yet.
 */
class OverdueScheduledPostTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private Tenant $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_editing_an_overdue_scheduled_post_without_touching_the_date_succeeds(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:00');

        // What the edit form sends back: the stored time, as an ISO string in UTC with milliseconds.
        $this->update($id, ['body' => 'Fixed a typo.', 'publish_at' => '2026-11-22T15:00:00.000Z'])
            ->assertSessionHasNoErrors();

        $post = $this->acme->run(fn () => Post::findOrFail($id));
        $this->assertSame('Fixed a typo.', $post->body);
        $this->assertSame(PostStatus::Scheduled, $post->status);
        $this->assertSame('2026-11-22 15:00:00', $post->publish_at->format('Y-m-d H:i:s'));
        $this->assertNull($post->published_at);
    }

    public function test_the_same_time_in_another_offset_or_format_is_not_a_change(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:00');

        foreach (['2026-11-22T20:00:00+05:00', '2026-11-22 15:00', '2026-11-22T10:00:00-05:00'] as $sameTime) {
            $this->update($id, ['body' => "Edited with {$sameTime}", 'publish_at' => $sameTime])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(PostStatus::Scheduled, $this->acme->run(fn () => Post::findOrFail($id)->status));
    }

    public function test_seconds_in_the_stored_time_are_kept_when_the_form_sends_whole_minutes(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:42');

        $this->update($id, ['publish_at' => '2026-11-22T15:00:00.000Z'])->assertSessionHasNoErrors();

        $this->assertSame(
            '2026-11-22 15:00:42',
            $this->acme->run(fn () => Post::findOrFail($id)->publish_at->format('Y-m-d H:i:s')),
        );
    }

    public function test_changing_the_date_of_a_scheduled_post_to_a_past_time_fails(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:00');

        $this->update($id, ['publish_at' => '2026-11-22T15:00:10.000Z', 'body' => 'Changed'])
            ->assertSessionHasNoErrors();
        $this->update($id, ['publish_at' => '2026-11-22T14:59:00.000Z', 'body' => 'Should not save'])
            ->assertSessionHasErrors('publish_at');

        $post = $this->acme->run(fn () => Post::findOrFail($id));
        $this->assertSame('Changed', $post->body);
        $this->assertSame('2026-11-22 15:00:00', $post->publish_at->format('Y-m-d H:i:s'));
    }

    public function test_changing_the_date_of_a_scheduled_post_to_a_future_time_succeeds(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:00');

        $this->update($id, ['publish_at' => '2026-11-23T09:00:00.000Z'])->assertSessionHasNoErrors();

        $this->assertSame(
            '2026-11-23 09:00:00',
            $this->acme->run(fn () => Post::findOrFail($id)->publish_at->format('Y-m-d H:i:s')),
        );
    }

    public function test_scheduling_a_draft_with_a_past_time_fails(): void
    {
        Carbon::setTestNow('2026-11-22 15:00:30');
        $id = $this->acme->run(fn () => Post::factory()->draft()->create()->id);

        $this->update($id, ['publish_at' => '2026-11-22T15:00:00.000Z'])->assertSessionHasErrors('publish_at');

        $this->assertSame(PostStatus::Draft, $this->acme->run(fn () => Post::findOrFail($id)->status));
    }

    public function test_rescheduling_a_published_post_with_its_old_past_time_fails(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:00');
        $this->acme->run(fn () => Post::publishIfDue($id));

        $this->update($id, ['publish_at' => '2026-11-22T15:00:00.000Z'])->assertSessionHasErrors('publish_at');

        $this->assertSame(PostStatus::Published, $this->acme->run(fn () => Post::findOrFail($id)->status));
    }

    public function test_creating_a_scheduled_post_with_a_past_time_fails(): void
    {
        Carbon::setTestNow('2026-11-22 15:00:30');

        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->form(['publish_at' => '2026-11-22T15:00:00.000Z']))
            ->assertSessionHasErrors('publish_at');

        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
    }

    public function test_scheduling_without_a_time_fails(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:00');

        $this->update($id, ['publish_at' => null])->assertSessionHasErrors('publish_at');
    }

    public function test_publish_if_due_still_publishes_the_post_after_the_edit(): void
    {
        $id = $this->overduePost('2026-11-22 15:00:00');

        $this->update($id, ['body' => 'Edited while overdue', 'publish_at' => '2026-11-22T15:00:00.000Z'])
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-11-22 15:01:00');
        $this->assertTrue($this->acme->run(fn () => Post::publishIfDue($id)));

        $post = $this->acme->run(fn () => Post::findOrFail($id));
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame('Edited while overdue', $post->body);
        $this->assertSame('2026-11-22 15:00:00', $post->published_at->format('Y-m-d H:i:s'));
    }

    /**
     * A post scheduled for $publishAt (UTC), with the clock moved 30 seconds past that time.
     */
    private function overduePost(string $publishAt): int
    {
        Carbon::setTestNow(Carbon::parse($publishAt)->subHour());
        $id = $this->acme->run(fn () => Post::factory()->scheduled()->create(['publish_at' => $publishAt])->id);
        Carbon::setTestNow(Carbon::parse($publishAt)->addSeconds(30));

        return $id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(int $id, array $overrides): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsWriter($this->acme)
            ->put(self::ACME."/dashboard/posts/{$id}", $this->form($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'title' => 'Scheduled post',
            'body' => 'Body.',
            'status' => 'scheduled',
            ...$overrides,
        ];
    }
}
