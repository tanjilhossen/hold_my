@extends('layouts.app')

@section('title', 'Dashboard - Slot Sniper Engine')
@section('page_title', 'Dashboard Overview')

@section('content')
<div class="row g-4 mb-4">
    <!-- Stat 1: Active Slot Holds -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 fw-bold small text-uppercase">Active Slot Holds</h6>
                    <h2 class="mb-0 text-primary fw-bold">{{ $stats['active_holds'] ?? 0 }}</h2>
                    <small class="text-success"><i class="fa-solid fa-circle text-[8px] animate-pulse me-1"></i> Auto-Renewing</small>
                </div>
                <div class="bg-primary-subtle p-3 rounded-circle text-primary">
                    <i class="fa-solid fa-hand-holding-hand fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 2: Vaulted Hashes -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 fw-bold small text-uppercase">Vaulted Hashes</h6>
                    <h2 class="mb-0 text-success fw-bold">{{ $stats['vault_items'] ?? 0 }}</h2>
                    <small class="text-muted">Unique Test Centers</small>
                </div>
                <div class="bg-success-subtle p-3 rounded-circle text-success">
                    <i class="fa-solid fa-vault fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 3: Candidate Pool Accounts -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 fw-bold small text-uppercase">Pool Accounts</h6>
                    <h2 class="mb-0 text-info fw-bold">{{ $stats['pool_accounts'] ?? 0 }}</h2>
                    <small class="text-success fw-semibold"><i class="fa-solid fa-key me-1"></i> {{ $stats['active_tokens'] ?? 0 }} Active Tokens</small>
                </div>
                <div class="bg-info-subtle p-3 rounded-circle text-info">
                    <i class="fa-solid fa-users fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 4: System Health -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 fw-bold small text-uppercase">System Status</h6>
                    <span class="badge bg-success fs-6 mt-1"><i class="fa-solid fa-circle-check me-1"></i> {{ $stats['system_status'] ?? 'Online' }}</span>
                </div>
                <div class="bg-warning-subtle p-3 rounded-circle text-warning">
                    <i class="fa-solid fa-shield-halved fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Controls & Active Holds Summary Table -->
    <div class="col-lg-8">
        <!-- Quick Automation Controls -->
        <div class="card card-custom p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-rocket text-primary me-2"></i> Quick Engine Controls</h5>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('hold') }}" class="btn btn-primary fw-bold px-4 py-2">
                    <i class="fa-solid fa-play me-1"></i> Hold Slot Engine
                </a>
                <a href="{{ route('vault') }}" class="btn btn-outline-success fw-bold px-4 py-2">
                    <i class="fa-solid fa-vault me-1"></i> View Slot Vault
                </a>
                <a href="{{ route('auto_login') }}" class="btn btn-outline-warning fw-bold px-4 py-2">
                    <i class="fa-solid fa-key me-1"></i> Auto Login Checker
                </a>
                <a href="{{ route('ip_manager') }}" class="btn btn-outline-info fw-bold px-4 py-2">
                    <i class="fa-solid fa-network-wired me-1"></i> IP Manager
                </a>
            </div>
        </div>

        <!-- Recent Active Holds Table (Grouped by Mother Hash) -->
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0"><i class="fa-solid fa-layer-group text-success me-2"></i> Vaulted Center Holds</h5>
                <a href="{{ route('vault') }}" class="btn btn-sm btn-outline-primary fw-bold">View Full Vault Details <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Center Name</th>
                            <th>Profession</th>
                            <th>Exam Date</th>
                            <th>Locked Slots</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedVaultHolds as $idx => $group)
                        <tr>
                            <td class="fw-bold">{{ $idx + 1 }}</td>
                            <td>
                                <div><strong class="text-dark">{{ $group['center_name'] }}</strong></div>
                                <small class="text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i> {{ $group['city'] }}</small>
                            </td>
                            <td>
                                <span class="badge bg-primary fs-7"><i class="fa-solid fa-briefcase me-1"></i> {{ $group['category_name'] }}</span>
                            </td>
                            <td><i class="fa-solid fa-calendar me-1 text-primary"></i> {{ $group['exam_date'] }}</td>
                            <td>
                                <span class="badge bg-success fs-6"><i class="fa-solid fa-lock me-1"></i> {{ $group['total_locked'] }} Slots Locked</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                No active holds currently in vault. Go to <a href="{{ route('hold') }}" class="fw-bold">Hold Slot Engine</a> to scan & lock slots.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Engine Telemetry & System Event Logs -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-microchip text-info me-2"></i> Engine Status Telemetry</h5>
            <div class="space-y-3">
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded border mb-2">
                    <span class="small fw-bold text-dark"><i class="fa-solid fa-server me-1 text-primary"></i> SQLite Database:</span>
                    <span class="badge bg-success">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded border mb-2">
                    <span class="small fw-bold text-dark"><i class="fa-solid fa-network-wired me-1 text-info"></i> Decodo Proxy Engine:</span>
                    <span class="badge bg-success">Ports 41001-41020</span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded border mb-2">
                    <span class="small fw-bold text-dark"><i class="fa-solid fa-robot me-1 text-warning"></i> Auto-Login Bot:</span>
                    <span class="badge bg-success">Direct OTP Active</span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded border">
                    <span class="small fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left me-1 text-success"></i> Auto-Renew Worker:</span>
                    <span class="badge bg-success">20-Min Loop</span>
                </div>
            </div>
        </div>

        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check text-primary me-2"></i> Recent Engine Events</h5>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item px-0 text-muted border-0 pb-2">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Taqamul Direct OTP Engine active.
                </li>
                <li class="list-group-item px-0 text-muted border-0 pb-2">
                    <i class="fa-solid fa-shield-halved text-info me-1"></i> Decodo Proxy IP Fallback protected.
                </li>
                <li class="list-group-item px-0 text-muted border-0">
                    <i class="fa-solid fa-vault text-warning me-1"></i> Slot Vault auto-renew active.
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
