<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Order Details</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        body{
            background: #f8fafc;
            font-family: sans-serif;
        }

        .card-box{
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .label{
            font-weight: 700;
            color: #475569;
        }

        pre{
            background: #0f172a;
            color: #f8fafc;
            padding: 20px;
            border-radius: 15px;
            overflow-x: auto;
        }

    </style>

</head>
<body>

<div class="container py-5">

    <div class="mb-4">

        <a href="{{ url()->previous() }}" class="btn btn-dark">
            ← Back
        </a>

    </div>

    <div class="card-box">

        <h2 class="fw-bold mb-4">
            📦 Order #{{ $order->order_number }}
        </h2>

        <div class="row g-4">

            <div class="col-md-6">

                <div class="mb-3">
                    <div class="label">WooCommerce Order ID</div>
                    <div>{{ $order->woocommerce_order_id }}</div>
                </div>

                <div class="mb-3">
                    <div class="label">Customer</div>
                    <div>{{ $order->customer_name ?? 'N/A' }}</div>
                </div>

                <div class="mb-3">
                    <div class="label">Amount</div>
                    <div>₹{{ $order->amount ?? 0 }}</div>
                </div>

                <div class="mb-3">
                    <div class="label">Sync Status</div>
                    <div>{{ strtoupper($order->sync_status) }}</div>
                </div>

            </div>

            <div class="col-md-6">

                <div class="mb-3">
                    <div class="label">Retries</div>
                    <div>{{ $order->retry_count }}</div>
                </div>

                <div class="mb-3">
                    <div class="label">Last Error</div>
                    <div class="text-danger">
                        {{ $order->last_error ?? 'No Error' }}
                    </div>
                </div>

                <div class="mb-3">
                    <div class="label">Synced At</div>
                    <div>{{ $order->synced_at }}</div>
                </div>

                <div class="mb-3">
                    <div class="label">Created At</div>
                    <div>{{ $order->created_at }}</div>
                </div>

            </div>

        </div>

        <hr class="my-4">

        <h4 class="fw-bold mb-3">
            Payload Data
        </h4>

        <pre>{{ json_encode($order->payload, JSON_PRETTY_PRINT) }}</pre>

    </div>

</div>

</body>
</html>