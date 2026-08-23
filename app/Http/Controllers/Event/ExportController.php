<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Policies\PurchasePolicy;
use App\Policies\SettlementPolicy;
use App\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    /**
     * 購入結果一覧をCSVでダウンロードする。
     */
    public function results(Event $event): StreamedResponse
    {
        abort_unless(app(PurchasePolicy::class)->view(auth()->user(), $event), 403);

        return $this->download(
            $this->exports->purchaseResultsCsv($event, auth()->user()),
            $this->exports->fileName($event, '購入結果')
        );
    }

    /**
     * 精算リストをCSVでダウンロードする。
     */
    public function settlements(Event $event): StreamedResponse
    {
        abort_unless(app(SettlementPolicy::class)->view(auth()->user(), $event), 403);

        return $this->download(
            $this->exports->settlementsCsv($event),
            $this->exports->fileName($event, '精算')
        );
    }

    private function download(string $body, string $fileName): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($body) {
                echo $body;
            },
            $fileName,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }
}
