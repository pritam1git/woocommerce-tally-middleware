<?php

namespace App\Services\Tally;

class TallyLedgerService
{
    public function create($ledgerName)
    {
        $company = config('tally.company_name');
        $ledgerName = htmlspecialchars($ledgerName);

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

                      <LEDGER NAME="$ledgerName" ACTION="Create">

                        <NAME>$ledgerName</NAME>

                        <PARENT>Sundry Debtors</PARENT>

                      </LEDGER>

                    </TALLYMESSAGE>

                  </REQUESTDATA>

                  </IMPORTDATA>
                </BODY>

                </ENVELOPE>
                XML;

        return app(TallyClient::class)
            ->send($xml, 'LEDGER');
    }
}