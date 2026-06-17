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
        try {

            /*
            |--------------------------------------------------------------------------
            | RAW JSON PAYLOAD
            |--------------------------------------------------------------------------
            | WooCommerce sends application/json body
            | $request->all() won't work — must use getContent()
            */

            $raw = $request->getContent();

            $payload = json_decode($raw, true);

            /*
            |--------------------------------------------------------------------------
            | INVALID JSON
            |--------------------------------------------------------------------------
            */

            if (
                empty($payload) ||
                !is_array($payload)
            ) {

                Log::warning('INVALID WEBHOOK JSON', [
                    'raw' => substr($raw, 0, 500)
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload'
                ], 400);
            }

            /*
            |--------------------------------------------------------------------------
            | LOG RAW PAYLOAD
            |--------------------------------------------------------------------------
            */

            Log::info('RAW WC PAYLOAD', [
                'order_id'     => $payload['id'] ?? null,
                'order_number' => $payload['number'] ?? null,
                'status'       => $payload['status'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | TEST WEBHOOK PING
            |--------------------------------------------------------------------------
            | WooCommerce sends only webhook_id on save/test
            | No real order data — just acknowledge it
            */

            if (empty($payload['id'])) {

                Log::warning('WEBHOOK TEST PING', [
                    'payload' => $payload
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Webhook ping received'
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | ORDER STATUS
            |--------------------------------------------------------------------------
            | processing = prepaid online order success
            | completed  = COD / manually completed
            */

            $status = strtolower(
                $payload['status'] ?? ''
            );

            if (
                !in_array($status, ['processing', 'completed'])
            ) {

                Log::info('ORDER SKIPPED — STATUS NOT ALLOWED', [
                    'order_id'     => $payload['id'],
                    'order_number' => $payload['number'] ?? null,
                    'status'       => $status
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Status skipped: ' . $status
                ], 200);
            }

            Log::info('WC ORDER RECEIVED', [
                'order_id'     => $payload['id'],
                'order_number' => $payload['number'] ?? null,
                'status'       => $status
            ]);

            /*
            |--------------------------------------------------------------------------
            | EXISTING ORDER CHECK
            |--------------------------------------------------------------------------
            */

            $existingOrder = TallyOrder::where(
                'woocommerce_order_id',
                $payload['id']
            )->first();

            /*
            |--------------------------------------------------------------------------
            | BLOCK DUPLICATE SUCCESS
            |--------------------------------------------------------------------------
            */

            if (
                $existingOrder &&
                $existingOrder->sync_status === 'success'
            ) {

                Log::info('ORDER ALREADY SYNCED — SKIPPING', [
                    'order_id'     => $payload['id'],
                    'order_number' => $payload['number'] ?? null
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Already synced'
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | BLOCK DUPLICATE PROCESSING
            |--------------------------------------------------------------------------
            */

            if (
                $existingOrder &&
                $existingOrder->sync_status === 'processing'
            ) {

                Log::warning('ORDER ALREADY IN PROCESSING — SKIPPING', [
                    'order_id'     => $payload['id'],
                    'order_number' => $payload['number'] ?? null
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Already processing'
                ], 200);
            }

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
            | INVOICE NUMBER
            |--------------------------------------------------------------------------
            | Try WooCommerce PDF Invoices plugin fields first
            */

            $invoiceNumber =
                $metaData['_wcpdf_invoice_number']
                ?? $metaData['_invoice_number']
                ?? $payload['invoice_number']
                ?? ('INV-' . ($payload['number'] ?? $payload['id']));

            /*
            |--------------------------------------------------------------------------
            | PLATFORM FEE
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
            | LINE ITEMS
            |--------------------------------------------------------------------------
            */

            $items = [];

            foreach ($payload['line_items'] ?? [] as $lineItem) {

                $qty = (float) ($lineItem['quantity'] ?? 1);

                if ($qty <= 0) {
                    $qty = 1;
                }

                $subtotal = (float) ($lineItem['subtotal'] ?? 0);
                $subtotalTax = (float) ($lineItem['subtotal_tax'] ?? 0);

                $rate = round($subtotal / $qty, 2);

                $gstRate = $this->extractGstRate($lineItem);

                $items[] = [

                    'product_id'   => $lineItem['product_id'] ?? null,
                    'variation_id' => $lineItem['variation_id'] ?? null,
                    'name'         => $lineItem['name'] ?? 'Product',
                    'sku'          => $lineItem['sku'] ?? '',
                    'quantity'     => $qty,
                    'rate'         => $rate,
                    'subtotal'     => $subtotal,
                    'total'        => (float) ($lineItem['total'] ?? 0),
                    'subtotal_tax' => $subtotalTax,
                    'total_tax'    => (float) ($lineItem['total_tax'] ?? 0),
                    'tax_class'    => $lineItem['tax_class'] ?? '',
                    'gst_rate'     => $gstRate,
                    'group'        => $this->detectGroup($lineItem),
                    'unit'         => 'PCS',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | ORDER DATA — CLEAN STRUCTURE
            |--------------------------------------------------------------------------
            | This is what gets stored in DB and passed to TallyVoucherService
            */

            $orderData = [

                'id'     => $payload['id'],
                'number' => $payload['number'] ?? $payload['id'],
                'status' => $status,

                'invoice_number'  => $invoiceNumber,
                'currency'        => $payload['currency'] ?? 'INR',

                'payment_method'       => $payload['payment_method'] ?? '',
                'payment_method_title' => $payload['payment_method_title'] ?? 'Online',
                'transaction_id'       => $payload['transaction_id'] ?? '',

                'date_created' => $payload['date_created'] ?? null,

                'billing'  => $payload['billing'] ?? [],
                'shipping' => $payload['shipping'] ?? [],

                'customer_note' => $payload['customer_note'] ?? '',

                'meta_data'      => $payload['meta_data'] ?? [],
                'coupon_lines'   => $payload['coupon_lines'] ?? [],
                'fee_lines'      => $payload['fee_lines'] ?? [],
                'shipping_lines' => $payload['shipping_lines'] ?? [],
                'tax_lines'      => $payload['tax_lines'] ?? [],

                'line_items' => $items,

                'discount_total' => (float) ($payload['discount_total'] ?? 0),
                'discount_tax'   => (float) ($payload['discount_tax'] ?? 0),
                'shipping_total' => (float) ($payload['shipping_total'] ?? 0),
                'shipping_tax'   => (float) ($payload['shipping_tax'] ?? 0),
                'cart_tax'       => (float) ($payload['cart_tax'] ?? 0),
                'total_tax'      => (float) ($payload['total_tax'] ?? 0),
                'total'          => (float) ($payload['total'] ?? 0),

                'platform_fee'   => $platformFee,
            ];

            /*
            |--------------------------------------------------------------------------
            | SAVE / UPDATE ORDER
            |--------------------------------------------------------------------------
            */

            $savedOrder = TallyOrder::updateOrCreate(

                [
                    'woocommerce_order_id' => $payload['id']
                ],

                [
                    'order_number'        => $payload['number'] ?? $payload['id'],
                    'customer_name'       => $customerName,
                    'amount'              => (float) ($payload['total'] ?? 0),
                    'payload'             => json_encode($orderData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'tax_lines'           => json_encode($payload['tax_lines'] ?? [], JSON_UNESCAPED_UNICODE),
                    'webhook_headers'     => json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE),
                    'webhook_received_at' => now(),
                    'sync_status'         => 'pending',
                    'retry_count'         => 0,
                    'last_error'          => null,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | DISPATCH JOB
            |--------------------------------------------------------------------------
            */

            SyncOrderToTallyJob::dispatch($savedOrder->id);

            Log::info('ORDER QUEUED FOR TALLY', [
                'db_order_id'         => $savedOrder->id,
                'woocommerce_order_id' => $payload['id'],
                'order_number'        => $payload['number'] ?? null,
                'invoice_number'      => $invoiceNumber,
                'customer'            => $customerName,
                'total'               => $orderData['total'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order queued successfully'
            ], 200);

        } catch (\Throwable $e) {

            Log::error('WEBHOOK EXCEPTION', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => substr($e->getTraceAsString(), 0, 1000),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook failed'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT GST RATE
    |--------------------------------------------------------------------------
    | Calculate from subtotal vs subtotal_tax
    | Normalize to standard Indian GST slabs: 0, 5, 12, 18, 28
    */

    private function extractGstRate(array $lineItem): float
    {
        $subtotal = (float) ($lineItem['subtotal'] ?? 0);
        $tax      = (float) ($lineItem['subtotal_tax'] ?? 0);

        if ($subtotal <= 0 || $tax <= 0) {
            return 0;
        }

        $rate = ($tax / $subtotal) * 100;

        if ($rate <= 1)   return 0;
        if ($rate <= 5.5) return 5;
        if ($rate <= 12.5) return 12;
        if ($rate <= 18.5) return 18;
        if ($rate <= 28.5) return 28;

        return round($rate, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT PRODUCT GROUP
    |--------------------------------------------------------------------------
    */

    private function detectGroup(array $lineItem): string
    {
        $taxClass = strtolower($lineItem['tax_class'] ?? '');
        $name     = strtolower($lineItem['name'] ?? '');
        $sku      = strtolower($lineItem['sku'] ?? '');

        if (
            str_contains($taxClass, 'fertilizer') ||
            str_contains($name, 'fertilizer') ||
            str_contains($name, 'manure') ||
            str_contains($name, 'compost') ||
            str_contains($sku, 'fert')
        ) {
            return 'FERTILIZER';
        }

        if (
            str_contains($taxClass, 'tool') ||
            str_contains($name, 'tool') ||
            str_contains($name, 'kurpi') ||
            str_contains($name, 'spade') ||
            str_contains($name, 'shovel') ||
            str_contains($name, 'trowel') ||
            str_contains($sku, 'tool')
        ) {
            return 'TOOLS';
        }

        if (
            str_contains($taxClass, 'seed') ||
            str_contains($name, 'seed') ||
            str_contains($sku, 'seed')
        ) {
            return 'SEEDS';
        }

        if (
            str_contains($taxClass, 'sapling') ||
            str_contains($name, 'sapling')
        ) {
            return 'SAPLINGS';
        }

        if (
            str_contains($taxClass, 'plant') ||
            str_contains($name, 'plant') ||
            str_contains($name, 'tree') ||
            str_contains($name, 'shrub')
        ) {
            return 'PLANTS';
        }

        return 'BULBS';
    }
}
