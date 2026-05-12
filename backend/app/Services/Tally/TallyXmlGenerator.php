<?php

namespace App\Services\Tally;

use App\Models\Voucher;
use App\Models\Ledger;

class TallyXmlGenerator
{
    public function generateVoucherXml(Voucher $voucher): string
    {
        $voucher->load('entries.ledger', 'company');

        $voucherTypeName = ucfirst($voucher->voucher_type);
        $date = $voucher->voucher_date->format('Ymd');
        $companyName = $this->escapeXml($voucher->company->tally_company_name);
        $narration = $this->escapeXml($voucher->narration ?? '');
        $voucherNumber = $this->escapeXml($voucher->voucher_number ?? '');

        $partyLedgerName = '';
        $partyEntry = $voucher->entries->first(fn($e) => in_array(optional($e->ledger)->type, ['debtor', 'creditor']));
        if ($partyEntry && $partyEntry->ledger) {
            $partyLedgerName = $this->escapeXml($partyEntry->ledger->name);
        }

        $isInvoice = 'No';
        $vchView = 'Accounting Voucher View';

        $allLedgerEntries = '';
        foreach ($voucher->entries as $entry) {
            $amount = $entry->entry_type === 'debit' ? $entry->amount : -$entry->amount;
            $isDeemed = $partyEntry && $entry->id === $partyEntry->id ? 'Yes' : 'No';
            $allLedgerEntries .= $this->buildLedgerEntry($entry->ledger->name, $amount, $entry->cgst_amount, $entry->sgst_amount, $entry->igst_amount, $isDeemed);
        }

        return <<<XML
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>Vouchers</REPORTNAME>
        <STATICVARIABLES>
          <SVCURRENTCOMPANY>{$companyName}</SVCURRENTCOMPANY>
        </STATICVARIABLES>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          <VOUCHER VCHTYPE="{$voucherTypeName}" ACTION="Create" OBJVIEW="{$vchView}">
            <DATE>{$date}</DATE>
            <EFFECTIVEDATE>{$date}</EFFECTIVEDATE>
            <VOUCHERTYPENAME>{$voucherTypeName}</VOUCHERTYPENAME>
            <VOUCHERNUMBER>{$voucherNumber}</VOUCHERNUMBER>
            <PARTYLEDGERNAME>{$partyLedgerName}</PARTYLEDGERNAME>
            <PARTYNAME>{$partyLedgerName}</PARTYNAME>
            <NARRATION>{$narration}</NARRATION>
            <PERSISTEDVIEW>{$vchView}</PERSISTEDVIEW>
            <ISINVOICE>{$isInvoice}</ISINVOICE>
            {$allLedgerEntries}
          </VOUCHER>
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>
XML;
    }

    public function generateLedgerXml(Ledger $ledger): string
    {
        $ledger->load('company');

        return <<<XML
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>All Masters</REPORTNAME>
        <STATICVARIABLES>
          <SVCURRENTCOMPANY>{$ledger->company->tally_company_name}</SVCURRENTCOMPANY>
        </STATICVARIABLES>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          <LEDGER NAME="{$this->escapeXml($ledger->name)}" ACTION="Create">
            <NAME>{$this->escapeXml($ledger->name)}</NAME>
            <PARENT>{$this->escapeXml($this->getLedgerParentGroup($ledger->type))}</PARENT>
            <GSTIN>{$this->escapeXml($ledger->gstin ?? '')}</GSTIN>
            <OPENINGBALANCE>{$ledger->opening_balance}</OPENINGBALANCE>
          </LEDGER>
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>
XML;
    }

    private function buildLedgerEntry(string $ledgerName, float $amount, float $cgst, float $sgst, float $igst, string $isDeemedPositive = 'No'): string
    {
        $name = $this->escapeXml($ledgerName);

        return <<<XML
<ALLLEDGERENTRIES.LIST>
  <LEDGERNAME>{$name}</LEDGERNAME>
  <ISDEEMEDPOSITIVE>{$isDeemedPositive}</ISDEEMEDPOSITIVE>
  <AMOUNT>{$amount}</AMOUNT>
</ALLLEDGERENTRIES.LIST>
XML;
    }

    private function getLedgerParentGroup(string $type): string
    {
        return match ($type) {
            'debtor' => 'Sundry Debtors',
            'creditor' => 'Sundry Creditors',
            'bank' => 'Bank Accounts',
            'cash' => 'Cash-in-Hand',
            'income' => 'Sales Accounts',
            'expense' => 'Indirect Expenses',
            'gst' => 'Duties & Taxes',
            default => 'Indirect Expenses',
        };
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
