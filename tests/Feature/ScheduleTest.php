<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_scheduled_posts_are_published_every_minute_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains($event->command, 'posts:publish-scheduled'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
