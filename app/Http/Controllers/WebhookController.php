<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use App\Jobs\SyncOrderToTallyJob;
use App\Models\TallyOrder;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        /*
        |--------------------------------------------------------------------------
        | ORDER STATUS
        |--------------------------------------------------------------------------
        */

        $status = strtolower(
            $payload['status'] ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | SKIP FAILED / CANCELLED / REFUNDED
        |--------------------------------------------------------------------------
        */

        if ($status !== 'processing') {

            Log::info('ORDER SKIPPED', [

                'order_number' => $payload['number'] ?? '',

                'status' => $status
            ]);

            return response()->json([

                'success' => false,

                'message' => 'Only processing orders allowed'
            ]);
        }{

            Log::info('ORDER SKIPPED', [

                'order_number' => $payload['number'] ?? '',

                'status' => $status
            ]);

            return response()->json([

                'success' => false,

                'message' => 'Order skipped'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | WEBHOOK LOG
        |--------------------------------------------------------------------------
        */

        Log::info('WC ORDER RECEIVED', [

            'order_number' => $payload['number'] ?? '',

            'status' => $status
        ]);

        /*
        |--------------------------------------------------------------------------
        | ITEMS
        |--------------------------------------------------------------------------
        */

        $items = [];

        foreach ($payload['line_items'] ?? [] as $lineItem) {

            $items[] = [

                'name' => $lineItem['name'] ?? '',

                'qty' => (float) (
                    $lineItem['quantity'] ?? 1
                ),

                'rate' => (float) (
                    $lineItem['price'] ?? 0
                ),

                'tax_class' => $lineItem['tax_class'] ?? '',

                'sku' => $lineItem['sku'] ?? '',

                'hsn_code' => $lineItem['hsn_code'] ?? '',

                'unit' => 'PCS',

                'group' => 'BULBS',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SHIPPING
        |--------------------------------------------------------------------------
        */

        $shippingTotal = (float) (
            $payload['shipping_total'] ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | DISCOUNT
        |--------------------------------------------------------------------------
        */

        $discountTotal = (float) (
            $payload['discount_total'] ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | PLATFORM FEE
        |--------------------------------------------------------------------------
        */

        $platformFee = (float) (
            $this->extractPlatformFee($payload)
        );

        /*
        |--------------------------------------------------------------------------
        | FINAL TOTAL
        |--------------------------------------------------------------------------
        */

        $finalTotal = (float) (
            $payload['total'] ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | ORDER ARRAY
        |--------------------------------------------------------------------------
        */

        $order = [

            'customer_name' => trim(
                ($payload['billing']['first_name'] ?? '') . ' ' .
                ($payload['billing']['last_name'] ?? '')
            ),

            'order_number' => $payload['number'] ?? '',

            'woocommerce_order_id' => $payload['id'] ?? '',

            'state' => $payload['billing']['state'] ?? 'RJ',

            'payment_method' => $payload['payment_method_title'] ?? '',

            'shipping_total' => $shippingTotal,

            'discount_total' => $discountTotal,

            'platform_fee' => $platformFee,

            'final_total' => $finalTotal,

            'items' => $items,
        ];

        /*
        |--------------------------------------------------------------------------
        | CREATE OR UPDATE ORDER
        |--------------------------------------------------------------------------
        */

        $existingOrder = TallyOrder::where(
            'woocommerce_order_id',
            $order['woocommerce_order_id']
        )->first();

        if ($existingOrder) {

            Log::info('ORDER ALREADY EXISTS', [

                'order_number' => $order['order_number'],

                'sync_status' => $existingOrder->sync_status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order already queued'
            ]);
        }

        $savedOrder = TallyOrder::create([

            'woocommerce_order_id' =>
                $order['woocommerce_order_id'],

            'order_number' =>
                $order['order_number'],

            'customer_name' =>
                $order['customer_name'],

            'amount' =>
                $finalTotal,

            'payload' =>
                json_encode($order),

            'sync_status' =>
                'pending',

            'retry_count' => 0,

            'last_error' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | DISPATCH JOB
        |--------------------------------------------------------------------------
        */

        SyncOrderToTallyJob::dispatch(
            $savedOrder->id
        );

        Log::info('ORDER QUEUED', [

            'order_number' => $order['order_number'],

            'amount' => $finalTotal
        ]);

        return response()->json([

            'success' => true
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PLATFORM FEE
    |--------------------------------------------------------------------------
    */

    private function extractPlatformFee($payload)
    {
        foreach ($payload['fee_lines'] ?? [] as $fee) {

            if (
                str_contains(
                    strtolower($fee['name'] ?? ''),
                    'platform'
                )
            ) {

                return (float) (
                    $fee['total'] ?? 0
                );
            }
        }

        return 0;
    }
}