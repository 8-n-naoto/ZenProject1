<?php

namespace Tests\Unit\Enums;

use App\Enums\GroupRole;
use PHPUnit\Framework\TestCase;

class GroupRoleTest extends TestCase
{
    public function test_rank_order(): void
    {
        $this->assertTrue(GroupRole::HighestResponsible->isAtLeast(GroupRole::Responsible));
        $this->assertTrue(GroupRole::Responsible->isAtLeast(GroupRole::Member));
        $this->assertFalse(GroupRole::Member->isAtLeast(GroupRole::Responsible));
    }

    public function test_responsible_or_above(): void
    {
        $this->assertTrue(GroupRole::HighestResponsible->isResponsibleOrAbove());
        $this->assertTrue(GroupRole::Responsible->isResponsibleOrAbove());
        $this->assertFalse(GroupRole::Member->isResponsibleOrAbove());
    }

    public function test_roles_that_require_at_least_one_member(): void
    {
        $this->assertTrue(GroupRole::HighestResponsible->requiresAtLeastOne());
        $this->assertTrue(GroupRole::Responsible->requiresAtLeastOne());
        $this->assertFalse(GroupRole::Member->requiresAtLeastOne());
    }

    public function test_values_match_database_representation(): void
    {
        $this->assertSame(['最高責任者', '責任者', '一般メンバー'], GroupRole::values());
    }
}
