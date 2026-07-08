<?php

namespace App\Services\Tally;

use App\Models\Voucher;
use App\Models\Ledger;
use App\Models\TallySyncLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;

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
                $isConnectionError = $result['connection_error'] ?? false;
                $newStatus = $isConnectionError ? 'pending' : 'failed';

                $voucher->update(['tally_sync_status' => $newStatus]);
                $this->propagateStatusToInvoice($voucher, $newStatus);

                return [
                    'success' => false,
                    'connection_error' => $isConnectionError,
                    'error' => $isConnectionError
                        ? 'Tally is not reachable. Please make sure Tally is running and ODBC is enabled on port ' . ($voucher->company->tally_port ?? 9000) . '.'
                        : "Ledger sync failed for '{$ledger->name}': " . ($result['error'] ?? 'Unknown error'),
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
                'error_message' => $success ? null : $this->extractTallyError($response),
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

        } catch (ConnectException $e) {
            $errorMessage = "Tally is not reachable at {$host}:{$port}. Start Tally and enable ODBC.";
            $log->update([
                'status' => 'failed',
                'error_message' => $errorMessage . ' [' . $e->getMessage() . ']',
                'synced_at' => now(),
            ]);
            // Treat as transient: keep status pending so retries/manual sync can resume.
            $voucher->update(['tally_sync_status' => 'pending']);
            $this->propagateStatusToInvoice($voucher, 'pending');

            return [
                'success' => false,
                'connection_error' => true,
                'error' => $errorMessage,
                'log_id' => $log->id,
            ];
        } catch (GuzzleException | \Throwable $e) {
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
                'error_message' => $success ? null : $this->extractTallyError($response),
                'synced_at' => now(),
            ]);

            if ($success) {
                $ledger->update(['synced_to_tally' => true, 'tally_synced_at' => now()]);
            }

            return ['success' => $success, 'response' => $response];

        } catch (ConnectException $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => "Tally unreachable at {$host}:{$port} [" . $e->getMessage() . ']',
                'synced_at' => now(),
            ]);
            return [
                'success' => false,
                'connection_error' => true,
                'error' => "Tally not reachable at {$host}:{$port}",
            ];
        } catch (GuzzleException | \Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'synced_at' => now(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function testConnection(string $host = 'localhost', int $port = 9000): array
    {
        $xml = <<<XML
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Export</TALLYREQUEST>
    <TYPE>Data</TYPE>
    <ID>List of Companies</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVEXPORTFORMAT>$SysName:XML</SVEXPORTFORMAT>
      </STATICVARIABLES>
    </DESC>
  </BODY>
</ENVELOPE>
XML;

        try {
            $client = new Client(['timeout' => 5, 'connect_timeout' => 5]);
            $response = $client->post("http://{$host}:{$port}", [
                'body' => $xml,
                'headers' => ['Content-Type' => 'application/xml'],
            ]);
            return [
                'success' => true,
                'message' => 'Tally is reachable',
                'response' => (string) $response->getBody(),
            ];
        } catch (ConnectException $e) {
            return [
                'success' => false,
                'message' => "Cannot connect to Tally at {$host}:{$port}. Start Tally, open a company, and enable ODBC Server (F12 > Advanced > Tally.ERP 9 acts as Server set to Yes).",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendToTally(string $xml, string $host, int $port): string
    {
        $client = new Client(['timeout' => 30, 'connect_timeout' => 5]);
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

    private function extractTallyError(string $response): ?string
    {
        if (preg_match('/<LINEERROR>(.*?)<\/LINEERROR>/is', $response, $m)) {
            return trim(strip_tags($m[1]));
        }
        if (preg_match('/<EXCEPTIONS>(.*?)<\/EXCEPTIONS>/is', $response, $m)) {
            return trim(strip_tags($m[1]));
        }
        return null;
    }
}
