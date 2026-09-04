@extends('layouts.app')

@section('title', 'Slot Vault - Taqamul Engine')
@section('page_title', 'Slot Vault & Active Holds')

@section('content')
<div class="row g-4">
    <!-- Header Metrics Banner -->
    <div class="col-12">
        <div class="card card-custom p-3 bg-dark text-white border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-warning text-dark rounded-circle me-1">
                        <i class="fa-solid fa-vault fa-xl"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold m-0 text-warning">Slot Vault Control Center</h5>
                        <small class="text-muted">Live 20-Minute Auto-Renewing Candidate Pool Holds</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-success fs-6"><i class="fa-solid fa-rotate me-1"></i> 20-Min Auto-Renew Active</span>
                    <button class="btn btn-outline-info btn-sm fw-bold" onclick="loadVaultData()">
                        <i class="fa-solid fa-arrows-rotate me-1" id="vault-refresh-icon"></i> Refresh Vault
                    </button>
                    <a href="{{ route('hold') }}" class="btn btn-primary btn-sm fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Hold More Slots
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="col-md-4">
        <div class="card card-custom p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-bold">Active Hashes Locked</small>
                    <h3 class="fw-bold text-primary m-0" id="stat-active-hashes">0</h3>
                </div>
                <i class="fa-solid fa-hashtag fa-2x text-primary opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-bold">Total Slots Held</small>
                    <h3 class="fw-bold text-success m-0" id="stat-total-slots">0</h3>
                </div>
                <i class="fa-solid fa-user-lock fa-2x text-success opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-bold">Candidate Accounts In Use</small>
                    <h3 class="fw-bold text-info m-0" id="stat-active-accounts">0</h3>
                </div>
                <i class="fa-solid fa-users fa-2x text-info opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Grouped Vault Table -->
    <div class="col-12">
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0"><i class="fa-solid fa-layer-group text-primary me-2"></i> Vaulted Mother Hashes</h5>
                <span class="badge bg-secondary" id="vault-status-badge">Idle</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Center Name</th>
                            <th>Profession</th>
                            <th>Exam Date</th>
                            <th>Mother Hash</th>
                            <th>Locked Slots</th>
                            <th>20-Min Expiry Timer</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vault-grouped-table-body">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>
                                Loading active holds from Slot Vault...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 🔍 EXPANDED SLOTS MODAL (VIEW ALL 10 SLOTS UNDER MOTHER HASH) -->
<!-- ========================================================================= -->
<div class="modal fade" id="expandedSlotsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark text-white border border-secondary shadow-lg">
            <div class="modal-header border-secondary">
                <div>
                    <h5 class="modal-title font-weight-bold text-warning" id="modalCenterName">
                        <i class="fa-solid fa-list-check me-2"></i> Candidate Pool Slots Detail
                    </h5>
                    <small class="text-muted" id="modalMotherHash">Mother Hash: ---</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border border-secondary">
                        <thead class="table-secondary text-dark">
                            <tr>
                                <th>Slot #</th>
                                <th>Candidate Pool Email</th>
                                <th>Reservation / Seat ID</th>
                                <th>Expiry Time</th>
                                <th>Renew Count</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="modal-slots-table-body">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No slots found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm fw-bold" id="btn-modal-release-all">
                    <i class="fa-solid fa-trash me-1"></i> Release All Slots For This Hash
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let vaultGroupsData = [];
    let vaultTimerInterval = null;
    let currentSelectedHash = null;

    $(document).ready(function() {
        loadVaultData();
        
        // Auto-refresh vault data every 10 seconds
        setInterval(loadVaultData, 10000);
    });

    function loadVaultData() {
        const icon = document.getElementById('vault-refresh-icon');
        if (icon) icon.classList.add('fa-spin');

        fetch(`{{ route('vault.data') }}`)
            .then(res => res.json())
            .then(data => {
                if (icon) icon.classList.remove('fa-spin');
                if (data.success) {
                    vaultGroupsData = data.groups || [];
                    renderVaultTable();
                    updateMetrics(data);
                }
            })
            .catch(err => {
                if (icon) icon.classList.remove('fa-spin');
            });
    }

    function updateMetrics(data) {
        document.getElementById('stat-active-hashes').innerText = data.count || 0;
        document.getElementById('stat-total-slots').innerText = data.total_active_holds || 0;
        document.getElementById('stat-active-accounts').innerText = data.total_active_holds || 0;
    }

    function renderVaultTable() {
        const tbody = document.getElementById('vault-grouped-table-body');
        if (vaultGroupsData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="fa-solid fa-box-archive fa-3x mb-3 text-secondary d-block"></i>
                        <h6>No active held slots in vault right now</h6>
                        <p class="small">Slots locked using <strong>Lock All Slots</strong> on the Hold Slot page will show up here.</p>
                        <a href="{{ route('hold') }}" class="btn btn-primary btn-sm mt-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Scan & Lock Slots
                        </a>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = '';
        vaultGroupsData.forEach((g, idx) => {
            const shortHash = g.mother_hash.substring(0, 16) + '...';
            
            tbody.innerHTML += `
                <tr>
                    <td class="fw-bold">${idx + 1}</td>
                    <td>
                        <strong class="text-dark">${g.center_name}</strong>
                        <div class="text-muted small"><i class="fa-solid fa-location-dot me-1 text-danger"></i> ${g.city}</div>
                    </td>
                    <td>
                        <span class="badge bg-primary fs-7"><i class="fa-solid fa-briefcase me-1"></i> ${g.category_name || 'Profession'}</span>
                    </td>
                    <td><i class="fa-solid fa-calendar me-1 text-primary"></i> ${g.exam_date}</td>
                    <td>
                        <code class="user-select-all bg-light px-2 py-1 border rounded text-dark">${shortHash}</code>
                        <button class="btn btn-sm btn-link p-0 ms-1 text-decoration-none" onclick="navigator.clipboard.writeText('${g.mother_hash}'); alert('Copied Mother Hash!');">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </td>
                    <td>
                        ${g.is_locking_in_progress 
                            ? `<span class="badge bg-warning text-dark fs-6"><span class="spinner-border spinner-border-sm me-1" role="status"></span> Locking ${g.total_locked_slots > 0 ? g.total_locked_slots + ' Slots Locked' : 'In Progress...'}</span>`
                            : `<span class="badge bg-success fs-6"><i class="fa-solid fa-lock me-1"></i> ${g.total_locked_slots} Slots Locked</span>`
                        }
                    </td>
                    <td>
                        <span class="font-monospace fw-bold text-warning timer-badge" data-seconds="${g.remaining_seconds}">
                            <i class="fa-solid fa-stopwatch me-1"></i> ${formatTimer(g.remaining_seconds)}
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-info text-dark fw-bold me-1" onclick="openExpandedModal('${g.mother_hash}')">
                            <i class="fa-solid fa-eye me-1"></i> View Slots (${g.total_locked_slots})
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="releaseGroup('${g.mother_hash}')">
                            <i class="fa-solid fa-trash me-1"></i> Release All
                        </button>
                    </td>
                </tr>
            `;
        });

        startLiveTimers();
    }

    function formatTimer(sec) {
        if (sec <= 0) return 'Expired (Renewing...)';
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}m ${s < 10 ? '0' : ''}${s}s`;
    }

    function startLiveTimers() {
        if (vaultTimerInterval) clearInterval(vaultTimerInterval);
        vaultTimerInterval = setInterval(() => {
            document.querySelectorAll('.timer-badge').forEach(el => {
                let sec = parseInt(el.getAttribute('data-seconds') || '0');
                if (sec > 0) {
                    sec--;
                    el.setAttribute('data-seconds', sec);
                    el.innerHTML = `<i class="fa-solid fa-stopwatch me-1"></i> ${formatTimer(sec)}`;
                }
            });
        }, 1000);
    }

    function openExpandedModal(hash) {
        currentSelectedHash = hash;
        const group = vaultGroupsData.find(g => g.mother_hash === hash);
        if (!group) return;

        document.getElementById('modalCenterName').innerText = group.center_name;
        document.getElementById('modalMotherHash').innerText = `Mother Hash: ${group.mother_hash}`;

        const tbody = document.getElementById('modal-slots-table-body');
        tbody.innerHTML = '';

        group.slots.forEach((s, idx) => {
            tbody.innerHTML += `
                <tr>
                    <td class="fw-bold">${idx + 1}</td>
                    <td><code>${s.email}</code></td>
                    <td><span class="badge bg-secondary font-monospace">${s.temp_seat_id}</span></td>
                    <td>${s.expires_at}</td>
                    <td><span class="badge bg-info">${s.renew_count} renewals</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-danger" onclick="releaseSingleSlot(${s.id})">
                            <i class="fa-solid fa-xmark me-1"></i> Release Slot
                        </button>
                    </td>
                </tr>
            `;
        });

        document.getElementById('btn-modal-release-all').onclick = function() {
            releaseGroup(hash);
        };

        const modal = new bootstrap.Modal(document.getElementById('expandedSlotsModal'));
        modal.show();
    }

    function releaseSingleSlot(holdId) {
        if (!confirm('Are you sure you want to release this candidate slot reservation?')) return;

        fetch(`{{ route('vault.release_single') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ hold_id: holdId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const modalEl = document.getElementById('expandedSlotsModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                
                loadVaultData();
            } else {
                alert('Failed to release slot: ' + data.message);
            }
        });
    }

    function releaseGroup(hash) {
        if (!confirm('Are you sure you want to release ALL slots under this Mother Hash?')) return;

        fetch(`{{ route('vault.release_group') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ mother_hash: hash })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const modalEl = document.getElementById('expandedSlotsModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                loadVaultData();
            } else {
                alert('Failed to release group slots: ' + data.message);
            }
        });
    }
</script>
@endsection
