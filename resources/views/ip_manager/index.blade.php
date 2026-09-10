@extends('layouts.app')

@section('title', 'IP Manager - Decodo Proxy Pool & Real-Time Bandwidth')
@section('page_title', 'IP Manager & Decodo Proxy Engine')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <!-- Main Controls & Pool Status Bar -->
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="fw-bold text-dark m-0">
                        <i class="fa-solid fa-network-wired text-info me-2"></i> Decodo Multi-Account Proxy Engine
                    </h5>
                    <small class="text-muted">Unbreakable failover engine for Decodo Residential Proxies. Auto-switches only when real bandwidth is closed.</small>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <form action="{{ route('ip_manager.update') }}" method="POST" class="d-flex align-items-center gap-2">
                        @csrf
                        <input type="hidden" name="only_toggle_proxy" value="1">
                        <div class="form-check form-switch fs-5 m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="proxy_enabled" name="proxy_enabled" value="1" {{ $proxyEnabled == '1' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="form-check-label fs-6 fw-semibold ms-1" for="proxy_enabled">
                                <span class="badge {{ $proxyEnabled == '1' ? 'bg-success' : 'bg-secondary' }}">{{ $proxyEnabled == '1' ? 'Active' : 'Disabled' }}</span>
                            </label>
                        </div>
                    </form>
                    <form action="{{ route('ip_manager.reactivate_all') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-success fw-semibold" title="Reset and reactivate all accounts in pool">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Reactivate All
                        </button>
                    </form>
                    <button type="button" class="btn btn-primary px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                        <i class="fa-solid fa-plus me-1"></i> Add Proxy Account
                    </button>
                </div>
            </div>
        </div>

        <!-- High-Throughput Load Balancing & Decodo Port Sharding Settings -->
        <div class="card card-custom p-4 mb-4 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-dark m-0">
                        <i class="fa-solid fa-bolt-lightning text-primary me-2"></i> High-Throughput Engine (500+ Slots Optimization)
                    </h6>
                    <small class="text-muted">Prevents single-port proxy choke and 502/503/504 errors by sharding requests across Decodo's 50 sticky exit ports (41001-41050) & balancing across active accounts.</small>
                </div>
                <span class="badge bg-primary fs-7">Enterprise Scale</span>
            </div>
            
            <form action="{{ route('ip_manager.update') }}" method="POST">
                @csrf
                <input type="hidden" name="proxy_settings_form" value="1">
                <input type="hidden" name="proxy_enabled" value="{{ $proxyEnabled }}">
                
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="proxy_load_balancing" name="proxy_load_balancing" value="1" {{ $proxyLoadBalancing == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="proxy_load_balancing">
                                    Multi-Account Load Balancing
                                </label>
                            </div>
                            <small class="text-muted d-block">Distributes requests across all healthy accounts in the pool.</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="proxy_port_multiplexing" name="proxy_port_multiplexing" value="1" {{ $proxyPortMultiplexing == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="proxy_port_multiplexing">
                                    Decodo 50-Port Sharding
                                </label>
                            </div>
                            <small class="text-muted d-block">Cycles residential ports (41001-41050) to prevent single IP throttling.</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="form-label small fw-bold text-dark mb-1">Port Sharding Range</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" name="proxy_port_range" value="{{ $proxyPortRange }}" placeholder="41001-41050">
                                <button type="submit" class="btn btn-primary fw-semibold px-3">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Default Decodo residential range: <code>41001-41050</code></small>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Decodo Accounts Pool Table -->
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark m-0">
                    <i class="fa-solid fa-layer-group text-primary me-2"></i> Decodo Proxy Pool Accounts ({{ count($proxyAccounts) }} in pool)
                </h6>
                <span class="badge bg-light text-dark border font-monospace">Scalable Pool: 1 to 1000+ Proxies</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle border mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Account Name</th>
                            <th>Endpoint & User</th>
                            <th>Status</th>
                            <th>Live IP & Latency</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proxyAccounts as $acc)
                            @php
                                $status = $acc['status'] ?? 'idle';
                            @endphp
                            <tr id="row-acc-{{ $acc['id'] }}" class="{{ $status === 'exhausted' ? 'table-danger' : ($status === 'active' ? 'table-success bg-opacity-10' : ($status === 'cooling' ? 'table-warning bg-opacity-10' : '')) }}" data-id="{{ $acc['id'] }}" data-status="{{ $status }}">
                                <td>
                                    <strong class="text-dark">{{ $acc['name'] ?? 'Decodo Account' }}</strong>
                                    @if(!empty($acc['notes']))
                                        <br><small class="text-muted">{{ $acc['notes'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $acc['host'] ?? 'bd.decodo.com' }}:{{ $acc['port'] ?? '41001' }}</code>
                                    <br>
                                    <span class="font-monospace text-secondary small"><i class="fa-solid fa-user small me-1"></i>{{ $acc['username'] ?? 'N/A' }}</span>
                                </td>
                                <td id="status-cell-{{ $acc['id'] }}">
                                    @if($status === 'active')
                                        <span class="badge bg-success px-3 py-2 fs-6 status-badge">
                                            <i class="fa-solid fa-circle-check me-1"></i> Active (Routing)
                                        </span>
                                    @elseif($status === 'exhausted')
                                        <span class="badge bg-danger px-3 py-2 fs-6 status-badge">
                                            <i class="fa-solid fa-ban me-1"></i> Bandwidth Closed (0 MB)
                                        </span>
                                    @elseif($status === 'cooling')
                                        <span class="badge bg-warning text-dark px-3 py-2 fs-6 status-badge">
                                            <i class="fa-solid fa-hourglass-half me-1"></i> Cooling (30s)
                                        </span>
                                    @else
                                        <span class="badge bg-secondary px-3 py-2 fs-6 status-badge">
                                            <i class="fa-solid fa-clock me-1"></i> Idle in Pool
                                        </span>
                                    @endif

                                    <div class="exhausted-reason-text">
                                        @if($status === 'exhausted' && !empty($acc['exhausted_reason']))
                                            <small class="text-danger fw-semibold d-block mt-1">{{ $acc['exhausted_reason'] }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td id="network-cell-{{ $acc['id'] }}">
                                    <div class="ip-wrap">
                                        @if(!empty($acc['last_ip']))
                                            <span class="font-monospace text-dark fw-bold small"><i class="fa-solid fa-location-dot text-danger me-1"></i>{{ $acc['last_ip'] }}</span>
                                        @else
                                            <span class="text-muted small">Not probed yet</span>
                                        @endif
                                    </div>
                                    <div class="latency-wrap">
                                        @if(!empty($acc['last_latency_ms']))
                                            <span class="badge bg-light text-dark border small mt-1">{{ round($acc['last_latency_ms']) }} ms</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if($status !== 'active')
                                            <form action="{{ route('ip_manager.account.activate', $acc['id']) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success" title="Activate Account">
                                                    <i class="fa-solid fa-power-off"></i> Activate
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-outline-info btn-test-acc" 
                                            data-host="{{ $acc['host'] }}" 
                                            data-port="{{ $acc['port'] }}" 
                                            data-user="{{ $acc['username'] }}" 
                                            data-pass="{{ $acc['password'] }}"
                                            data-id="{{ $acc['id'] }}"
                                            title="Test Live Connection">
                                            <i class="fa-solid fa-vial"></i> Test
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-edit-acc" 
                                            data-acc="{{ json_encode($acc) }}"
                                            title="Edit Account">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        @if(count($proxyAccounts) > 1)
                                            <form action="{{ route('ip_manager.account.delete', $acc['id']) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this account?')">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger" title="Delete Account">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No proxy accounts found. Click "Add Proxy Account" above.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Terminal Output Log -->
        <div class="card card-custom p-4 bg-dark text-light mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-info"><i class="fa-solid fa-terminal me-2"></i> Live Decodo Proxy Connection Console</span>
                <span class="badge bg-secondary small" id="latencyBadge">Idle</span>
            </div>
            <pre class="bg-black text-success p-3 rounded font-monospace m-0" id="consoleOutput" style="min-height: 140px; max-height: 250px; overflow-y: auto; font-size: 0.9rem;">
[System] Proxy engine active. Click "Test" on any proxy account above to verify live connection and IP status.
            </pre>
        </div>
    </div>
</div>

<!-- Modal: Add Account -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('ip_manager.account.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-plus-circle text-primary me-2"></i> Add Decodo Proxy Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account Label Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Decodo Residential BD 1" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold">Proxy Host</label>
                            <input type="text" class="form-control" name="host" value="bd.decodo.com" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="text" class="form-control" name="port" value="41001" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proxy Username</label>
                        <input type="text" class="form-control" name="username" placeholder="e.g. spywmt3zb9" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proxy Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Decodo Password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Initial Status</label>
                        <select class="form-select" name="status">
                            <option value="idle" selected>Idle (Backup Pool)</option>
                            <option value="active">Active (Set as Primary)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes (Optional)</label>
                        <input type="text" class="form-control" name="notes" placeholder="e.g. Residential Pool 1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Account -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAccountForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Decodo Proxy Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account Label Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold">Proxy Host</label>
                            <input type="text" class="form-control" id="edit_host" name="host" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="text" class="form-control" id="edit_port" name="port" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proxy Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proxy Password</label>
                        <input type="password" class="form-control" id="edit_password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active (Primary Proxy)</option>
                            <option value="idle">Idle (Backup Pool / Reset Exhausted)</option>
                            <option value="exhausted">Bandwidth Closed / Limit Reached</option>
                        </select>
                        <small class="text-muted">Selecting "Idle" or "Active" resets the exhausted flag if you refilled MB.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes (Optional)</label>
                        <input type="text" class="form-control" id="edit_notes" name="notes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="fa-solid fa-floppy-disk me-1"></i> Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('.btn-edit-acc').on('click', function() {
        const acc = $(this).data('acc');
        $('#edit_name').val(acc.name);
        $('#edit_host').val(acc.host);
        $('#edit_port').val(acc.port);
        $('#edit_username').val(acc.username);
        $('#edit_password').val(acc.password);
        $('#edit_status').val(acc.status);
        $('#edit_notes').val(acc.notes || '');

        let updateUrl = "{{ route('ip_manager.account.update', ':id') }}".replace(':id', acc.id);
        $('#editAccountForm').attr('action', updateUrl);

        $('#editAccountModal').modal('show');
    });

    $('.btn-test-acc').on('click', function() {
        const btn = $(this);
        const host = btn.data('host');
        const port = btn.data('port');
        const user = btn.data('user');
        const pass = btn.data('pass');
        const accId = btn.data('id');

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');
        $('#consoleOutput').removeClass('text-danger text-success').addClass('text-warning').text('[Proxy Test] Testing Decodo proxy: ' + host + ':' + port + ' (' + user + ')...');
        $('#latencyBadge').removeClass('bg-success bg-danger').addClass('bg-warning').text('Testing...');

        $.ajax({
            url: "{{ route('ip_manager.test') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                proxy_host: host,
                proxy_port: port,
                proxy_username: user,
                proxy_password: pass,
                account_id: accId,
                proxy_test_url: "{{ $proxyTestUrl }}"
            },
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-vial"></i> Test');
                let formatted = '';
                if (res.auto_healed) {
                    formatted += '[AUTO-HEALED 🔄] Residential exit node refreshed! Auto-connected to: ' + res.proxy_endpoint + '\n';
                }
                formatted += '[SUCCESS] HTTP Code: ' + res.http_code + ' | Latency: ' + res.latency_ms + 'ms\n';
                formatted += 'Endpoint: ' + res.proxy_endpoint + '\n';
                formatted += 'External IP: ' + (res.external_ip || 'N/A') + '\n';
                formatted += '--------------------------------------------------\n';
                formatted += res.raw_response;

                $('#consoleOutput').removeClass('text-warning text-danger').addClass('text-success').text(formatted);

                if (res.auto_healed) {
                    setTimeout(() => location.reload(), 1500);
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-vial"></i> Test');
                $('#latencyBadge').removeClass('bg-warning bg-success').addClass('bg-danger').text('Error');

                let errJson = xhr.responseJSON || {};
                let errText = '[ERROR] ' + (errJson.message || 'Failed to connect via proxy.') + '\n';
                if (errJson.raw_response) {
                    errText += 'Raw Output: ' + errJson.raw_response + '\n';
                }
                $('#consoleOutput').removeClass('text-warning text-success').addClass('text-danger').text(errText);

                // Reload only if proxy is genuinely marked exhausted
                if (errJson.is_bandwidth_exhausted) {
                    setTimeout(() => location.reload(), 2500);
                }
            }
        });
    });
});
</script>
@endsection
