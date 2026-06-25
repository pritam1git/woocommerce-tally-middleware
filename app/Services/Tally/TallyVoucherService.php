<?php

namespace App\Services\Tally;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TallyVoucherService
{
    protected TallyClient $client;

    public function __construct(TallyClient $client)
    {
        $this->client = $client;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE VOUCHER
    |--------------------------------------------------------------------------
    */

    public function create(array $order): bool
    {
        try {

            Log::info('TALLY VOUCHER SERVICE START', [
                'order_number'   => $order['number'] ?? null,
                'invoice_number' => $order['invoice_number'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | META DATA
            |--------------------------------------------------------------------------
            */

            $metaData = [];

            foreach ($order['meta_data'] ?? [] as $meta) {
                if (!empty($meta['key'])) {
                    $metaData[$meta['key']] = $meta['value'] ?? null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | BILLING
            |--------------------------------------------------------------------------
            */

            $billing = $order['billing'] ?? [];

            $customerName = trim(
                ($billing['first_name'] ?? '') . ' ' .
                ($billing['last_name'] ?? '')
            );

            if (empty($customerName)) {
                $customerName = 'Walk-in Customer';
            }

            /*
            |--------------------------------------------------------------------------
            | INVOICE
            |--------------------------------------------------------------------------
            */

            $invoiceNumber =
                $order['invoice_number']
                ?? $metaData['_wcpdf_invoice_number']
                ?? $metaData['_invoice_number']
                ?? ('INV-' . ($order['number'] ?? time()));

            $buyerOrderNumber =
                $order['number']
                ?? $order['id']
                ?? '';

            /*
            |--------------------------------------------------------------------------
            | DATE
            |--------------------------------------------------------------------------
            */

            $voucherDate = now()->format('Ymd');

            if (!empty($order['date_created'])) {
                try {
                    $voucherDate = Carbon::parse($order['date_created'])->format('Ymd');
                } catch (\Throwable $e) {
                    $voucherDate = now()->format('Ymd');
                }
            }

            /*
            |--------------------------------------------------------------------------
            | ADDRESS
            |--------------------------------------------------------------------------
            */

            $address1    = trim($billing['address_1'] ?? '');
            $address2    = trim($billing['address_2'] ?? '');
            $fullAddress = trim($address1 . ' ' . $address2);
            $city        = trim($billing['city'] ?? '');
            $pincode     = trim($billing['postcode'] ?? '');
            $phone       = trim($billing['phone'] ?? '');
            $email       = trim($billing['email'] ?? '');

            /*
            |--------------------------------------------------------------------------
            | STATE
            |--------------------------------------------------------------------------
            */

            $customerState = $this->normalizeState($billing['state'] ?? 'UP');
            $companyState  = $this->normalizeState(config('tally.company_state', 'UP'));
            $isInterState  = strtolower($customerState) !== strtolower($companyState);

            Log::info('TALLY GST TYPE', [
                'customer_state' => $customerState,
                'company_state'  => $companyState,
                'is_inter_state' => $isInterState,
            ]);

            /*
            |--------------------------------------------------------------------------
            | PAYMENT
            |--------------------------------------------------------------------------
            */

            $paymentMethod =
                $order['payment_method_title']
                ?? $order['payment_method']
                ?? 'Online';

            /*
            |--------------------------------------------------------------------------
            | TOTALS
            |--------------------------------------------------------------------------
            */

            $shippingTotal = (float) ($order['shipping_total'] ?? 0);
            $platformFee   = (float) ($order['platform_fee']   ?? 0);
            $finalTotal    = (float) ($order['total']          ?? 0);

            /*
            |--------------------------------------------------------------------------
            | CREATE PARTY LEDGER
            |--------------------------------------------------------------------------
            */

            $this->createPartyLedger(
                $customerName,
                $customerState,
                $fullAddress,
                $city,
                $pincode,
                $phone,
                $email
            );

            /*
            |--------------------------------------------------------------------------
            | ITEMS LOOP — Inventory entries + GST summary
            |--------------------------------------------------------------------------
            */

            $inventoryEntries  = '';
            $ledgerEntries     = '';
            $gstSummary        = [];
            $totalItemsTaxable = 0;
            $totalGstAmount    = 0;

            foreach ($order['line_items'] ?? [] as $item) {

                $itemName = trim($item['name'] ?? 'Product');

                $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                if ($qty <= 0) $qty = 1;

                $unit = config('tally.default_unit', 'PCS');

                $taxableAmount = (float) ($item['subtotal'] ?? $item['total'] ?? 0);
                $taxAmount     = (float) ($item['subtotal_tax'] ?? $item['total_tax'] ?? 0);

                $totalItemsTaxable += $taxableAmount;
                $totalGstAmount    += $taxAmount;

                /*
                |--------------------------------------------------------------------------
                | GST RATE — normalize to slab
                |--------------------------------------------------------------------------
                */

                $gstRate = 0;

                if ($taxableAmount > 0 && $taxAmount > 0) {
                    $rawRate = ($taxAmount / $taxableAmount) * 100;

                    if ($rawRate <= 1)         $gstRate = 0;
                    elseif ($rawRate <= 5.5)   $gstRate = 5;
                    elseif ($rawRate <= 12.5)  $gstRate = 12;
                    elseif ($rawRate <= 18.5)  $gstRate = 18;
                    elseif ($rawRate <= 28.5)  $gstRate = 28;
                    else                       $gstRate = round($rawRate, 2);
                }

                $salesLedger = $this->getSalesLedger($gstRate);

                $this->createSimpleLedger($salesLedger, 'Sales Accounts');
                $this->createStockItem($itemName, $unit);

                /*
                |--------------------------------------------------------------------------
                | GST SUMMARY
                |--------------------------------------------------------------------------
                */

                if ($gstRate > 0 && $taxAmount > 0) {

                    if ($isInterState) {

                        $gstKey = 'igst_' . (int) $gstRate;
                        $gstSummary[$gstKey] = ($gstSummary[$gstKey] ?? 0) + $taxAmount;

                    } else {

                        $halfRate    = $gstRate / 2;
                        $halfRateStr = str_replace('.', '', rtrim(number_format($halfRate, 1, '.', ''), '0'));

                        $cgstKey = 'cgst_' . $halfRateStr;
                        $sgstKey = 'sgst_' . $halfRateStr;

                        $gstSummary[$cgstKey] = ($gstSummary[$cgstKey] ?? 0) + ($taxAmount / 2);
                        $gstSummary[$sgstKey] = ($gstSummary[$sgstKey] ?? 0) + ($taxAmount / 2);
                    }
                }

                $rate = round($taxableAmount / $qty, 2);

                /*
                |--------------------------------------------------------------------------
                | INVENTORY ENTRY XML
                |--------------------------------------------------------------------------
                |
                | TALLY SALES VOUCHER SIGN RULE:
                |
                | "To" entries (Credit) → ISDEEMEDPOSITIVE=Yes, AMOUNT=negative
                | "By" entries (Debit)  → ISDEEMEDPOSITIVE=No,  AMOUNT=positive
                |
                | Stock items are CREDIT side in sales → Yes, negative
                |
                */

                $inventoryEntries .= "

                                    <ALLINVENTORYENTRIES.LIST>

                                        <STOCKITEMNAME>{$this->xml($itemName)}</STOCKITEMNAME>

                                        <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

                                        <RATE>{$rate}/{$unit}</RATE>

                                        <AMOUNT>-" . round($taxableAmount, 2) . "</AMOUNT>

                                        <ACTUALQTY>{$qty} {$unit}</ACTUALQTY>

                                        <BILLEDQTY>{$qty} {$unit}</BILLEDQTY>

                                        <ACCOUNTINGALLOCATIONS.LIST>

                                            <LEDGERNAME>{$this->xml($salesLedger)}</LEDGERNAME>

                                            <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

                                            <AMOUNT>-" . round($taxableAmount, 2) . "</AMOUNT>

                                        </ACCOUNTINGALLOCATIONS.LIST>

                                    </ALLINVENTORYENTRIES.LIST>";
            }

            /*
            |--------------------------------------------------------------------------
            | BALANCE DIFFERENCE CALCULATION
            |--------------------------------------------------------------------------
            |
            | Credit side total = items + GST + shipping + platform
            | finalTotal = WooCommerce paid amount (already discount cut ke baad)
            |
            | balanceDiff = creditTotal - finalTotal
            |
            | Agar balanceDiff > 0 → discount tha → DISCOUNT ALLOWED credit mein jayega
            | Agar balanceDiff < 0 → extra charge → ROUND OFF credit mein jayega
            | Agar balanceDiff = 0 → perfect balance, koi adjustment nahi
            |
            | Example:
            |   Items=847, GST=99.54, Platform=5 → creditTotal=951.54
            |   finalTotal (paid) = 934.61
            |   balanceDiff = 951.54 - 934.61 = 16.93 → DISCOUNT ALLOWED = 16.93 credit
            |
            */

            $creditTotal = round(
                $totalItemsTaxable + $totalGstAmount + $shippingTotal + $platformFee,
                2
            );

            $balanceDiff = round(
                                    bcsub(
                                        number_format($creditTotal,2,'.',''),
                                        number_format($finalTotal,2,'.',''),
                                        2
                                    ),
                                    2
                                );

            Log::info('TALLY BALANCE CALCULATION', [
                'items_taxable' => $totalItemsTaxable,
                'gst_total'     => $totalGstAmount,
                'shipping'      => $shippingTotal,
                'platform_fee'  => $platformFee,
                'credit_total'  => $creditTotal,
                'final_total'   => $finalTotal,
                'balance_diff'  => $balanceDiff,
                'adjustment'    => $balanceDiff > 0.01
                    ? 'DISCOUNT ALLOWED credit: ' . $balanceDiff
                    : ($balanceDiff < -0.01 ? 'ROUND OFF credit: ' . abs($balanceDiff) : 'NO ADJUSTMENT'),
            ]);

            /*
            |--------------------------------------------------------------------------
            | LEDGER ENTRIES
            |--------------------------------------------------------------------------
            |
            | TALLY SALES VOUCHER SIGN RULE:
            |
            | "By" Customer  (Debit)  → ISDEEMEDPOSITIVE=No,  AMOUNT=+positive
            | "To" Sales A/C (Credit) → ISDEEMEDPOSITIVE=Yes, AMOUNT=-negative
            | "To" GST       (Credit) → ISDEEMEDPOSITIVE=Yes, AMOUNT=-negative
            | "To" Shipping  (Credit) → ISDEEMEDPOSITIVE=Yes, AMOUNT=-negative
            | "To" Platform  (Credit) → ISDEEMEDPOSITIVE=Yes, AMOUNT=-negative
            | "To" Discount  (Credit) → ISDEEMEDPOSITIVE=Yes, AMOUNT=-negative ← CREDIT SIDE
            |
            */

            /*
            | 1. CUSTOMER — Debit (By)
            */

            $ledgerEntries .= "

            <LEDGERENTRIES.LIST>

            <LEDGERNAME>{$this->xml($customerName)}</LEDGERNAME>

            <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>

            <ISPARTYLEDGER>Yes</ISPARTYLEDGER>

            <AMOUNT>" . round($finalTotal,2) . "</AMOUNT>

            </LEDGERENTRIES.LIST>";

            /*
            | 2. GST — Credit (To)
            */

            foreach ($gstSummary as $key => $amount) {

                if ($amount <= 0) continue;

                $ledgerName = config('tally.ledger_' . $key);
                if (!$ledgerName) continue;

                $this->createSimpleLedger($ledgerName, 'Duties & Taxes');

                $ledgerEntries .= "

                                <LEDGERENTRIES.LIST>

                                    <LEDGERNAME>{$this->xml($ledgerName)}</LEDGERNAME>

                                    <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

                                    <AMOUNT>-" . round($amount, 2) . "</AMOUNT>

                                </LEDGERENTRIES.LIST>";
            }

            /*
            | 3. SHIPPING — Credit (To)
            */

            if ($shippingTotal > 0) {

                $shippingLedger = config('tally.shipping_ledger', 'SHIPPING CHARGES COLLECTED');
                $this->createSimpleLedger($shippingLedger, 'Indirect Incomes');

                $ledgerEntries .= "

                                <LEDGERENTRIES.LIST>

                                    <LEDGERNAME>{$this->xml($shippingLedger)}</LEDGERNAME>

                                    <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

                                    <AMOUNT>-" . round($shippingTotal, 2) . "</AMOUNT>

                                </LEDGERENTRIES.LIST>";
            }

            /*
            | 4. PLATFORM FEE — Credit (To)
            */

            if ($platformFee > 0) {

                $platformLedger = config('tally.platform_ledger', 'PLATFORM CHARGES COLLECTED');
                $this->createSimpleLedger($platformLedger, 'Indirect Incomes');

                $ledgerEntries .= "

                                    <LEDGERENTRIES.LIST>

                                        <LEDGERNAME>{$this->xml($platformLedger)}</LEDGERNAME>

                                        <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

                                        <AMOUNT>-" . round($platformFee, 2) . "</AMOUNT>

                                    </LEDGERENTRIES.LIST>";
            }

            /*
            |--------------------------------------------------------------------------
            | 5. DISCOUNT / ROUND-OFF AUTO BALANCE ENTRY
            |--------------------------------------------------------------------------
            |
            | balanceDiff > 0.01 → Discount tha → DISCOUNT ALLOWED Credit (To)
            |   ISDEEMEDPOSITIVE=Yes, Amount=-balanceDiff
            |   Ye credit side ko balance karta hai
            |
            | balanceDiff < -0.01 → Extra rounding charge → ROUND OFF Credit (To)
            |   ISDEEMEDPOSITIVE=Yes, Amount=+|balanceDiff| (ye debit reduce karega)
            |
            */

            if ($balanceDiff > 0.01) {

                $discountLedger = config('tally.discount_ledger', 'DISCOUNT ALLOWED');

                $this->createSimpleLedger($discountLedger, 'Indirect Expenses');

                $ledgerEntries .= "

                <LEDGERENTRIES.LIST>

                    <LEDGERNAME>{$this->xml($discountLedger)}</LEDGERNAME>

                    <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

                    <AMOUNT>" . round($balanceDiff, 2) . "</AMOUNT>

                </LEDGERENTRIES.LIST>";
            } elseif ($balanceDiff < -0.01) {

                $roundOffLedger = config('tally.roundoff_ledger', 'ROUND OFF');
                $this->createSimpleLedger($roundOffLedger, 'Indirect Expenses');

                $ledgerEntries .= "

                                <LEDGERENTRIES.LIST>

                                    <LEDGERNAME>{$this->xml($roundOffLedger)}</LEDGERNAME>

                                    <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>

                                    <AMOUNT>" . abs(round($balanceDiff, 2)) . "</AMOUNT>

                                </LEDGERENTRIES.LIST>";

                Log::info('ROUND OFF ENTRY ADDED', [
                    'ledger' => $roundOffLedger,
                    'amount' => abs($balanceDiff),
                    'side'   => 'Debit (By)',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | NARRATION
            |--------------------------------------------------------------------------
            */

            $narration = $this->xml(
                "Invoice No   : {$invoiceNumber}\n" .
                "Order No     : {$buyerOrderNumber}\n" .
                "Customer     : {$customerName}\n" .
                "Phone        : {$phone}\n" .
                "Email        : {$email}\n" .
                "Payment      : {$paymentMethod}\n" .
                "Address      : {$fullAddress}, {$city}, {$customerState} - {$pincode}"
            );

            /*
            |--------------------------------------------------------------------------
            | FINAL XML
            |--------------------------------------------------------------------------
            */

            $xml = "

                    <ENVELOPE>

                    <HEADER>

                    <TALLYREQUEST>Import Data</TALLYREQUEST>

                    </HEADER>

                    <BODY>

                    <IMPORTDATA>

                    <REQUESTDESC>

                    <REPORTNAME>Vouchers</REPORTNAME>

                    <STATICVARIABLES>

                    <SVCURRENTCOMPANY>" . $this->xml(config('tally.company_name')) . "</SVCURRENTCOMPANY>

                    </STATICVARIABLES>

                    </REQUESTDESC>

                    <REQUESTDATA>

                    <TALLYMESSAGE xmlns:UDF='TallyUDF'>

                    <VOUCHER
                        VCHTYPE='Sales'
                        ACTION='Create Alter'
                        OBJVIEW='Invoice Voucher View'
                    >

                        <DATE>{$voucherDate}</DATE>

                        <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>

                        <VOUCHERNUMBER>{$this->xml($invoiceNumber)}</VOUCHERNUMBER>

                        <REFERENCE>{$this->xml($invoiceNumber)}</REFERENCE>

                        <ORDERNO>{$this->xml($buyerOrderNumber)}</ORDERNO>

                        <PARTYLEDGERNAME>{$this->xml($customerName)}</PARTYLEDGERNAME>

                        <PERSISTEDVIEW>Invoice Voucher View</PERSISTEDVIEW>

                        <ISINVOICE>Yes</ISINVOICE>

                        <BASICBUYERNAME>{$this->xml($customerName)}</BASICBUYERNAME>

                        <BASICBASEPARTYNAME>{$this->xml($customerName)}</BASICBASEPARTYNAME>

                        <BUYERSORDERNO>{$this->xml($buyerOrderNumber)}</BUYERSORDERNO>

                        <BASICBUYERORDERNO>{$this->xml($buyerOrderNumber)}</BASICBUYERORDERNO>

                        <BASICORDERREF>{$this->xml($buyerOrderNumber . ' - Order No')}</BASICORDERREF>

                        <CONSIGNEENAME>{$this->xml($customerName)}</CONSIGNEENAME>

                        <BASICBUYERADDRESS>{$this->xml($fullAddress)}</BASICBUYERADDRESS>

                        <BASICBUYERSTATE>{$this->xml($customerState)}</BASICBUYERSTATE>

                        <BASICBUYERPINCODE>{$this->xml($pincode)}</BASICBUYERPINCODE>

                        <BASICBUYERCOUNTRY>India</BASICBUYERCOUNTRY>

                        <NARRATION>{$narration}</NARRATION>

                        {$inventoryEntries}

                        {$ledgerEntries}

                    </VOUCHER>

                    </TALLYMESSAGE>

                    </REQUESTDATA>

                    </IMPORTDATA>

                    </BODY>

                    </ENVELOPE>";

            Log::info('FINAL TALLY XML', ['xml' => $xml]);

            /*
            |--------------------------------------------------------------------------
            | SEND XML
            |--------------------------------------------------------------------------
            */

            $response = $this->client->send($xml, 'VOUCHER');

            Log::info('TALLY RESPONSE', ['response' => $response]);

            if (empty($response)) {
                Log::error('TALLY EMPTY RESPONSE');
                return false;
            }

            $success =
                str_contains($response, '<CREATED>1</CREATED>') ||
                str_contains($response, '<ALTERED>1</ALTERED>') ||
                str_contains($response, '<COMBINED>1</COMBINED>');

            if (!$success) {

                preg_match('/<LINEERROR>(.*?)<\/LINEERROR>/s', $response, $m);

                Log::error('TALLY VOUCHER FAILED', [
                    'error'         => html_entity_decode($m[1] ?? 'Unknown'),
                    'full_response' => $response,
                    'balance_check' => [
                        'credit_total' => $creditTotal,
                        'final_total'  => $finalTotal,
                        'balance_diff' => $balanceDiff,
                    ],
                ]);
            }

            return $success;

        } catch (\Throwable $e) {

            Log::error('TALLY VOUCHER EXCEPTION', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PARTY LEDGER
    |--------------------------------------------------------------------------
    */

    private function createPartyLedger(
        string $customerName,
        string $state,
        string $address,
        string $city,
        string $pincode,
        string $phone,
        string $email
    ): void {

        $xml = "

                    <ENVELOPE>

                    <HEADER>

                    <TALLYREQUEST>Import Data</TALLYREQUEST>

                    </HEADER>

                    <BODY>

                    <IMPORTDATA>

                    <REQUESTDESC>

                    <REPORTNAME>All Masters</REPORTNAME>

                    <STATICVARIABLES>

                    <SVCURRENTCOMPANY>" . $this->xml(config('tally.company_name')) . "</SVCURRENTCOMPANY>

                    </STATICVARIABLES>

                    </REQUESTDESC>

                    <REQUESTDATA>

                    <TALLYMESSAGE xmlns:UDF='TallyUDF'>

                    <LEDGER NAME='" . $this->xml($customerName) . "' ACTION='Create Alter'>

                        <NAME>" . $this->xml($customerName) . "</NAME>

                        <PARENT>Sundry Debtors</PARENT>

                        <MAILINGNAME>" . $this->xml($customerName) . "</MAILINGNAME>

                        <ADDRESS>" . $this->xml($address) . "</ADDRESS>

                        <ADDRESS>" . $this->xml($city) . "</ADDRESS>

                        <ADDRESS>" . $this->xml($pincode) . "</ADDRESS>

                        <LEDSTATENAME>" . $this->xml($state) . "</LEDSTATENAME>

                        <COUNTRYNAME>India</COUNTRYNAME>

                        <LEDGERPHONE>" . $this->xml($phone) . "</LEDGERPHONE>

                        <EMAIL>" . $this->xml($email) . "</EMAIL>

                        <ISBILLWISEON>Yes</ISBILLWISEON>

                    </LEDGER>

                    </TALLYMESSAGE>

                    </REQUESTDATA>

                    </IMPORTDATA>

                    </BODY>

                    </ENVELOPE>";

        $response = $this->client->send($xml, 'LEDGER');

        Log::info('PARTY LEDGER RESPONSE', [
            'customer' => $customerName,
            'response' => $response,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK ITEM
    |--------------------------------------------------------------------------
    */

    private function createStockItem(string $itemName, string $unit): void
    {
        $group = config('tally.default_stock_group', 'Primary');

        $xml = "

                <ENVELOPE>

                <HEADER>

                <TALLYREQUEST>Import Data</TALLYREQUEST>

                </HEADER>

                <BODY>

                <IMPORTDATA>

                <REQUESTDESC>

                <REPORTNAME>All Masters</REPORTNAME>

                <STATICVARIABLES>

                <SVCURRENTCOMPANY>" . $this->xml(config('tally.company_name')) . "</SVCURRENTCOMPANY>

                </STATICVARIABLES>

                </REQUESTDESC>

                <REQUESTDATA>

                <TALLYMESSAGE xmlns:UDF='TallyUDF'>

                <STOCKITEM NAME='" . $this->xml($itemName) . "' ACTION='Create Alter'>

                    <NAME>" . $this->xml($itemName) . "</NAME>

                    <PARENT>" . $this->xml($group) . "</PARENT>

                    <BASEUNITS>{$unit}</BASEUNITS>

                </STOCKITEM>

                </TALLYMESSAGE>

                </REQUESTDATA>

                </IMPORTDATA>

                </BODY>

                </ENVELOPE>";

        $response = $this->client->send($xml, 'STOCK');

        Log::info('STOCK ITEM RESPONSE', [
            'item'     => $itemName,
            'response' => $response,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPLE LEDGER
    |--------------------------------------------------------------------------
    */

    private function createSimpleLedger(string $ledgerName, string $parent): void
    {
        $xml = "

                <ENVELOPE>

                <HEADER>

                <TALLYREQUEST>Import Data</TALLYREQUEST>

                </HEADER>

                <BODY>

                <IMPORTDATA>

                <REQUESTDESC>

                <REPORTNAME>All Masters</REPORTNAME>

                <STATICVARIABLES>

                <SVCURRENTCOMPANY>" . $this->xml(config('tally.company_name')) . "</SVCURRENTCOMPANY>

                </STATICVARIABLES>

                </REQUESTDESC>

                <REQUESTDATA>

                <TALLYMESSAGE xmlns:UDF='TallyUDF'>

                <LEDGER NAME='" . $this->xml($ledgerName) . "' ACTION='Create'>

                    <NAME>" . $this->xml($ledgerName) . "</NAME>

                    <PARENT>" . $this->xml($parent) . "</PARENT>

                </LEDGER>

                </TALLYMESSAGE>

                </REQUESTDATA>

                </IMPORTDATA>

                </BODY>

                </ENVELOPE>";

        $this->client->send($xml, 'LEDGER');
    }

    /*
    |--------------------------------------------------------------------------
    | SALES LEDGER BY GST RATE
    |--------------------------------------------------------------------------
    */

    private function getSalesLedger(float $gstRate): string
    {
        return match ((int) $gstRate) {
            5       => config('tally.ledger_taxable_5',  'TAXABLE SALE 5%'),
            12      => config('tally.ledger_taxable_12', 'TAXABLE SALE 12%'),
            18      => config('tally.ledger_taxable_18', 'TAXABLE SALE 18%'),
            28      => config('tally.ledger_taxable_28', 'TAXABLE SALE 28%'),
            default => config('tally.ledger_nil_rated',  'NIL RATED SALES A/C'),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | XML ESCAPE
    |--------------------------------------------------------------------------
    */

    private function xml($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE STATE
    |--------------------------------------------------------------------------
    */

    private function normalizeState(string $state): string
    {
        $map = [
            'DL' => 'Delhi',
            'UP' => 'Uttar Pradesh',
            'HR' => 'Haryana',
            'RJ' => 'Rajasthan',
            'PB' => 'Punjab',
            'MH' => 'Maharashtra',
            'GJ' => 'Gujarat',
            'KA' => 'Karnataka',
            'TN' => 'Tamil Nadu',
            'WB' => 'West Bengal',
            'BR' => 'Bihar',
            'MP' => 'Madhya Pradesh',
            'UK' => 'Uttarakhand',
            'HP' => 'Himachal Pradesh',
            'JK' => 'Jammu & Kashmir',
            'GA' => 'Goa',
            'CG' => 'Chhattisgarh',
            'JH' => 'Jharkhand',
            'OD' => 'Odisha',
            'KL' => 'Kerala',
            'AP' => 'Andhra Pradesh',
            'TS' => 'Telangana',
            'AS' => 'Assam',
            'MN' => 'Manipur',
            'ML' => 'Meghalaya',
            'MZ' => 'Mizoram',
            'NL' => 'Nagaland',
            'SK' => 'Sikkim',
            'TR' => 'Tripura',
            'AR' => 'Arunachal Pradesh',
            'CH' => 'Chandigarh',
            'PY' => 'Puducherry',
            'AN' => 'Andaman and Nicobar Islands',
        ];

        $state = strtoupper(trim($state));
        return $map[$state] ?? $state;
    }
}

