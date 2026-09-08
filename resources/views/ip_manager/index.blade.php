@extends('layouts.app')

@section('title', 'IP Manager - Slot Sniper Engine')
@section('page_title', 'IP Manager & Proxy Settings')

@section('content')
<div class="row">
    <div class="col-lg-8">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card card-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <h5 class="fw-bold text-dark m-0">
                        <i class="fa-solid fa-network-wired text-info me-2"></i> Residential Proxy Setup (Decodo / Custom)
                    </h5>
                    <small class="text-muted">Configure dynamic residential IP rotation to bypass Taqamul API rate limits</small>
                </div>
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" role="switch" id="proxy_enabled" name="proxy_enabled" form="proxyForm" {{ $proxyEnabled == '1' ? 'checked' : '' }}>
                    <label class="form-check-label fs-6 fw-semibold ms-2" for="proxy_enabled">
                        Proxy Status: <span class="badge {{ $proxyEnabled == '1' ? 'bg-success' : 'bg-secondary' }}" id="statusBadge">{{ $proxyEnabled == '1' ? 'Active' : 'Disabled' }}</span>
                    </label>
                </div>
            </div>

            <form action="{{ route('ip_manager.update') }}" method="POST" id="proxyForm">
                @csrf

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-dark">Proxy Host / Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-server text-secondary"></i></span>
                            <input type="text" class="form-control" name="proxy_host" value="{{ old('proxy_host', $proxyHost) }}" placeholder="e.g. bd.decodo.com" required>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-dark">Port / Port Range</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-plug text-secondary"></i></span>
                            <input type="text" class="form-control" name="proxy_port" value="{{ old('proxy_port', $proxyPort) }}" placeholder="41001 or 41001-41010" required>
                        </div>
                        <small class="text-muted">Single (41001) or Range (41001-41010)</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-user text-secondary"></i></span>
                            <input type="text" class="form-control" name="proxy_username" value="{{ old('proxy_username', $proxyUsername) }}" placeholder="Proxy Username">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-key text-secondary"></i></span>
                            <input type="password" class="form-control" name="proxy_password" value="{{ old('proxy_password', $proxyPassword) }}" placeholder="Proxy Password">
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark">Test Connection Endpoint URL</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-globe text-secondary"></i></span>
                            <input type="text" class="form-control" name="proxy_test_url" id="proxy_test_url" value="{{ old('proxy_test_url', $proxyTestUrl) }}" placeholder="ip.decodo.com/json">
                        </div>
                        <small class="text-muted">Target endpoint to verify public IP & latency when testing proxy connection</small>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Configurations
                    </button>
                    <button type="button" class="btn btn-success px-4 fw-semibold" id="btnTestProxy">
                        <i class="fa-solid fa-play me-1"></i> Test Proxy Connection
                    </button>
                </div>
            </form>
        </div>

        <!-- Terminal Output Log -->
        <div class="card card-custom p-4 bg-dark text-light">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-info"><i class="fa-solid fa-terminal me-2"></i> Live Proxy Connection Console</span>
                <span class="badge bg-secondary small" id="latencyBadge">Idle</span>
            </div>
            <pre class="bg-black text-success p-3 rounded font-monospace m-0" id="consoleOutput" style="min-height: 140px; max-height: 250px; overflow-y: auto; font-size: 0.9rem;">
[System] Click "Test Proxy Connection" to verify proxy credentials and fetch live IP details from Decodo.
            </pre>
        </div>
    </div>

    <!-- Instructions / Status Widget -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 mb-4">
            <h6 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-circle-info text-primary me-2"></i> Quick Reference
            </h6>
            <ul class="text-muted small ps-3 mb-0" style="line-height: 1.7;">
                <li><strong>Host & Port:</strong> Entry point for Decodo proxy routing (e.g. <code>bd.decodo.com:41001</code>).</li>
                <li><strong>Location:</strong> Bangladesh Residential IPs match Taqamul Bangladesh server expectations.</li>
                <li><strong>Rate Limit Bypass:</strong> Automatically routes all Taqamul API requests through rotating IPs.</li>
                <li><strong>cURL Command Equivalent:</strong></li>
            </ul>
            <div class="bg-light p-2 rounded border mt-3 font-monospace text-dark small" style="font-size: 0.8rem;">
                curl -x bd.decodo.com:41001 -U spua00a572:PASS http://ip.decodo.com/json
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#proxy_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#statusBadge').removeClass('bg-secondary').addClass('bg-success').text('Active');
        } else {
            $('#statusBadge').removeClass('bg-success').addClass('bg-secondary').text('Disabled');
        }
    });

    $('#btnTestProxy').on('click', function() {
        const btn = $(this);
        const originalHtml = btn.html();

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Testing...');
        $('#consoleOutput').removeClass('text-danger text-success').addClass('text-warning').text('[Proxy Test] Connecting to Decodo proxy server...\nTarget: ' + $('#proxy_test_url').val());
        $('#latencyBadge').removeClass('bg-success bg-danger').addClass('bg-warning').text('Testing...');

        $.ajax({
            url: "{{ route('ip_manager.test') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                proxy_host: $('input[name="proxy_host"]').val(),
                proxy_port: $('input[name="proxy_port"]').val(),
                proxy_username: $('input[name="proxy_username"]').val(),
                proxy_password: $('input[name="proxy_password"]').val(),
                proxy_test_url: $('#proxy_test_url').val(),
            },
            success: function(res) {
                btn.prop('disabled', false).html(originalHtml);
                $('#latencyBadge').removeClass('bg-warning bg-danger').addClass('bg-success').text(res.latency_ms + ' ms');

                let formatted = '[SUCCESS] HTTP Code: ' + res.http_code + ' | Latency: ' + res.latency_ms + 'ms\n';
                formatted += 'Endpoint: ' + res.proxy_endpoint + '\n';
                formatted += '--------------------------------------------------\n';
                formatted += res.raw_response;

                $('#consoleOutput').removeClass('text-warning text-danger').addClass('text-success').text(formatted);
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                $('#latencyBadge').removeClass('bg-warning bg-success').addClass('bg-danger').text('Error');

                let errJson = xhr.responseJSON || {};
                let errText = '[ERROR] ' + (errJson.message || 'Failed to connect via proxy.') + '\n';
                if (errJson.raw_response) {
                    errText += 'Raw Output: ' + errJson.raw_response + '\n';
                }
                $('#consoleOutput').removeClass('text-warning text-success').addClass('text-danger').text(errText);
            }
        });
    });
});
</script>
@endsection
