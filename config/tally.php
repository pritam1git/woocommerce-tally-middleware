<?php

return [

    'tally_url' => env('TALLY_URL'),

    'company_name' => env('TALLY_COMPANY', 'Upjau'),

    'company_state' => env('TALLY_COMPANY_STATE', 'UP'),

    /*
    |--------------------------------------------------------------------------
    | SALES LEDGERS
    |--------------------------------------------------------------------------
    */

    'ledger_nil_rated' => 'NIL RATED SALES A/C',

    'ledger_taxable_5' => 'TAXABLE SALE 5%',

    'ledger_taxable_12' => 'TAXABLE SALE 12%',

    'ledger_taxable_18' => 'TAXABLE SALE 18%',

    /*
    |--------------------------------------------------------------------------
    | GST LEDGERS
    |--------------------------------------------------------------------------
    */

    'ledger_cgst_25' => 'CGST 2.5%',

    'ledger_sgst_25' => 'SGST 2.5%',

    'ledger_igst_5' => 'IGST 5%',

    'ledger_cgst_6' => 'CGST 6%',

    'ledger_sgst_6' => 'SGST 6%',

    'ledger_igst_12' => 'IGST 12%',

    'ledger_cgst_9' => 'CGST 9%',

    'ledger_sgst_9' => 'SGST 9%',

    'ledger_igst_18' => 'IGST 18%',

    /*
    |--------------------------------------------------------------------------
    | OTHER LEDGERS
    |--------------------------------------------------------------------------
    */

    'shipping_ledger' => 'SHIPPING CHARGES COLLECTED',

    'discount_ledger' => 'DISCOUNT ALLOWED',

    'platform_ledger' => 'PLATFORM CHARGES COLLECTED',

    'roundoff_ledger' => 'ROUND OFF',

    /*
    |--------------------------------------------------------------------------
    | STOCK
    |--------------------------------------------------------------------------
    */

    'default_stock_item_group' => 'Stock',

    'default_unit' => 'PCS',

    /*
    |--------------------------------------------------------------------------
    | STOCK GROUP MAP
    |--------------------------------------------------------------------------
    */

    'stock_group_map' => [

        'BULBS' => 'BULBS',

        'FERTILIZER' => 'PLANT CARE',

        'TOOLS' => 'Stock',

        'SEEDS' => 'SEEDS',

        'PLANTS' => 'PLANTS',

        'SAPLINGS' => 'SAPLINGS',

        'COMBOS' => 'COMBOS',
    ],

];