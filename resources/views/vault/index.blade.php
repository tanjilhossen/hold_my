@extends('layouts.app')

@section('title', 'Slot Vault - Slot Sniper Engine')
@section('page_title', 'Slot Vault & Active Holds')

@section('content')
<div class="row g-4">
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
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-info btn-sm fw-bold" onclick="loadVaultData()">
                        <i class="fa-solid fa-arrows-rotate me-1" id="vault-refresh-icon"></i> Refresh Vault
                    </button>
                    <a href="{{ route('hold') }}" class="btn btn-primary btn-sm fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Hold More Slots
                    </a>
                    <span class="badge bg-secondary fs-6" id="vault-status-badge">Idle</span>
                </div>
            </div>

            <!-- Dynamic Interactive Filter Controls Bar -->
            <div class="row g-2 mb-3 p-3 bg-light rounded-3 border">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark mb-1"><i class="fa-solid fa-briefcase text-primary me-1"></i> Profession</label>
                    <select class="form-select form-select-sm border-secondary shadow-sm" id="vault-filter-profession" onchange="applyVaultFilters()">
                        <option value="ALL">All Professions (All)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-dark mb-1"><i class="fa-solid fa-location-dot text-danger me-1"></i> City</label>
                    <select class="form-select form-select-sm border-secondary shadow-sm" id="vault-filter-city" onchange="applyVaultFilters()">
                        <option value="ALL">All Cities (All)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark mb-1"><i class="fa-solid fa-calendar text-info me-1"></i> Exam Date</label>
                    <select class="form-select form-select-sm border-secondary shadow-sm" id="vault-filter-date" onchange="applyVaultFilters()">
                        <option value="ALL">All Exam Dates (All)</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button class="btn btn-outline-secondary btn-sm fw-bold shadow-sm flex-fill" onclick="resetVaultFilters()" title="Reset all active filters">
                        <i class="fa-solid fa-filter-circle-xmark me-1"></i> Reset Filters
                    </button>
                    <button class="btn btn-success btn-sm fw-bold shadow-sm flex-fill text-white" onclick="copySlotsSummary()" title="Copy slots summary text to clipboard">
                        <i class="fa-solid fa-copy me-1"></i> Copy Slots
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Center Name</th>
                            <th>Profession</th>
                            <th>Exam Date & Time</th>
                            <th>Mother Hash</th>
                            <th>Locked Slots</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vault-grouped-table-body">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
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

<!-- ========================================================================= -->
<!-- 🕒 SEAT RENEWAL HISTORY TIMELINE MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="seatHistoryModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border border-info shadow-lg">
            <div class="modal-header border-secondary">
                <div>
                    <h5 class="modal-title font-weight-bold text-info" id="historyModalCandidateTitle">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> Seat ID Renewal History
                    </h5>
                    <small class="text-muted" id="historyModalCandidateEmail">Candidate: ---</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border border-secondary mb-0">
                        <thead class="table-secondary text-dark">
                            <tr>
                                <th># Cycle</th>
                                <th>Reservation / Seat ID</th>
                                <th>Event Type</th>
                                <th>Timestamp</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="history-modal-table-body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No history records available</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary">
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
                    updateDynamicFilterOptions();
                    renderVaultTable();
                    updateMetrics(data);
                }
            })
            .catch(err => {
                if (icon) icon.classList.remove('fa-spin');
            });
    }

    function updateDynamicFilterOptions() {
        const profSelect = document.getElementById('vault-filter-profession');
        const citySelect = document.getElementById('vault-filter-city');
        const dateSelect = document.getElementById('vault-filter-date');

        if (!profSelect || !citySelect || !dateSelect) return;

        const currentProf = profSelect.value || 'ALL';
        const currentCity = citySelect.value || 'ALL';
        const currentDate = dateSelect.value || 'ALL';

        const professions = new Set();
        const cities = new Set();
        const dates = new Set();

        vaultGroupsData.forEach(g => {
            if (g.category_name) professions.add(g.category_name);
            if (g.city) cities.add(g.city);
            if (g.exam_date) dates.add(g.exam_date);
        });

        // 1. Profession options
        let profHtml = '<option value="ALL">All Professions (All)</option>';
        Array.from(professions).sort().forEach(p => {
            const sel = p === currentProf ? 'selected' : '';
            profHtml += `<option value="${p}" ${sel}>${p}</option>`;
        });
        profSelect.innerHTML = profHtml;

        // 2. City options
        let cityHtml = '<option value="ALL">All Cities (All)</option>';
        Array.from(cities).sort().forEach(c => {
            const sel = c === currentCity ? 'selected' : '';
            cityHtml += `<option value="${c}" ${sel}>${c}</option>`;
        });
        citySelect.innerHTML = cityHtml;

        // 3. Exam Date options
        let dateHtml = '<option value="ALL">All Exam Dates (All)</option>';
        Array.from(dates).sort().forEach(d => {
            const sel = d === currentDate ? 'selected' : '';
            dateHtml += `<option value="${d}" ${sel}>${d}</option>`;
        });
        dateSelect.innerHTML = dateHtml;
    }

    function getFilteredVaultData() {
        const profSelect = document.getElementById('vault-filter-profession');
        const citySelect = document.getElementById('vault-filter-city');
        const dateSelect = document.getElementById('vault-filter-date');

        if (!profSelect || !citySelect || !dateSelect) return vaultGroupsData;

        const selectedProf = profSelect.value;
        const selectedCity = citySelect.value;
        const selectedDate = dateSelect.value;

        return vaultGroupsData.filter(g => {
            const matchProf = (selectedProf === 'ALL' || (g.category_name || '') === selectedProf);
            const matchCity = (selectedCity === 'ALL' || (g.city || '') === selectedCity);
            const matchDate = (selectedDate === 'ALL' || (g.exam_date || '') === selectedDate);
            return matchProf && matchCity && matchDate;
        });
    }

    function applyVaultFilters() {
        renderVaultTable();
    }

    function resetVaultFilters() {
        if (document.getElementById('vault-filter-profession')) document.getElementById('vault-filter-profession').value = 'ALL';
        if (document.getElementById('vault-filter-city')) document.getElementById('vault-filter-city').value = 'ALL';
        if (document.getElementById('vault-filter-date')) document.getElementById('vault-filter-date').value = 'ALL';
        applyVaultFilters();
    }

    function copySlotsSummary() {
        const filteredData = getFilteredVaultData();

        if (!filteredData || filteredData.length === 0) {
            alert('No active locked slots available to copy.');
            return;
        }

        // Group by Center Name + City and Exam Date (summing across Exam Times)
        const centerMap = {};
        let totalSlotsOverall = 0;
        const selectedProf = document.getElementById('vault-filter-profession')?.value;
        const selectedCity = document.getElementById('vault-filter-city')?.value;

        filteredData.forEach(g => {
            const centerKey = `${g.center_name} (${g.city})`;
            const dateStr = g.exam_date || 'N/A';
            const count = g.total_locked_slots || 0;

            totalSlotsOverall += count;

            if (!centerMap[centerKey]) {
                centerMap[centerKey] = {
                    profession: g.category_name || 'Profession',
                    dates: {}
                };
            }

            if (!centerMap[centerKey].dates[dateStr]) {
                centerMap[centerKey].dates[dateStr] = 0;
            }

            centerMap[centerKey].dates[dateStr] += count;
        });

        let msg = `📋 SLOT SNIPER - HELD SLOTS SUMMARY\n`;
        msg += `====================================\n`;
        if (selectedProf && selectedProf !== 'ALL') {
            msg += `Profession: ${selectedProf}\n`;
        }
        if (selectedCity && selectedCity !== 'ALL') {
            msg += `City: ${selectedCity}\n`;
        }

        Object.keys(centerMap).forEach(center => {
            msg += `\n📍 ${center}\n`;
            const datesObj = centerMap[center].dates;
            Object.keys(datesObj).sort().forEach(d => {
                msg += `  • Date: ${d} -> ${datesObj[d]} Slots\n`;
            });
        });

        msg += `\n====================================\n`;
        msg += `Total Held Slots: ${totalSlotsOverall} Slots`;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(msg).then(() => {
                alert('Copied Slots Summary to Clipboard!\n\n' + msg);
            }).catch(() => {
                fallbackCopyText(msg);
            });
        } else {
            fallbackCopyText(msg);
        }
    }

    function fallbackCopyText(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand("copy");
        document.body.removeChild(textArea);
        alert('Copied Slots Summary to Clipboard!\n\n' + text);
    }

    function updateMetrics(data) {
        document.getElementById('stat-active-hashes').innerText = data.count || 0;
        document.getElementById('stat-total-slots').innerText = data.total_active_holds || 0;
        document.getElementById('stat-active-accounts').innerText = data.total_active_holds || 0;
    }

    function renderVaultTable(dataToRender = null) {
        const data = dataToRender !== null ? dataToRender : getFilteredVaultData();
        const tbody = document.getElementById('vault-grouped-table-body');

        if (vaultGroupsData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
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

        if (data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="fa-solid fa-filter-circle-xmark fa-3x mb-3 text-secondary d-block"></i>
                        <h6>No locked slots match the selected filters</h6>
                        <p class="small">Try resetting your filters or select "All" from the dropdowns above.</p>
                        <button onclick="resetVaultFilters()" class="btn btn-outline-primary btn-sm mt-1 fw-bold">
                            <i class="fa-solid fa-rotate-left me-1"></i> Reset Filters
                        </button>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = '';
        data.forEach((g, idx) => {
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
                    <td>
                        <div><i class="fa-solid fa-calendar me-1 text-primary"></i> ${g.exam_date}</div>
                        <div class="text-muted small fw-semibold mt-1"><i class="fa-solid fa-clock me-1 text-info"></i> ${g.start_time || '09:30 AM'}</div>
                    </td>
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

        // If modal is currently open for a hash, refresh modal rows live
        if (currentSelectedHash && $('#expandedSlotsModal').hasClass('show')) {
            const group = vaultGroupsData.find(g => g.mother_hash === currentSelectedHash);
            if (group) {
                renderModalRows(group);
            }
        }

        startLiveTimers();
    }

    function formatTimer(sec) {
        if (sec <= 0) return '0m 00s';
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
                }
                el.innerHTML = `<i class="fa-solid fa-stopwatch me-1"></i> ${formatTimer(sec)}`;
            });
        }, 1000);
    }

    function renderModalRows(group) {
        const tbody = document.getElementById('modal-slots-table-body');
        if (!tbody) return;

        document.getElementById('modalCenterName').innerHTML = `<i class="fa-solid fa-list-check me-2 text-warning"></i> ${group.center_name}`;
        document.getElementById('modalMotherHash').innerHTML = `
            <div class="d-flex align-items-center gap-2 mt-2">
                <small class="text-muted fw-bold">Mother Hash:</small>
                <code class="user-select-all bg-dark text-warning border border-secondary px-2 py-1 rounded font-monospace">${group.mother_hash}</code>
                <button class="btn btn-sm btn-outline-warning px-2 py-0" onclick="navigator.clipboard.writeText('${group.mother_hash}'); alert('Copied Mother Hash!');">
                    <i class="fa-solid fa-copy me-1"></i> Copy
                </button>
            </div>
        `;

        tbody.innerHTML = '';
        if (!group.slots || group.slots.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No slots found</td></tr>`;
            return;
        }

        group.slots.forEach((s, idx) => {
            const isPending = s.status === 'pending_locking';
            
            let seatDisplay = `Seat #${idx + 1}`;
            if (s.temp_seat_id && !String(s.temp_seat_id).startsWith('VAULT_') && !String(s.temp_seat_id).startsWith('PENDING_') && !String(s.temp_seat_id).startsWith('HOLD_')) {
                seatDisplay = `Seat #${idx + 1} (ID: ${s.temp_seat_id})`;
            }

            const seatIdBadge = isPending 
                ? `<span class="badge bg-warning text-dark"><i class="fa-solid fa-spinner fa-spin me-1"></i> Reserving...</span>` 
                : `<span class="badge bg-success text-white font-monospace fs-6 px-2 py-1"><i class="fa-solid fa-chair me-1"></i> ${seatDisplay}</span>`;

            const expiryText = isPending 
                ? `<span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half fa-spin me-1"></i> Reserving in background...</span>` 
                : `<span class="font-monospace fw-bold text-warning timer-badge" data-seconds="${s.remaining_seconds}">
                      <i class="fa-solid fa-stopwatch me-1"></i> ${formatTimer(s.remaining_seconds)}
                   </span>
                   <div class="text-muted small">(${s.expires_at})</div>`;

            const actionBtn = isPending
                ? `<span class="badge bg-dark border border-secondary text-warning"><i class="fa-solid fa-spinner fa-spin me-1"></i> Reserving Seat...</span>`
                : `<button class="btn btn-sm btn-outline-danger" onclick="releaseSingleSlot(${s.id})"><i class="fa-solid fa-xmark me-1"></i> Release Slot</button>`;

            const historyData = s.seat_history || [];
            const historyJsonStr = JSON.stringify(historyData).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
            const renewBadge = `<button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2.5 py-0.5 fw-bold shadow-sm" onclick="showSeatHistory('${s.email}', ${historyJsonStr}, '${s.temp_seat_id}')" title="Click to view Seat ID renewal history"><i class="fa-solid fa-clock-rotate-left me-1"></i> ${s.renew_count} renewals</button>`;

            tbody.innerHTML += `
                <tr>
                    <td class="fw-bold">${idx + 1}</td>
                    <td><code class="fs-6 text-info">${s.email}</code></td>
                    <td>${seatIdBadge}</td>
                    <td>${expiryText}</td>
                    <td>${renewBadge}</td>
                    <td class="text-end">${actionBtn}</td>
                </tr>
            `;
        });
    }

    function showSeatHistory(email, history, currentSeatId) {
        document.getElementById('historyModalCandidateEmail').innerText = `Candidate Account: ${email}`;
        const tbody = document.getElementById('history-modal-table-body');
        tbody.innerHTML = '';

        if (!history || history.length === 0) {
            if (currentSeatId && !currentSeatId.startsWith('VAULT_') && !currentSeatId.startsWith('PENDING_')) {
                history = [{
                    renew_count: 1,
                    seat_id: currentSeatId,
                    timestamp: 'Initial Lock',
                    type: 'Initial Hold'
                }];
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No renewal history entries recorded yet</td></tr>`;
                const hModal = new bootstrap.Modal(document.getElementById('seatHistoryModal'));
                hModal.show();
                return;
            }
        }

        history.forEach((item, idx) => {
            const isLatest = idx === history.length - 1;
            const cycleNum = item.renew_count || (idx + 1);
            const typeBadge = item.type === 'Initial Hold' 
                ? `<span class="badge bg-primary text-white"><i class="fa-solid fa-play me-1"></i> Initial Hold</span>` 
                : `<span class="badge bg-success text-white"><i class="fa-solid fa-rotate me-1"></i> Auto-Renewed</span>`;

            const seatBadge = isLatest
                ? `<span class="badge bg-success font-monospace fs-6 px-2 py-1"><i class="fa-solid fa-chair me-1"></i> ID: ${item.seat_id} (Current)</span>`
                : `<code class="bg-secondary text-white font-monospace px-2 py-1 rounded fs-6">ID: ${item.seat_id}</code>`;

            tbody.innerHTML += `
                <tr>
                    <td class="fw-bold text-warning">Cycle #${cycleNum}</td>
                    <td>${seatBadge}</td>
                    <td>${typeBadge}</td>
                    <td class="small text-light fw-semibold"><i class="fa-solid fa-clock me-1 text-info"></i> ${item.timestamp || 'N/A'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-light py-0.5 px-2" onclick="navigator.clipboard.writeText('${item.seat_id}'); alert('Copied Seat ID: ${item.seat_id}');">
                            <i class="fa-solid fa-copy me-1"></i> Copy ID
                        </button>
                    </td>
                </tr>
            `;
        });

        const hModalEl = document.getElementById('seatHistoryModal');
        let hModal = bootstrap.Modal.getInstance(hModalEl);
        if (!hModal) {
            hModal = new bootstrap.Modal(hModalEl);
        }
        hModal.show();
    }

    function openExpandedModal(hash) {
        currentSelectedHash = hash;
        const group = vaultGroupsData.find(g => g.mother_hash === hash);
        if (!group) return;

        renderModalRows(group);
        startLiveTimers();

        document.getElementById('btn-modal-release-all').onclick = function() {
            releaseGroup(hash);
        };

        const modalEl = document.getElementById('expandedSlotsModal');
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
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
