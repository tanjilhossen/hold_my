@extends('layouts.app')

@section('title', 'Dashboard - Taqamul Engine')
@section('page_title', 'Dashboard Overview')

@section('content')
<div class="row g-4 mb-4">
    <!-- Stat 1: Active Holds -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 fw-bold">Active Slot Holds</h6>
                    <h2 class="mb-0 text-primary fw-bold">{{ $stats['active_holds'] ?? 0 }}</h2>
                </div>
                <div class="bg-primary-subtle p-3 rounded-circle text-primary">
                    <i class="fa-solid fa-hand-holding-hand fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 2: Vault Items -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 fw-bold">Vaulted Hashes</h6>
                    <h2 class="mb-0 text-success fw-bold">{{ $stats['vault_items'] ?? 0 }}</h2>
                </div>
                <div class="bg-success-subtle p-3 rounded-circle text-success">
                    <i class="fa-solid fa-vault fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 3: Active Pool Accounts -->
    <div class="col-md-3">
        <div class="card card-custom p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1 fw-bold">Active Pool Accounts</h6>
                    <h2 class="mb-0 text-info fw-bold">{{ $stats['pool_accounts'] ?? 0 }}</h2>
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
                    <h6 class="text-muted mb-1 fw-bold">System Status</h6>
                    <span class="badge bg-success fs-6 mt-1">{{ $stats['system_status'] ?? 'Operational' }}</span>
                </div>
                <div class="bg-warning-subtle p-3 rounded-circle text-warning">
                    <i class="fa-solid fa-shield-halved fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Actions & System Info -->
    <div class="col-lg-8">
        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-rocket text-primary me-2"></i> Quick Automation Controls</h5>
            <p class="text-muted">Direct shortcut to launch continuous slot sniper or inspect current slot vault.</p>
            <div class="d-flex gap-3">
                <a href="{{ route('hold') }}" class="btn btn-primary btn-lg px-4">
                    <i class="fa-solid fa-play me-2"></i> Launch Slot Holder
                </a>
                <a href="{{ route('vault') }}" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fa-solid fa-vault me-2"></i> View Slot Vault
                </a>
                <a href="{{ route('settings') }}" class="btn btn-outline-info btn-lg px-4">
                    <i class="fa-solid fa-user-plus me-2"></i> Manage Pool Tokens
                </a>
            </div>
        </div>
    </div>

    <!-- Live Activity Log Placeholder -->
    <div class="col-lg-4">
        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check me-2"></i> Recent Engine Events</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item px-0 text-muted small">
                    <i class="fa-solid fa-circle-info text-info me-1"></i> Clean framework initialized.
                </li>
                <li class="list-group-item px-0 text-muted small">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Dashboard UI ready.
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
