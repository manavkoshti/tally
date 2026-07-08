<?php

namespace App\Http\Controllers\Api\V1\Tally;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Tally\TallySyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyController extends Controller
{
    use ApiResponse;

    public function __construct(private TallySyncService $tallySyncService) {}

    public function testConnection(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $company = Company::findOrFail($companyId);

        $host = $request->input('host') ?? $company->tally_host ?? 'localhost';
        $port = (int) ($request->input('port') ?? $company->tally_port ?? 9000);

        $result = $this->tallySyncService->testConnection($host, $port);

        return $this->successResponse([
            'reachable' => $result['success'],
            'host' => $host,
            'port' => $port,
            'message' => $result['message'] ?? null,
        ]);
    }
}
