<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TallyOrder;
use App\Jobs\SyncOrderToTallyJob;
use Carbon\Carbon;

class SyncWooCommerceOrdersCommand extends Command
{
    /*
    |--------------------------------------------------------------------------
    | COMMAND SIGNATURE
    |--------------------------------------------------------------------------
    |
    | Usage examples:
    |
    |   php artisan tally:sync-orders
    |       → syncs last 30 days, both processing + completed
    |
    |   php artisan tally:sync-orders --from=2025-05-01 --to=2025-05-31
    |       → syncs specific date range
    |
    |   php artisan tally:sync-orders --status=processing
    |       → only processing orders
    |
    |   php artisan tally:sync-orders --retry-failed
    |       → retry all failed orders already in DB (no WC API call)
    |
    |   php artisan tally:sync-orders --dry-run
    |       → show what would be synced, no actual queue dispatch
    |
    */

    protected $signature = 'tally:sync-orders
        {--from=       : Start date (Y-m-d). Default: 30 days ago}
        {--to=         : End date (Y-m-d). Default: today}
        {--status=     : Order status filter: processing|completed|all. Default: all}
        {--per-page=50 : Orders per API page (max 100)}
        {--retry-failed: Retry only failed orders already in DB}
        {--dry-run     : Show orders without dispatching jobs}
    ';

    protected $description = 'Sync WooCommerce orders to Tally (bulk / date range / retry failed)';

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║   WooCommerce → Tally Bulk Sync      ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->info('');

        /*
        |--------------------------------------------------------------------------
        | RETRY FAILED — DB ONLY MODE
        |--------------------------------------------------------------------------
        | No WooCommerce API call needed
        | Just retry orders already in DB with sync_status = failed
        */

        if ($this->option('retry-failed')) {

            return $this->retryFailedOrders();
        }

        /*
        |--------------------------------------------------------------------------
        | DATE RANGE
        |--------------------------------------------------------------------------
        */

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $statusFilter = $this->option('status') ?? 'all';

        $perPage = (int) ($this->option('per-page') ?? 50);
        $perPage = min($perPage, 100); // WooCommerce max is 100

        $isDryRun = $this->option('dry-run');

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $this->line("📅 From     : " . $from->format('d M Y'));
        $this->line("📅 To       : " . $to->format('d M Y'));
        $this->line("📦 Status   : " . ($statusFilter === 'all' ? 'processing + completed' : $statusFilter));
        $this->line("📄 Per Page : " . $perPage);
        $this->line("🔍 Dry Run  : " . ($isDryRun ? 'YES — no jobs will be dispatched' : 'NO'));
        $this->info('');

        /*
        |--------------------------------------------------------------------------
        | WC API CREDENTIALS
        |--------------------------------------------------------------------------
        */

        $wcUrl = config('services.woocommerce.url');
        $ckKey = config('services.woocommerce.consumer_key');
        $csKey = config('services.woocommerce.consumer_secret');

        if (empty($wcUrl) || empty($ckKey) || empty($csKey)) {

            $this->error('❌ WooCommerce credentials not set in config/services.php');
            $this->line('   Add these to your .env:');
            $this->line('   WOOCOMMERCE_URL=https://yoursite.com');
            $this->line('   WOOCOMMERCE_CONSUMER_KEY=ck_xxxx');
            $this->line('   WOOCOMMERCE_CONSUMER_SECRET=cs_xxxx');

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS LIST
        |--------------------------------------------------------------------------
        */

        $statuses = match ($statusFilter) {

            'processing' => ['processing'],
            'completed'  => ['completed'],
            default      => ['processing', 'completed'],
        };

        /*
        |--------------------------------------------------------------------------
        | FETCH + PROCESS ORDERS
        |--------------------------------------------------------------------------
        */

        $totalFetched  = 0;
        $totalQueued   = 0;
        $totalSkipped  = 0;
        $totalFailed   = 0;

        foreach ($statuses as $status) {

            $this->info("🔄 Fetching '{$status}' orders...");

            $page = 1;

            do {

                /*
                |--------------------------------------------------------------------------
                | WC REST API CALL
                |--------------------------------------------------------------------------
                */

                try {

                    $response = Http::withBasicAuth($ckKey, $csKey)
                        ->timeout(30)
                        ->get("{$wcUrl}/wp-json/wc/v3/orders", [
                            'status'    => $status,
                            'after'     => $from->toIso8601String(),
                            'before'    => $to->toIso8601String(),
                            'per_page'  => $perPage,
                            'page'      => $page,
                            'orderby'   => 'date',
                            'order'     => 'asc',
                        ]);

                    if (!$response->successful()) {

                        $this->error("❌ WooCommerce API error: HTTP " . $response->status());
                        $this->error($response->body());

                        Log::error('BULK SYNC WC API ERROR', [
                            'status'   => $response->status(),
                            'body'     => $response->body(),
                            'page'     => $page,
                        ]);

                        break;
                    }

                    $orders = $response->json();

                    if (empty($orders)) {
                        break; // No more orders
                    }

                } catch (\Throwable $e) {

                    $this->error("❌ HTTP Exception: " . $e->getMessage());

                    Log::error('BULK SYNC HTTP EXCEPTION', [
                        'message' => $e->getMessage(),
                        'page'    => $page,
                    ]);

                    break;
                }

                $this->line("  Page {$page} → " . count($orders) . " orders");

                /*
                |--------------------------------------------------------------------------
                | PROCESS EACH ORDER
                |--------------------------------------------------------------------------
                */

                foreach ($orders as $wcOrder) {

                    $totalFetched++;

                    $wcOrderId  = $wcOrder['id'];
                    $orderNum   = $wcOrder['number'] ?? $wcOrderId;

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK EXISTING IN DB
                    |--------------------------------------------------------------------------
                    */

                    $existing = TallyOrder::where(
                        'woocommerce_order_id', $wcOrderId
                    )->first();

                    if ($existing && $existing->sync_status === 'success') {

                        $this->line("  ⏭  #{$orderNum} — Already synced, skipping");

                        $totalSkipped++;

                        continue;
                    }

                    if ($isDryRun) {

                        $this->line("  🔍 #{$orderNum} — Would be queued [DRY RUN]");

                        $totalQueued++;

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | RESOLVE META DATA
                    |--------------------------------------------------------------------------
                    */

                    $metaData = [];

                    foreach ($wcOrder['meta_data'] ?? [] as $meta) {

                        if (!empty($meta['key'])) {

                            $metaData[$meta['key']] = $meta['value'] ?? null;
                        }
                    }

                    $invoiceNumber =
                        $metaData['_wcpdf_invoice_number']
                        ?? $metaData['_invoice_number']
                        ?? ('INV-' . $orderNum);

                    /*
                    |--------------------------------------------------------------------------
                    | PLATFORM FEE
                    |--------------------------------------------------------------------------
                    */

                    $platformFee = 0;

                    foreach ($wcOrder['fee_lines'] ?? [] as $fee) {

                        $feeName = strtolower($fee['name'] ?? '');

                        if (
                            str_contains($feeName, 'platform') ||
                            str_contains($feeName, 'charge') ||
                            str_contains($feeName, 'convenience')
                        ) {
                            $platformFee += (float) ($fee['total'] ?? 0);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | BUILD CLEAN ORDER DATA (same as WebhookController)
                    |--------------------------------------------------------------------------
                    */

                    $items = [];

                    foreach ($wcOrder['line_items'] ?? [] as $lineItem) {

                        $qty      = (float) ($lineItem['quantity'] ?? 1);
                        $subtotal = (float) ($lineItem['subtotal'] ?? 0);

                        $items[] = [
                            'product_id'   => $lineItem['product_id'] ?? null,
                            'variation_id' => $lineItem['variation_id'] ?? null,
                            'name'         => $lineItem['name'] ?? 'Product',
                            'sku'          => $lineItem['sku'] ?? '',
                            'quantity'     => $qty > 0 ? $qty : 1,
                            'rate'         => $qty > 0 ? round($subtotal / $qty, 2) : 0,
                            'subtotal'     => $subtotal,
                            'total'        => (float) ($lineItem['total'] ?? 0),
                            'subtotal_tax' => (float) ($lineItem['subtotal_tax'] ?? 0),
                            'total_tax'    => (float) ($lineItem['total_tax'] ?? 0),
                            'tax_class'    => $lineItem['tax_class'] ?? '',
                            'unit'         => 'PCS',
                        ];
                    }

                    $orderData = [
                        'id'                   => $wcOrderId,
                        'number'               => $orderNum,
                        'invoice_number'       => $invoiceNumber,
                        'status'               => strtolower($wcOrder['status'] ?? ''),
                        'currency'             => $wcOrder['currency'] ?? 'INR',
                        'payment_method'       => $wcOrder['payment_method'] ?? '',
                        'payment_method_title' => $wcOrder['payment_method_title'] ?? 'Online',
                        'transaction_id'       => $wcOrder['transaction_id'] ?? '',
                        'date_created'         => $wcOrder['date_created'] ?? null,
                        'billing'              => $wcOrder['billing'] ?? [],
                        'shipping'             => $wcOrder['shipping'] ?? [],
                        'customer_note'        => $wcOrder['customer_note'] ?? '',
                        'meta_data'            => $wcOrder['meta_data'] ?? [],
                        'coupon_lines'         => $wcOrder['coupon_lines'] ?? [],
                        'fee_lines'            => $wcOrder['fee_lines'] ?? [],
                        'shipping_lines'       => $wcOrder['shipping_lines'] ?? [],
                        'tax_lines'            => $wcOrder['tax_lines'] ?? [],
                        'line_items'           => $items,
                        'discount_total'       => (float) ($wcOrder['discount_total'] ?? 0),
                        'discount_tax'         => (float) ($wcOrder['discount_tax'] ?? 0),
                        'shipping_total'       => (float) ($wcOrder['shipping_total'] ?? 0),
                        'shipping_tax'         => (float) ($wcOrder['shipping_tax'] ?? 0),
                        'cart_tax'             => (float) ($wcOrder['cart_tax'] ?? 0),
                        'total_tax'            => (float) ($wcOrder['total_tax'] ?? 0),
                        'total'                => (float) ($wcOrder['total'] ?? 0),
                        'platform_fee'         => $platformFee,
                    ];

                    $customerName = trim(
                        ($wcOrder['billing']['first_name'] ?? '') . ' ' .
                        ($wcOrder['billing']['last_name'] ?? '')
                    );

                    if (empty($customerName)) {
                        $customerName = 'Walk-in Customer';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SAVE TO DB + DISPATCH JOB
                    |--------------------------------------------------------------------------
                    */

                    try {

                        $savedOrder = TallyOrder::updateOrCreate(

                            ['woocommerce_order_id' => $wcOrderId],

                            [
                                'order_number'        => $orderNum,
                                'customer_name'       => $customerName,
                                'amount'              => (float) ($wcOrder['total'] ?? 0),
                                'payload'             => json_encode($orderData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'tax_lines'           => json_encode($wcOrder['tax_lines'] ?? [], JSON_UNESCAPED_UNICODE),
                                'webhook_headers'     => json_encode(['source' => 'bulk_sync_command']),
                                'webhook_received_at' => now(),
                                'sync_status'         => 'pending',
                                'retry_count'         => 0,
                                'last_error'          => null,
                            ]
                        );

                        SyncOrderToTallyJob::dispatch($savedOrder->id);

                        $this->line("  ✅ #{$orderNum} — Queued ({$invoiceNumber}) — ₹" . number_format($orderData['total'], 2));

                        $totalQueued++;

                        // Small delay between jobs to not overwhelm Tally
                        usleep(100000); // 100ms

                    } catch (\Throwable $e) {

                        $this->error("  ❌ #{$orderNum} — Failed: " . $e->getMessage());

                        Log::error('BULK SYNC ORDER SAVE FAILED', [
                            'order_id' => $wcOrderId,
                            'message'  => $e->getMessage(),
                        ]);

                        $totalFailed++;
                    }
                }

                $page++;

                // WooCommerce pagination: if we got fewer than perPage, we're done
                if (count($orders) < $perPage) {
                    break;
                }

            } while (true);
        }

        /*
        |--------------------------------------------------------------------------
        | FINAL SUMMARY
        |--------------------------------------------------------------------------
        */

        $this->info('');
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║           SYNC COMPLETE              ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->line("📦 Total Fetched  : {$totalFetched}");
        $this->line("✅ Queued         : {$totalQueued}");
        $this->line("⏭  Skipped        : {$totalSkipped}");
        $this->line("❌ Failed         : {$totalFailed}");
        $this->info('');

        if (!$isDryRun && $totalQueued > 0) {

            $this->info("🚀 Jobs dispatched! Make sure queue worker is running:");
            $this->line("   php artisan queue:work --tries=3 --timeout=120");
            $this->info('');
        }

        Log::info('BULK SYNC COMPLETE', [
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'total_fetched' => $totalFetched,
            'total_queued'  => $totalQueued,
            'total_skipped' => $totalSkipped,
            'total_failed'  => $totalFailed,
        ]);

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | RETRY FAILED ORDERS (DB ONLY)
    |--------------------------------------------------------------------------
    | WooCommerce API call nahi hoga
    | Sirf DB mein failed orders ko re-queue karega
    */

    private function retryFailedOrders(): int
    {
        $this->info("🔄 Fetching failed orders from DB...");
        $this->info('');

        $failedOrders = TallyOrder::where('sync_status', 'failed')
            ->orderBy('id')
            ->get();

        if ($failedOrders->isEmpty()) {

            $this->info('✅ No failed orders found in DB.');

            return self::SUCCESS;
        }

        $this->line("Found {$failedOrders->count()} failed orders:");
        $this->info('');

        $retried = 0;

        foreach ($failedOrders as $order) {

            $this->line("  🔁 #{$order->order_number} — {$order->customer_name} — ₹{$order->amount}");
            $this->line("     Last Error: " . substr($order->last_error ?? 'Unknown', 0, 80));

            $order->update([
                'sync_status' => 'pending',
                'retry_count' => 0,
                'last_error'  => null,
            ]);

            SyncOrderToTallyJob::dispatch($order->id);

            $retried++;
        }

        $this->info('');
        $this->info("✅ {$retried} orders re-queued for retry.");
        $this->info("🚀 Make sure queue worker is running:");
        $this->line("   php artisan queue:work --tries=3 --timeout=120");

        return self::SUCCESS;
    }
}
