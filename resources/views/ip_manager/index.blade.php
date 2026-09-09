@extends('layouts.app')

@section('title', 'IP Manager - Decodo Proxy Pool')
@section('page_title', 'IP Manager & Decodo Proxy Pool')

@section('content')
<div class="row">
    <div class="col-lg-12">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Global Status & Control Header -->
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold text-dark m-0">
                        <i class="fa-solid fa-network-wired text-info me-2"></i> Decodo Multi-Account Proxy Engine
                    </h5>
                    <small class="text-muted">Automatic failover pool for Decodo Residential Proxies. Auto-switches when data (MB) runs out.</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <form action="{{ route('ip_manager.update') }}" method="POST" class="d-flex align-items-center gap-2">
                        @csrf
                        <div class="form-check form-switch fs-5 m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="proxy_enabled" name="proxy_enabled" value="1" {{ $proxyEnabled == '1' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="form-check-label fs-6 fw-semibold ms-1" for="proxy_enabled">
                                Proxy Engine: <span class="badge {{ $proxyEnabled == '1' ? 'bg-success' : 'bg-secondary' }}">{{ $proxyEnabled == '1' ? 'Enabled' : 'Disabled' }}</span>
                            </label>
                        </div>
                    </form>
                    <button type="button" class="btn btn-primary px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                        <i class="fa-solid fa-plus me-1"></i> Add Decodo Account
                    </button>
                </div>
            </div>
        </div>

        <!-- Decodo Accounts Pool Table -->
        <div class="card card-custom p-4 mb-4">
            <h6 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-layer-group text-primary me-2"></i> Decodo Proxy Pool Accounts
            </h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle border mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Account Name</th>
                            <th>Host & Port</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Exhausted / Failover Info</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proxyAccounts as $acc)
                            <tr class="{{ ($acc['status'] ?? '') === 'exhausted' ? 'table-danger' : (($acc['status'] ?? '') === 'active' ? 'table-success bg-opacity-10' : '') }}">
                                <td>
                                    <strong class="text-dark">{{ $acc['name'] ?? 'Decodo Account' }}</strong>
                                    @if(!empty($acc['notes']))
                                        <br><small class="text-muted">{{ $acc['notes'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $acc['host'] ?? 'bd.decodo.com' }}:{{ $acc['port'] ?? '41001' }}</code>
                                </td>
                                <td>
                                    <span class="font-monospace text-secondary">{{ $acc['username'] ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    @if(($acc['status'] ?? '') === 'active')
                                        <span class="badge bg-success px-3 py-2 fs-6">
                                            <i class="fa-solid fa-circle-check me-1"></i> Active
                                        </span>
                                    @elseif(($acc['status'] ?? '') === 'exhausted')
                                        <span class="badge bg-danger px-3 py-2 fs-6">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> MB Finished / Data Exhausted
                                        </span>
                                    @else
                                        <span class="badge bg-secondary px-3 py-2 fs-6">
                                            <i class="fa-solid fa-clock me-1"></i> Idle
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if(($acc['status'] ?? '') === 'exhausted')
                                        <span class="text-danger small fw-semibold">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> {{ $acc['exhausted_reason'] ?? 'Data Limit Reached' }}
                                        </span>
                                        @if(!empty($acc['exhausted_at']))
                                            <br><small class="text-muted">{{ $acc['exhausted_at'] }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted small">Ready for auto-switch</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if(($acc['status'] ?? '') !== 'active')
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
                                            title="Test Credentials">
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
                                <td colspan="6" class="text-center py-4 text-muted">No proxy accounts found. Click "Add Decodo Account" above.</td>
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
[System] Click "Test" on any proxy account above to verify credentials and live IP status.
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
                        <input type="text" class="form-control" name="name" placeholder="e.g. Decodo Account 2 (5GB)" required>
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
                        <input type="text" class="form-control" name="username" placeholder="e.g. spua00a572" required>
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
                        <input type="text" class="form-control" name="notes" placeholder="e.g. Purchased 10 Sep">
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
                            <option value="exhausted">MB Finished / Data Exhausted</option>
                        </select>
                        <small class="text-muted">Selecting "Idle" or "Active" will reset the MB Finished flag.</small>
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
                $('#latencyBadge').removeClass('bg-warning bg-danger').addClass('bg-success').text(res.latency_ms + ' ms');

                let formatted = '[SUCCESS] HTTP Code: ' + res.http_code + ' | Latency: ' + res.latency_ms + 'ms\n';
                formatted += 'Endpoint: ' + res.proxy_endpoint + '\n';
                formatted += '--------------------------------------------------\n';
                formatted += res.raw_response;

                $('#consoleOutput').removeClass('text-warning text-danger').addClass('text-success').text(formatted);
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

                // Auto reload page after 2s if proxy account was marked exhausted during test
                if (xhr.status === 500 && errText.includes('407')) {
                    setTimeout(() => location.reload(), 2000);
                }
            }
        });
    });
});
</script>
@endsection
