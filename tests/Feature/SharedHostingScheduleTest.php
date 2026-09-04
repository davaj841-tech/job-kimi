<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SharedHostingScheduleTest extends TestCase
{
    #[Test]
    public function all_scheduled_events_are_in_process_callbacks(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $this->assertNotEmpty($schedule->events());

        foreach ($schedule->events() as $event) {
            $this->assertInstanceOf(
                CallbackEvent::class,
                $event,
                'Scheduled event must not use Symfony Process (proc_open): '.$event->getSummaryForDisplay()
            );
        }
    }
}
