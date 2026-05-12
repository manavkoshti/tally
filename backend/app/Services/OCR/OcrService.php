<?php

namespace App\Services\OCR;

use App\Models\Invoice;

class OcrService
{
    public function __construct(
        private TesseractOcrDriver $driver
    ) {}

    public function processInvoice(Invoice $invoice): array
    {
        $invoice->update(['ocr_status' => 'processing']);

        try {
            $filePath = storage_path('app/public/' . $invoice->file_path);
            $rawText = $this->driver->extractText($filePath, $invoice->file_type);
            $extracted = $this->parseInvoiceData($rawText);

            $invoice->update([
                'ocr_status' => 'completed',
                'ocr_raw_data' => ['raw_text' => $rawText, 'extracted' => $extracted],
                'invoice_number' => $extracted['invoice_number'] ?? $invoice->invoice_number,
                'invoice_date' => $extracted['invoice_date'] ?? $invoice->invoice_date,
                'party_name' => $extracted['party_name'] ?? $invoice->party_name,
                'party_gstin' => $extracted['gstin'] ?? $invoice->party_gstin,
                'taxable_amount' => $extracted['taxable_amount'] ?? 0,
                'cgst_amount' => $extracted['cgst_amount'] ?? 0,
                'sgst_amount' => $extracted['sgst_amount'] ?? 0,
                'igst_amount' => $extracted['igst_amount'] ?? 0,
                'total_gst_amount' => ($extracted['cgst_amount'] ?? 0) + ($extracted['sgst_amount'] ?? 0) + ($extracted['igst_amount'] ?? 0),
                'total_amount' => $extracted['total_amount'] ?? 0,
            ]);

            return $extracted;
        } catch (\Exception $e) {
            $invoice->update(['ocr_status' => 'failed']);
            throw $e;
        }
    }

    private function parseInvoiceData(string $text): array
    {
        $data = [];

        // Invoice number
        if (preg_match('/invoice\s*(?:no|number|#)\s*[:\-]?\s*([A-Z0-9\-\/]+)/i', $text, $m)) {
            $data['invoice_number'] = trim($m[1]);
        }

        // GSTIN - 15 digit alphanumeric
        if (preg_match('/\b([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1})\b/', $text, $m)) {
            $data['gstin'] = $m[1];
        }

        // Invoice date
        if (preg_match('/(?:date|dt)\s*[:\-]?\s*(\d{1,2}[\-\/\.]\d{1,2}[\-\/\.]\d{2,4})/i', $text, $m)) {
            try {
                $data['invoice_date'] = date('Y-m-d', strtotime($m[1]));
            } catch (\Exception) {}
        }

        // Total amount - look for "Total" followed by amount
        if (preg_match('/(?:grand\s*total|total\s*amount|net\s*payable)[:\s]*(?:Rs\.?|INR)?\s*([\d,]+(?:\.\d{2})?)/i', $text, $m)) {
            $data['total_amount'] = (float) str_replace(',', '', $m[1]);
        }

        // CGST
        if (preg_match('/cgst[:\s]*(?:Rs\.?|INR)?\s*([\d,]+(?:\.\d{2})?)/i', $text, $m)) {
            $data['cgst_amount'] = (float) str_replace(',', '', $m[1]);
        }

        // SGST
        if (preg_match('/sgst[:\s]*(?:Rs\.?|INR)?\s*([\d,]+(?:\.\d{2})?)/i', $text, $m)) {
            $data['sgst_amount'] = (float) str_replace(',', '', $m[1]);
        }

        // IGST
        if (preg_match('/igst[:\s]*(?:Rs\.?|INR)?\s*([\d,]+(?:\.\d{2})?)/i', $text, $m)) {
            $data['igst_amount'] = (float) str_replace(',', '', $m[1]);
        }

        // Taxable amount
        $gst = ($data['cgst_amount'] ?? 0) + ($data['sgst_amount'] ?? 0) + ($data['igst_amount'] ?? 0);
        if (isset($data['total_amount']) && $gst > 0) {
            $data['taxable_amount'] = $data['total_amount'] - $gst;
        }

        return $data;
    }
}
