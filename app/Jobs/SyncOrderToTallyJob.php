<?php

namespace App\Jobs;

use Throwable;
use App\Models\TallyOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Tally\TallyVoucherService;

class SyncOrderToTallyJob implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | QUEUE SETTINGS
    |--------------------------------------------------------------------------
    */

    public $tries = 10;

    public $backoff = [60, 120, 300];

    public $timeout = 120;

    /*
    |--------------------------------------------------------------------------
    | ORDER ID
    |--------------------------------------------------------------------------
    */

    public int $orderId;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(): void
    {
        /*
        |--------------------------------------------------------------------------
        | FETCH ORDER
        |--------------------------------------------------------------------------
        */

        $tallyOrder = TallyOrder::find(
            $this->orderId
        );

        if (!$tallyOrder) {

            Log::error('QUEUE ORDER NOT FOUND', [

                'order_id' => $this->orderId,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT DUPLICATE SUCCESS SYNC
        |--------------------------------------------------------------------------
        */

        if ($tallyOrder->sync_status === 'success') {

            Log::info('ORDER ALREADY SYNCED', [

                'order_number' => $tallyOrder->order_number,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | MARK PROCESSING
        |--------------------------------------------------------------------------
        */

        $tallyOrder->update([

            'sync_status' => 'processing',

            'retry_count' => $this->attempts(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | DECODE PAYLOAD
        |--------------------------------------------------------------------------
        */

        $order = json_decode(
            $tallyOrder->payload,
            true
        );

        if (
            empty($order)
            ||
            !is_array($order)
        ) {

            throw new \Exception(
                'Invalid order payload'
            );
        }

        Log::info('TALLY JOB STARTED', [

            'order_number' => (
                $order['order_number'] ?? null
            ),

            'attempt' => $this->attempts(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | CREATE VOUCHER
        |--------------------------------------------------------------------------
        */

        $response = app(
            TallyVoucherService::class
        )->create($order);

        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        if ($response === true) {

            $tallyOrder->update([

                'sync_status' => 'success',

                'synced_at' => now(),

                'last_error' => null,

                'retry_count' => $this->attempts(),
            ]);

            Log::info('TALLY JOB SUCCESS', [

                'order_number' => (
                    $order['order_number'] ?? null
                ),
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | FAILURE
        |--------------------------------------------------------------------------
        */

        throw new \Exception(
            'Voucher creation failed'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FAILED
    |--------------------------------------------------------------------------
    */

    public function failed(
        Throwable $exception
    ): void {

        $tallyOrder = TallyOrder::find(
            $this->orderId
        );

        if (!$tallyOrder) {
            return;
        }

        $tallyOrder->update([

            'sync_status' => 'failed',

            'retry_count' => $this->attempts(),

            'last_error' => substr(
                $exception->getMessage(),
                0,
                1000
            ),
        ]);

        Log::error('TALLY JOB FAILED', [

            'order_id' => $this->orderId,

            'order_number' => (
                $tallyOrder->order_number ?? null
            ),

            'attempt' => $this->attempts(),

            'message' => $exception->getMessage(),
        ]);
    }
}