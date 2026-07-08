<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Voucher;
use App\Services\Tally\TallySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncVoucherToTally implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public Voucher $voucher) {}

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(TallySyncService $tallySyncService): void
    {
        $result = $tallySyncService->syncVoucher($this->voucher);

        if (!$result['success']) {
            $message = $result['error'] ?? 'Unknown error';
            Log::warning("Tally sync failed for voucher {$this->voucher->id}: {$message}");

            // Tally not running — let the queue retry, do NOT throw which would burn an attempt instantly.
            if (!empty($result['connection_error']) && $this->attempts() < $this->tries) {
                $this->release(60);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->voucher->update(['tally_sync_status' => 'failed']);

        if ($this->voucher->invoice_id) {
            Invoice::where('id', $this->voucher->invoice_id)->update(['tally_sync_status' => 'failed']);
        }

        Log::error("Tally sync Job failed permanently for voucher {$this->voucher->id}: " . $exception->getMessage());
    }
}
