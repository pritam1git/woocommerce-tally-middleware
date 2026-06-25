<?php

namespace App\Services\Tally;

class TallyGroupService
{
    public function create($group)
    {
        $group = htmlspecialchars($group);

        $company = config('tally.company_name');

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

                        <STOCKGROUP NAME="{$group}" ACTION="Create">

                          <NAME>{$group}</NAME>

                          <PARENT></PARENT>

                        </STOCKGROUP>

                      </TALLYMESSAGE>

                    </REQUESTDATA>

                    </IMPORTDATA>

                  </BODY>

                  </ENVELOPE>
                  XML;

      return app(TallyClient::class)
            ->send($xml, 'STOCK GROUP');
    }
}