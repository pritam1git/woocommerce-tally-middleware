@extends('layouts.app')

@section('content')

<div class="container">

    <div class="row justify-content-center align-items-center min-vh-75">

        <div class="col-lg-10">

            <div class="glass-card overflow-hidden">

                <div class="row g-0">

                    <div class="col-lg-6 d-none d-lg-flex">

                        <div class="p-5 d-flex flex-column justify-content-center h-100">

                            <span class="feature-badge">
                                UPJAU TALLY CONNECT
                            </span>

                            <h1 class="fw-bold display-5 mb-4">
                                WooCommerce to Tally Automation
                            </h1>

                            <p class="text-muted-custom fs-5 mb-4">
                                Automatically sync WooCommerce orders into Tally ERP with GST handling, vouchers, ledgers, queue processing and multi-channel integrations.
                            </p>

                            <div class="mb-4">

                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">✅</div>
                                    <div>Automatic Sales Voucher Creation</div>
                                </div>

                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">✅</div>
                                    <div>GST & Tax Ledger Handling</div>
                                </div>

                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">✅</div>
                                    <div>Queue Based Reliable Processing</div>
                                </div>

                                <div class="d-flex align-items-center">
                                    <div class="me-3">✅</div>
                                    <div>1,999/year Multi Channel Support</div>
                                </div>

                            </div>

                            <div class="footer-note">
                                Powered by UPJAU.IN
                            </div>

                        </div>

                    </div>

                    <div class="col-lg-6">

                        <div class="p-5">

                            <div class="mb-4">

                                <span class="feature-badge">
                                    ADMIN ACCESS
                                </span>

                                <h2 class="fw-bold mb-2">
                                    Welcome Back
                                </h2>

                                <p class="text-muted-custom">
                                    Login to manage Tally sync operations and WooCommerce order processing.
                                </p>

                            </div>

                            <form method="POST" action="{{ route('login') }}">
                                @csrf

                                <div class="mb-4">

                                    <label class="form-label mb-2">
                                        Email Address
                                    </label>

                                    <input
                                        id="email"
                                        type="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autofocus
                                        placeholder="Enter admin email">

                                    @error('email')
                                        <span class="invalid-feedback d-block mt-2">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror

                                </div>

                                <div class="mb-4">

                                    <label class="form-label mb-2">
                                        Password
                                    </label>

                                    <input
                                        id="password"
                                        type="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        name="password"
                                        required
                                        placeholder="Enter password">

                                    @error('password')
                                        <span class="invalid-feedback d-block mt-2">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror

                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-4">

                                    <div class="form-check">

                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="remember"
                                               id="remember"
                                               {{ old('remember') ? 'checked' : '' }}>

                                        <label class="form-check-label text-light" for="remember">
                                            Remember Me
                                        </label>

                                    </div>

                                </div>

                                <button type="submit" class="btn btn-green w-100">
                                    Login to Dashboard
                                </button>

                            </form>

                            <div class="mt-4 text-center">

                                <small class="text-muted-custom">
                                    Registration is disabled.<br>
                                    Your admin account will be created manually by UPJAU Team.
                                </small>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection