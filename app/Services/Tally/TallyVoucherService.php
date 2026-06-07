<?php

namespace App\Services\Tally;

use Illuminate\Support\Facades\Log;

class TallyVoucherService
{
    public function create($order): bool
    {
        try {

            Log::info('TALLY VOUCHER START', [
                'order' => $order['order_number'] ?? null
            ]);

            $company = htmlspecialchars(
                trim(config('tally.company_name'))
            );

            $customer = htmlspecialchars(
                trim(
                    $order['customer_name']
                    ?? 'Walk-in Customer'
                )
            );

            $companyState = strtoupper(
                trim(
                    config('tally.company_state', 'UP')
                )
            );

            $customerState = strtoupper(
                trim(
                    $order['state']
                    ?? $companyState
                )
            );

            $isInterState = (
                $companyState !== $customerState
            );

            $date = now()->format('Ymd');

            $voucherNumber = htmlspecialchars(
                trim(
                    $order['order_number']
                    ?? ('ORD-' . time())
                )
            );

            $addressParts = array_filter([
                trim($order['address'] ?? ''),
                trim($order['city'] ?? ''),
                trim($order['state'] ?? ''),
                trim($order['pincode'] ?? ''),
            ]);

            $customerAddress = htmlspecialchars(
                implode(', ', $addressParts)
            );

            $master = app(
                TallyMasterService::class
            );

            /*
            |--------------------------------------------------------------------------
            | CUSTOMER LEDGER
            |--------------------------------------------------------------------------
            */

            $customerLedger = $master
                ->ensureCustomerLedger(
                    $customer,
                    $company
                );

            if (!$customerLedger) {

                Log::error(
                    'CUSTOMER LEDGER FAILED'
                );

                return false;
            }

            $inventoryEntries = '';

            $ledgerEntries = '';

            $partyAmount = 0;

            $gstSummary = [];

            /*
            |--------------------------------------------------------------------------
            | ITEMS
            |--------------------------------------------------------------------------
            */

            foreach ($order['items'] ?? [] as $item) {

                $calc = app(
                    GstCalculatorService::class
                )->calculate($item);

                $gstRate = round(
                    (float) ($calc['gst_rate'] ?? 0),
                    2
                );

                $baseAmount = round(
                    (float) ($calc['base_amount'] ?? 0),
                    2
                );

                $gstAmount = round(
                    (float) ($calc['gst_amount'] ?? 0),
                    2
                );

                $finalAmount = round(
                    (float) ($calc['final_amount'] ?? 0),
                    2
                );

                $partyAmount += $finalAmount;

                $name = htmlspecialchars(
                    trim(
                        $item['name']
                        ?? 'Product'
                    )
                );

                $qty = round(
                    (float) (
                        $item['qty'] ?? 1
                    ),
                    2
                );

                $rate = round(
                    (float) (
                        $item['rate'] ?? 0
                    ),
                    2
                );

                $unit = strtoupper(
                    trim(
                        $item['unit']
                        ?? config(
                            'tally.default_unit',
                            'PCS'
                        )
                    )
                );

                if (
                    empty($unit)
                    || is_numeric($unit)
                ) {
                    $unit = 'PCS';
                }

                $group = trim(
                    $item['group']
                    ?? config(
                        'tally.default_stock_item_group',
                        'Stock'
                    )
                );

                $hsn = trim(
                    (string) (
                        $item['hsn_code']
                        ?? ''
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | ENSURE STOCK ITEM
                |--------------------------------------------------------------------------
                */

                $stockCreated = $master->ensureStockItem(
                    $name,
                    $company,
                    $unit,
                    $group,
                    $hsn
                );

                if (!$stockCreated) {

                    Log::error(
                        'STOCK ITEM FAILED',
                        [
                            'item' => $name
                        ]
                    );

                    return false;
                }

                /*
                |--------------------------------------------------------------------------
                | SALES LEDGER
                |--------------------------------------------------------------------------
                */

                $salesLedger = htmlspecialchars(
                    $this->salesLedger(
                        $gstRate
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | INVENTORY ENTRY
                |--------------------------------------------------------------------------
                */

                $inventoryEntries .= "

<ALLINVENTORYENTRIES.LIST>

    <STOCKITEMNAME>{$name}</STOCKITEMNAME>

    <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>

    <RATE>" . number_format($rate, 2, '.', '') . "/{$unit}</RATE>

    <AMOUNT>" . number_format($baseAmount, 2, '.', '') . "</AMOUNT>

    <ACTUALQTY>" . number_format($qty, 2, '.', '') . " {$unit}</ACTUALQTY>

    <BILLEDQTY>" . number_format($qty, 2, '.', '') . " {$unit}</BILLEDQTY>

    <ACCOUNTINGALLOCATIONS.LIST>

        <LEDGERNAME>{$salesLedger}</LEDGERNAME>

        <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>

        <AMOUNT>" . number_format($baseAmount, 2, '.', '') . "</AMOUNT>

    </ACCOUNTINGALLOCATIONS.LIST>

</ALLINVENTORYENTRIES.LIST>";

                /*
                |--------------------------------------------------------------------------
                | GST SUMMARY
                |--------------------------------------------------------------------------
                */

                if (!isset($gstSummary[$gstRate])) {

                    $gstSummary[$gstRate] = 0;
                }

                $gstSummary[$gstRate] += $gstAmount;
            }

            /*
            |--------------------------------------------------------------------------
            | GST ENTRIES
            |--------------------------------------------------------------------------
            */

            foreach ($gstSummary as $rate => $taxAmount) {

                if ((float) $rate <= 0) {
                    continue;
                }

                $ledgerEntries .= $this->gstEntries(
                    (float) $rate,
                    (float) $taxAmount,
                    $isInterState
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SHIPPING
            |--------------------------------------------------------------------------
            */

            $shipping = round(
                (float) (
                    $order['shipping_total']
                    ?? 0
                ),
                2
            );

            if ($shipping > 0) {

                $partyAmount += $shipping;

                $ledgerEntries .= $this->ledgerEntry(
                    config('tally.shipping_ledger'),
                    $shipping
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PLATFORM FEE
            |--------------------------------------------------------------------------
            */

            $platform = round(
                (float) (
                    $order['platform_fee']
                    ?? 0
                ),
                2
            );

            if ($platform > 0) {

                $partyAmount += $platform;

                $ledgerEntries .= $this->ledgerEntry(
                    config('tally.platform_ledger'),
                    $platform
                );
            }

            /*
            |--------------------------------------------------------------------------
            | DISCOUNT
            |--------------------------------------------------------------------------
            */

            $discount = round(
                (float) (
                    $order['discount_total']
                    ?? 0
                ),
                2
            );

            if ($discount > 0) {

                $partyAmount -= $discount;

                $ledgerEntries .= "

<LEDGERENTRIES.LIST>

    <LEDGERNAME>DISCOUNT ALLOWED</LEDGERNAME>

    <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

    <AMOUNT>-" . number_format($discount, 2, '.', '') . "</AMOUNT>

</LEDGERENTRIES.LIST>";
            }

            /*
            |--------------------------------------------------------------------------
            | PARTY LEDGER
            |--------------------------------------------------------------------------
            */

            $partyAmount = round(
                $partyAmount,
                2
            );

            $ledgerEntries .= "

<LEDGERENTRIES.LIST>

    <LEDGERNAME>{$customer}</LEDGERNAME>

    <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>

    <ISPARTYLEDGER>Yes</ISPARTYLEDGER>

    <AMOUNT>-" . number_format($partyAmount, 2, '.', '') . "</AMOUNT>

</LEDGERENTRIES.LIST>";

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

<SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>

</STATICVARIABLES>

</REQUESTDESC>

<REQUESTDATA>

<TALLYMESSAGE xmlns:UDF='TallyUDF'>

<VOUCHER
VCHTYPE='Sales'
ACTION='Create'
OBJVIEW='Invoice Voucher View'>

<DATE>{$date}</DATE>

<VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>

<VOUCHERNUMBER>{$voucherNumber}</VOUCHERNUMBER>

<REFERENCE>{$voucherNumber}</REFERENCE>

<PARTYNAME>{$customer}</PARTYNAME>

<PARTYLEDGERNAME>{$customer}</PARTYLEDGERNAME>

<PERSISTEDVIEW>Invoice Voucher View</PERSISTEDVIEW>

<ISINVOICE>Yes</ISINVOICE>

<HASINVENTORYENTRIES>Yes</HASINVENTORYENTRIES>

<BASICBASEPARTYNAME>{$customer}</BASICBASEPARTYNAME>

<BASICBUYERNAME>{$customer}</BASICBUYERNAME>

<BASICORDERREF>{$voucherNumber}</BASICORDERREF>

<NARRATION>WooCommerce Order {$voucherNumber}</NARRATION>

<BASICBUYERADDRESS.LIST TYPE='String'>

<ADDRESS>{$customerAddress}</ADDRESS>

</BASICBUYERADDRESS.LIST>

{$inventoryEntries}

{$ledgerEntries}

</VOUCHER>

</TALLYMESSAGE>

</REQUESTDATA>

</IMPORTDATA>

</BODY>

</ENVELOPE>";

            Log::info('FINAL VOUCHER XML', [

                'xml' => $xml
            ]);

            $response = app(
                TallyClient::class
            )->send($xml, 'VOUCHER');

            if (!$response) {

                Log::error(
                    'VOUCHER CREATION FAILED',
                    [
                        'order' => $voucherNumber
                    ]
                );

                return false;
            }

            Log::info(
                'VOUCHER SUCCESS',
                [
                    'order' => $voucherNumber
                ]
            );

            return true;

        } catch (\Throwable $e) {

            Log::error('VOUCHER ERROR', [

                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }

    private function salesLedger(
        float $rate
    ): string {

        return match ((int) $rate) {

            5 => config(
                'tally.ledger_taxable_5'
            ),

            12 => config(
                'tally.ledger_taxable_12'
            ),

            18 => config(
                'tally.ledger_taxable_18'
            ),

            default => config(
                'tally.ledger_nil_rated'
            ),
        };
    }

    private function ledgerEntry(
        string $ledger,
        float $amount
    ): string {

        $ledger = htmlspecialchars(
            trim($ledger)
        );

        $amount = round($amount, 2);

        return "

<LEDGERENTRIES.LIST>

    <LEDGERNAME>{$ledger}</LEDGERNAME>

    <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>

    <AMOUNT>" . number_format($amount, 2, '.', '') . "</AMOUNT>

</LEDGERENTRIES.LIST>";
    }

    private function gstEntries(
        float $rate,
        float $amount,
        bool $interState
    ): string {

        if ($interState) {

            $ledger = match ((int) $rate) {

                5 => config('tally.ledger_igst_5'),

                12 => config('tally.ledger_igst_12'),

                18 => config('tally.ledger_igst_18'),

                default => null,
            };

            if (!$ledger) {
                return '';
            }

            return $this->ledgerEntry(
                $ledger,
                $amount
            );
        }

        $half = round(
            $amount / 2,
            2
        );

        return match ((int) $rate) {

            5 =>

                $this->ledgerEntry(
                    config('tally.ledger_cgst_25'),
                    $half
                )

                .

                $this->ledgerEntry(
                    config('tally.ledger_sgst_25'),
                    $half
                ),

            12 =>

                $this->ledgerEntry(
                    config('tally.ledger_cgst_6'),
                    $half
                )

                .

                $this->ledgerEntry(
                    config('tally.ledger_sgst_6'),
                    $half
                ),

            18 =>

                $this->ledgerEntry(
                    config('tally.ledger_cgst_9'),
                    $half
                )

                .

                $this->ledgerEntry(
                    config('tally.ledger_sgst_9'),
                    $half
                ),

            default => '',
        };
    }
}