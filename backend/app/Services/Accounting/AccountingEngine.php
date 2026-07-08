<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use Illuminate\Support\Facades\DB;

class AccountingEngine
{
    public function processInvoice(Invoice $invoice): Voucher
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->update(['accounting_status' => 'processing']);

            $partyLedger = $this->resolvePartyLedger($invoice);
            $invoice->update(['party_ledger_id' => $partyLedger->id]);

            $voucherType = $this->determineVoucherType($invoice->invoice_type);
            $entries = $this->buildEntries($invoice, $partyLedger);

            $voucherNumber = $invoice->invoice_number ?: $this->generateVoucherNumber($invoice, $voucherType);

            $voucher = Voucher::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'created_by' => $invoice->user_id,
                'voucher_number' => $voucherNumber,
                'voucher_type' => $voucherType,
                'voucher_date' => $invoice->invoice_date,
                'amount' => $invoice->total_amount,
                'narration' => $invoice->narration ?? "Being {$invoice->invoice_type} invoice {$voucherNumber}",
                'status' => 'approved',
            ]);

            if (empty($invoice->invoice_number)) {
                $invoice->update(['invoice_number' => $voucherNumber]);
            }

            foreach ($entries as $index => $entry) {
                VoucherEntry::create([
                    'voucher_id' => $voucher->id,
                    'ledger_id' => $entry['ledger_id'],
                    'entry_type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'cgst_amount' => $entry['cgst'] ?? 0,
                    'sgst_amount' => $entry['sgst'] ?? 0,
                    'igst_amount' => $entry['igst'] ?? 0,
                    'narration' => $entry['narration'] ?? null,
                    'sort_order' => $index + 1,
                ]);
            }

            $invoice->update(['accounting_status' => 'completed']);

            return $voucher->load('entries.ledger');
        });
    }

    private function resolvePartyLedger(Invoice $invoice): Ledger
    {
        if ($invoice->party_ledger_id) {
            $existing = Ledger::find($invoice->party_ledger_id);
            if ($existing) {
                return $existing;
            }
        }

        $ledgerType = in_array($invoice->invoice_type, ['sales', 'receipt']) ? 'debtor' : 'creditor';

        $ledger = null;
        if ($invoice->party_name || $invoice->party_gstin) {
            $ledger = Ledger::where('company_id', $invoice->company_id)
                ->where(function ($q) use ($invoice) {
                    if ($invoice->party_name) {
                        $q->where('name', 'like', '%' . $invoice->party_name . '%');
                    }
                    if ($invoice->party_gstin) {
                        $q->orWhere('gstin', $invoice->party_gstin);
                    }
                })
                ->first();
        }

        if (!$ledger) {
            $ledger = Ledger::create([
                'company_id' => $invoice->company_id,
                'name' => $invoice->party_name ?? 'Unknown Party',
                'type' => $ledgerType,
                'gstin' => $invoice->party_gstin,
            ]);
        }

        return $ledger;
    }

    private function generateVoucherNumber(Invoice $invoice, string $voucherType): string
    {
        $prefix = match ($voucherType) {
            'sales' => 'SAL',
            'purchase' => 'PUR',
            'payment' => 'PAY',
            'receipt' => 'REC',
            'journal' => 'JV',
            default => 'VCH',
        };

        $count = Voucher::where('company_id', $invoice->company_id)
            ->where('voucher_type', $voucherType)
            ->count() + 1;

        return sprintf('%s/%s/%04d', $prefix, now()->format('Y-m'), $count);
    }

    private function determineVoucherType(string $invoiceType): string
    {
        return match ($invoiceType) {
            'sales' => 'sales',
            'purchase' => 'purchase',
            'payment' => 'payment',
            'receipt' => 'receipt',
            'expense' => 'journal',
            default => 'journal',
        };
    }

    private function buildEntries(Invoice $invoice, Ledger $partyLedger): array
    {
        $entries = [];

        switch ($invoice->invoice_type) {
            case 'sales':
                $entries = $this->buildSalesEntries($invoice, $partyLedger);
                break;
            case 'purchase':
                $entries = $this->buildPurchaseEntries($invoice, $partyLedger);
                break;
            case 'expense':
                $entries = $this->buildExpenseEntries($invoice, $partyLedger);
                break;
            default:
                $entries = $this->buildJournalEntries($invoice, $partyLedger);
        }

        return $entries;
    }

    private function buildSalesEntries(Invoice $invoice, Ledger $partyLedger): array
    {
        $salesLedger = $this->getOrCreateLedger($invoice->company_id, 'Sales Account', 'income');
        $entries = [];

        // Debit: Customer/Debtor
        $entries[] = [
            'ledger_id' => $partyLedger->id,
            'type' => 'debit',
            'amount' => $invoice->total_amount,
            'narration' => 'Being sales to ' . $invoice->party_name,
        ];

        // Credit: Sales Account
        $entries[] = [
            'ledger_id' => $salesLedger->id,
            'type' => 'credit',
            'amount' => $invoice->taxable_amount,
        ];

        // GST entries
        $entries = array_merge($entries, $this->buildGstCreditEntries($invoice));

        return $entries;
    }

    private function buildPurchaseEntries(Invoice $invoice, Ledger $partyLedger): array
    {
        $purchaseLedger = $this->getOrCreateLedger($invoice->company_id, 'Purchase Account', 'expense');
        $entries = [];

        // Debit: Purchase Account
        $entries[] = [
            'ledger_id' => $purchaseLedger->id,
            'type' => 'debit',
            'amount' => $invoice->taxable_amount,
        ];

        // Debit: Input GST
        $entries = array_merge($entries, $this->buildGstDebitEntries($invoice));

        // Credit: Vendor/Creditor
        $entries[] = [
            'ledger_id' => $partyLedger->id,
            'type' => 'credit',
            'amount' => $invoice->total_amount,
            'narration' => 'Being purchase from ' . $invoice->party_name,
        ];

        return $entries;
    }

    private function buildExpenseEntries(Invoice $invoice, Ledger $partyLedger): array
    {
        $expenseLedger = $this->getOrCreateLedger($invoice->company_id, 'Miscellaneous Expenses', 'expense');

        return [
            ['ledger_id' => $expenseLedger->id, 'type' => 'debit', 'amount' => $invoice->taxable_amount],
            ...($invoice->is_interstate
                ? $this->buildIgstDebitEntries($invoice)
                : $this->buildCgstSgstDebitEntries($invoice)),
            ['ledger_id' => $partyLedger->id, 'type' => 'credit', 'amount' => $invoice->total_amount],
        ];
    }

    private function buildJournalEntries(Invoice $invoice, Ledger $partyLedger): array
    {
        $sundryLedger = $this->getOrCreateLedger($invoice->company_id, 'Sundry Account', 'other');
        return [
            ['ledger_id' => $partyLedger->id, 'type' => 'debit', 'amount' => $invoice->total_amount],
            ['ledger_id' => $sundryLedger->id, 'type' => 'credit', 'amount' => $invoice->total_amount],
        ];
    }

    private function buildGstCreditEntries(Invoice $invoice): array
    {
        $entries = [];
        if ($invoice->is_interstate && $invoice->igst_amount > 0) {
            $igstLedger = $this->getOrCreateLedger($invoice->company_id, 'Output IGST', 'gst');
            $entries[] = ['ledger_id' => $igstLedger->id, 'type' => 'credit', 'amount' => $invoice->igst_amount, 'igst' => $invoice->igst_amount];
        } else {
            if ($invoice->cgst_amount > 0) {
                $cgstLedger = $this->getOrCreateLedger($invoice->company_id, 'Output CGST', 'gst');
                $entries[] = ['ledger_id' => $cgstLedger->id, 'type' => 'credit', 'amount' => $invoice->cgst_amount, 'cgst' => $invoice->cgst_amount];
            }
            if ($invoice->sgst_amount > 0) {
                $sgstLedger = $this->getOrCreateLedger($invoice->company_id, 'Output SGST', 'gst');
                $entries[] = ['ledger_id' => $sgstLedger->id, 'type' => 'credit', 'amount' => $invoice->sgst_amount, 'sgst' => $invoice->sgst_amount];
            }
        }
        return $entries;
    }

    private function buildGstDebitEntries(Invoice $invoice): array
    {
        $entries = [];
        if ($invoice->is_interstate && $invoice->igst_amount > 0) {
            $igstLedger = $this->getOrCreateLedger($invoice->company_id, 'Input IGST', 'gst');
            $entries[] = ['ledger_id' => $igstLedger->id, 'type' => 'debit', 'amount' => $invoice->igst_amount, 'igst' => $invoice->igst_amount];
        } else {
            if ($invoice->cgst_amount > 0) {
                $cgstLedger = $this->getOrCreateLedger($invoice->company_id, 'Input CGST', 'gst');
                $entries[] = ['ledger_id' => $cgstLedger->id, 'type' => 'debit', 'amount' => $invoice->cgst_amount, 'cgst' => $invoice->cgst_amount];
            }
            if ($invoice->sgst_amount > 0) {
                $sgstLedger = $this->getOrCreateLedger($invoice->company_id, 'Input SGST', 'gst');
                $entries[] = ['ledger_id' => $sgstLedger->id, 'type' => 'debit', 'amount' => $invoice->sgst_amount, 'sgst' => $invoice->sgst_amount];
            }
        }
        return $entries;
    }

    private function buildIgstDebitEntries(Invoice $invoice): array
    {
        if ($invoice->igst_amount <= 0) return [];
        $igstLedger = $this->getOrCreateLedger($invoice->company_id, 'Input IGST', 'gst');
        return [['ledger_id' => $igstLedger->id, 'type' => 'debit', 'amount' => $invoice->igst_amount]];
    }

    private function buildCgstSgstDebitEntries(Invoice $invoice): array
    {
        $entries = [];
        if ($invoice->cgst_amount > 0) {
            $cgst = $this->getOrCreateLedger($invoice->company_id, 'Input CGST', 'gst');
            $entries[] = ['ledger_id' => $cgst->id, 'type' => 'debit', 'amount' => $invoice->cgst_amount];
        }
        if ($invoice->sgst_amount > 0) {
            $sgst = $this->getOrCreateLedger($invoice->company_id, 'Input SGST', 'gst');
            $entries[] = ['ledger_id' => $sgst->id, 'type' => 'debit', 'amount' => $invoice->sgst_amount];
        }
        return $entries;
    }

    private function getOrCreateLedger(int $companyId, string $name, string $type): Ledger
    {
        return Ledger::firstOrCreate(
            ['company_id' => $companyId, 'name' => $name],
            ['type' => $type, 'is_active' => true]
        );
    }
}
