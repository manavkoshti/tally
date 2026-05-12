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
        $voucher->load('company', 'entries.ledger');

        foreach ($voucher->entries as $entry) {
            $ledger = $entry->ledger;
            if (!$ledger || $ledger->synced_to_tally) {
                continue;
            }
            $result = $this->syncLedger($ledger);
            if (!$result['success']) {
                $voucher->update(['tally_sync_status' => 'failed']);
                $this->propagateStatusToInvoice($voucher, 'failed');
                return [
                    'success' => false,
                    'error' => "Ledger sync failed for '{$ledger->name}': " . ($result['error'] ?? 'Unknown error'),
                ];
            }
        }

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
                $this->propagateStatusToInvoice($voucher, 'synced');
            } else {
                $voucher->update(['tally_sync_status' => 'failed']);
                $this->propagateStatusToInvoice($voucher, 'failed');
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
            $this->propagateStatusToInvoice($voucher, 'failed');

            return ['success' => false, 'error' => $errorMessage, 'log_id' => $log->id];
        }
    }

    private function propagateStatusToInvoice(Voucher $voucher, string $status): void
    {
        if ($voucher->invoice_id) {
            \App\Models\Invoice::where('id', $voucher->invoice_id)->update(['tally_sync_status' => $status]);
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
        $lower = strtolower($response);
        if (str_contains($lower, '<lineerror>')) {
            return false;
        }
        if (preg_match('/<created>(\d+)<\/created>/i', $response, $m) && (int) $m[1] > 0) {
            return true;
        }
        if (preg_match('/<altered>(\d+)<\/altered>/i', $response, $m) && (int) $m[1] > 0) {
            return true;
        }
        return str_contains($lower, 'success');
    }
}
