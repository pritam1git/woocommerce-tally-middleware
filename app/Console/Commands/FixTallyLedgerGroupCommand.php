<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\TallyOrder;
use App\Services\Tally\TallyClient;

class FixTallyLedgerGroupCommand extends Command
{
    /*
    |--------------------------------------------------------------------------
    | SIGNATURE
    |--------------------------------------------------------------------------
    |
    | Usage:
    |   php artisan tally:fix-ledger-groups
    |       → Fix all success orders' sales ledger groups
    |
    |   php artisan tally:fix-ledger-groups --dry-run
    |       → Preview what will be changed, no actual Tally calls
    |
    |   php artisan tally:fix-ledger-groups --ledger="NIL RATED SALES A/C"
    |       → Fix specific ledger only
    |
    */

    protected $signature = 'tally:fix-ledger-groups
        {--dry-run : Preview only, no Tally changes}
        {--ledger= : Fix specific ledger only (default: all sales ledgers)}
    ';

    protected $description = 'Fix sales ledger groups in Tally — moves ledgers to correct Sales Accounts group so amounts show on Credit side';

    protected TallyClient $client;

    public function __construct(TallyClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    public function handle(): int
    {
        $isDryRun      = $this->option('dry-run');
        $specificLedger = $this->option('ledger');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║   Tally Sales Ledger Group Fix               ║');
        $this->info('║   Moving ledgers to → Sales Accounts         ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE — No changes will be made to Tally');
            $this->info('');
        }

        /*
        |--------------------------------------------------------------------------
        | SALES LEDGERS TO FIX
        |--------------------------------------------------------------------------
        | These are all sales ledgers that need to be in "Sales Accounts" group
        | so that amounts show on Credit side in Tally ledger view
        */

        $salesLedgers = [];

        if ($specificLedger) {
            $salesLedgers = [$specificLedger];
        } else {
            $salesLedgers = array_filter([
                config('tally.ledger_nil_rated',   'NIL RATED SALES A/C'),
            ]);
        }

        $this->info('📋 Ledgers to fix:');
        foreach ($salesLedgers as $l) {
            $this->line("   → {$l}");
        }
        $this->info('');

        /*
        |--------------------------------------------------------------------------
        | FIX EACH LEDGER
        |--------------------------------------------------------------------------
        | Send Tally XML to alter each ledger's group to "Sales Accounts"
        | This will make amounts show on Credit side
        */

        $fixed   = 0;
        $failed  = 0;
        $results = [];

        foreach ($salesLedgers as $ledgerName) {

            $this->line("🔧 Fixing: {$ledgerName}");

            if ($isDryRun) {
                $this->line("   [DRY RUN] Would alter group → Sales Accounts");
                $results[] = [
                    'Ledger'  => $ledgerName,
                    'Status'  => 'DRY RUN',
                    'Message' => 'Would alter group to Sales Accounts',
                ];
                continue;
            }

            $result = $this->alterLedgerGroup($ledgerName, 'Sales Accounts');

            if ($result['success']) {
                $this->info("   ✅ Fixed — {$result['message']}");
                $fixed++;
                $results[] = [
                    'Ledger'  => $ledgerName,
                    'Status'  => 'SUCCESS',
                    'Message' => $result['message'],
                ];
            } else {
                $this->error("   ❌ Failed — {$result['message']}");
                $failed++;
                $results[] = [
                    'Ledger'  => $ledgerName,
                    'Status'  => 'FAILED',
                    'Message' => $result['message'],
                ];
            }

            // Small delay
            usleep(200000); // 200ms
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE CSV
        |--------------------------------------------------------------------------
        */

        $this->saveResultsCsv($results, $isDryRun);

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║   COMPLETE                                   ║');
        $this->info('╚══════════════════════════════════════════════╝');

        if (!$isDryRun) {
            $this->line("✅ Fixed  : {$fixed}");
            $this->line("❌ Failed : {$failed}");
        }

        $this->info('');
        $this->info('📁 Results saved to: storage/app/tally-fixes/');
        $this->info('');

        if (!$isDryRun && $fixed > 0) {
            $this->info('🎯 Done! Now check Tally:');
            $this->line('   → NIL RATED SALES A/C → should show Credit side');
            $this->line('   → TAXABLE SALE 18% etc → should show Credit side');
        }

        Log::info('TALLY LEDGER GROUP FIX COMPLETE', [
            'fixed'  => $fixed,
            'failed' => $failed,
            'ledgers' => $salesLedgers,
        ]);

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | ALTER LEDGER GROUP IN TALLY
    |--------------------------------------------------------------------------
    */

    private function alterLedgerGroup(string $ledgerName, string $newParent): array
    {
        $xName   = htmlspecialchars($ledgerName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xParent = htmlspecialchars($newParent,  ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xCompany = htmlspecialchars(config('tally.company_name'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

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
                <SVCURRENTCOMPANY>{$xCompany}</SVCURRENTCOMPANY>
                </STATICVARIABLES>
                </REQUESTDESC>
                <REQUESTDATA>
                <TALLYMESSAGE xmlns:UDF='TallyUDF'>

                <LEDGER NAME='{$xName}' ACTION='Alter'>
                    <NAME>{$xName}</NAME>
                    <PARENT>{$xParent}</PARENT>
                </LEDGER>

                </TALLYMESSAGE>
                </REQUESTDATA>
                </IMPORTDATA>
                </BODY>
                </ENVELOPE>";

        try {

            $response = $this->client->send($xml, 'LEDGER_FIX');

            Log::info('LEDGER GROUP FIX RESPONSE', [
                'ledger'   => $ledgerName,
                'parent'   => $newParent,
                'response' => $response,
            ]);

            if (empty($response)) {
                return ['success' => false, 'message' => 'Empty response from Tally'];
            }

            $altered = str_contains($response, '<ALTERED>1</ALTERED>');
            $created = str_contains($response, '<CREATED>1</CREATED>');

            if ($altered || $created) {
                return [
                    'success' => true,
                    'message' => $altered ? 'Group altered to Sales Accounts' : 'Ledger created with Sales Accounts',
                ];
            }

            // Check for error
            preg_match('/<LINEERROR>(.*?)<\/LINEERROR>/s', $response, $m);
            $error = html_entity_decode($m[1] ?? 'Unknown Tally error');

            return ['success' => false, 'message' => $error];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE RESULTS CSV
    |--------------------------------------------------------------------------
    */

    private function saveResultsCsv(array $results, bool $isDryRun): void
    {
        if (empty($results)) return;

        $dir      = storage_path('app/tally-fixes');
        $filename = 'ledger-group-fix-' . now()->format('Y-m-d-His') . ($isDryRun ? '-dryrun' : '') . '.csv';
        $path     = $dir . '/' . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $csv  = "Ledger,Status,Message,Timestamp\n";

        foreach ($results as $row) {
            $csv .= '"' . str_replace('"', '""', $row['Ledger']) . '",' .
                    '"' . str_replace('"', '""', $row['Status']) . '",' .
                    '"' . str_replace('"', '""', $row['Message']) . '",' .
                    '"' . now()->format('d M Y H:i:s') . '"' . "\n";
        }

        file_put_contents($path, $csv);

        $this->line("💾 CSV saved: storage/app/tally-fixes/{$filename}");
    }
}