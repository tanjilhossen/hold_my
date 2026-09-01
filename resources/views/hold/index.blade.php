@extends('layouts.app')

@section('title', 'Hold Slot - Taqamul Engine')
@section('page_title', 'Hold Slot Engine')

@section('content')
<div class="row g-4">
    <!-- Dedicated Slot Checker Account Status Banner -->
    <div class="col-12">
        <div class="card card-custom p-3 bg-dark text-white border-start border-4 border-emerald-500">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="spinner-grow spinner-grow-sm text-success" role="status"></span>
                    <strong class="text-info">Slot Checker Dedicated Account:</strong>
                    <span class="badge bg-secondary font-monospace">{{ $poolAccount['email'] ?? 'pool__485381@wafidmaster.com' }}</span>
                    <span class="badge bg-warning text-dark">ONLY FOR SLOT CHECKING</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div id="token-status-wrapper">
                        @if($hasActiveToken)
                            <span class="badge bg-success"><i class="fa-solid fa-key me-1"></i> Bearer Token Active</span>
                        @else
                            <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Token Expired (Auto-Authenticating...)</span>
                        @endif
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="triggerAutoLoginForPool()">
                        <i class="fa-solid fa-rotate me-1" id="refresh-icon"></i> Keep-Alive / Refresh Token
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scanner Controls Card -->
    <div class="col-lg-4">
        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-sliders text-primary me-2"></i> Scan & Hold Config</h5>
            <form id="hold-config-form">
                @csrf
                
                <!-- 1. Select Profession (Searchable Dropdown) -->
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="fa-solid fa-briefcase text-info me-1"></i> Select Profession</label>
                    <select class="form-select searchable-select" id="profession_select" name="category_id" data-placeholder="Search or select profession...">
                        <option value=""></option>
                        @foreach($formattedOccupations as $occ)
                            <option value="{{ $occ['category_id'] }}">{{ $occ['full_label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. Select City (Filtered by available dates with count) -->
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="fa-solid fa-location-dot text-danger me-1"></i> Select City</label>
                    <select class="form-select searchable-select" id="city_select" name="city" data-placeholder="Select profession to load active cities...">
                        <option value="">-- Select Profession First --</option>
                    </select>
                </div>

                <!-- 3. Target Date (Fetched dynamically from Taqamul API) -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="fa-solid fa-calendar-days text-success me-1"></i> Exam Date
                        <span id="date-spinner" class="spinner-border spinner-border-sm text-primary d-none ms-1" role="status"></span>
                    </label>
                    <select class="form-select searchable-select" id="date_select" name="exam_date">
                        <option value="ALL">-- Select City First --</option>
                    </select>
                </div>

                <!-- 4. Hold Strategy -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fa-solid fa-shield-halved text-warning me-1"></i> Hold Strategy</label>
                    <select class="form-select" id="hold_strategy">
                        <option value="staggered">Staggered Hold (Auto-Renew)</option>
                        <option value="single">Single Account Fast Lock</option>
                    </select>
                </div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary btn-lg fw-bold" id="btn-start-scan">
                        <i class="fa-solid fa-magnifying-glass me-2"></i> Scan Slots & Fetch Hashes
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="btn-stop-scan" disabled>
                        <i class="fa-solid fa-square me-2"></i> Stop Scanner
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Scanner Console & Results Table -->
    <div class="col-lg-8">
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-satellite-dish text-success me-2"></i> Live Mother Hash & Seat Monitor</h5>
                    <small class="text-muted" id="scan-summary-text">Select profession and date to scan center seat availability.</small>
                </div>
                <span class="badge bg-secondary fs-6" id="scan-status-badge">Idle</span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Center Name</th>
                            <th>Exam Date & Time</th>
                            <th>Mother Hash</th>
                            <th>Available Seats</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="scan-results-table">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                Select <strong>Profession</strong> and click <strong>Scan Slots</strong> to query Taqamul server.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Live Log Output Box -->
        <div class="card card-custom p-4 bg-dark text-white font-monospace" style="min-height: 180px; max-height: 250px; overflow-y: auto;">
            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary pb-2">
                <small class="text-info"><i class="fa-solid fa-terminal me-1"></i> Live Stream Console</small>
                <button class="btn btn-sm btn-outline-light py-0 fs-7" onclick="document.getElementById('console-log').innerHTML='[SYSTEM]: Console cleared.'">Clear</button>
            </div>
            <div id="console-log" class="small">
                [SYSTEM]: Dedicated Slot Checker Account: pool__485381@wafidmaster.com
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 🔐 AUTOMATED CANDIDATE POOL AUTHENTICATION MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="poolLoginModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border border-secondary shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-warning" id="poolModalTitle">
                    <i class="fa-solid fa-key me-2 animate-bounce"></i> Auto-Authenticating Slot Checker Pool Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body space-y-3">
                <p class="text-muted small mb-2" id="poolModalSubtitle">
                    Bearer token expired for <code>pool__485381@wafidmaster.com</code>. Background bot is executing visual login & OTP retrieval...
                </p>
                
                <!-- Live Terminal Stream Box -->
                <div class="bg-black border border-secondary rounded p-3 font-monospace text-success small" style="height: 200px; overflow-y: auto;" id="poolLiveTerminalOutput">
                    [Token Bot] Initializing Live Authentication Console...
                </div>

                <!-- Progress Bar & Timer -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between text-xs text-muted mb-1">
                        <span id="poolModalFooterStatus" class="text-warning font-weight-bold">
                            <i class="fa-solid fa-spinner fa-spin me-1"></i> Authentication in progress...
                        </span>
                        <span id="poolModalTimer" class="font-monospace">0.0s</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div id="poolModalProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 20%"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let fetchedCityDatesMap = {};
    let fetchedAllDates = [];
    let poolLoginTimerInterval = null;
    let poolLoginLogsPollingInterval = null;
    let poolLoginStartTime = 0;
    let isAutoLoginInProgress = false;
    let bsLoginModal = null;

    $(document).ready(function() {
        $('#profession_select').select2({
            theme: 'bootstrap-5',
            placeholder: 'Type to search profession or Cat ID...',
            allowClear: true,
            width: '100%'
        });

        $('#city_select').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select city with available dates...',
            width: '100%'
        });

        $('#date_select').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select date...',
            width: '100%'
        });

        bsLoginModal = new bootstrap.Modal(document.getElementById('poolLoginModal'));

        // AUTO-CHECK ON PAGE LOAD: If token expired, auto-trigger background login immediately
        const initialHasActiveToken = @json($hasActiveToken);
        if (!initialHasActiveToken && !isAutoLoginInProgress) {
            appendLog('Token expired on load. Auto-launching background login for pool__485381@wafidmaster.com...');
            triggerAutoLoginForPool();
        }

        // 1. Trigger Date & Active City Fetch on Profession Select
        $('#profession_select').on('change', function() {
            const categoryId = $(this).val();
            const $citySelect = $('#city_select');
            const $dateSelect = $('#date_select');
            const spinner = document.getElementById('date-spinner');
            
            if (!categoryId) {
                $citySelect.html('<option value="">-- Select Profession First --</option>').trigger('change');
                $dateSelect.html('<option value="ALL">-- Select City First --</option>').trigger('change');
                return;
            }

            spinner.classList.remove('d-none');
            appendLog(`Fetching available dates via pool__485381@wafidmaster.com (Cat ID: ${categoryId})...`);

            fetch(`{{ route('hold.available_dates') }}?category_id=${categoryId}`)
                .then(res => res.json())
                .then(data => {
                    spinner.classList.add('d-none');
                    if (data.success) {
                        fetchedCityDatesMap = data.city_dates_map || {};
                        fetchedAllDates = data.all_dates || [];
                        
                        updateCityDropdown();
                        appendLog(`Found ${data.all_dates.length} available dates across ${Object.keys(fetchedCityDatesMap).length} active cities.`);
                    } else if (data.code === 'TOKEN_EXPIRED') {
                        appendLog(`Warning: Bearer token expired for pool__485381@wafidmaster.com. Auto-refreshing...`);
                        if (!isAutoLoginInProgress) {
                            triggerAutoLoginForPool(() => $('#profession_select').trigger('change'));
                        }
                    } else {
                        $citySelect.html('<option value="">No Cities Found</option>').trigger('change');
                        $dateSelect.html('<option value="ALL">All Available Dates</option>').trigger('change');
                        appendLog(`Warning: ${data.message}`);
                    }
                })
                .catch(err => {
                    spinner.classList.add('d-none');
                    appendLog(`Error fetching dates: ${err.message}`);
                });
        });

        $('#city_select').on('change', updateDateDropdown);
    });

    function appendLog(msg) {
        const log = document.getElementById('console-log');
        const timestamp = new Date().toLocaleTimeString();
        log.innerHTML += `<br>[${timestamp}]: ${msg}`;
        log.scrollTop = log.scrollHeight;
    }

    // Populate City Dropdown with ONLY Cities that have Available Dates (+ Count badge)
    function updateCityDropdown() {
        const $citySelect = $('#city_select');
        $citySelect.empty();

        const activeCities = Object.keys(fetchedCityDatesMap);

        if (activeCities.length === 0) {
            $citySelect.append('<option value="">No Cities with Available Dates</option>');
        } else {
            activeCities.forEach(city => {
                const count = (fetchedCityDatesMap[city] || []).length;
                $citySelect.append(`<option value="${city}">${city} (${count} Date${count > 1 ? 's' : ''})</option>`);
            });
        }

        $citySelect.trigger('change');
    }

    // Populate Date Dropdown based on Selected City
    function updateDateDropdown() {
        const selectedCity = $('#city_select').val();
        const $dateSelect = $('#date_select');
        $dateSelect.empty();

        const cityDates = fetchedCityDatesMap[selectedCity] || fetchedAllDates;

        if (cityDates && cityDates.length > 0) {
            $dateSelect.append(`<option value="ALL">All Available Dates (${cityDates.length} Dates)</option>`);
            cityDates.forEach(d => {
                $dateSelect.append(`<option value="${d}">${d}</option>`);
            });
        } else {
            $dateSelect.append('<option value="ALL">All Available Dates</option>');
        }
        $dateSelect.trigger('change');
    }

    // AUTOMATIC BACKGROUND LOGIN & LIVE LOG STREAMING FOR pool__485381@wafidmaster.com
    function triggerAutoLoginForPool(onSuccessCallback) {
        if (isAutoLoginInProgress) return;
        isAutoLoginInProgress = true;

        if (bsLoginModal) bsLoginModal.show();

        const terminal = document.getElementById('poolLiveTerminalOutput');
        terminal.innerHTML = '<div class="text-muted">[Token Bot] Initializing Live Authentication Console...</div>';
        
        const bar = document.getElementById('poolModalProgressBar');
        if (bar) bar.style.width = '20%';
        
        const footer = document.getElementById('poolModalFooterStatus');
        if (footer) footer.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-warning me-1"></i> Authentication in progress...`;

        poolLoginStartTime = Date.now();
        if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);
        poolLoginTimerInterval = setInterval(() => {
            const elapsed = ((Date.now() - poolLoginStartTime) / 1000).toFixed(1);
            document.getElementById('poolModalTimer').innerText = `${elapsed}s`;
        }, 100);

        // Send AJAX request to launch Node.js bot background process
        fetch(`{{ route('hold.auto_login') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                appendLog('Background login process started for pool__485381@wafidmaster.com.');
                pollLoginLogs(onSuccessCallback);
            } else {
                isAutoLoginInProgress = false;
                if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);
                if (footer) footer.innerHTML = `<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Failed to launch bot: ${data.message}</span>`;
            }
        })
        .catch(err => {
            isAutoLoginInProgress = false;
            if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);
            if (footer) footer.innerHTML = `<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Error: ${err.message}</span>`;
        });
    }

    function pollLoginLogs(onSuccessCallback) {
        if (poolLoginLogsPollingInterval) clearInterval(poolLoginLogsPollingInterval);

        let lastLogs = '';
        poolLoginLogsPollingInterval = setInterval(() => {
            fetch(`{{ route('hold.auto_login_logs') }}`)
                .then(res => res.json())
                .then(data => {
                    if (data.logs && data.logs !== lastLogs) {
                        lastLogs = data.logs;
                        const terminal = document.getElementById('poolLiveTerminalOutput');
                        const lines = data.logs.split('\n').filter(l => l.trim().length > 0 && !l.includes('FINAL_TOKEN_RESULT'));
                        terminal.innerHTML = lines.map(l => `<div>${l}</div>`).join('');
                        terminal.scrollTop = terminal.scrollHeight;

                        // Dynamic progress bar updates
                        const bar = document.getElementById('poolModalProgressBar');
                        if (bar) {
                            if (data.logs.includes('FINAL_TOKEN_RESULT')) bar.style.width = '100%';
                            else if (data.logs.includes('OTP')) bar.style.width = '80%';
                            else if (data.logs.includes('CapSolver AI')) bar.style.width = '60%';
                            else if (data.logs.includes('Navigating')) bar.style.width = '40%';
                        }
                    }

                    if (data.done) {
                        clearInterval(poolLoginLogsPollingInterval);
                        if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);

                        if (data.token && data.token.startsWith('eyJ')) {
                            document.getElementById('token-status-wrapper').innerHTML = `<span class="badge bg-success"><i class="fa-solid fa-key me-1"></i> Bearer Token Active</span>`;
                            const footer = document.getElementById('poolModalFooterStatus');
                            if (footer) footer.innerHTML = `<span class="text-success font-weight-bold"><i class="fa-solid fa-circle-check me-1"></i> Login Successful! Bearer Token Acquired!</span>`;

                            appendLog('Success! Acquired fresh Bearer Token for pool__485381@wafidmaster.com.');
                            
                            setTimeout(() => {
                                isAutoLoginInProgress = false;
                                if (bsLoginModal) bsLoginModal.hide();
                                if (typeof onSuccessCallback === 'function') onSuccessCallback();
                            }, 1200);
                        } else {
                            isAutoLoginInProgress = false;
                            document.getElementById('token-status-wrapper').innerHTML = `<span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Login Failed</span>`;
                            const footer = document.getElementById('poolModalFooterStatus');
                            if (footer) footer.innerHTML = `<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${data.error || 'Authentication Failed.'}</span>`;
                        }
                    }
                })
                .catch(() => {});
        }, 1500);
    }

    // 3. Perform Live Slot Scan on Button Click
    document.getElementById('btn-start-scan').addEventListener('click', function() {
        const categoryId = $('#profession_select').val();
        const city = $('#city_select').val();
        const examDate = $('#date_select').val();
        const csrfToken = '{{ csrf_token() }}';

        if (!categoryId) {
            alert('Please select a Profession first.');
            return;
        }

        if (!city) {
            alert('Please select a City first.');
            return;
        }

        document.getElementById('scan-status-badge').className = 'badge bg-success fs-6';
        document.getElementById('scan-status-badge').innerText = 'Scanning Server...';
        document.getElementById('btn-start-scan').disabled = true;
        document.getElementById('btn-stop-scan').disabled = false;

        const tableBody = document.getElementById('scan-results-table');
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-primary fw-bold">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Querying Taqamul API via pool__485381@wafidmaster.com...
                </td>
            </tr>
        `;

        appendLog(`Initiating HTTP request to Taqamul server via pool__485381@wafidmaster.com for ${city} (${examDate})...`);

        fetch(`{{ route('hold.scan') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                category_id: categoryId,
                city: city,
                exam_date: examDate,
                all_dates: fetchedAllDates
            })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('scan-status-badge').className = 'badge bg-secondary fs-6';
            document.getElementById('scan-status-badge').innerText = 'Idle';
            document.getElementById('btn-start-scan').disabled = false;
            document.getElementById('btn-stop-scan').disabled = true;

            if (data.code === 'TOKEN_EXPIRED') {
                appendLog(`Token expired during scan. Triggering auto-reauthentication for pool__485381@wafidmaster.com...`);
                if (!isAutoLoginInProgress) {
                    triggerAutoLoginForPool(() => document.getElementById('btn-start-scan').click());
                }
                return;
            }

            if (data.success && data.centers && data.centers.length > 0) {
                tableBody.innerHTML = '';
                document.getElementById('scan-summary-text').innerText = `Found ${data.count} center session(s) in ${city}.`;
                appendLog(`Success! Found ${data.count} center session(s) with Mother Hashes.`);

                data.centers.forEach(c => {
                    const shortHash = c.mother_hash.substring(0, 16) + '...';
                    const seatBadge = c.available_seats > 0 
                        ? `<span class="badge bg-success fs-6">${c.available_seats} / ${c.total_seats} Available</span>` 
                        : `<span class="badge bg-danger fs-6">Full</span>`;

                    tableBody.innerHTML += `
                        <tr>
                            <td class="fw-bold">${c.session_index}</td>
                            <td>
                                <strong class="text-dark">${c.center_name}</strong>
                                <div class="text-muted small">${c.center_address}</div>
                            </td>
                            <td>
                                <div><i class="fa-solid fa-calendar me-1 text-primary"></i> ${c.exam_date}</div>
                                <small class="text-muted"><i class="fa-solid fa-clock me-1 text-info"></i> ${c.start_time}</small>
                            </td>
                            <td>
                                <code class="user-select-all bg-light px-2 py-1 border rounded text-dark">${shortHash}</code>
                                <button class="btn btn-sm btn-link p-0 ms-1 text-decoration-none" onclick="navigator.clipboard.writeText('${c.mother_hash}'); alert('Copied Mother Hash!');" title="Copy Full Hash">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </td>
                            <td>${seatBadge}</td>
                            <td><span class="badge bg-primary">Scheduled</span></td>
                        </tr>
                    `;
                });
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fa-solid fa-triangle-exclamation fa-2x mb-2 text-warning d-block"></i>
                            No available seats or exam sessions found for <strong>${city}</strong> on <strong>${examDate}</strong>.
                        </td>
                    </tr>
                `;
                appendLog(`No exam seats found for ${city} on ${examDate}.`);
            }
        })
        .catch(err => {
            document.getElementById('scan-status-badge').className = 'badge bg-danger fs-6';
            document.getElementById('scan-status-badge').innerText = 'Error';
            document.getElementById('btn-start-scan').disabled = false;
            document.getElementById('btn-stop-scan').disabled = true;

            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger py-4">
                        Failed to connect to Taqamul server: ${err.message}
                    </td>
                </tr>
            `;
            appendLog(`Error querying slots: ${err.message}`);
        });
    });
</script>
@endsection
