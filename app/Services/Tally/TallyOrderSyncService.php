<?php

namespace App\Services\Tally;

use Illuminate\Support\Facades\Log;

class TallyOrderSyncService
{
    public function sync($order)
    {
        try {

            Log::info('Starting Tally Sync', [
                'order_number' => $order['order_number'] ?? ''
            ]);

            $response = app(TallyVoucherService::class)
                ->create($order);

            Log::info('Tally Sync Success', [
                'order_number' => $order['order_number'] ?? '',
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {

            Log::error('Tally Sync Failed', [

                'order_number' =>
                    $order['order_number'] ?? '',

                'message' => $e->getMessage(),

                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}