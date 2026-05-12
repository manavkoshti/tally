<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Accounting\AccountingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInvoiceAccounting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public Invoice $invoice) {}

    public function handle(AccountingEngine $accountingEngine): void
    {
        try {
            $voucher = $accountingEngine->processInvoice($this->invoice);
            SyncVoucherToTally::dispatch($voucher)->delay(now()->addSeconds(5));
        } catch (\Exception $e) {
            Log::error("Accounting failed for invoice {$this->invoice->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->invoice->update(['accounting_status' => 'failed']);
        Log::error("Accounting Job failed permanently for invoice {$this->invoice->id}: " . $exception->getMessage());
    }
}
