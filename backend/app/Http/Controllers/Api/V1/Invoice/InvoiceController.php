<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInvoiceOcr;
use App\Jobs\ProcessInvoiceAccounting;
use App\Jobs\SyncVoucherToTally;
use App\Models\Invoice;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::where('company_id', $request->user()->company_id)
            ->with('user:id,name', 'partyLedger:id,name')
            ->when($request->type, fn($q) => $q->where('invoice_type', $request->type))
            ->when($request->status, fn($q) => $q->where('tally_sync_status', $request->status))
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('invoice_number', 'like', '%' . $request->search . '%')
                   ->orWhere('party_name', 'like', '%' . $request->search . '%');
            }))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->paginatedResponse($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $hasFile = $request->hasFile('file');

        $request->validate([
            'invoice_type' => 'required|in:sales,purchase,expense,journal,payment,receipt',
            // date only required when no file uploaded (OCR will extract it from file)
            'invoice_date' => $hasFile ? 'nullable|date' : 'required|date',
            'party_name' => 'nullable|string|max:255',
            'party_gstin' => 'nullable|string|max:15',
            'narration' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'items' => $hasFile ? 'nullable|array' : 'nullable|array',
            'items.*.description' => 'required_with:items|string',
            'items.*.rate' => 'required_with:items|numeric|min:0',
            'items.*.quantity' => 'required_with:items|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0',
        ]);

        $fileType = 'manual';
        $filePath = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileType = $file->extension() === 'pdf' ? 'pdf' : 'image';
            $filePath = $file->store('invoices/' . $request->user()->company_id, 'public');
        }

        $invoice = Invoice::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'invoice_type' => $request->invoice_type,
            'invoice_date' => $request->invoice_date ?? now()->toDateString(),
            'party_name' => $request->party_name,
            'party_gstin' => $request->party_gstin,
            'narration' => $request->narration,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'ocr_status' => $filePath ? 'pending' : 'completed',
            'accounting_status' => 'pending',
        ]);

        if ($request->has('items') && !empty($request->items)) {
            $this->processManualItems($invoice, $request->items, $request->party_gstin);
        }

        if ($filePath) {
            ProcessInvoiceOcr::dispatch($invoice);
        } elseif (!empty($request->items)) {
            ProcessInvoiceAccounting::dispatch($invoice);
        }

        return $this->successResponse($invoice->load('items'), 'Invoice created successfully.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->with('items', 'gstDetails', 'vouchers.entries.ledger', 'partyLedger', 'user:id,name')
            ->findOrFail($id);

        return $this->successResponse($invoice);
    }

    public function processAccounting(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)->findOrFail($id);

        if ($invoice->accounting_status === 'completed') {
            return $this->errorResponse('Invoice accounting already completed.');
        }

        if ($invoice->accounting_status === 'processing') {
            return $this->errorResponse('Invoice accounting is already in progress.');
        }

        $invoice->update(['accounting_status' => 'processing']);
        ProcessInvoiceAccounting::dispatch($invoice);

        return $this->successResponse(null, 'Accounting processing started.');
    }

    public function syncToTally(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)
            ->with('vouchers')
            ->findOrFail($id);

        $voucher = $invoice->vouchers->first();

        if (!$voucher) {
            return $this->errorResponse('No voucher found for this invoice. Process accounting first.');
        }

        if ($voucher->tally_sync_status === 'synced') {
            return $this->errorResponse('Invoice is already synced to Tally.');
        }

        $invoice->update(['tally_sync_status' => 'pending']);
        $voucher->update(['tally_sync_status' => 'pending']);
        SyncVoucherToTally::dispatch($voucher);

        return $this->successResponse(null, 'Tally sync queued. It will retry automatically if Tally is offline.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('company_id', $request->user()->company_id)->findOrFail($id);

        if ($invoice->file_path) {
            Storage::disk('public')->delete($invoice->file_path);
        }

        $invoice->delete();

        return $this->successResponse(null, 'Invoice deleted.');
    }

    private function processManualItems(Invoice $invoice, array $items, ?string $gstin): void
    {
        $taxableTotal = 0;
        $cgstTotal = 0;
        $sgstTotal = 0;
        $igstTotal = 0;
        $companyGstin = $invoice->company?->gstin;
        $isInterstate = $this->isInterstate($gstin, $companyGstin);

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $rate = (float) ($item['rate'] ?? 0);
            $amount = $quantity * $rate;
            $discount = (float) ($item['discount'] ?? 0);
            $taxableAmount = $amount - $discount;
            $gstRate = (float) ($item['gst_rate'] ?? 0);

            $cgst = $sgst = $igst = 0;
            if ($isInterstate) {
                $igst = round($taxableAmount * $gstRate / 100, 2);
            } else {
                $cgst = round($taxableAmount * ($gstRate / 2) / 100, 2);
                $sgst = round($taxableAmount * ($gstRate / 2) / 100, 2);
            }

            $invoice->items()->create([
                'description' => $item['description'],
                'hsn_sac' => $item['hsn_sac'] ?? null,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'Nos',
                'rate' => $rate,
                'amount' => $amount,
                'discount' => $discount,
                'taxable_amount' => $taxableAmount,
                'gst_rate' => $gstRate,
                'cgst_rate' => $isInterstate ? 0 : $gstRate / 2,
                'sgst_rate' => $isInterstate ? 0 : $gstRate / 2,
                'igst_rate' => $isInterstate ? $gstRate : 0,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_amount' => $taxableAmount + $cgst + $sgst + $igst,
            ]);

            $taxableTotal += $taxableAmount;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $igstTotal += $igst;
        }

        $totalGst = $cgstTotal + $sgstTotal + $igstTotal;
        $invoice->update([
            'taxable_amount' => $taxableTotal,
            'cgst_amount' => $cgstTotal,
            'sgst_amount' => $sgstTotal,
            'igst_amount' => $igstTotal,
            'total_gst_amount' => $totalGst,
            'total_amount' => $taxableTotal + $totalGst,
            'is_interstate' => $isInterstate,
        ]);
    }

    private function isInterstate(?string $partyGstin, ?string $companyGstin): bool
    {
        // GST state code = first 2 digits of GSTIN. Different code => interstate (IGST).
        if (!$partyGstin || !$companyGstin) {
            return false;
        }

        return substr($partyGstin, 0, 2) !== substr($companyGstin, 0, 2);
    }
}
