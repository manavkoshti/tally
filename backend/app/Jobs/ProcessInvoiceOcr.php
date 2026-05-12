<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\OCR\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInvoiceOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public Invoice $invoice) {}

    public function handle(OcrService $ocrService): void
    {
        try {
            $ocrService->processInvoice($this->invoice);
            ProcessInvoiceAccounting::dispatch($this->invoice->fresh());
        } catch (\Exception $e) {
            Log::error("OCR processing failed for invoice {$this->invoice->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->invoice->update(['ocr_status' => 'failed']);
        Log::error("OCR Job failed permanently for invoice {$this->invoice->id}: " . $exception->getMessage());
    }
}
