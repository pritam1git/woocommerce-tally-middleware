<?php

namespace App\Services\Tally;

class TallyStockItemService
{
    public function create($itemName)
    {
        $itemName = htmlspecialchars($itemName);
        $company = config('tally.company_name');
        $group = config('tally.default_stock_item_group');

        $unit = config('tally.default_unit');

        $xml = <<<XML
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

    <TALLYMESSAGE>

      <STOCKITEM NAME="$itemName" ACTION="Create">

        <NAME>$itemName</NAME>

        <PARENT>$group</PARENT>

        <BASEUNITS>$unit</BASEUNITS>

      </STOCKITEM>

    </TALLYMESSAGE>

   </REQUESTDATA>

  </IMPORTDATA>

 </BODY>

</ENVELOPE>
XML;

        return app(TallyClient::class)
            ->send($xml, 'STOCK ITEM');
    }
}