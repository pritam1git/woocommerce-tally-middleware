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
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SyncOrderToTallyJob implements ShouldQueue, ShouldBeUnique
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

    public $tries = 3;

    /*
    |--------------------------------------------------------------------------
    | BACKOFF
    |--------------------------------------------------------------------------
    | Retry after: 1 min, 3 mins
    */

    public $backoff = [60, 180];

    public $timeout = 120;
    /*
    |--------------------------------------------------------------------------
    | PREVENT DUPLICATE JOBS
    |--------------------------------------------------------------------------
    */

    public $uniqueFor = 600;
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

        $tallyOrder = TallyOrder::find($this->orderId);

        if (!$tallyOrder) {

            Log::error('TALLY JOB — ORDER NOT FOUND IN DB', [
                'order_id' => $this->orderId
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SKIP IF ALREADY SYNCED
        |--------------------------------------------------------------------------
        | Prevent re-processing a successfully synced order
        | This can happen if webhook fires twice for same order
        */

        if ($tallyOrder->sync_status === 'success') {

            Log::info('TALLY JOB — SKIPPED (ALREADY SUCCESS)', [
                'order_id'     => $tallyOrder->id,
                'order_number' => $tallyOrder->order_number
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | MARK AS PROCESSING
        |--------------------------------------------------------------------------
        */

        $tallyOrder->update([
            'sync_status' => 'processing',
            'retry_count' => $this->attempts(),
            'last_error'  => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | DECODE PAYLOAD
        |--------------------------------------------------------------------------
        */

        $order = json_decode($tallyOrder->payload, true);

        /*
        |--------------------------------------------------------------------------
        | VALIDATE PAYLOAD
        |--------------------------------------------------------------------------
        */

        if (empty($order) || !is_array($order)) {

            throw new \Exception(
                'Invalid or empty payload in DB for order ID: ' . $this->orderId
            );
        }

        Log::info('TALLY JOB STARTED', [
            'db_order_id'          => $tallyOrder->id,
            'woocommerce_order_id' => $order['id'] ?? null,
            'order_number'         => $order['number'] ?? null,
            'invoice_number'       => $order['invoice_number'] ?? null,
            'attempt'              => $this->attempts(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | CREATE VOUCHER IN TALLY
        |--------------------------------------------------------------------------
        | TallyVoucherService::create() returns bool
        | true  = voucher created successfully in Tally
        | false = something failed (connection, XML error, Tally rejection)
        */

        $result = app(TallyVoucherService::class)->create($order);

        Log::info('TALLY SERVICE RESULT', [
            'order_number' => $order['number'] ?? null,
            'result'       => $result,
        ]);

        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        | TallyVoucherService returns bool — check strictly
        */

        if ($result === true) {

            $tallyOrder->update([
                'sync_status' => 'success',
                'synced_at'   => now(),
                'retry_count' => $this->attempts(),
                'last_error'  => null,
            ]);

            Log::info('TALLY SYNC SUCCESS', [
                'order_number'   => $order['number'] ?? null,
                'invoice_number' => $order['invoice_number'] ?? null,
                'customer'       => $tallyOrder->customer_name,
                'amount'         => $tallyOrder->amount,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | FAILURE → THROW EXCEPTION
        |--------------------------------------------------------------------------
        | Job will retry based on $backoff setting
        | After $tries exhausted → failed() method is called
        */

        throw new \Exception(
            'Tally voucher creation returned false for order: ' .
            ($order['number'] ?? $this->orderId)
        );
    }
    /*
    |--------------------------------------------------------------------------
    | UNIQUE JOB ID
    |--------------------------------------------------------------------------
    */

    public function uniqueId(): int
    {
        return $this->orderId;
    }
    /*
    |--------------------------------------------------------------------------
    | FAILED
    |--------------------------------------------------------------------------
    | Called when all retries exhausted
    */

    public function failed(Throwable $exception): void
    {
        $tallyOrder = TallyOrder::find($this->orderId);

        if (!$tallyOrder) {
            return;
        }

        $tallyOrder->update([
            'sync_status' => 'failed',
            'retry_count' => $this->attempts(),
            'last_error'  => substr($exception->getMessage(), 0, 2000),
        ]);

        Log::error('TALLY JOB PERMANENTLY FAILED', [
            'db_order_id'  => $this->orderId,
            'order_number' => $tallyOrder->order_number,
            'attempt'      => $this->attempts(),
            'message'      => $exception->getMessage(),
        ]);
    }
}
