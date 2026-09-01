@extends('layouts.app')

@section('title', 'Slot Vault - Taqamul Engine')
@section('page_title', 'Slot Vault & Hash Manager')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-vault text-warning me-2"></i> Vaulted Held Slots</h5>
                    <small class="text-muted">Manage active held slots, group locks, and Mother Hashes</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" id="btn-refresh-vault">
                        <i class="fa-solid fa-rotate me-1"></i> Refresh Vault
                    </button>
                    <button class="btn btn-outline-success" id="btn-export-csv">
                        <i class="fa-solid fa-file-csv me-1"></i> Export CSV
                    </button>
                    <button class="btn btn-danger" id="btn-release-all">
                        <i class="fa-solid fa-trash me-1"></i> Release All Holds
                    </button>
                </div>
            </div>

            <!-- Vault Items Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light">
                        <tr>
                            <th># ID</th>
                            <th>Candidate Account</th>
                            <th>Center</th>
                            <th>Date & Shift</th>
                            <th>Slot Hash</th>
                            <th>Hold Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vault-table-body">
                        @forelse($vaultItems as $item)
                        <tr>
                            <td>{{ $item['id'] }}</td>
                            <td>{{ $item['account'] }}</td>
                            <td>{{ $item['center'] }}</td>
                            <td>{{ $item['date'] }} ({{ $item['shift'] }})</td>
                            <td><code>{{ Str::limit($item['hash'], 15) }}</code></td>
                            <td><span class="badge bg-info">{{ $item['duration'] }}</span></td>
                            <td><span class="badge bg-success">Active Hold</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger me-1">Release</button>
                                <button class="btn btn-sm btn-outline-primary">Details</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fa-solid fa-box-archive fa-3x mb-3 text-secondary d-block"></i>
                                <h6>No active held slots in vault right now</h6>
                                <p class="small">Holds executed from the <strong>Hold Slot</strong> page will automatically show up here.</p>
                                <a href="{{ route('hold') }}" class="btn btn-primary btn-sm mt-2">
                                    <i class="fa-solid fa-plus me-1"></i> Start New Hold
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
