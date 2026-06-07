<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Upjau Tally Sync') }}</title>

    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:300,400,600,700,800" rel="stylesheet">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        body{
            margin:0;
            padding:0;
            background:#0f172a;
            color:#fff;
            font-family:'Nunito',sans-serif;
            min-height:100vh;
            overflow-x:hidden;
        }

        .bg-blur{
            position:fixed;
            width:500px;
            height:500px;
            border-radius:50%;
            filter:blur(120px);
            opacity:.25;
            z-index:0;
        }

        .blur-1{
            background:#22c55e;
            top:-150px;
            left:-100px;
        }

        .blur-2{
            background:#3b82f6;
            bottom:-200px;
            right:-100px;
        }

        .main-wrapper{
            position:relative;
            z-index:2;
        }

        .navbar-custom{
            background:rgba(15,23,42,.75);
            backdrop-filter:blur(12px);
            border-bottom:1px solid rgba(255,255,255,.08);
            padding:16px 0;
        }

        .brand-logo{
            font-size:24px;
            font-weight:800;
            color:#fff !important;
            text-decoration:none;
        }

        .brand-logo span{
            color:#22c55e;
        }

        .nav-btn{
            background:#22c55e;
            color:#fff !important;
            padding:10px 22px;
            border-radius:12px;
            font-weight:700;
            text-decoration:none;
            transition:.3s;
        }

        .nav-btn:hover{
            background:#16a34a;
            transform:translateY(-2px);
        }

        .glass-card{
            background:rgba(255,255,255,.06);
            border:1px solid rgba(255,255,255,.08);
            border-radius:24px;
            backdrop-filter:blur(15px);
            box-shadow:0 10px 40px rgba(0,0,0,.35);
        }

        .form-control{
            background:rgba(255,255,255,.08) !important;
            border:1px solid rgba(255,255,255,.12) !important;
            color:#fff !important;
            border-radius:14px;
            padding:14px 18px;
        }

        .form-control:focus{
            box-shadow:none;
            border-color:#22c55e !important;
        }

        .form-control::placeholder{
            color:#cbd5e1;
        }

        .btn-green{
            background:#22c55e;
            border:none;
            color:#fff;
            font-weight:700;
            padding:14px;
            border-radius:14px;
            transition:.3s;
        }

        .btn-green:hover{
            background:#16a34a;
            transform:translateY(-2px);
        }

        .text-muted-custom{
            color:#cbd5e1;
        }

        .feature-badge{
            display:inline-block;
            padding:8px 16px;
            border-radius:999px;
            background:rgba(34,197,94,.15);
            color:#4ade80;
            font-size:13px;
            font-weight:700;
            margin-bottom:18px;
        }

        .footer-note{
            color:#94a3b8;
            font-size:14px;
        }
    </style>
</head>
<body>

<div class="bg-blur blur-1"></div>
<div class="bg-blur blur-2"></div>

<div id="app" class="main-wrapper">

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">

            <a class="brand-logo" href="{{ url('/') }}">
                UPJAU <span>TALLY</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">

                <ul class="navbar-nav ms-auto align-items-center">

                    @guest

                        <li class="nav-item me-3">
                            <a class="nav-link text-white" href="{{ url('/') }}">
                                Home
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-btn" href="{{ route('login') }}">
                                Admin Login
                            </a>
                        </li>

                    @else

                        <li class="nav-item dropdown">

                            <a id="navbarDropdown"
                               class="nav-link dropdown-toggle text-white"
                               href="#"
                               role="button"
                               data-bs-toggle="dropdown">

                                {{ Auth::user()->name }}
                            </a>

                            <div class="dropdown-menu dropdown-menu-end">

                                <a class="dropdown-item"
                                   href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                   document.getElementById('logout-form').submit();">

                                    Logout
                                </a>

                                <form id="logout-form"
                                      action="{{ route('logout') }}"
                                      method="POST"
                                      class="d-none">

                                    @csrf
                                </form>

                            </div>
                        </li>

                    @endguest

                </ul>

            </div>

        </div>
    </nav>

    <main class="py-5">
        @yield('content')
    </main>

</div>

</body>
</html>