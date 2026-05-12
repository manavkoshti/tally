<?php

namespace App\Http\Controllers\Api\V1\Report;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\TallySyncLog;
use App\Models\Voucher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function dashboard(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $data = [
            'total_invoices' => Invoice::where('company_id', $companyId)->count(),
            'pending_sync' => Voucher::where('company_id', $companyId)->where('tally_sync_status', 'pending')->count(),
            'synced_today' => Voucher::where('company_id', $companyId)->where('tally_sync_status', 'synced')->whereDate('tally_synced_at', today())->count(),
            'failed_sync' => Voucher::where('company_id', $companyId)->where('tally_sync_status', 'failed')->count(),
            'monthly_sales' => Invoice::where('company_id', $companyId)->where('invoice_type', 'sales')->whereMonth('invoice_date', $month)->whereYear('invoice_date', $year)->sum('total_amount'),
            'monthly_purchase' => Invoice::where('company_id', $companyId)->where('invoice_type', 'purchase')->whereMonth('invoice_date', $month)->whereYear('invoice_date', $year)->sum('total_amount'),
            'monthly_gst' => Invoice::where('company_id', $companyId)->whereMonth('invoice_date', $month)->whereYear('invoice_date', $year)->sum('total_gst_amount'),
        ];

        return $this->successResponse($data);
    }

    public function salesReport(Request $request): JsonResponse
    {
        $request->validate(['from_date' => 'nullable|date', 'to_date' => 'nullable|date']);
        $companyId = $request->user()->company_id;

        $invoices = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'sales')
            ->when($request->from_date, fn($q) => $q->whereDate('invoice_date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('invoice_date', '<=', $request->to_date))
            ->with('partyLedger:id,name')
            ->get(['id', 'invoice_number', 'invoice_date', 'party_name', 'taxable_amount', 'total_gst_amount', 'total_amount', 'tally_sync_status', 'party_ledger_id']);

        $summary = [
            'total_taxable' => $invoices->sum('taxable_amount'),
            'total_gst' => $invoices->sum('total_gst_amount'),
            'total_amount' => $invoices->sum('total_amount'),
            'count' => $invoices->count(),
        ];

        return $this->successResponse(compact('invoices', 'summary'));
    }

    public function purchaseReport(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $invoices = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'purchase')
            ->when($request->from_date, fn($q) => $q->whereDate('invoice_date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('invoice_date', '<=', $request->to_date))
            ->with('partyLedger:id,name')
            ->get(['id', 'invoice_number', 'invoice_date', 'party_name', 'taxable_amount', 'total_gst_amount', 'total_amount', 'tally_sync_status', 'party_ledger_id']);

        $summary = [
            'total_taxable' => $invoices->sum('taxable_amount'),
            'total_gst' => $invoices->sum('total_gst_amount'),
            'total_amount' => $invoices->sum('total_amount'),
            'count' => $invoices->count(),
        ];

        return $this->successResponse(compact('invoices', 'summary'));
    }

    public function gstReport(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $gst = Invoice::where('company_id', $companyId)
            ->whereMonth('invoice_date', $month)
            ->whereYear('invoice_date', $year)
            ->select('invoice_type',
                DB::raw('SUM(taxable_amount) as taxable_amount'),
                DB::raw('SUM(cgst_amount) as cgst'),
                DB::raw('SUM(sgst_amount) as sgst'),
                DB::raw('SUM(igst_amount) as igst'),
                DB::raw('SUM(total_gst_amount) as total_gst')
            )
            ->groupBy('invoice_type')
            ->get();

        return $this->successResponse($gst);
    }

    public function tallyFailedReport(Request $request): JsonResponse
    {
        $logs = TallySyncLog::where('company_id', $request->user()->company_id)
            ->where('status', 'failed')
            ->with('voucher:id,voucher_number,voucher_type', 'ledger:id,name')
            ->latest()
            ->paginate(20);

        return $this->paginatedResponse($logs);
    }

    public function auditReport(Request $request): JsonResponse
    {
        $logs = \App\Models\AuditLog::where('company_id', $request->user()->company_id)
            ->with('user:id,name')
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->latest()
            ->paginate(20);

        return $this->paginatedResponse($logs);
    }
}
