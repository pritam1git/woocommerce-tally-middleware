<?php

use Illuminate\Support\Facades\Route;

use App\Services\Tally\TallyOrderSyncService;
use App\Http\Controllers\WebhookController;
use App\Jobs\SyncOrderToTallyJob;
use App\Http\Controllers\Admin\DashboardController;

Route::middleware(['auth'])->prefix('admin')->group(function () {

    Route::get('/dashboard', [
        DashboardController::class,
        'index'
    ])->name('admin.dashboard');
    Route::get('/admin/orders/{id}', [DashboardController::class, 'show'])
        ->name('admin.orders.show');

    Route::delete('/admin/orders/{id}', [DashboardController::class, 'delete'])
        ->name('admin.orders.delete');
    Route::post('/retry/{id}', [
        DashboardController::class,
        'retry'
    ])->name('admin.retry');
});

Route::get('/test-tally', function () {

    $order = [

        /*
        |--------------------------------------------------------------------------
        | CUSTOMER
        |--------------------------------------------------------------------------
        */

        'customer_name' => 'Pritam Customer',

        'order_number' => 'TEST-' . time(),

        'woocommerce_order_id' => rand(1000, 9999),

        'state' => 'UP',

        'address' => 'House No 21, Civil Lines',

        'city' => 'Lucknow',

        'pincode' => '226001',

        'phone' => '9876543210',

        'email' => 'pritam@example.com',

        /*
        |--------------------------------------------------------------------------
        | PAYMENT
        |--------------------------------------------------------------------------
        */

        'payment_method' => 'Cash On Delivery',

        /*
        |--------------------------------------------------------------------------
        | CHARGES
        |--------------------------------------------------------------------------
        */

        'shipping_total' => 40,

        'platform_fee' => 5,

        'discount_total' => 20,

        /*
        |--------------------------------------------------------------------------
        | FINAL TOTAL
        |--------------------------------------------------------------------------
        | Exact payable amount
        */

        'final_total' => 796.82,

        /*
        |--------------------------------------------------------------------------
        | ITEMS
        |--------------------------------------------------------------------------
        */

        'items' => [

            /*
            |--------------------------------------------------------------------------
            | NIL GST ITEM
            |--------------------------------------------------------------------------
            */

            [
                'name' => 'Achimenes Blue Bulb',

                'sku' => 'ACH-BL-BL',

                'hsn_code' => '0601',

                'qty' => 1,

                'rate' => 49,

                'line_total' => 49,

                'line_subtotal' => 49,

                'tax_total' => 0,

                'unit' => 'PCS',

                'group' => 'BULBS',
            ],

            /*
            |--------------------------------------------------------------------------
            | 5% GST ITEM
            |--------------------------------------------------------------------------
            */

            [
                'name' => 'Organic Neem Cake Fertilizer',

                'sku' => 'NC-5-GST',

                'hsn_code' => '3101',

                'qty' => 2,

                'rate' => 120,

                'line_total' => 240,

                'line_subtotal' => 240,

                'tax_total' => 12,

                'unit' => 'PCS',

                'group' => 'FERTILIZER',
            ],

            /*
            |--------------------------------------------------------------------------
            | 18% GST ITEM
            |--------------------------------------------------------------------------
            */

            [
                'name' => 'Kurpi Gardening Tool with Wooden Handle',

                'sku' => 'KU-PC-WH',

                'hsn_code' => '8201',

                'qty' => 1,

                'rate' => 399,

                'line_total' => 399,
            
                'line_subtotal' => 399,

                'line_subtotal' => 399,

                'tax_total' => 71.82,

                'unit' => 'PCS',

                'group' => 'TOOLS',
            ],
        ]
    ];

    $result = app(
        \App\Services\Tally\TallyVoucherService::class
    )->create($order);

    return response()->json([

        'success' => $result,

        'message' => $result
            ? 'Voucher Created Successfully'
            : 'Voucher Creation Failed',

        'test_order' => $order
    ]);
});
Auth::routes();

Route::get('/', function () {
    return view('welcome');
});
Route::get('/home', function () {
    return redirect('/admin/dashboard');
});