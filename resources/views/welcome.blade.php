<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upjau Tally Connector</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body{
            background:#0f172a;
        }

        .glass{
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border:1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body class="text-white">

<section class="min-h-screen flex items-center justify-center px-6">

    <div class="max-w-5xl w-full">

        <div class="grid md:grid-cols-2 gap-10 items-center">

            <div>
                <span class="bg-green-500/20 text-green-300 px-4 py-2 rounded-full text-sm">
                    🚀 Coming Soon
                </span>

                <h1 class="text-5xl md:text-6xl font-bold leading-tight mt-6">
                    Upjau
                    <span class="text-green-400">
                        Tally Connector
                    </span>
                </h1>

                <p class="text-gray-300 text-lg mt-6 leading-8">
                    Connect WooCommerce, Shopify, Amazon, Flipkart and multiple sales channels directly with Tally ERP & Tally Prime.
                </p>

                <div class="mt-8 space-y-4">

                    <div class="flex items-center gap-3">
                        ✅ Automatic Voucher Creation
                    </div>

                    <div class="flex items-center gap-3">
                        ✅ GST Ledger Mapping
                    </div>

                    <div class="flex items-center gap-3">
                        ✅ Multi Channel Integration
                    </div>

                    <div class="flex items-center gap-3">
                        ✅ Auto Sync Orders to Tally
                    </div>

                </div>

                <div class="mt-10 flex gap-4 flex-wrap">

                    <div class="relative inline-block">

                        <div class="glass px-7 py-5 rounded-2xl border border-white/10 relative overflow-hidden min-w-[200px]">

                            {{-- COMING SOON RIBBON --}}

                            <div class="absolute top-5 -right-12 rotate-45 bg-red-500 text-white text-[11px] font-bold px-10 py-1 shadow-xl uppercase tracking-wider z-10">
                                Coming Soon
                            </div>

                            {{-- PRICE --}}

                            <div class="text-4xl font-extrabold text-green-400 relative z-20">
                                -----
                            </div>

                            <div class="text-gray-300 mt-2 text-sm relative z-20">
                                Yearly Plan
                            </div>

                        </div>

                    </div>

                    <div class="glass px-6 py-4 rounded-2xl">
                        <div class="text-3xl font-bold text-blue-400">
                            Unlimited
                        </div>

                        <div class="text-gray-300">
                            Channel Connections
                        </div>
                    </div>

                </div>

            </div>

            <div>

                <div class="glass rounded-3xl p-8 shadow-2xl">

                    <h2 class="text-3xl font-bold mb-6">
                        Features
                    </h2>

                    <div class="space-y-5">

                        <div class="p-5 rounded-2xl bg-white/5">
                            <h3 class="font-semibold text-xl">
                                WooCommerce Integration
                            </h3>

                            <p class="text-gray-300 mt-2">
                                Real-time order sync directly into Tally Prime.
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-white/5">
                            <h3 class="font-semibold text-xl">
                                GST Automation
                            </h3>

                            <p class="text-gray-300 mt-2">
                                Automatic GST ledger mapping with CGST, SGST & IGST support.
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-white/5">
                            <h3 class="font-semibold text-xl">
                                Multi Store Support
                            </h3>

                            <p class="text-gray-300 mt-2">
                                Connect multiple stores and platforms from one dashboard.
                            </p>
                        </div>

                    </div>

                    <div class="mt-8">
                        <a href="/login"
                           class="bg-green-500 hover:bg-green-600 transition px-6 py-4 rounded-2xl inline-block font-semibold">
                            Admin Login
                        </a>
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

</body>
</html>