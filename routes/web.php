<?php

use Illuminate\Support\Facades\Route;

use App\Models\TallyOrder;
use App\Jobs\SyncOrderToTallyJob;
use App\Services\Tally\TallyVoucherService;
use App\Services\Tally\TallyOrderSyncService;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BulkSyncController;


/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
        'auth',
        'throttle:60,1',
    ])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        // Fixed: removed duplicate /admin prefix
        Route::get('/orders/{id}', [DashboardController::class, 'show'])
            ->whereNumber('id')
            ->name('orders.show');

        Route::delete('/orders/{id}', [DashboardController::class, 'delete'])
            ->middleware('throttle:10,1')
            ->whereNumber('id')
            ->name('orders.delete');

        /*
        |--------------------------------------------------------------------------
        | Retry Sync
        |--------------------------------------------------------------------------
        */

        Route::post('/retry/{id}', [DashboardController::class, 'retry'])
            ->middleware('throttle:10,1')
            ->whereNumber('id')
            ->name('retry');

        /*
        |--------------------------------------------------------------------------
        | Bulk Sync
        |--------------------------------------------------------------------------
        */

        Route::get('/bulk-sync', [BulkSyncController::class, 'index'])
            ->name('bulk-sync');

        Route::post('/bulk-sync/preview', [BulkSyncController::class, 'preview'])
            ->middleware('throttle:20,1')
            ->name('bulk-sync.preview');

        Route::post('/bulk-sync/sync', [BulkSyncController::class, 'sync'])
            ->middleware('throttle:5,1')
            ->name('bulk-sync.sync');

        Route::get('/bulk-sync/progress', [BulkSyncController::class, 'progress'])
            ->name('bulk-sync.progress');

        Route::post('/bulk-sync/retry-failed', [BulkSyncController::class, 'retryFailed'])
            ->middleware('throttle:5,1')
            ->name('bulk-sync.retry-failed');

        Route::post('/bulk-sync/discount-orders', [BulkSyncController::class, 'discountOrders'])
            ->middleware('throttle:10,1')
            ->name('bulk-sync.discount-orders');

        Route::post('/bulk-sync/discount-orders/download', [BulkSyncController::class, 'downloadDiscountOrders'])
            ->middleware('throttle:10,1')
            ->name('bulk-sync.discount-download');
    });

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Auth::routes();

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function () {
    return redirect()->route('admin.dashboard');
});



Route::get('/test-tally', function () {

    /*
    |--------------------------------------------------------------------------
    | DYNAMIC IDs — HAR BAAR ALAG
    |--------------------------------------------------------------------------
    | time() + rand se ensure hoga ki:
    | - Har run par alag order number
    | - Alag invoice number
    | - DB aur Tally mein duplicate nahi banega
    */

    $timestamp     = time();
    $randomSuffix  = rand(100, 999);
    $fakeOrderId   = 9100000 + $randomSuffix;   // fake WC order ID
    $fakeOrderNum  = 'TEST-' . $timestamp;
    $invoiceNumber = 'UPJ/TEST-' . $timestamp;

    /*
    |--------------------------------------------------------------------------
    | SIMULATED WEBHOOK PAYLOAD
    |--------------------------------------------------------------------------
    | Bilkul real WooCommerce webhook JSON jaisa structure
    | WebhookController iska getContent() karke parse karta hai
    | Hum yahan same structure directly banate hain
    */

    $payload = [

        /*
        |--------------------------------------------------------------------------
        | ORDER IDENTITY
        |--------------------------------------------------------------------------
        */

        'id'     => $fakeOrderId,
        'number' => $fakeOrderNum,
        'status' => 'processing',     // allowed status — processing ya completed
        'currency' => 'INR',

        /*
        |--------------------------------------------------------------------------
        | PAYMENT
        |--------------------------------------------------------------------------
        */

        'payment_method'       => 'cod',
        'payment_method_title' => 'Cash on Delivery',
        'transaction_id'       => '',

        /*
        |--------------------------------------------------------------------------
        | DATE
        |--------------------------------------------------------------------------
        */

        'date_created' => now()->toIso8601String(),

        /*
        |--------------------------------------------------------------------------
        | BILLING
        |--------------------------------------------------------------------------
        */

        'billing' => [
            'first_name' => 'Test',
            'last_name'  => 'Customer',
            'address_1'  => 'New Ashok Nagar',
            'address_2'  => 'Near Metro Gate 2',
            'city'       => 'East Delhi',
            'state'      => 'DL',
            'postcode'   => '110096',
            'country'    => 'IN',
            'email'      => 'test@example.com',
            'phone'      => '9876543210',
        ],

        'shipping' => [
            'first_name' => 'Test',
            'last_name'  => 'Customer',
        ],

        /*
        |--------------------------------------------------------------------------
        | META DATA
        |--------------------------------------------------------------------------
        | _wcpdf_invoice_number — WooCommerce PDF Invoices plugin field
        | WebhookController yahan se invoice number uthata hai
        */

        'meta_data' => [
            [
                'key'   => '_wcpdf_invoice_number',
                'value' => $invoiceNumber,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | FEE LINES
        |--------------------------------------------------------------------------
        | WebhookController 'platform', 'charge', 'convenience' words check karta hai
        | 'Platform Charge' → platform_fee = 5.00
        */

        'fee_lines' => [
            [
                'name'      => 'Platform Charge',
                'total'     => '5.00',
                'total_tax' => '0.00',
            ],
        ],

        'coupon_lines'   => [],
        'shipping_lines' => [],
        'tax_lines'      => [],

        /*
        |--------------------------------------------------------------------------
        | TOTALS
        |--------------------------------------------------------------------------
        */

        'shipping_total' => 0,
        'discount_total' => 0,
        'discount_tax'   => 0,
        'shipping_tax'   => 0,
        'cart_tax'       => 79.98,
        'total_tax'      => 79.98,

        /*
        |--------------------------------------------------------------------------
        | GRAND TOTAL
        |--------------------------------------------------------------------------
        | Item 1: 392 (taxable) + 47.04 (12% GST) = 439.04
        | Item 2: 183 (taxable) + 32.94 (18% GST) = 215.94
        | Platform fee: 5.00
        | Grand Total: 439.04 + 215.94 + 5.00 = 659.98
        */

        'total' => 659.98,

        /*
        |--------------------------------------------------------------------------
        | LINE ITEMS
        |--------------------------------------------------------------------------
        | WebhookController 'quantity' field read karta hai (qty nahi)
        | GST rate WebhookController khud calculate karta hai subtotal/subtotal_tax se
        | group WebhookController detectGroup() se assign karta hai
        */

        'line_items' => [

            /*
            |--------------------------------------------------------------------------
            | ITEM 1 — PLANT (12% GST)
            |--------------------------------------------------------------------------
            | subtotal_tax / subtotal = 47.04 / 392 = 0.12 = 12%
            | detectGroup: name mein 'plant' hai → GROUP = PLANTS
            */

            [
                'product_id'   => 16712,
                'variation_id' => null,
                'name'         => 'Kazi Lemon / Nimbu Grafted Plant',
                'sku'          => 'KL-PL-GR',
                'quantity'     => 1,
                'subtotal'     => 392.00,
                'total'        => 392.00,
                'subtotal_tax' => 47.04,
                'total_tax'    => 47.04,
                'tax_class'    => '',
            ],

            /*
            |--------------------------------------------------------------------------
            | ITEM 2 — TOOL (18% GST)
            |--------------------------------------------------------------------------
            | subtotal_tax / subtotal = 32.94 / 183 = 0.18 = 18%
            | detectGroup: name mein 'tool' hai → GROUP = TOOLS
            */

            [
                'product_id'   => 12672,
                'variation_id' => null,
                'name'         => 'Kurpi Gardening Tool',
                'sku'          => 'KU-PC',
                'quantity'     => 1,
                'subtotal'     => 183.00,
                'total'        => 183.00,
                'subtotal_tax' => 32.94,
                'total_tax'    => 32.94,
                'tax_class'    => '',
            ],
        ],
    ];

    /*
    ============================================================
    | STEP 1 — WEBHOOKCONTROLLER LOGIC (exact copy)
    ============================================================
    | Real flow mein WebhookController@handle() yahi karta hai
    | Hum yahan same logic manually run karte hain
    */

    Log::info('[TEST-TALLY] STEP 1 — WebhookController logic start', [
        'order_id'     => $payload['id'],
        'order_number' => $payload['number'],
    ]);

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER NAME
    |--------------------------------------------------------------------------
    */

    $customerName = trim(
        ($payload['billing']['first_name'] ?? '') . ' ' .
        ($payload['billing']['last_name'] ?? '')
    );

    if (empty($customerName)) {
        $customerName = 'Walk-in Customer';
    }

    /*
    |--------------------------------------------------------------------------
    | META DATA → FLAT MAP
    |--------------------------------------------------------------------------
    */

    $metaData = [];

    foreach ($payload['meta_data'] ?? [] as $meta) {
        if (!empty($meta['key'])) {
            $metaData[$meta['key']] = $meta['value'] ?? null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE NUMBER — same priority as WebhookController
    |--------------------------------------------------------------------------
    */

    $resolvedInvoice =
        $metaData['_wcpdf_invoice_number']
        ?? $metaData['_invoice_number']
        ?? $payload['invoice_number']
        ?? ('INV-' . ($payload['number'] ?? $payload['id']));

    /*
    |--------------------------------------------------------------------------
    | PLATFORM FEE — same logic as WebhookController
    |--------------------------------------------------------------------------
    */

    $platformFee = 0;

    foreach ($payload['fee_lines'] ?? [] as $fee) {

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
    | LINE ITEMS — same logic as WebhookController
    |--------------------------------------------------------------------------
    */

    $items = [];

    foreach ($payload['line_items'] as $lineItem) {

        $qty      = (float) ($lineItem['quantity'] ?? 1);
        $subtotal = (float) ($lineItem['subtotal'] ?? 0);

        if ($qty <= 0) $qty = 1;

        $rate = round($subtotal / $qty, 2);

        // extractGstRate() logic
        $tax      = (float) ($lineItem['subtotal_tax'] ?? 0);
        $gstRate  = 0;

        if ($subtotal > 0 && $tax > 0) {
            $rawRate = ($tax / $subtotal) * 100;

            if ($rawRate <= 1)      $gstRate = 0;
            elseif ($rawRate <= 5.5)  $gstRate = 5;
            elseif ($rawRate <= 12.5) $gstRate = 12;
            elseif ($rawRate <= 18.5) $gstRate = 18;
            elseif ($rawRate <= 28.5) $gstRate = 28;
            else $gstRate = round($rawRate, 2);
        }

        // detectGroup() logic
        $taxClass = strtolower($lineItem['tax_class'] ?? '');
        $name     = strtolower($lineItem['name'] ?? '');
        $sku      = strtolower($lineItem['sku'] ?? '');

        $group = 'BULBS';

        if (str_contains($taxClass, 'fertilizer') || str_contains($name, 'fertilizer') || str_contains($name, 'manure') || str_contains($sku, 'fert')) {
            $group = 'FERTILIZER';
        } elseif (str_contains($taxClass, 'tool') || str_contains($name, 'tool') || str_contains($name, 'kurpi') || str_contains($sku, 'tool')) {
            $group = 'TOOLS';
        } elseif (str_contains($taxClass, 'seed') || str_contains($name, 'seed') || str_contains($sku, 'seed')) {
            $group = 'SEEDS';
        } elseif (str_contains($name, 'sapling')) {
            $group = 'SAPLINGS';
        } elseif (str_contains($taxClass, 'plant') || str_contains($name, 'plant') || str_contains($name, 'tree')) {
            $group = 'PLANTS';
        }

        $items[] = [
            'product_id'   => $lineItem['product_id'] ?? null,
            'variation_id' => $lineItem['variation_id'] ?? null,
            'name'         => $lineItem['name'] ?? 'Product',
            'sku'          => $lineItem['sku'] ?? '',
            'quantity'     => $qty,
            'rate'         => $rate,
            'subtotal'     => $subtotal,
            'total'        => (float) ($lineItem['total'] ?? 0),
            'subtotal_tax' => (float) ($lineItem['subtotal_tax'] ?? 0),
            'total_tax'    => (float) ($lineItem['total_tax'] ?? 0),
            'tax_class'    => $lineItem['tax_class'] ?? '',
            'gst_rate'     => $gstRate,
            'group'        => $group,
            'unit'         => 'PCS',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAN ORDER DATA — exact same as WebhookController
    |--------------------------------------------------------------------------
    */

    $orderData = [
        'id'                   => $payload['id'],
        'number'               => $payload['number'],
        'invoice_number'       => $resolvedInvoice,
        'status'               => strtolower($payload['status']),
        'currency'             => $payload['currency'] ?? 'INR',
        'payment_method'       => $payload['payment_method'] ?? '',
        'payment_method_title' => $payload['payment_method_title'] ?? 'Online',
        'transaction_id'       => $payload['transaction_id'] ?? '',
        'date_created'         => $payload['date_created'] ?? null,
        'billing'              => $payload['billing'] ?? [],
        'shipping'             => $payload['shipping'] ?? [],
        'customer_note'        => $payload['customer_note'] ?? '',
        'meta_data'            => $payload['meta_data'] ?? [],
        'coupon_lines'         => $payload['coupon_lines'] ?? [],
        'fee_lines'            => $payload['fee_lines'] ?? [],
        'shipping_lines'       => $payload['shipping_lines'] ?? [],
        'tax_lines'            => $payload['tax_lines'] ?? [],
        'line_items'           => $items,
        'discount_total'       => (float) ($payload['discount_total'] ?? 0),
        'discount_tax'         => (float) ($payload['discount_tax'] ?? 0),
        'shipping_total'       => (float) ($payload['shipping_total'] ?? 0),
        'shipping_tax'         => (float) ($payload['shipping_tax'] ?? 0),
        'cart_tax'             => (float) ($payload['cart_tax'] ?? 0),
        'total_tax'            => (float) ($payload['total_tax'] ?? 0),
        'total'                => (float) ($payload['total'] ?? 0),
        'platform_fee'         => $platformFee,
    ];

    Log::info('[TEST-TALLY] STEP 1 COMPLETE — Order data prepared', [
        'invoice_number' => $resolvedInvoice,
        'customer'       => $customerName,
        'platform_fee'   => $platformFee,
        'items_count'    => count($items),
        'items_debug'    => collect($items)->map(fn($i) => [
            'name'     => $i['name'],
            'gst_rate' => $i['gst_rate'],
            'group'    => $i['group'],
        ])->toArray(),
    ]);

    /*
    ============================================================
    | STEP 2 — DB SAVE (TallyOrder::updateOrCreate)
    ============================================================
    | Real flow mein WebhookController yahan DB mein save karta hai
    */

    Log::info('[TEST-TALLY] STEP 2 — Saving to DB (tally_orders table)');

    $savedOrder = TallyOrder::updateOrCreate(

        ['woocommerce_order_id' => $payload['id']],

        [
            'order_number'        => $payload['number'],
            'customer_name'       => $customerName,
            'amount'              => (float) ($payload['total'] ?? 0),
            'payload'             => json_encode($orderData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tax_lines'           => json_encode($payload['tax_lines'] ?? [], JSON_UNESCAPED_UNICODE),
            'webhook_headers'     => json_encode(['source' => 'test_route', 'timestamp' => $timestamp]),
            'webhook_received_at' => now(),
            'sync_status'         => 'pending',
            'retry_count'         => 0,
            'last_error'          => null,
        ]
    );

    Log::info('[TEST-TALLY] STEP 2 COMPLETE — DB saved', [
        'db_order_id'          => $savedOrder->id,
        'woocommerce_order_id' => $savedOrder->woocommerce_order_id,
        'sync_status'          => $savedOrder->sync_status,
    ]);

    /*
    ============================================================
    | STEP 3 — JOB DISPATCH (Queue mein add)
    ============================================================
    | Real flow mein WebhookController yahan SyncOrderToTallyJob dispatch karta hai
    | Job queue mein jayegi aur queue worker uthayega
    | Queue worker → SyncOrderToTallyJob@handle() → TallyVoucherService::create()
    */

    Log::info('[TEST-TALLY] STEP 3 — Dispatching SyncOrderToTallyJob', [
        'db_order_id' => $savedOrder->id,
    ]);

    SyncOrderToTallyJob::dispatch($savedOrder->id);

    Log::info('[TEST-TALLY] STEP 3 COMPLETE — Job dispatched to queue', [
        'db_order_id'   => $savedOrder->id,
        'order_number'  => $savedOrder->order_number,
        'note'          => 'Queue worker will now pick this up and call TallyVoucherService::create()',
    ]);

    /*
    ============================================================
    | RESPONSE
    ============================================================
    | Job queue mein chali gayi hai
    | Tally voucher abhi queue worker banayega
    | DB mein check karo sync_status change hoga: pending → processing → success/failed
    */

    return response()->json([

        'flow' => [
            '1_webhook_controller' => '✅ Done — Payload parsed, invoice resolved, items processed',
            '2_db_save'            => '✅ Done — Saved to tally_orders table',
            '3_job_dispatched'     => '✅ Done — SyncOrderToTallyJob added to queue',
            '4_queue_worker'       => '⏳ Pending — Queue worker will call TallyVoucherService::create()',
            '5_tally_voucher'      => '⏳ Pending — Watch logs for TALLY SYNC SUCCESS or TALLY JOB FAILED',
        ],

        'order_info' => [
            'db_order_id'          => $savedOrder->id,
            'woocommerce_order_id' => $fakeOrderId,
            'order_number'         => $fakeOrderNum,
            'invoice_number'       => $resolvedInvoice,
            'customer'             => $customerName,
            'total'                => $orderData['total'],
            'platform_fee'         => $platformFee,
        ],

        'items_processed' => collect($items)->map(fn($i) => [
            'name'     => $i['name'],
            'sku'      => $i['sku'],
            'qty'      => $i['quantity'],
            'subtotal' => $i['subtotal'],
            'tax'      => $i['subtotal_tax'],
            'gst_rate' => $i['gst_rate'] . '%',
            'group'    => $i['group'],
        ])->toArray(),

        'next_steps' => [
            'check_db'     => 'tally_orders table mein id=' . $savedOrder->id . ' dekho — sync_status change hoga',
            'check_logs'   => 'tail -f storage/logs/laravel.log',
            'queue_worker' => 'php artisan queue:work --tries=3 --timeout=120',
            'tally_check'  => 'Tally mein Sales voucher: ' . $resolvedInvoice . ' dhundho',
        ],
    ]);
});