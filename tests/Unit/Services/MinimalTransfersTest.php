<?php

namespace Tests\Unit\Services;

use App\Services\ChangeHistoryService;
use App\Services\NotificationService;
use App\Services\PurchaseResultService;
use App\Services\SettlementService;
use PHPUnit\Framework\TestCase;

class MinimalTransfersTest extends TestCase
{
    private function service(): SettlementService
    {
        return new SettlementService(new PurchaseResultService, new NotificationService, new ChangeHistoryService);
    }

    public function test_single_pair(): void
    {
        $transfers = $this->service()->minimalTransfers([1 => -1000, 2 => 1000]);

        $this->assertCount(1, $transfers);
        $this->assertSame(['payer_id' => 1, 'payee_id' => 2, 'amount' => 1000], $transfers[0]);
    }

    public function test_circular_debt_is_netted_out(): void
    {
        // A→B 1000, B→C 1000, C→A 1000 のような循環は純額が全て0になる
        $transfers = $this->service()->minimalTransfers([]);

        $this->assertSame([], $transfers);
    }

    public function test_three_people_need_only_one_transfer_when_one_nets_to_zero(): void
    {
        $transfers = $this->service()->minimalTransfers([1 => -2000, 2 => 2000, 3 => 0]);

        $this->assertCount(1, $transfers);
        $this->assertSame(2000, $transfers[0]['amount']);
    }

    public function test_transfers_are_minimised(): void
    {
        // 4人: -500, -1500, +1000, +1000 → 3件で済む
        $transfers = $this->service()->minimalTransfers([1 => -500, 2 => -1500, 3 => 1000, 4 => 1000]);

        $this->assertLessThanOrEqual(3, count($transfers));
        $this->assertSame(2000, array_sum(array_column($transfers, 'amount')));
    }

    public function test_totals_always_balance(): void
    {
        $balances = [1 => -3000, 2 => -1000, 3 => 2500, 4 => 1500];
        $transfers = $this->service()->minimalTransfers($balances);

        $paid = [];
        foreach ($transfers as $transfer) {
            $paid[$transfer['payer_id']] = ($paid[$transfer['payer_id']] ?? 0) + $transfer['amount'];
            $paid[$transfer['payee_id']] = ($paid[$transfer['payee_id']] ?? 0) - $transfer['amount'];
        }

        foreach ($balances as $userId => $balance) {
            $this->assertSame(-$balance, $paid[$userId] ?? 0, 'user '.$userId);
        }
    }

    public function test_no_transfer_has_zero_amount(): void
    {
        $transfers = $this->service()->minimalTransfers([1 => -1000, 2 => 600, 3 => 400]);

        foreach ($transfers as $transfer) {
            $this->assertGreaterThan(0, $transfer['amount']);
        }
        $this->assertCount(2, $transfers);
    }
}
