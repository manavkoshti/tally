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

        $allLedgerEntries = '';
        foreach ($voucher->entries as $entry) {
            $amount = $entry->entry_type === 'debit' ? $entry->amount : -$entry->amount;
            $allLedgerEntries .= $this->buildLedgerEntry($entry->ledger->name, $amount, $entry->cgst_amount, $entry->sgst_amount, $entry->igst_amount);
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
          <SVCURRENTCOMPANY>{$voucher->company->tally_company_name}</SVCURRENTCOMPANY>
        </STATICVARIABLES>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          <VOUCHER VCHTYPE="{$voucherTypeName}" ACTION="Create">
            <DATE>{$date}</DATE>
            <VOUCHERTYPENAME>{$voucherTypeName}</VOUCHERTYPENAME>
            <VOUCHERNUMBER>{$voucher->voucher_number}</VOUCHERNUMBER>
            <NARRATION>{$this->escapeXml($voucher->narration)}</NARRATION>
            <ISINVOICE>Yes</ISINVOICE>
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
            <PARENT>{$this->getLedgerParentGroup($ledger->type)}</PARENT>
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

    private function buildLedgerEntry(string $ledgerName, float $amount, float $cgst, float $sgst, float $igst): string
    {
        $gstDetails = '';
        if ($cgst > 0) {
            $gstDetails .= "<TAXCLASSIFICATIONDETAILS.LIST><TAXCLASSIFICATIONNAME>CGST</TAXCLASSIFICATIONNAME><AMOUNT>{$cgst}</AMOUNT></TAXCLASSIFICATIONDETAILS.LIST>";
        }
        if ($sgst > 0) {
            $gstDetails .= "<TAXCLASSIFICATIONDETAILS.LIST><TAXCLASSIFICATIONNAME>SGST/UTGST</TAXCLASSIFICATIONNAME><AMOUNT>{$sgst}</AMOUNT></TAXCLASSIFICATIONDETAILS.LIST>";
        }
        if ($igst > 0) {
            $gstDetails .= "<TAXCLASSIFICATIONDETAILS.LIST><TAXCLASSIFICATIONNAME>IGST</TAXCLASSIFICATIONNAME><AMOUNT>{$igst}</AMOUNT></TAXCLASSIFICATIONDETAILS.LIST>";
        }

        return <<<XML
<ALLLEDGERENTRIES.LIST>
  <LEDGERNAME>{$this->escapeXml($ledgerName)}</LEDGERNAME>
  <AMOUNT>{$amount}</AMOUNT>
  {$gstDetails}
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
