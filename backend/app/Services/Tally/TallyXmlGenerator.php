<?php

namespace App\Services\Tally;

use App\Models\Voucher;
use App\Models\Ledger;

class TallyXmlGenerator
{
    public function generateVoucherXml(Voucher $voucher): string
    {
        $voucher->load('entries.ledger', 'company', 'invoice');

        $voucherTypeName = $this->resolveVoucherTypeName($voucher->voucher_type);
        $date = $voucher->voucher_date->format('Ymd');
        $companyName = $this->escapeXml($voucher->company->tally_company_name ?? $voucher->company->name);
        $narration = $this->escapeXml($voucher->narration ?? '');

        $invoice = $voucher->invoice;
        $voucherNumber = $voucher->voucher_number
            ?? ($invoice ? $invoice->invoice_number : null)
            ?? ('VCH-' . $voucher->id);
        $voucherNumberX = $this->escapeXml($voucherNumber);

        $referenceNumber = $invoice && $invoice->invoice_number
            ? $this->escapeXml($invoice->invoice_number)
            : $voucherNumberX;

        $partyEntry = $voucher->entries->first(
            fn ($e) => in_array(optional($e->ledger)->type, ['debtor', 'creditor'])
        );
        $partyLedgerName = '';
        if ($partyEntry && $partyEntry->ledger) {
            $partyLedgerName = $this->escapeXml($partyEntry->ledger->name);
        } elseif ($invoice && $invoice->party_name) {
            $partyLedgerName = $this->escapeXml($invoice->party_name);
        }

        $isInvoice = in_array($voucher->voucher_type, ['sales', 'purchase']) ? 'Yes' : 'No';
        $vchView = $isInvoice === 'Yes' ? 'Invoice Voucher View' : 'Accounting Voucher View';

        $placeOfSupply = $invoice && $invoice->place_of_supply
            ? $this->escapeXml($invoice->place_of_supply)
            : $this->escapeXml($voucher->company->state ?? '');

        $remoteId = $this->escapeXml($voucher->uuid ?? '');

        $allLedgerEntries = '';
        foreach ($voucher->entries as $entry) {
            if (! $entry->ledger) {
                continue;
            }

            $isPartyRow = $partyEntry && $entry->id === $partyEntry->id;
            $allLedgerEntries .= $this->buildLedgerEntry(
                $entry->ledger,
                (float) $entry->amount,
                $entry->entry_type,
                $isPartyRow,
                $isPartyRow ? $referenceNumber : null
            );
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
          <VOUCHER REMOTEID="{$remoteId}" VCHTYPE="{$voucherTypeName}" ACTION="Create" OBJVIEW="{$vchView}">
            <DATE>{$date}</DATE>
            <EFFECTIVEDATE>{$date}</EFFECTIVEDATE>
            <REFERENCEDATE>{$date}</REFERENCEDATE>
            <REFERENCE>{$referenceNumber}</REFERENCE>
            <VOUCHERTYPENAME>{$voucherTypeName}</VOUCHERTYPENAME>
            <VOUCHERNUMBER>{$voucherNumberX}</VOUCHERNUMBER>
            <PARTYLEDGERNAME>{$partyLedgerName}</PARTYLEDGERNAME>
            <PARTYNAME>{$partyLedgerName}</PARTYNAME>
            <CSTFORMISSUETYPE/>
            <CSTFORMRECVTYPE/>
            <FBTPAYMENTTYPE>Default</FBTPAYMENTTYPE>
            <PERSISTEDVIEW>{$vchView}</PERSISTEDVIEW>
            <PLACEOFSUPPLY>{$placeOfSupply}</PLACEOFSUPPLY>
            <NARRATION>{$narration}</NARRATION>
            <ISINVOICE>{$isInvoice}</ISINVOICE>
            <ISCANCELLED>No</ISCANCELLED>
            <ISDELETED>No</ISDELETED>
            <ISOPTIONAL>No</ISOPTIONAL>
            <ISPOSTDATED>No</ISPOSTDATED>
            <ASORIGINAL>No</ASORIGINAL>
            <USETRACKINGNUMBER>No</USETRACKINGNUMBER>
            <HASDISCOUNTS>No</HASDISCOUNTS>
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

        $name = $this->escapeXml($ledger->name);
        $parent = $this->escapeXml($this->getLedgerParentGroup($ledger->type, $ledger->name));
        $gstin = $this->escapeXml($ledger->gstin ?? '');
        $pan = $this->escapeXml($ledger->pan ?? '');
        $state = $this->escapeXml($ledger->state ?? $ledger->company->state ?? '');
        $email = $this->escapeXml($ledger->email ?? '');
        $phone = $this->escapeXml($ledger->phone ?? '');
        $address = $this->escapeXml($ledger->address ?? '');
        $companyName = $this->escapeXml($ledger->company->tally_company_name ?? $ledger->company->name);
        $opening = number_format((float) $ledger->opening_balance, 2, '.', '');
        if ($ledger->opening_balance_type === 'credit') {
            $opening = '-' . $opening;
        }

        $isPartyLedger = in_array($ledger->type, ['debtor', 'creditor']);
        $isGstLedger = $ledger->type === 'gst';
        $isSalesOrPurchase = in_array($ledger->type, ['income', 'expense']);

        $billWise = $isPartyLedger ? 'Yes' : 'No';
        $gstRegistrationType = $isPartyLedger && $gstin ? 'Regular' : 'Unregistered';
        $gstApplicable = ($isPartyLedger || $isGstLedger || $isSalesOrPurchase) ? 'Applicable' : 'Not Applicable';

        $gstBlock = '';
        if ($isGstLedger) {
            $taxType = $this->resolveGstTaxType($ledger->name);
            $gstBlock = <<<XML
            <TAXTYPE>GST</TAXTYPE>
            <GSTDUTYHEAD>{$taxType}</GSTDUTYHEAD>
            <RATEOFTAXCALCULATION>0</RATEOFTAXCALCULATION>
XML;
        }

        $partyBlock = '';
        if ($isPartyLedger) {
            $partyBlock = <<<XML
            <ISBILLWISEON>{$billWise}</ISBILLWISEON>
            <LEDGERMAILINGNAMES.LIST>
              <MAILINGNAME>{$name}</MAILINGNAME>
            </LEDGERMAILINGNAMES.LIST>
            <ADDRESS.LIST TYPE="String">
              <ADDRESS>{$address}</ADDRESS>
            </ADDRESS.LIST>
XML;
        }

        $addressLine = $address !== '' ? "<ADDRESS>{$address}</ADDRESS>" : '';

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
          <SVCURRENTCOMPANY>{$companyName}</SVCURRENTCOMPANY>
        </STATICVARIABLES>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          <LEDGER NAME="{$name}" RESERVEDNAME="" ACTION="Create">
            <NAME>{$name}</NAME>
            <PARENT>{$parent}</PARENT>
            <LANGUAGENAME.LIST>
              <NAME.LIST TYPE="String">
                <NAME>{$name}</NAME>
              </NAME.LIST>
              <LANGUAGEID>1033</LANGUAGEID>
            </LANGUAGENAME.LIST>
            <ISBILLWISEON>{$billWise}</ISBILLWISEON>
            <ISCOSTCENTRESON>No</ISCOSTCENTRESON>
            <AFFECTSSTOCK>No</AFFECTSSTOCK>
            <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
            <GSTAPPLICABLE>{$gstApplicable}</GSTAPPLICABLE>
            <GSTREGISTRATIONTYPE>{$gstRegistrationType}</GSTREGISTRATIONTYPE>
            <PARTYGSTIN>{$gstin}</PARTYGSTIN>
            <GSTIN>{$gstin}</GSTIN>
            <INCOMETAXNUMBER>{$pan}</INCOMETAXNUMBER>
            <LEDSTATENAME>{$state}</LEDSTATENAME>
            <COUNTRYOFRESIDENCE>India</COUNTRYOFRESIDENCE>
            <EMAIL>{$email}</EMAIL>
            <LEDGERPHONE>{$phone}</LEDGERPHONE>
            <LEDGERMOBILE>{$phone}</LEDGERMOBILE>
            {$addressLine}
            <OPENINGBALANCE>{$opening}</OPENINGBALANCE>
            {$gstBlock}
            {$partyBlock}
          </LEDGER>
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>
XML;
    }

    private function buildLedgerEntry(
        Ledger $ledger,
        float $amount,
        string $entryType,
        bool $isPartyEntry,
        ?string $billReference
    ): string {
        $name = $this->escapeXml($ledger->name);
        $absAmount = number_format(abs($amount), 2, '.', '');

        // Tally convention: debit -> negative AMOUNT with ISDEEMEDPOSITIVE=Yes
        //                   credit -> positive AMOUNT with ISDEEMEDPOSITIVE=No
        if ($entryType === 'debit') {
            $isDeemedPositive = 'Yes';
            $signedAmount = '-' . $absAmount;
        } else {
            $isDeemedPositive = 'No';
            $signedAmount = $absAmount;
        }

        $isPartyTag = $isPartyEntry ? 'Yes' : 'No';

        $billAllocations = '';
        if ($isPartyEntry && $billReference) {
            $billRef = $this->escapeXml($billReference);
            $billAllocations = <<<XML
            <BILLALLOCATIONS.LIST>
              <NAME>{$billRef}</NAME>
              <BILLTYPE>New Ref</BILLTYPE>
              <TYPEOFREF>New Ref</TYPEOFREF>
              <AMOUNT>{$signedAmount}</AMOUNT>
            </BILLALLOCATIONS.LIST>
XML;
        }

        return <<<XML
<ALLLEDGERENTRIES.LIST>
            <LEDGERNAME>{$name}</LEDGERNAME>
            <GSTCLASS/>
            <ISDEEMEDPOSITIVE>{$isDeemedPositive}</ISDEEMEDPOSITIVE>
            <LEDGERFROMITEM>No</LEDGERFROMITEM>
            <REMOVEZEROENTRIES>No</REMOVEZEROENTRIES>
            <ISPARTYLEDGER>{$isPartyTag}</ISPARTYLEDGER>
            <ISLASTDEEMEDPOSITIVE>{$isDeemedPositive}</ISLASTDEEMEDPOSITIVE>
            <AMOUNT>{$signedAmount}</AMOUNT>
            {$billAllocations}
          </ALLLEDGERENTRIES.LIST>
XML;
    }

    private function resolveVoucherTypeName(string $type): string
    {
        return match ($type) {
            'sales' => 'Sales',
            'purchase' => 'Purchase',
            'payment' => 'Payment',
            'receipt' => 'Receipt',
            'contra' => 'Contra',
            'journal' => 'Journal',
            default => ucfirst($type),
        };
    }

    private function resolveGstTaxType(string $ledgerName): string
    {
        $lower = strtolower($ledgerName);
        if (str_contains($lower, 'igst')) {
            return 'Integrated Tax';
        }
        if (str_contains($lower, 'cgst')) {
            return 'Central Tax';
        }
        if (str_contains($lower, 'sgst')) {
            return 'State Tax';
        }
        if (str_contains($lower, 'cess')) {
            return 'Cess';
        }
        return 'Central Tax';
    }

    private function getLedgerParentGroup(string $type, string $name = ''): string
    {
        if ($type === 'expense') {
            return stripos($name, 'purchase') !== false ? 'Purchase Accounts' : 'Indirect Expenses';
        }
        if ($type === 'income') {
            return stripos($name, 'sales') !== false ? 'Sales Accounts' : 'Indirect Incomes';
        }
        return match ($type) {
            'debtor' => 'Sundry Debtors',
            'creditor' => 'Sundry Creditors',
            'bank' => 'Bank Accounts',
            'cash' => 'Cash-in-Hand',
            'gst' => 'Duties & Taxes',
            default => 'Sundry Creditors',
        };
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
