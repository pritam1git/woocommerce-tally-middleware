<?php

namespace App\Services\Tally;

class TallyUnitService
{
    public function create($unit)
    {
        $unit = strtoupper($unit);
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

                      <UNIT NAME="$unit" ACTION="Create">

                        <NAME>$unit</NAME>

                        <ISSIMPLEUNIT>Yes</ISSIMPLEUNIT>

                      </UNIT>

                    </TALLYMESSAGE>

                  </REQUESTDATA>

                  </IMPORTDATA>
                </BODY>

                </ENVELOPE>
                XML;

        return app(TallyClient::class)
                ->send($xml, 'UNIT');
    }
}