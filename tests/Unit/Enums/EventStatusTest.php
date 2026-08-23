<?php

namespace Tests\Unit\Enums;

use App\Enums\EventStatus;
use PHPUnit\Framework\TestCase;

class EventStatusTest extends TestCase
{
    public function test_transition_chain(): void
    {
        $status = EventStatus::Preparation;
        $seen = [$status];

        while (($status = $status->next()) !== null) {
            $seen[] = $status;
        }

        $this->assertSame([
            EventStatus::Preparation,
            EventStatus::Accepting,
            EventStatus::Fixed,
            EventStatus::Ongoing,
            EventStatus::Settling,
            EventStatus::Completed,
        ], $seen);
    }

    public function test_lock_starts_at_fixed(): void
    {
        $this->assertFalse(EventStatus::Preparation->isLocked());
        $this->assertFalse(EventStatus::Accepting->isLocked());
        $this->assertTrue(EventStatus::Fixed->isLocked());
        $this->assertTrue(EventStatus::Completed->isLocked());
    }

    public function test_only_accepting_takes_purchase_requests(): void
    {
        $this->assertTrue(EventStatus::Accepting->acceptsPurchaseRequests());
        $this->assertFalse(EventStatus::Fixed->acceptsPurchaseRequests());
    }
}
