<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Taqamul Slot Holder Engine')</title>
    <!-- Site Favicon (AI Bot Engine Icon) -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        :root {
            --sidebar-width: 210px;
            --sidebar-collapsed-width: 65px;
            --top-navbar-height: 60px;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Fixed Sidebar Navigation */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background-color: #1e293b;
            color: #fff;
            z-index: 1040;
            transition: width 0.25s ease-in-out;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.12);
        }

        .brand-logo {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 0 16px;
            border-bottom: 1px solid #334155;
            color: #38bdf8;
            white-space: nowrap;
            height: var(--top-navbar-height);
            display: flex;
            align-items: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .brand-text {
            transition: opacity 0.2s ease;
        }

        .sidebar .nav-link {
            color: #94a3b8;
            padding: 10px 14px;
            font-weight: 500;
            border-radius: 8px;
            margin: 4px 8px;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
            display: flex;
            align-items: center;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #ffffff;
            background-color: #3b82f6;
        }

        .sidebar .nav-link i {
            width: 24px;
            font-size: 1.05rem;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-text {
            margin-left: 8px;
            transition: opacity 0.2s ease;
        }

        .sidebar-status-footer {
            white-space: nowrap;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* Collapsed Sidebar Mode */
        body.sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        body.sidebar-collapsed .brand-text,
        body.sidebar-collapsed .sidebar-text,
        body.sidebar-collapsed .sidebar-status-footer small {
            display: none !important;
        }

        body.sidebar-collapsed .sidebar .nav-link {
            justify-content: center;
            padding: 10px 0;
            margin: 4px 6px;
        }

        body.sidebar-collapsed .sidebar .nav-link i {
            margin: 0;
        }

        /* Main Wrapper */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.25s ease-in-out;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        body.sidebar-collapsed .main-wrapper {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Top Header - Sticky */
        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 24px;
            height: var(--top-navbar-height);
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .content-body {
            padding: 24px;
            flex-grow: 1;
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

        /* Responsive Mobile Layout */
        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width) !important;
            }
            body.sidebar-open .sidebar {
                transform: translateX(0);
            }
            .main-wrapper {
                margin-left: 0 !important;
            }
        }
    </style>
    @yield('styles')
</head>
<body class="{{ request()->cookie('sidebar_state') === 'collapsed' ? 'sidebar-collapsed' : '' }}">

<!-- Fixed Sidebar Navigation -->
<div class="sidebar">
    <div class="brand-logo d-flex align-items-center">
        <i class="fa-solid fa-bolt me-2 text-warning fs-5"></i>
        <span class="brand-text">TAQAMUL ENGINE</span>
    </div>
    <ul class="nav flex-column mt-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard">
                <i class="fa-solid fa-chart-line"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('hold') ? 'active' : '' }}" href="{{ route('hold') }}" title="Hold Slot">
                <i class="fa-solid fa-hand-holding-hand"></i>
                <span class="sidebar-text">Hold Slot</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('vault') ? 'active' : '' }}" href="{{ route('vault') }}" title="Slot Vault">
                <i class="fa-solid fa-vault"></i>
                <span class="sidebar-text">Slot Vault</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('auto_login*') ? 'active' : '' }}" href="{{ route('auto_login') }}" title="Auto Login Checker">
                <i class="fa-solid fa-key text-warning"></i>
                <span class="sidebar-text">Auto Login Checker</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('ip_manager*') ? 'active' : '' }}" href="{{ route('ip_manager') }}" title="IP Manager">
                <i class="fa-solid fa-network-wired text-info"></i>
                <span class="sidebar-text">IP Manager</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}" title="Settings">
                <i class="fa-solid fa-gear"></i>
                <span class="sidebar-text">Settings</span>
            </a>
        </li>
    </ul>
    <div class="mt-auto p-3 text-center border-top border-secondary text-muted small sidebar-status-footer">
        <small class="d-block mb-1">System Status:</small>
        <span class="badge bg-success" title="System Online">Online</span>
    </div>
</div>

<!-- Main Content Area -->
<div class="main-wrapper">
    <!-- Top Header Navigation -->
    <div class="top-navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light border shadow-sm px-2.5 py-1" id="sidebarToggleBtn" title="Toggle Sidebar">
                <i class="fa-solid fa-bars fs-6 text-secondary"></i>
            </button>
            <h5 class="m-0 font-weight-bold text-dark">@yield('page_title', 'Dashboard')</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-light text-dark border">
                <i class="fa-solid fa-clock text-primary me-1"></i> <span id="live-time"></span>
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

<!-- jQuery (Required for Select2) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Restore sidebar state from localStorage
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }

    // Toggle Sidebar Open / Close
    document.getElementById('sidebarToggleBtn').addEventListener('click', function() {
        if (window.innerWidth < 768) {
            document.body.classList.toggle('sidebar-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
            document.cookie = `sidebar_state=${isCollapsed ? 'collapsed' : 'expanded'}; path=/; max-age=31536000`;
        }
    });

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
