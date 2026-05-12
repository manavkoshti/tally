<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Services\Tally\TallySyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    use ApiResponse;

    public function __construct(private TallySyncService $tallySyncService) {}

    public function index(Request $request): JsonResponse
    {
        $ledgers = Ledger::where('company_id', $request->user()->company_id)
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', '%' . $request->search . '%')
                   ->orWhere('gstin', 'like', '%' . $request->search . '%');
            }))
            ->paginate($request->per_page ?? 15);

        return $this->paginatedResponse($ledgers);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:debtor,creditor,bank,cash,income,expense,gst,other',
            'gstin' => 'nullable|string|max:15',
            'pan' => 'nullable|string|max:10',
            'address' => 'nullable|string',
            'state' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'opening_balance' => 'nullable|numeric',
            'opening_balance_type' => 'nullable|in:debit,credit',
        ]);

        $ledger = Ledger::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return $this->successResponse($ledger, 'Ledger created.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ledger = Ledger::where('company_id', $request->user()->company_id)->findOrFail($id);
        return $this->successResponse($ledger);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ledger = Ledger::where('company_id', $request->user()->company_id)->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:debtor,creditor,bank,cash,income,expense,gst,other',
            'gstin' => 'nullable|string|max:15',
        ]);

        $ledger->update($request->validated());
        return $this->successResponse($ledger, 'Ledger updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ledger = Ledger::where('company_id', $request->user()->company_id)->findOrFail($id);
        $ledger->delete();
        return $this->successResponse(null, 'Ledger deleted.');
    }

    public function syncToTally(Request $request, int $id): JsonResponse
    {
        $ledger = Ledger::where('company_id', $request->user()->company_id)->findOrFail($id);
        $result = $this->tallySyncService->syncLedger($ledger);
        return $this->successResponse($result, $result['success'] ? 'Ledger synced to Tally.' : 'Sync failed.');
    }
}
