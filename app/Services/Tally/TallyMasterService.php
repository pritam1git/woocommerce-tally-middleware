<?php

namespace App\Services\Tally;

class TallyMasterService
{
    /*
    |--------------------------------------------------------------------------
    | CUSTOMER LEDGER
    |--------------------------------------------------------------------------
    */

    public function ensureCustomerLedger(
        string $customer,
        string $company
    ): bool {

        $customer = htmlspecialchars(
            trim($customer)
        );

        $company = htmlspecialchars(
            trim($company)
        );

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

<SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>

</STATICVARIABLES>

</REQUESTDESC>

<REQUESTDATA>

<TALLYMESSAGE xmlns:UDF='TallyUDF'>

<LEDGER NAME='{$customer}' ACTION='Create'>

<NAME>{$customer}</NAME>

<PARENT>Sundry Debtors</PARENT>

</LEDGER>

</TALLYMESSAGE>

</REQUESTDATA>

</IMPORTDATA>

</BODY>

</ENVELOPE>";

        return app(TallyClient::class)
            ->send($xml, 'LEDGER');
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK ITEM
    |--------------------------------------------------------------------------
    */

    public function ensureStockItem(
        string $name,
        string $company,
        string $unit,
        string $group,
        string $hsnCode
    ): bool {

        $groupMap = config(
            'tally.stock_group_map',
            []
        );

        $group = $groupMap[$group]
            ?? config(
                'tally.default_stock_item_group',
                'Stock'
            );

        $unit = strtoupper(
            trim($unit ?: 'PCS')
        );

        if ($unit === '0') {
            $unit = 'PCS';
        }

        $name = htmlspecialchars($name);

        $group = htmlspecialchars($group);

        $unit = htmlspecialchars($unit);

        $hsnCode = htmlspecialchars($hsnCode);

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

<SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>

</STATICVARIABLES>

</REQUESTDESC>

<REQUESTDATA>

<TALLYMESSAGE xmlns:UDF='TallyUDF'>

<STOCKITEM NAME='{$name}' ACTION='Create'>

<NAME>{$name}</NAME>

<PARENT>{$group}</PARENT>

<BASEUNITS>{$unit}</BASEUNITS>

<ADDITIONALUNITS></ADDITIONALUNITS>

<GSTAPPLICABLE>&#4; Applicable</GSTAPPLICABLE>

<GSTTYPEOFSUPPLY>Goods</GSTTYPEOFSUPPLY>

<TAXABILITY>Taxable</TAXABILITY>

<HSNCODE>{$hsnCode}</HSNCODE>

<OPENINGBALANCE>0</OPENINGBALANCE>

<ISBATCHWISEON>No</ISBATCHWISEON>

<ISCOSTCENTRESON>No</ISCOSTCENTRESON>

</STOCKITEM>

</TALLYMESSAGE>

</REQUESTDATA>

</IMPORTDATA>

</BODY>

</ENVELOPE>";

        return app(TallyClient::class)
            ->send($xml, 'STOCK');
    }
}