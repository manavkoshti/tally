<?php

namespace App\Services\Tally;

use App\Models\Voucher;
use App\Models\Ledger;
use App\Models\TallySyncLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TallySyncService
{
    public function __construct(
        private TallyXmlGenerator $xmlGenerator
    ) {}

    public function syncVoucher(Voucher $voucher): array
    {
        $voucher->load('company');

        $xml = $this->xmlGenerator->generateVoucherXml($voucher);
        $host = $voucher->company->tally_host ?? 'localhost';
        $port = $voucher->company->tally_port ?? 9000;

        $log = TallySyncLog::create([
            'company_id' => $voucher->company_id,
            'voucher_id' => $voucher->id,
            'sync_type' => 'voucher',
            'status' => 'pending',
            'xml_request' => $xml,
            'tally_host' => $host,
            'tally_port' => $port,
        ]);

        try {
            $response = $this->sendToTally($xml, $host, $port);
            $success = $this->parseResponse($response);

            $log->update([
                'status' => $success ? 'success' : 'failed',
                'xml_response' => $response,
                'response_code' => 200,
                'synced_at' => now(),
            ]);

            if ($success) {
                $voucher->update([
                    'tally_sync_status' => 'synced',
                    'tally_synced_at' => now(),
                ]);
            } else {
                $voucher->update(['tally_sync_status' => 'failed']);
            }

            return ['success' => $success, 'response' => $response, 'log_id' => $log->id];

        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            $log->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'synced_at' => now(),
            ]);
            $voucher->update(['tally_sync_status' => 'failed']);

            return ['success' => false, 'error' => $errorMessage, 'log_id' => $log->id];
        }
    }

    public function syncLedger(Ledger $ledger): array
    {
        $ledger->load('company');

        $xml = $this->xmlGenerator->generateLedgerXml($ledger);
        $host = $ledger->company->tally_host ?? 'localhost';
        $port = $ledger->company->tally_port ?? 9000;

        $log = TallySyncLog::create([
            'company_id' => $ledger->company_id,
            'ledger_id' => $ledger->id,
            'sync_type' => 'ledger',
            'status' => 'pending',
            'xml_request' => $xml,
            'tally_host' => $host,
            'tally_port' => $port,
        ]);

        try {
            $response = $this->sendToTally($xml, $host, $port);
            $success = $this->parseResponse($response);

            $log->update([
                'status' => $success ? 'success' : 'failed',
                'xml_response' => $response,
                'synced_at' => now(),
            ]);

            if ($success) {
                $ledger->update(['synced_to_tally' => true, 'tally_synced_at' => now()]);
            }

            return ['success' => $success, 'response' => $response];

        } catch (RequestException $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendToTally(string $xml, string $host, int $port): string
    {
        $client = new Client(['timeout' => 30]);
        $response = $client->post("http://{$host}:{$port}", [
            'body' => $xml,
            'headers' => ['Content-Type' => 'application/xml'],
        ]);
        return (string) $response->getBody();
    }

    private function parseResponse(string $response): bool
    {
        return str_contains(strtolower($response), '<lineerror>') === false
            && (str_contains(strtolower($response), 'success') || str_contains($response, 'Created'));
    }
}
