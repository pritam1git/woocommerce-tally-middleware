<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TallyOrder extends Model
{
    protected $fillable = [

        'woocommerce_order_id',

        'order_number',

        'customer_name',

        'amount',

        'payload',

        'sync_status',

        'retry_count',

        'last_error',

        'request_xml',

        'response_xml',

        'synced_at'
    ];

    protected $casts = [

        'payload' => 'array',

        'synced_at' => 'datetime'
    ];
}