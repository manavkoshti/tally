<?php

namespace App\Http\Controllers\Api\V1\Voucher;

use App\Http\Controllers\Controller;
use App\Jobs\SyncVoucherToTally;
use App\Models\Voucher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $vouchers = Voucher::where('company_id', $request->user()->company_id)
            ->with('creator:id,name', 'invoice:id,invoice_number,party_name')
            ->when($request->type, fn($q) => $q->where('voucher_type', $request->type))
            ->when($request->status, fn($q) => $q->where('tally_sync_status', $request->status))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->paginatedResponse($vouchers);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $voucher = Voucher::where('company_id', $request->user()->company_id)
            ->with('entries.ledger', 'invoice', 'tallySyncLogs')
            ->findOrFail($id);

        return $this->successResponse($voucher);
    }

    public function syncToTally(Request $request, int $id): JsonResponse
    {
        $voucher = Voucher::where('company_id', $request->user()->company_id)->findOrFail($id);

        if ($voucher->tally_sync_status === 'synced') {
            return $this->errorResponse('Voucher already synced to Tally.');
        }

        SyncVoucherToTally::dispatch($voucher);

        return $this->successResponse(null, 'Tally sync initiated.');
    }

    public function bulkSync(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $vouchers = Voucher::where('company_id', $request->user()->company_id)
            ->whereIn('id', $request->ids)
            ->where('tally_sync_status', '!=', 'synced')
            ->get();

        foreach ($vouchers as $voucher) {
            SyncVoucherToTally::dispatch($voucher);
        }

        return $this->successResponse(['queued' => $vouchers->count()], "Sync initiated for {$vouchers->count()} vouchers.");
    }

    public function downloadXml(Request $request, int $id): \Illuminate\Http\Response
    {
        $voucher = Voucher::where('company_id', $request->user()->company_id)
            ->with('entries.ledger', 'company')
            ->findOrFail($id);

        $generator = app(\App\Services\Tally\TallyXmlGenerator::class);
        $xml = $generator->generateVoucherXml($voucher);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"voucher-{$voucher->voucher_number}.xml\"",
        ]);
    }
}
