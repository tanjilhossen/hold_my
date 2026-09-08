@extends('layouts.app')

@section('title', 'Auto Login Manager - Zero Captcha Engine')
@section('page_title', 'Auto Login & Account Manager')

@section('styles')
<style>
    .terminal-box {
        background-color: #0f172a;
        color: #38ef7d;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.85rem;
        border-radius: 8px;
        padding: 14px;
        height: 250px;
        overflow-y: auto;
        border: 1px solid #334155;
    }
    .badge-soft-success {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .badge-soft-danger {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .badge-soft-warning {
        background-color: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .badge-soft-primary {
        background-color: #e0e7ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
    }
    .step-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 6px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        margin-bottom: 6px;
        font-size: 0.85rem;
    }
    .step-item.active {
        background: #eff6ff;
        border-color: #93c5fd;
        color: #1d4ed8;
        font-weight: 600;
    }
    .step-item.done {
        background: #f0fdf4;
        border-color: #86efac;
        color: #166534;
    }
    .table-hover tbody tr:hover {
        background-color: #f1f5f9;
    }
    .filter-btn.active {
        background-color: #0d6efd !important;
        color: white !important;
        border-color: #0d6efd !important;
    }
</style>
@endsection

@section('content')
<!-- Alert Explaining Zero Captcha vs Flagged Accounts -->
<div class="alert alert-info border-info d-flex align-items-center gap-3 mb-4 py-2 px-3">
    <div class="fs-4 text-info"><i class="fa-solid fa-circle-info"></i></div>
    <div class="small">
        <strong>Direct Legislator Payload (Zero Captcha):</strong>
        Taqamul API allows <strong>100% Captcha-Free direct login</strong> for clean accounts using the Legislator payload.
        Accounts flagged by Taqamul's server for reCAPTCHA require a funded CapSolver/2Captcha key, or you can skip them and instantly extract tokens for all the <strong>Zero-Captcha pool accounts</strong>!
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Stat 1: Zero Captcha Verified Accounts -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Zero-Captcha Verified</div>
                    <h3 class="fw-bold my-1 text-success" id="stat-zero-count">{{ $stats['zero_captcha_count'] ?? 0 }}</h3>
                    <small class="text-success fw-semibold"><i class="fa-solid fa-bolt me-1"></i> 100% Captcha-Free</small>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-circle p-3">
                    <i class="fa-solid fa-bolt fa-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 2: Active Tokens -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Active Bearer Tokens</div>
                    <h3 class="fw-bold my-1 text-primary" id="stat-active-count">{{ $stats['active'] }}</h3>
                    <small class="text-muted">Ready for slot holding/checking</small>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                    <i class="fa-solid fa-key fa-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 3: Flagged / Captcha Required -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Requires Captcha</div>
                    <h3 class="fw-bold my-1 text-warning" id="stat-captcha-count">{{ $stats['requires_captcha_count'] ?? 0 }}</h3>
                    <small class="text-warning fw-semibold"><i class="fa-solid fa-shield-halved me-1"></i> Flagged by Taqamul</small>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                    <i class="fa-solid fa-shield fa-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 4: Dedicated Slot Checker -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div class="overflow-hidden">
                    <div class="text-muted small fw-bold text-uppercase">Slot Checker (#0)</div>
                    <div class="fw-bold my-1 text-truncate font-monospace" style="max-width: 180px;" title="{{ $slotChecker['email'] ?? 'None' }}">
                        {{ $slotChecker['email'] ?? 'pool__259939@wafidmaster.com' }}
                    </div>
                    <small class="text-info fw-semibold"><i class="fa-solid fa-crown me-1"></i> Primary Token Source</small>
                </div>
                <div class="bg-info bg-opacity-10 text-info rounded-circle p-3">
                    <i class="fa-solid fa-robot fa-xl"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 🚀 INTERACTIVE LOGIN TESTER & LIVE TERMINAL -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h5 class="fw-bold m-0 text-dark">
                    <i class="fa-solid fa-bolt text-warning me-2"></i> Direct Login Controller
                </h5>
                <span class="badge bg-success bg-opacity-10 text-success border border-success">
                    <i class="fa-solid fa-shield-halved me-1"></i> Zero Captcha Solver
                </span>
            </div>

            <p class="text-muted small mb-3">
                Select an account from the pool or enter credentials. For <code>pool__</code> accounts, login is instant without solving captchas!
            </p>

            <form id="direct-login-form">
                @csrf
                <!-- Select Account Preset -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Choose Account from Pool:</label>
                    <select class="form-select" id="account_preset_select">
                        <option value="">-- Choose an Account --</option>
                        
                        <!-- Group 1: Zero Captcha Pool Accounts -->
                        <optgroup label="⚡ Zero-Captcha Pool Accounts (322) - 100% Captcha Free">
                            @foreach($accounts as $idx => $acc)
                                @if(str_starts_with(strtolower($acc['email'] ?? ''), 'pool__'))
                                    <option value="{{ $idx }}" 
                                        data-email="{{ $acc['email'] }}" 
                                        data-pass="{{ $acc['password'] ?? 'Taqamul@4642!' }}"
                                        data-token="{{ $acc['token'] ?? '' }}"
                                        data-is-pool="1"
                                        {{ $idx === 0 ? 'selected' : '' }}>
                                        ⚡ #{{ $idx + 1 }}: {{ $acc['email'] }} {{ !empty($acc['token']) ? '🟢 [Active]' : '🔴 [Expired]' }}
                                    </option>
                                @endif
                            @endforeach
                        </optgroup>

                        <!-- Group 2: Passenger Candidate Accounts -->
                        <optgroup label="👤 Passenger Candidate Accounts (6) - Requires reCAPTCHA">
                            @foreach($accounts as $idx => $acc)
                                @if(!str_starts_with(strtolower($acc['email'] ?? ''), 'pool__'))
                                    <option value="{{ $idx }}" 
                                        data-email="{{ $acc['email'] }}" 
                                        data-pass="{{ $acc['password'] ?? 'Taqamul@8399!' }}"
                                        data-token="{{ $acc['token'] ?? '' }}"
                                        data-is-pool="0">
                                        👤 #{{ $idx + 1 }}: {{ $acc['email'] }} (reCAPTCHA Required)
                                    </option>
                                @endif
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <!-- Account Type Warning (dynamically shown) -->
                <div id="candidate-warning-box" class="d-none alert alert-info py-2 px-3 small mb-3 border-info">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    <strong>Passenger Account:</strong> This is a passenger account. You can test direct login with the legislator payload.
                </div>

                <!-- Email Input -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Account Email:</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-primary"></i></span>
                        <input type="email" class="form-control font-monospace" id="login_email" name="email" 
                            value="{{ $slotChecker['email'] ?? 'pool__259939@wafidmaster.com' }}" required>
                    </div>
                </div>

                <!-- Password Input -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Account Password:</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-primary"></i></span>
                        <input type="text" class="form-control font-monospace" id="login_password" name="password" 
                            value="{{ $slotChecker['password'] ?? 'Taqamul@4642!' }}" required>
                    </div>
                </div>

                <!-- Force Fresh Login Option -->
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="force_fresh" name="force_fresh" value="1" checked>
                    <label class="form-check-label small fw-semibold text-secondary" for="force_fresh">
                        Force Live Fresh Login (Request new token from Taqamul API)
                    </label>
                </div>

                <!-- Action Button -->
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" id="btn-submit-login">
                    <i class="fa-solid fa-play me-2"></i> Execute Auto-Login (No Captcha)
                </button>
            </form>
        </div>
    </div>

    <!-- Live Execution Console & Progress -->
    <div class="col-lg-7">
        <div class="card card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold m-0 text-dark">
                        <i class="fa-solid fa-terminal text-info me-2"></i> Live Execution Console
                    </h5>
                    <span id="login-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="login-timer" class="badge bg-light text-dark border font-monospace">0.0s</span>
                    <button class="btn btn-sm btn-outline-secondary py-0" onclick="clearConsole()">Clear</button>
                </div>
            </div>

            <!-- 4-Step Visual Progress Tracker -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="step-item" id="step-1">
                        <i class="fa-solid fa-paper-plane text-muted" id="step-1-icon"></i>
                        <span>1. Session Login</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="step-item" id="step-2">
                        <i class="fa-solid fa-inbox text-muted" id="step-2-icon"></i>
                        <span>2. WAFID OTP</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="step-item" id="step-3">
                        <i class="fa-solid fa-shield text-muted" id="step-3-icon"></i>
                        <span>3. Verify OTP</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="step-item" id="step-4">
                        <i class="fa-solid fa-key text-muted" id="step-4-icon"></i>
                        <span>4. Token Saved</span>
                    </div>
                </div>
            </div>

            <!-- Terminal Stream Box -->
            <div class="terminal-box mb-3" id="live-terminal">
                <div>[Console Ready]: Select an account and click "Execute Auto-Login" to test.</div>
            </div>

            <!-- Result Box (Hidden by default) -->
            <div id="result-box" class="d-none p-3 rounded bg-light border">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-success fw-bold">
                        <i class="fa-solid fa-circle-check me-1"></i> Bearer Token Acquired Successfully!
                    </span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="copyAcquiredToken()">
                            <i class="fa-solid fa-copy me-1"></i> Copy Token
                        </button>
                        <button class="btn btn-sm btn-outline-warning text-dark" id="btn-set-primary-from-result" onclick="setAsPrimaryFromTest()">
                            <i class="fa-solid fa-crown me-1"></i> Set as Slot Checker (#0)
                        </button>
                    </div>
                </div>
                <textarea id="result-token-text" class="form-control font-monospace text-xs" rows="2" readonly></textarea>
                <div id="result-profile-info" class="small text-muted mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 📋 FULL ACCOUNTS MANAGEMENT TABLE -->
<!-- ========================================================================= -->
<div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h5 class="fw-bold m-0 text-dark">
                <i class="fa-solid fa-list-check text-primary me-2"></i> All Candidate Pool Accounts
            </h5>
            <small class="text-muted">Click "Login Now" on any account to immediately authenticate without captcha.</small>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Batch Login Button -->
            <button type="button" class="btn btn-sm btn-success fw-semibold" id="btn-batch-login" onclick="toggleBatchLogin()">
                <i class="fa-solid fa-spinner fa-spin d-none me-1" id="batch-spinner"></i>
                <i class="fa-solid fa-play me-1" id="batch-icon"></i>
                <span id="batch-btn-text">Batch Auto-Login</span>
            </button>

            <!-- Skip Captcha Toggle -->
            <div class="form-check form-switch form-check-inline m-0 ms-1" title="Skip accounts flagged for reCAPTCHA to prevent 401s">
                <input class="form-check-input" type="checkbox" id="skip-captcha-flagged" checked>
                <label class="form-check-label small fw-semibold text-dark" for="skip-captcha-flagged">
                    ⚡ Fast (Skip Captcha Accounts)
                </label>
            </div>

            <!-- Filter by Account Category Buttons -->
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary filter-btn active" data-filter-type="ALL">
                    All ({{ count($accounts) }})
                </button>
                <button type="button" class="btn btn-outline-success filter-btn" data-filter-type="ZERO_CAPTCHA">
                    ⚡ Zero-Captcha
                </button>
                <button type="button" class="btn btn-outline-warning filter-btn" data-filter-type="REQUIRES_CAPTCHA">
                    🛡️ Requires Captcha
                </button>
                <button type="button" class="btn btn-outline-secondary filter-btn" data-filter-type="CANDIDATE">
                    👤 Passengers
                </button>
            </div>

            <!-- Search input -->
            <div class="input-group input-group-sm" style="width: 170px;">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" id="table-search" class="form-control" placeholder="Search email...">
            </div>

            <!-- Filter Status Dropdown -->
            <select id="status-filter" class="form-select form-select-sm" style="width: 130px;">
                <option value="ALL">All Statuses</option>
                <option value="ACTIVE">Active Tokens</option>
                <option value="EXPIRED">Expired Tokens</option>
            </select>
        </div>
    </div>

    <!-- Batch Progress Bar Container -->
    <div id="batch-progress-container" class="d-none mb-3 p-3 bg-light border rounded shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-bold text-dark" id="batch-status-text">
                <i class="fa-solid fa-sync fa-spin text-success me-1"></i> Processing batch login...
            </span>
            <span class="badge bg-success" id="batch-percent-text">0%</span>
        </div>
        <div class="progress" style="height: 10px;">
            <div id="batch-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%"></div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0" id="accounts-table">
            <thead class="table-light sticky-top">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Account Email</th>
                    <th>Captcha Status</th>
                    <th>Password</th>
                    <th>Token Status</th>
                    <th>Last Verified</th>
                    <th class="text-end" style="width: 230px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $index => $acc)
                    @php
                        $hasToken = !empty($acc['token']);
                        $isPrimary = ($index === 0);
                        $isPool = str_starts_with(strtolower($acc['email'] ?? ''), 'pool__');
                        $cMode = $acc['captcha_mode'] ?? 'untested';
                    @endphp
                    <tr data-status="{{ $hasToken ? 'ACTIVE' : 'EXPIRED' }}" 
                        data-type="{{ $isPool ? 'POOL' : 'CANDIDATE' }}"
                        data-captcha="{{ $cMode }}"
                        data-email="{{ strtolower($acc['email']) }}">
                        <td class="font-monospace text-muted">{{ $index + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="font-monospace fw-semibold {{ $isPrimary ? 'text-primary' : 'text-dark' }}">
                                    {{ $acc['email'] }}
                                </span>
                                @if($isPrimary)
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown me-1"></i> Slot Checker</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div id="captcha-badge-{{ md5(strtolower($acc['email'])) }}">
                                @if($cMode === 'zero_captcha')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                        <i class="fa-solid fa-bolt me-1"></i> Zero-Captcha
                                    </span>
                                @elseif($cMode === 'requires_captcha')
                                    <span class="badge bg-warning bg-opacity-20 text-dark border border-warning" title="Taqamul requires reCAPTCHA for this account">
                                        <i class="fa-solid fa-shield-halved me-1"></i> Requires Captcha
                                    </span>
                                @elseif($cMode === 'invalid_credentials')
                                    <span class="badge bg-danger bg-opacity-20 text-danger border border-danger" title="Invalid Email or Password returned by Taqamul API">
                                        <i class="fa-solid fa-key me-1"></i> Invalid Password
                                    </span>
                                @elseif($isPool)
                                    <span class="badge bg-light text-muted border">
                                        <i class="fa-solid fa-bolt me-1"></i> Pool (Untested)
                                    </span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                        <i class="fa-solid fa-user-shield me-1"></i> Passenger
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <span class="font-monospace text-muted small" id="pwd-text-{{ md5(strtolower($acc['email'])) }}">{{ $acc['password'] ?? 'Taqamul@4642!' }}</span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-warning text-decoration-none" onclick="openEditPasswordModal('{{ $acc['email'] }}', '{{ $acc['password'] ?? 'Taqamul@4642!' }}')" title="Edit Password">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            @if($hasToken)
                                <span class="badge badge-soft-success">
                                    <i class="fa-solid fa-circle-check me-1"></i> Active Token
                                </span>
                            @else
                                <span class="badge badge-soft-danger">
                                    <i class="fa-solid fa-circle-xmark me-1"></i> Expired
                                </span>
                            @endif
                        </td>
                        <td class="small text-muted font-monospace">
                            {{ $acc['last_verified_at'] ?? 'Never' }}
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <!-- Fast 1-click Test Login -->
                                <button type="button" class="btn btn-sm {{ $isPool ? 'btn-outline-primary' : 'btn-outline-secondary' }}" 
                                    onclick="quickLoginAccount('{{ $acc['email'] }}', '{{ $acc['password'] ?? 'Taqamul@4642!' }}', {{ $isPool ? 1 : 0 }})"
                                    title="{{ $isPool ? 'Direct Login without Captcha' : 'Passenger Account (Requires reCAPTCHA)' }}">
                                    <i class="fa-solid fa-bolt me-1"></i> Login Now
                                </button>

                                <button type="button" class="btn btn-sm btn-outline-warning text-dark" 
                                    onclick="openEditPasswordModal('{{ $acc['email'] }}', '{{ $acc['password'] ?? 'Taqamul@4642!' }}')"
                                    title="Edit Password">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>

                                @if(!$isPrimary)
                                    <!-- Set as Primary -->
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark" 
                                        onclick="setAccountPrimary('{{ $acc['email'] }}')"
                                        title="Make Dedicated Slot Checker (#0)">
                                        <i class="fa-solid fa-crown"></i>
                                    </button>
                                @endif

                                @if($hasToken)
                                    <!-- Verify Existing Token -->
                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                        onclick="verifyTokenProbe('{{ $acc['token'] }}', '{{ $acc['email'] }}')"
                                        title="Verify Active Token on Server">
                                        <i class="fa-solid fa-check-double"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let activeTestEmail = '';
    let loginTimerInterval = null;
    let loginStartTime = 0;
    let currentTypeFilter = 'ALL';

    $(document).ready(function() {
        // Dropdown selection change
        $('#account_preset_select').on('change', function() {
            const selectedOpt = $(this).find(':selected');
            const email = selectedOpt.data('email');
            const pass = selectedOpt.data('pass');
            const isPool = selectedOpt.data('is-pool');

            if (email) {
                $('#login_email').val(email);
                if (pass) $('#login_password').val(pass);

                if (isPool === 0) {
                    $('#candidate-warning-box').removeClass('d-none');
                } else {
                    $('#candidate-warning-box').addClass('d-none');
                }
            }
        });

        // Category Filter Buttons
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentTypeFilter = $(this).data('filter-type');
            filterTableRows();
        });

        // Search in accounts table
        $('#table-search').on('keyup', function() {
            filterTableRows();
        });

        // Filter status in accounts table
        $('#status-filter').on('change', function() {
            filterTableRows();
        });

        // Handle Direct Login Form Submit
        $('#direct-login-form').on('submit', function(e) {
            e.preventDefault();
            const email = $('#login_email').val().trim();
            const password = $('#login_password').val().trim();
            const forceFresh = $('#force_fresh').is(':checked') ? 1 : 0;
            executeDirectLogin(email, password, forceFresh);
        });
    });

    function filterTableRows() {
        const searchVal = $('#table-search').val().toLowerCase().trim();
        const statusVal = $('#status-filter').val();

        $('#accounts-table tbody tr').each(function() {
            const rowEmail = $(this).data('email') || '';
            const rowStatus = $(this).data('status') || '';
            const rowType = $(this).data('type') || '';
            const rowCaptcha = $(this).data('captcha') || '';

            const matchesSearch = !searchVal || rowEmail.includes(searchVal);
            const matchesStatus = (statusVal === 'ALL') || (rowStatus === statusVal);

            let matchesType = false;
            if (currentTypeFilter === 'ALL') {
                matchesType = true;
            } else if (currentTypeFilter === 'ZERO_CAPTCHA') {
                matchesType = (rowCaptcha === 'zero_captcha') || (rowType === 'POOL' && rowCaptcha !== 'requires_captcha');
            } else if (currentTypeFilter === 'REQUIRES_CAPTCHA') {
                matchesType = (rowCaptcha === 'requires_captcha');
            } else if (currentTypeFilter === 'CANDIDATE') {
                matchesType = (rowType === 'CANDIDATE');
            }

            if (matchesSearch && matchesStatus && matchesType) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    function appendTerminalLog(msg, color = 'text-success') {
        const terminal = document.getElementById('live-terminal');
        const timestamp = new Date().toLocaleTimeString();
        terminal.innerHTML += '<div class="' + color + '">[' + timestamp + '] ' + msg + '</div>';
        terminal.scrollTop = terminal.scrollHeight;
    }

    function clearConsole() {
        document.getElementById('live-terminal').innerHTML = '<div>[Console Cleared] Ready for next login...</div>';
        resetProgressSteps();
        document.getElementById('result-box').classList.add('d-none');
    }

    function setStep(stepNum, status) {
        const item = document.getElementById('step-' + stepNum);
        const icon = document.getElementById('step-' + stepNum + '-icon');
        if (!item || !icon) return;

        item.classList.remove('active', 'done');
        if (status === 'active') {
            item.classList.add('active');
            icon.className = 'fa-solid fa-spinner fa-spin text-primary';
        } else if (status === 'done') {
            item.classList.add('done');
            icon.className = 'fa-solid fa-check text-success';
        } else {
            icon.className = 'fa-solid fa-circle-notch text-muted';
        }
    }

    function resetProgressSteps() {
        for (let i = 1; i <= 4; i++) {
            setStep(i, 'idle');
        }
    }

    function executeDirectLogin(email, password, forceFresh = 1) {
        activeTestEmail = email;
        clearConsole();
        resetProgressSteps();

        const isPool = email.toLowerCase().startsWith('pool__');

        $('#login-spinner').removeClass('d-none');
        $('#btn-submit-login').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Authenticating...');

        loginStartTime = Date.now();
        if (loginTimerInterval) clearInterval(loginTimerInterval);
        loginTimerInterval = setInterval(() => {
            const elapsed = ((Date.now() - loginStartTime) / 1000).toFixed(1);
            document.getElementById('login-timer').innerText = elapsed + 's';
        }, 100);

        setStep(1, 'active');
        if (isPool) {
            appendTerminalLog('Starting Direct Payload Login for: ' + email + ' (Zero Captcha Engine ⚡)...', 'text-info');
        } else {
            appendTerminalLog('Starting Login for Passenger Account: ' + email + ' (Checking reCAPTCHA requirements)...', 'text-warning');
        }

        fetch('{{ route('auto_login.execute') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ email, password, force_fresh: forceFresh })
        })
        .then(res => res.json().then(data => ({ status: res.status, data })))
        .then(({ status, data }) => {
            clearInterval(loginTimerInterval);
            $('#login-spinner').addClass('d-none');
            $('#btn-submit-login').prop('disabled', false).html('<i class="fa-solid fa-play me-2"></i> Execute Auto-Login (No Captcha)');

            // Print server logs to terminal
            if (data.logs && Array.isArray(data.logs)) {
                data.logs.forEach(l => appendTerminalLog(l, 'text-success'));
            }

            if (data.success && data.token) {
                setStep(1, 'done');
                setStep(2, 'done');
                setStep(3, 'done');
                setStep(4, 'done');

                appendTerminalLog('🎉 SUCCESS! Fresh Bearer Token acquired for ' + email + '!', 'text-warning fw-bold');
                
                // Show result box
                $('#result-box').removeClass('d-none');
                $('#result-token-text').val(data.token);

                let profDetails = 'Authenticated as: <strong>' + data.email + '</strong>';
                if (data.profile && data.profile.country) {
                    profDetails += ' | Country: <strong>' + (data.profile.country.english_name || 'Bangladesh') + '</strong>';
                }
                $('#result-profile-info').html(profDetails);

                // Update table row
                updateTableRowToken(email, data.token);
                updateTableRowCaptchaMode(email, 'zero_captcha');

            } else {
                if (data.requires_captcha) {
                    updateTableRowCaptchaMode(email, 'requires_captcha');
                }
                appendTerminalLog('❌ LOGIN FAILED: ' + (data.message || 'Unknown error'), 'text-danger fw-bold');
                alert(data.message || 'Authentication failed.');
            }
        })
        .catch(err => {
            clearInterval(loginTimerInterval);
            $('#login-spinner').addClass('d-none');
            $('#btn-submit-login').prop('disabled', false).html('<i class="fa-solid fa-play me-2"></i> Execute Auto-Login (No Captcha)');
            appendTerminalLog('❌ Request Exception: ' + err.message, 'text-danger');
        });
    }

    function quickLoginAccount(email, password, isPool = 1) {
        $('#login_email').val(email);
        $('#login_password').val(password);

        if (!isPool) {
            $('#candidate-warning-box').removeClass('d-none');
        } else {
            $('#candidate-warning-box').addClass('d-none');
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
        executeDirectLogin(email, password, 1);
    }

    function updateTableRowToken(email, token) {
        const cleanEmail = email.toLowerCase().trim();
        $('#accounts-table tbody tr').each(function() {
            if ($(this).data('email') === cleanEmail) {
                $(this).data('status', 'ACTIVE');
                $(this).find('td:nth-child(5)').html('<span class="badge badge-soft-success"><i class="fa-solid fa-circle-check me-1"></i> Active Token</span>');
                $(this).find('td:nth-child(6)').text('Just now');
            }
        });
    }

    function updateTableRowCaptchaMode(email, mode) {
        const cleanEmail = email.toLowerCase().trim();
        $('#accounts-table tbody tr').each(function() {
            if ($(this).data('email') === cleanEmail) {
                $(this).data('captcha', mode);
                const badgeCell = $(this).find('td:nth-child(3)');
                if (mode === 'zero_captcha') {
                    badgeCell.html('<span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fa-solid fa-bolt me-1"></i> Zero-Captcha</span>');
                } else if (mode === 'requires_captcha') {
                    badgeCell.html('<span class="badge bg-warning bg-opacity-20 text-dark border border-warning" title="Taqamul requires reCAPTCHA for this account"><i class="fa-solid fa-shield-halved me-1"></i> Requires Captcha</span>');
                } else if (mode === 'invalid_credentials') {
                    badgeCell.html('<span class="badge bg-danger bg-opacity-20 text-danger border border-danger" title="Invalid Password returned by Taqamul API"><i class="fa-solid fa-key me-1"></i> Invalid Password</span>');
                }
            }
        });
    }

    function copyAcquiredToken() {
        const copyText = document.getElementById('result-token-text');
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        alert('Bearer token copied to clipboard!');
    }

    function setAsPrimaryFromTest() {
        if (!activeTestEmail) return;
        setAccountPrimary(activeTestEmail);
    }

    function setAccountPrimary(email) {
        if (!confirm('Set ' + email + ' as the dedicated primary Slot Checker Account (#0)?')) return;

        fetch('{{ route('auto_login.set_primary') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ email })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Failed to update primary account.');
            }
        })
        .catch(err => alert('Error: ' + err.message));
    }

    function verifyTokenProbe(token, email) {
        if (!token) return;
        appendTerminalLog('Probing token for ' + email + ' on Taqamul API...', 'text-info');

        fetch('{{ route('auto_login.check_token') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ token })
        })
        .then(res => res.json())
        .then(data => {
            if (data.valid) {
                appendTerminalLog('🟢 ' + email + ': Token is VALID & ACTIVE! User: ' + (data.email || 'OK'), 'text-success fw-bold');
                alert('Token for ' + email + ' is VALID and active on Taqamul!');
            } else {
                appendTerminalLog('🔴 ' + email + ': Token is EXPIRED or Invalid (' + data.message + ')', 'text-danger fw-bold');
                alert('Token for ' + email + ' is EXPIRED or Invalid.');
            }
        })
        .catch(err => alert('Error verifying token: ' + err.message));
    }

    // ==========================================
    // ⚡ SEQUENTIAL BATCH AUTO-LOGIN CONTROLLER
    // ==========================================
    let isBatchRunning = false;
    let batchIndex = 0;
    const poolAccountsList = @json($accounts);

    async function toggleBatchLogin() {
        if (isBatchRunning) {
            isBatchRunning = false;
            $('#batch-btn-text').text('Resume Batch Login');
            $('#batch-icon').removeClass('fa-pause').addClass('fa-play');
            $('#batch-spinner').addClass('d-none');
            appendTerminalLog('⏸️ Batch Auto-Login Paused.', 'text-warning fw-bold');
            return;
        }

        if (batchIndex >= poolAccountsList.length) {
            batchIndex = 0;
        }

        isBatchRunning = true;
        $('#batch-progress-container').removeClass('d-none');
        $('#batch-btn-text').text('Pause Batch Login');
        $('#batch-icon').removeClass('fa-play').addClass('fa-pause');
        $('#batch-spinner').removeClass('d-none');
        appendTerminalLog('🚀 Starting Sequential Batch Auto-Login...', 'text-info fw-bold');

        for (; batchIndex < poolAccountsList.length; batchIndex++) {
            if (!isBatchRunning) break;

            const acc = poolAccountsList[batchIndex];
            const currentNum = batchIndex + 1;
            const totalNum = poolAccountsList.length;
            const progress = Math.round((currentNum / totalNum) * 100);

            $('#batch-progress-bar').css('width', progress + '%');
            $('#batch-percent-text').text(progress + '%');
            $('#batch-status-text').html('<i class="fa-solid fa-spinner fa-spin text-success me-1"></i> [' + currentNum + '/' + totalNum + '] Processing: <strong>' + acc.email + '</strong>');

            const isFlagged = (acc.captcha_mode === 'requires_captcha') || (acc.is_pool === false);
            const skipFlagged = $('#skip-captcha-flagged').is(':checked');

            if (skipFlagged && isFlagged) {
                appendTerminalLog('⏩ [' + currentNum + '/' + totalNum + '] ' + acc.email + ' is flagged for captcha. Skipping (Fast Mode)...', 'text-muted small');
                continue;
            }

            appendTerminalLog('------------------------------------------------------------', 'text-muted');
            appendTerminalLog('[' + currentNum + '/' + totalNum + '] Extracting unique token for: ' + acc.email + '...', 'text-primary fw-bold');

            try {
                const res = await fetch('{{ route('auto_login.execute') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        email: acc.email,
                        password: acc.password || 'Taqamul@4642!',
                        force_fresh: 1
                    })
                });

                const data = await res.json();
                if (data.logs && Array.isArray(data.logs)) {
                    data.logs.forEach(l => appendTerminalLog(l, 'text-success'));
                }

                if (data.success && data.token) {
                    appendTerminalLog('✅ Dedicated Bearer Token Acquired & Saved for: ' + acc.email, 'text-success fw-bold');
                    updateTableRowToken(acc.email, data.token);
                    updateTableRowCaptchaMode(acc.email, 'zero_captcha');
                    acc.captcha_mode = 'zero_captcha';
                } else {
                    if (data.requires_captcha) {
                        appendTerminalLog('🛡️ ' + acc.email + ': Flagged by Taqamul for reCAPTCHA. Marked as Requires Captcha.', 'text-warning');
                        updateTableRowCaptchaMode(acc.email, 'requires_captcha');
                        acc.captcha_mode = 'requires_captcha';
                    } else if (data.invalid_credentials) {
                        appendTerminalLog('🔑 ' + acc.email + ': Invalid Email or Password. Marked as Invalid Password.', 'text-danger fw-bold');
                        updateTableRowCaptchaMode(acc.email, 'invalid_credentials');
                        acc.captcha_mode = 'invalid_credentials';
                    } else {
                        appendTerminalLog('⚠️ ' + acc.email + ': ' + (data.message || 'Login failed'), 'text-warning');
                    }
                    if (data.message && data.message.includes('Rate Limit')) {
                        appendTerminalLog('🛑 Taqamul IP Rate Limit hit! Pausing batch execution for 5 minutes...', 'text-danger fw-bold');
                        toggleBatchLogin();
                        break;
                    }
                }
            } catch (err) {
                appendTerminalLog('❌ Request Error for ' + acc.email + ': ' + err.message, 'text-danger');
            }

            // Safe cooldown between accounts to prevent IP rate-limiting (429)
            if (isBatchRunning && batchIndex < poolAccountsList.length - 1) {
                appendTerminalLog('⏳ Waiting 4 seconds cooldown before next account...', 'text-muted small');
                await new Promise(r => setTimeout(r, 4000));
            }
        }

        if (batchIndex >= poolAccountsList.length) {
            isBatchRunning = false;
            $('#batch-btn-text').text('Batch Completed ✅');
            $('#batch-icon').removeClass('fa-pause').addClass('fa-check');
            $('#batch-spinner').addClass('d-none');
            $('#batch-status-text').html('🎉 All ' + poolAccountsList.length + ' accounts processed!');
            appendTerminalLog('🎉 Batch Auto-Login completed for all accounts!', 'text-success fw-bold');
            alert('Batch Auto-Login process completed for all accounts!');
        }
    }



    function openEditPasswordModal(email, currentPassword) {
        $('#edit-email-input').val(email);
        $('#edit-password-input').val(currentPassword || '');
        const modal = new bootstrap.Modal(document.getElementById('editPasswordModal'));
        modal.show();
    }

    function submitPasswordUpdate() {
        const email = $('#edit-email-input').val();
        const newPassword = $('#edit-password-input').val();

        if (!email || !newPassword) {
            alert('Please enter a valid password.');
            return;
        }

        fetch('{{ route('auto_login.update_password') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ email: email, password: newPassword })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Password updated successfully for ' + email + '!');
                const modalEl = document.getElementById('editPasswordModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                location.reload();
            } else {
                alert('Failed to update password: ' + (data.message || 'Error'));
            }
        })
        .catch(err => {
            alert('Server error updating password: ' + err.message);
        });
    }
</script>

<!-- Edit Password Modal -->
<div class="modal fade" id="editPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow-lg border">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold text-warning">
                    <i class="fa-solid fa-key me-2"></i> Edit Candidate Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-edit-password" onsubmit="event.preventDefault(); submitPasswordUpdate();">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Candidate Email</label>
                        <input type="email" id="edit-email-input" class="form-control font-monospace bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Taqamul Password</label>
                        <input type="text" id="edit-password-input" class="form-control font-monospace" placeholder="e.g. Taqamul@2723!" required>
                        <div class="form-text text-muted">
                            Update the password here after resetting it on Taqamul portal.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark" onclick="submitPasswordUpdate()">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save New Password
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
