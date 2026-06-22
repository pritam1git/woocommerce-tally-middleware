<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TallyOrder;
use App\Jobs\SyncOrderToTallyJob;
use Carbon\Carbon;

class BulkSyncController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW PAGE
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $stats = [
            'total'      => TallyOrder::count(),
            'success'    => TallyOrder::where('sync_status', 'success')->count(),
            'failed'     => TallyOrder::where('sync_status', 'failed')->count(),
            'pending'    => TallyOrder::where('sync_status', 'pending')->count(),
            'processing' => TallyOrder::where('sync_status', 'processing')->count(),
        ];

        return view('admin.bulk-sync', compact('stats'));
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW — WC se orders fetch karo, count batao
    |--------------------------------------------------------------------------
    */

    public function preview(Request $request)
    {
        $request->validate([
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'status' => 'nullable|in:processing,completed,ready-to-ship,shipped,delivered,all',
        ]);

        $from     = Carbon::parse($request->from)->startOfDay();
        $to       = Carbon::parse($request->to)->endOfDay();
        $status   = $request->status ?? 'all';
        $statuses = match($status) {
            'processing'    => ['processing'],
            'completed'     => ['completed'],
            'ready-to-ship' => ['ready-to-ship'],
            'shipped'     => ['shipped'],
            'delivered'     => ['delivered'],
            default         => ['processing', 'completed', 'ready-to-ship', 'shipped', 'delivered'],
        };

        $wcUrl = config('services.woocommerce.url');
        $ckKey = config('services.woocommerce.consumer_key');
        $csKey = config('services.woocommerce.consumer_secret');

        if (empty($wcUrl) || empty($ckKey) || empty($csKey)) {
            return response()->json([
                'success' => false,
                'message' => 'WooCommerce credentials not configured in .env',
            ], 500);
        }

        $totalOrders    = 0;
        $alreadySynced  = 0;
        $toBeQueued     = 0;
        $ordersList     = [];

        try {

            foreach ($statuses as $s) {

                $page = 1;

                do {

                    $response = Http::withBasicAuth($ckKey, $csKey)
                        ->timeout(120)
                        ->get("{$wcUrl}/wp-json/wc/v3/orders", [
                            'status'   => $s,
                            'after'    => $from->toIso8601String(),
                            'before'   => $to->toIso8601String(),
                            'per_page' => 50,
                            'page'     => $page,
                            'orderby'  => 'date',
                            'order'    => 'asc',
                            '_fields'  => 'id,number,status,total,billing,date_created',
                        ]);

                    if (!$response->successful()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'WooCommerce API error: HTTP ' . $response->status(),
                        ], 500);
                    }

                    $orders = $response->json();

                    if (empty($orders)) break;

                    foreach ($orders as $order) {

                        $totalOrders++;

                        $existing = TallyOrder::where('woocommerce_order_id', $order['id'])
                            ->where('sync_status', 'success')
                            ->exists();

                        if ($existing) {
                            $alreadySynced++;
                        } else {
                            $toBeQueued++;
                            $ordersList[] = [
                                'id'         => $order['id'],
                                'number'     => $order['number'],
                                'status'     => $order['status'],
                                'total'      => $order['total'],
                                'customer'   => trim(
                                    ($order['billing']['first_name'] ?? '') . ' ' .
                                    ($order['billing']['last_name'] ?? '')
                                ),
                                'date'       => Carbon::parse($order['date_created'])->format('d M Y'),
                                'synced'     => false,
                            ];
                        }
                    }

                    $page++;

                    if (count($orders) < 100) break;

                } while (true);
            }

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'        => true,
            'total_orders'   => $totalOrders,
            'already_synced' => $alreadySynced,
            'to_be_queued'   => $toBeQueued,
            'orders'         => array_slice($ordersList, 0, 50), // preview first 50
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SYNC — Orders queue mein daalo
    |--------------------------------------------------------------------------
    */

    public function sync(Request $request)
    {
        $request->validate([
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'status' => 'nullable|in:processing,completed,ready-to-ship,shipped,delivered,all',
        ]);

        $from     = Carbon::parse($request->from)->startOfDay();
        $to       = Carbon::parse($request->to)->endOfDay();
        $status   = $request->status ?? 'all';
        $statuses = match($status) {
            'processing'    => ['processing'],
            'completed'     => ['completed'],
            'ready-to-ship' => ['ready-to-ship'],
            'shipped'     => ['shipped'],
            'delivered'     => ['delivered'],
            default         => ['processing', 'completed', 'ready-to-ship', 'shipped', 'delivered'],
        };

        $wcUrl = config('services.woocommerce.url');
        $ckKey = config('services.woocommerce.consumer_key');
        $csKey = config('services.woocommerce.consumer_secret');

        if (empty($wcUrl) || empty($ckKey) || empty($csKey)) {
            return response()->json([
                'success' => false,
                'message' => 'WooCommerce credentials not configured',
            ], 500);
        }

        $queued  = 0;
        $skipped = 0;
        $failed  = 0;

        try {

            foreach ($statuses as $s) {

                $page = 1;

                do {

                    $response = Http::withBasicAuth($ckKey, $csKey)
                        ->timeout(120)
                        ->get("{$wcUrl}/wp-json/wc/v3/orders", [
                            'status'   => $s,
                            'after'    => $from->toIso8601String(),
                            'before'   => $to->toIso8601String(),
                            'per_page' => 50,
                            'page'     => $page,
                            'orderby'  => 'date',
                            'order'    => 'asc',
                        ]);

                    if (!$response->successful()) break;

                    $orders = $response->json();

                    if (empty($orders)) break;

                    foreach ($orders as $wcOrder) {

                        $wcOrderId = $wcOrder['id'];

                        // Already synced — skip
                        $existing = TallyOrder::where('woocommerce_order_id', $wcOrderId)
                            ->where('sync_status', 'success')
                            ->exists();

                        if ($existing) {
                            $skipped++;
                            continue;
                        }

                        try {

                            // Meta data
                            $metaData = [];
                            foreach ($wcOrder['meta_data'] ?? [] as $meta) {
                                if (!empty($meta['key'])) {
                                    $metaData[$meta['key']] = $meta['value'] ?? null;
                                }
                            }

                            $invoiceNumber =
                                $metaData['_wcpdf_invoice_number']
                                ?? $metaData['_invoice_number']
                                ?? ('INV-' . ($wcOrder['number'] ?? $wcOrderId));

                            // Platform fee
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

                            // Line items
                            $items = [];
                            foreach ($wcOrder['line_items'] ?? [] as $lineItem) {
                                $qty      = max((float) ($lineItem['quantity'] ?? 1), 1);
                                $subtotal = (float) ($lineItem['subtotal'] ?? 0);
                                $items[]  = [
                                    'product_id'   => $lineItem['product_id'] ?? null,
                                    'variation_id' => $lineItem['variation_id'] ?? null,
                                    'name'         => $lineItem['name'] ?? 'Product',
                                    'sku'          => $lineItem['sku'] ?? '',
                                    'quantity'     => $qty,
                                    'rate'         => round($subtotal / $qty, 2),
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
                                'number'               => $wcOrder['number'] ?? $wcOrderId,
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
                            ) ?: 'Walk-in Customer';

                            $savedOrder = TallyOrder::updateOrCreate(
                                ['woocommerce_order_id' => $wcOrderId],
                                [
                                    'order_number'        => $wcOrder['number'] ?? $wcOrderId,
                                    'customer_name'       => $customerName,
                                    'amount'              => (float) ($wcOrder['total'] ?? 0),
                                    'payload'             => json_encode($orderData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                    'tax_lines'           => json_encode($wcOrder['tax_lines'] ?? []),
                                    'webhook_headers'     => json_encode(['source' => 'bulk_sync_ui']),
                                    'webhook_received_at' => now(),
                                    'sync_status'         => 'pending',
                                    'retry_count'         => 0,
                                    'last_error'          => null,
                                ]
                            );

                            SyncOrderToTallyJob::dispatch($savedOrder->id);

                            $queued++;

                        } catch (\Throwable $e) {

                            Log::error('BULK SYNC UI — ORDER FAILED', [
                                'order_id' => $wcOrderId,
                                'message'  => $e->getMessage(),
                            ]);

                            $failed++;
                        }
                    }

                    $page++;

                    if (count($orders) < 100) break;

                } while (true);
            }

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Sync error: ' . $e->getMessage(),
            ], 500);
        }

        Log::info('BULK SYNC UI COMPLETE', [
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'queued'  => $queued,
            'skipped' => $skipped,
            'failed'  => $failed,
        ]);

        return response()->json([
            'success' => true,
            'queued'  => $queued,
            'skipped' => $skipped,
            'failed'  => $failed,
            'message' => "{$queued} orders queued, {$skipped} skipped (already synced), {$failed} failed",
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PROGRESS — Live status DB se
    |--------------------------------------------------------------------------
    */

    public function progress()
    {
        $stats = [
            'total'      => TallyOrder::count(),
            'success'    => TallyOrder::where('sync_status', 'success')->count(),
            'failed'     => TallyOrder::where('sync_status', 'failed')->count(),
            'pending'    => TallyOrder::where('sync_status', 'pending')->count(),
            'processing' => TallyOrder::where('sync_status', 'processing')->count(),
        ];

        $recentSuccess = TallyOrder::where('sync_status', 'success')
            ->orderByDesc('synced_at')
            ->limit(10)
            ->get(['order_number', 'customer_name', 'amount', 'synced_at']);

        $recentFailed = TallyOrder::where('sync_status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['order_number', 'customer_name', 'amount', 'last_error']);

        return response()->json([
            'stats'          => $stats,
            'recent_success' => $recentSuccess,
            'recent_failed'  => $recentFailed,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RETRY FAILED — DB se failed orders retry karo
    |--------------------------------------------------------------------------
    */

    public function retryFailed()
    {
        $failedOrders = TallyOrder::where('sync_status', 'failed')->get();

        if ($failedOrders->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No failed orders to retry',
                'count'   => 0,
            ]);
        }

        $count = 0;

        foreach ($failedOrders as $order) {

            $order->update([
                'sync_status' => 'pending',
                'retry_count' => 0,
                'last_error'  => null,
            ]);

            SyncOrderToTallyJob::dispatch($order->id);

            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} failed orders re-queued",
            'count'   => $count,
        ]);
    }
}
