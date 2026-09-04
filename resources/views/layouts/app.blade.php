<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Taqamul Slot Holder Engine')</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #1e293b;
            color: #fff;
        }
        .sidebar .nav-link {
            color: #94a3b8;
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 8px;
            margin: 4px 12px;
            transition: all 0.2s ease-in-out;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #ffffff;
            background-color: #3b82f6;
        }
        .sidebar .nav-link i {
            width: 24px;
        }
        .brand-logo {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 20px;
            border-bottom: 1px solid #334155;
            color: #38bdf8;
        }
        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 28px;
        }
        .content-body {
            padding: 28px;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            background: #ffffff;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 42px;
            padding: 6px 12px;
            border-radius: 8px;
        }
    </style>
    @yield('styles')
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 px-0 sidebar d-flex flex-column">
            <div class="brand-logo d-flex align-items-center">
                <i class="fa-solid fa-bolt me-2 text-warning"></i> TAQAMUL ENGINE
            </div>
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="fa-solid fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('hold') ? 'active' : '' }}" href="{{ route('hold') }}">
                        <i class="fa-solid fa-hand-holding-hand"></i> Hold Slot
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('vault') ? 'active' : '' }}" href="{{ route('vault') }}">
                        <i class="fa-solid fa-vault"></i> Slot Vault
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}">
                        <i class="fa-solid fa-gear"></i> Settings
                    </a>
                </li>
            </ul>
            <div class="mt-auto p-3 text-center border-top border-secondary text-muted small">
                System Status: <span class="badge bg-success">Online</span>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="col-md-9 col-lg-10 px-0">
            <!-- Top Header -->
            <div class="top-navbar d-flex justify-content-between align-items-center">
                <h5 class="m-0 font-weight-bold text-dark">@yield('page_title', 'Dashboard')</h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-light text-dark border">
                        <i class="fa-solid fa-clock text-primary"></i> <span id="live-time"></span>
                    </span>
                    @auth
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-circle-user text-primary fs-6"></i>
                            <span class="fw-semibold text-dark">{{ Auth::user()->name ?? 'User' }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenu">
                            <li><span class="dropdown-item-text text-muted small"><i class="fa-regular fa-envelope me-1"></i> {{ Auth::user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger fw-semibold">
                                        <i class="fa-solid fa-right-from-bracket me-1"></i> Log Out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    @endauth
                </div>
            </div>

            <!-- Page Body -->
            <div class="content-body">
                @yield('content')
            </div>
        </div>
    </div>
</div>

<!-- jQuery (Required for Select2) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('live-time').innerText = now.toLocaleTimeString();
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>
@yield('scripts')
</body>
</html>
