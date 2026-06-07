@extends('layouts.app')

@section('content')

<div class="container">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="glass-card p-5 text-center">

                <span class="feature-badge">
                    REGISTRATION DISABLED
                </span>

                <h1 class="fw-bold display-6 mb-4">
                    Account Registration Not Available
                </h1>

                <p class="text-muted-custom fs-5 mb-5">
                    New user registration is currently disabled for security and controlled onboarding purposes.
                    <br><br>
                    Your admin account will be created manually by the UPJAU Team.
                </p>

                <div class="row g-4 mb-5">

                    <div class="col-md-4">
                        <div class="glass-card p-4 h-100">
                            <div class="fs-2 mb-3">🔒</div>
                            <h5 class="fw-bold">Secure Access</h5>
                            <p class="text-muted-custom mb-0">
                                Only approved users can access the system.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="glass-card p-4 h-100">
                            <div class="fs-2 mb-3">⚡</div>
                            <h5 class="fw-bold">Fast Sync</h5>
                            <p class="text-muted-custom mb-0">
                                WooCommerce orders sync directly into Tally.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="glass-card p-4 h-100">
                            <div class="fs-2 mb-3">📊</div>
                            <h5 class="fw-bold">GST Automation</h5>
                            <p class="text-muted-custom mb-0">
                                Automated GST ledger and voucher handling.
                            </p>
                        </div>
                    </div>

                </div>

                <a href="{{ route('login') }}" class="btn btn-green px-5">
                    Go to Admin Login
                </a>

            </div>

        </div>

    </div>

</div>

@endsection