<?php

namespace Tests\Feature\Approval;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\EventStatus;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGroups;
use Tests\TestCase;

class ApprovalMessagesTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_withdrawal_is_shown_in_notifications_and_history(): void
    {
        ['group' => $group, 'highest' => $highests, 'responsible' => $responsibles] = $this->makeGroup(['responsible' => 3]);
        $participants = array_merge($highests, $responsibles);

        $event = $this->makeEvent($group, $highests[0], EventStatus::Accepting, $participants);
        $this->makeCatalog($event, '夏空スタジオ', [['name' => '新刊', 'price' => 1000]]);

        $approvals = app(ApprovalService::class);
        $approval = $approvals->request($event, $responsibles[0], ApprovalActionType::FixEvent);
        $approvals->withdraw($approval, $responsibles[0]);

        $this->assertSame(ApprovalStatus::Withdrawn, $approval->fresh()->status);

        $this->actingAs($responsibles[0])->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('申請が取り下げられました');

        $this->actingAs($highests[0])->get(route('histories.index', $event))
            ->assertOk()
            ->assertSee('承認申請が取り下げられました');

        $this->actingAs($highests[0])->get(route('approvals.index', $event))
            ->assertOk()
            ->assertSee('取り下げ');
    }
}
