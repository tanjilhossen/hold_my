@extends('layouts.app')

@section('title', 'SLOT VAULT - Grouped Held Slots & Seats Summary')

@section('content')
<div class="space-y-6">

    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-purple-950/60 to-slate-900 border border-purple-500/30 p-6 rounded-3xl backdrop-blur-xl shadow-2xl space-y-4 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-3 py-1 rounded-full bg-purple-500/20 text-purple-300 text-xs font-bold font-mono border border-purple-500/40 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-purple-400"></i> SLOT VAULT
                    </span>
                    <span class="text-xs text-slate-400">Continuous Held Seats Vault</span>
                </div>
                <h1 class="text-2xl font-extrabold text-white flex items-center gap-2">
                    <span>Grouped Held Slots Directory</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    Held seats grouped together by <b class="text-purple-300">City</b>, <b class="text-purple-300">Profession</b>, <b class="text-purple-300">Exam Date</b>, and <b class="text-purple-300">Center Name</b>.
                </p>
            </div>

            <!-- Stats Badge Group -->
            <div class="flex items-center gap-3">
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl px-4 py-2.5 text-center">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Total Groups</div>
                    <div class="text-lg font-black text-purple-400 font-mono">{{ count($groupedVaultList) }}</div>
                </div>
                <div class="bg-slate-950/80 border border-slate-800 rounded-2xl px-4 py-2.5 text-center">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Active Holds</div>
                    <div id="topActiveHoldsCount" class="text-lg font-black text-emerald-400 font-mono">{{ $totalActiveHolds }}</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <form method="GET" action="{{ route('admin.slots.held_vault') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Search Keyword</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-purple-400 text-xs"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search center, hash, email..."
                           class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:border-purple-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Filter by City</label>
                <select name="city" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:border-purple-500 outline-none">
                    <option value="">All Cities</option>
                    @foreach($cities as $c)
                        <option value="{{ $c }}" {{ request('city') == $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Filter by Profession</label>
                <select name="category_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:border-purple-500 outline-none">
                    <option value="">All Professions</option>
                    @foreach($formattedOccupations as $occ)
                        <option value="{{ $occ['id'] }}" {{ request('category_id') == $occ['id'] ? 'selected' : '' }}>
                            {{ $occ['english_name'] }} [ID: {{ $occ['id'] }}]
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-all shadow-lg shadow-purple-600/20 cursor-pointer">
                    <i class="fa-solid fa-filter mr-1"></i> Apply Filter
                </button>
                <a href="{{ route('admin.slots.held_vault') }}" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-all">
                    Reset
                </a>
            </div>
    </div>

    <!-- Live Real-Time Terminal Console Box -->
    <div id="liveTerminalContainer" class="bg-slate-950 border border-purple-500/40 rounded-3xl p-5 shadow-2xl space-y-3 font-mono relative overflow-hidden transition-all duration-300">
        <!-- Terminal Title Bar -->
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
            <div class="flex items-center gap-3">
                <!-- Mac-style Window Controls -->
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-rose-500/80 inline-block shadow"></span>
                    <span class="w-3 h-3 rounded-full bg-amber-500/80 inline-block shadow"></span>
                    <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block shadow"></span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-terminal text-purple-400 text-xs"></i>
                    <span class="text-xs font-extrabold text-white tracking-wider uppercase">TAQAMUL LIVE SEAT HOLD ENGINE TERMINAL</span>
                    <span id="terminalStatusBadge" class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold border border-emerald-500/30 flex items-center gap-1">
                        <i class="fa-solid fa-circle text-[6px] animate-pulse text-emerald-400"></i> READY / IDLE
                    </span>
                </div>
            </div>

            <!-- Terminal Actions -->
            <div class="flex items-center gap-2">
                <button onclick="clearTerminalLogs()" class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white text-[11px] font-mono transition-colors cursor-pointer" title="Clear Console">
                    <i class="fa-solid fa-trash-can mr-1 text-[10px]"></i> Clear
                </button>
                <button onclick="toggleTerminalExpand()" class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white text-[11px] font-mono transition-colors cursor-pointer" id="toggleTerminalBtn">
                    <i class="fa-solid fa-chevron-up text-[10px]" id="toggleTerminalIcon"></i>
                </button>
            </div>
        </div>

        <!-- Terminal Progress Bar -->
        <div id="terminalProgressContainer" class="hidden space-y-1.5">
            <div class="flex items-center justify-between text-[11px] font-bold">
                <span id="terminalProgressLabel" class="text-purple-300">Locking Seats on Taqamul Live API...</span>
                <span id="terminalProgressPercent" class="text-emerald-400">0%</span>
            </div>
            <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-800">
                <div id="terminalProgressBar" class="bg-gradient-to-r from-purple-500 via-purple-400 to-emerald-400 h-full w-0 transition-all duration-300"></div>
            </div>
        </div>

        <!-- Terminal Log Screen -->
        <div id="terminalLogScreen" class="bg-slate-950/90 border border-slate-900 rounded-2xl p-4 h-52 overflow-y-auto custom-scrollbar font-mono text-[11px] leading-relaxed text-slate-300 space-y-1">
            <div class="text-slate-500 italic">[Engine Ready] Waiting for slot holding process to initiate...</div>
        </div>
    </div>

    <!-- Main Grouped Holds Table Card -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-purple-400"></i>
                <span>Held Slots Groups</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                    {{ count($groupedVaultList) }} Groups
                </span>
            </h2>
            <div class="text-xs text-slate-400 font-mono">
                Auto-Renew Engine: <span class="text-emerald-400 font-bold">Active (20m Auto-Cycle)</span>
            </div>
        </div>

        @if(count($groupedVaultList) > 0)
            <div class="overflow-x-auto custom-scrollbar border border-slate-800 rounded-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-950/80 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                            <th class="py-3.5 px-4">City / Division</th>
                            <th class="py-3.5 px-4">Profession / Category</th>
                            <th class="py-3.5 px-4">Exam Date</th>
                            <th class="py-3.5 px-4">Test Center Name</th>
                            <th class="py-3.5 px-4 text-center">Total Seats</th>
                            <th class="py-3.5 px-4">Expiry Countdown</th>
                            <th class="py-3.5 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-xs">
                        @foreach($groupedVaultList as $index => $group)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <!-- City -->
                                <td class="py-3.5 px-4 font-bold text-white">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-location-dot text-emerald-400 text-xs"></i>
                                        <span>{{ $group['city'] }}</span>
                                    </div>
                                </td>

                                <!-- Profession -->
                                <td class="py-3.5 px-4 text-slate-200">
                                    <div class="font-bold text-purple-300">{{ $group['category_name'] }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">Cat ID: {{ $group['category_id'] }}</div>
                                </td>

                                <!-- Exam Date -->
                                <td class="py-3.5 px-4 font-mono font-bold text-amber-300">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-calendar-day text-amber-400 text-xs"></i>
                                        <span>{{ $group['exam_date'] }}</span>
                                    </div>
                                </td>

                                <!-- Test Center Name -->
                                <td class="py-3.5 px-4 text-slate-300 font-medium max-w-[260px] truncate" title="{{ $group['center_name'] }}">
                                    <div class="truncate flex items-center gap-1.5">
                                        <i class="fa-solid fa-building-columns text-slate-500 text-xs shrink-0"></i>
                                        <span class="truncate">{{ $group['center_name'] }}</span>
                                    </div>
                                </td>

                                <!-- Total Held Seats Badge -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-purple-500/20 text-purple-300 font-bold font-mono text-xs border border-purple-500/30">
                                        <i class="fa-solid fa-chair text-purple-400 text-[11px]"></i>
                                        <span>{{ $group['total_slots'] }} Held Seat(s)</span>
                                    </span>
                                </td>

                                <!-- Expiry & Status -->
                                <td class="py-3.5 px-4">
                                    @if($group['active_count'] > 0)
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold text-[10px] border border-emerald-500/30">Active</span>
                                            <span class="font-mono text-xs text-amber-300 font-bold group-timer" data-sec="{{ $group['min_seconds_remaining'] }}">
                                                Checking...
                                            </span>
                                        </div>
                                    @else
                                        <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-400 text-[10px] font-bold">In-active / Released</span>
                                    @endif
                                </td>

                                <!-- View Button -->
                                <td class="py-3.5 px-4 text-right">
                                    <button type="button" onclick="openGroupModal('{{ $group['group_key'] }}')" 
                                            class="px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs inline-flex items-center gap-1.5 shadow-lg shadow-purple-600/20 transition-all cursor-pointer">
                                        <i class="fa-solid fa-eye"></i>
                                        <span>View Slots</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-12 text-center bg-slate-950/60 border border-slate-800/80 rounded-2xl space-y-3">
                <div class="w-12 h-12 rounded-full bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto text-xl">
                    <i class="fa-solid fa-shield-cat"></i>
                </div>
                <div class="text-sm font-bold text-white">No Grouped Held Slots Found</div>
                <p class="text-xs text-slate-400 max-w-md mx-auto">
                    There are currently no active held slots in the vault matching your criteria. Use the <b class="text-amber-300">Hold Slot</b> page to scan and auto-hold seats.
                </p>
                <a href="{{ route('admin.slots.hold') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition-all">
                    <i class="fa-solid fa-lock"></i> Go to Hold Slot Page
                </a>
            </div>
        @endif
    </div>

</div>

<!-- View Group Slots Modal -->
<div id="groupDetailModal" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[99999] flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl animate-in fade-in zoom-in-95 duration-150">
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-purple-500/20 text-purple-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div>
                    <h3 id="modalGroupTitle" class="text-base font-bold text-white">Group Held Slots Details</h3>
                    <div id="modalGroupSubtitle" class="text-xs text-purple-300 font-medium">City • Category • Date</div>
                </div>
            </div>
            <button type="button" onclick="closeGroupModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body (Scrollable Table of Individual Holds) -->
        <div class="p-5 flex-1 overflow-y-auto space-y-4 custom-scrollbar">
            <div id="modalGroupInfoBox" class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div><span class="text-slate-500">Center Name:</span> <b id="modalCenterName" class="text-white">---</b></div>
                <div><span class="text-slate-500">Exam Date:</span> <b id="modalExamDate" class="text-amber-300 font-mono">---</b></div>
                <div><span class="text-slate-500">Held Count:</span> <b id="modalSeatCount" class="text-purple-300 font-mono">0 Seats</b></div>
            </div>

            <div class="overflow-x-auto border border-slate-800 rounded-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-950/90 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                            <th class="py-3 px-3.5"># / Seat ID</th>
                            <th class="py-3 px-3.5">Candidate Pool Account Email</th>
                            <th class="py-3 px-3.5">Mother Hash</th>
                            <th class="py-3 px-3.5">Expires In</th>
                            <th class="py-3 px-3.5">Status</th>
                            <th class="py-3 px-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="modalHoldRows" class="divide-y divide-slate-800/80 text-xs">
                        <!-- Dynamic Rows -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/60 rounded-b-3xl flex items-center justify-between">
            <button type="button" id="modalReleaseGroupBtn" class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 font-bold text-xs border border-rose-500/30 transition-all cursor-pointer">
                <i class="fa-solid fa-trash-can mr-1"></i> Release Entire Group
            </button>
            <button type="button" onclick="closeGroupModal()" class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition-all">
                Close
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const groupedVaultData = @json($groupedVaultList);
    let activeModalGroupKey = null;

    // Timer countdown for active groups table
    function updateTimers() {
        document.querySelectorAll('.group-timer').forEach(el => {
            let sec = parseInt(el.getAttribute('data-sec') || '0', 10);
            if (sec > 0) {
                sec--;
                el.setAttribute('data-sec', sec);
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                el.innerText = `${m}m ${s < 10 ? '0' : ''}${s}s`;
            } else {
                el.innerText = '0m 00s (Expired)';
                el.className = 'font-mono text-xs text-rose-400 font-bold';
            }
        });
    }
    setInterval(updateTimers, 1000);
    updateTimers();

    const pendingRenewals = new Set();
    async function triggerAutoRenewForMotherHash(motherHash, currentTempSeatId) {
        if (pendingRenewals.has(motherHash)) return;
        pendingRenewals.add(motherHash);

        try {
            const res = await fetch(`{{ route('admin.slots.renew_lock') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    mother_hash: motherHash,
                    current_temp_seat_id: currentTempSeatId
                })
            });
            const data = await res.json();
            if (data && data.success) {
                window.location.reload();
            }
        } catch(e) {
            console.error('Auto-renew error:', e);
        } finally {
            pendingRenewals.delete(motherHash);
        }
    }

    function checkExpiringHoldsAndRenew() {
        if (!groupedVaultData) return;
        groupedVaultData.forEach(group => {
            if (group.holds) {
                group.holds.forEach(h => {
                    if (h.seconds_remaining <= 300) {
                        triggerAutoRenewForMotherHash(h.mother_hash, h.temp_seat_id);
                    }
                });
            }
        });
    }

    setInterval(checkExpiringHoldsAndRenew, 10000);

    function openGroupModal(groupKey) {
        const group = groupedVaultData.find(g => g.group_key === groupKey);
        if (!group) return;

        activeModalGroupKey = groupKey;
        document.getElementById('modalGroupTitle').innerText = `${group.city} • ${group.category_name}`;
        document.getElementById('modalGroupSubtitle').innerText = `Date: ${group.exam_date} | Center: ${group.center_name}`;
        document.getElementById('modalCenterName').innerText = group.center_name;
        document.getElementById('modalExamDate').innerText = group.exam_date;
        document.getElementById('modalSeatCount').innerText = `${group.total_slots} Held Seat(s)`;

        const rowsContainer = document.getElementById('modalHoldRows');
        rowsContainer.innerHTML = '';

        group.holds.forEach((h, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-800/50 transition-colors';
            
            const shortHash = h.mother_hash ? `${h.mother_hash.substring(0, 16)}...` : '---';
            const m = Math.floor(h.seconds_remaining / 60);
            const s = h.seconds_remaining % 60;
            const timerStr = h.seconds_remaining > 0 ? `${m}m ${s < 10 ? '0' : ''}${s}s` : 'Expired';
            
            let statusBadge = '<span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold text-[10px]">Active</span>';
            if (h.status === 'released') statusBadge = '<span class="px-2 py-0.5 rounded bg-slate-800 text-slate-400 text-[10px]">Released</span>';
            else if (h.seconds_remaining <= 0) statusBadge = '<span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 text-[10px]">Expired</span>';

            tr.innerHTML = `
                <td class="py-2.5 px-3.5 font-mono font-bold text-purple-300">#${idx + 1} (${h.temp_seat_id || 'Seat'})</td>
                <td class="py-2.5 px-3.5 font-mono text-slate-300">${h.held_with_email || 'Pool Candidate'}</td>
                <td class="py-2.5 px-3.5 font-mono text-slate-400" title="${h.mother_hash}">${shortHash}</td>
                <td class="py-2.5 px-3.5 font-mono text-amber-300 font-bold">${timerStr}</td>
                <td class="py-2.5 px-3.5">${statusBadge}</td>
                <td class="py-2.5 px-3.5 text-right">
                    <a href="{{ route('admin.slots.book') }}?hash=${encodeURIComponent(h.mother_hash)}&city=${encodeURIComponent(h.city)}&category_id=${h.category_id}" 
                       class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] inline-flex items-center gap-1">
                        <i class="fa-solid fa-calendar-check"></i> Book
                    </a>
                </td>
            `;
            rowsContainer.appendChild(tr);
        });

        // Configure release group button
        const relBtn = document.getElementById('modalReleaseGroupBtn');
        relBtn.onclick = () => releaseGroupSlots(group.holds.map(h => h.id));

        document.getElementById('groupDetailModal').classList.remove('hidden');
    }

    function closeGroupModal() {
        document.getElementById('groupDetailModal').classList.add('hidden');
    }

    async function releaseGroupSlots(holdIds) {
        if (!confirm('Are you sure you want to release all held slots in this group?')) return;
        try {
            const res = await fetch(`{{ route('admin.slots.held_vault.release_group') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ hold_ids: holdIds })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Failed to release slots.');
            }
        } catch(e) {
            alert('Error releasing slots: ' + e.message);
        }
    }

    let terminalLogsHistory = [];

    document.addEventListener('DOMContentLoaded', () => {
        restoreTerminalLogsFromHistory();
        startLiveTerminalStreaming();
    });

    function restoreTerminalLogsFromHistory() {
        try {
            const history = localStorage.getItem('terminal_log_history');
            if (history) {
                terminalLogsHistory = JSON.parse(history);
                if (Array.isArray(terminalLogsHistory) && terminalLogsHistory.length > 0) {
                    const screen = document.getElementById('terminalLogScreen');
                    if (screen) {
                        screen.innerHTML = '';
                        terminalLogsHistory.forEach(item => {
                            appendLogElementToScreen(item.msg, item.type, item.time, false);
                        });
                    }
                }
            }
        } catch(e) {}
    }

    async function startLiveTerminalStreaming() {
        const rawData = localStorage.getItem('pending_bulk_hold_slots');
        if (!rawData) return;

        let payload;
        try {
            payload = JSON.parse(rawData);
            localStorage.removeItem('pending_bulk_hold_slots');
        } catch(e) {
            return;
        }

        if (!payload || !payload.slots || payload.slots.length === 0) return;

        const terminalBox = document.getElementById('liveTerminalContainer');
        const logScreen = document.getElementById('terminalLogScreen');
        const statusBadge = document.getElementById('terminalStatusBadge');
        const progressContainer = document.getElementById('terminalProgressContainer');

        if (terminalBox) terminalBox.classList.remove('hidden');
        if (progressContainer) progressContainer.classList.remove('hidden');

        statusBadge.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-purple-400"></i> LOCKING ${payload.slots.length} SEATS...`;
        statusBadge.className = 'px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 text-[10px] font-bold border border-purple-500/30 flex items-center gap-1';

        appendTerminalLog(`🚀 [Engine] Bulk Hold Initiated: ${payload.label || 'Locking requested seats'}...`);

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        try {
            const response = await fetch("{{ route('admin.slots.hold.bulk_hold_stream') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/x-ndjson'
                },
                body: JSON.stringify({
                    slots: payload.slots,
                    duration_minutes: 0
                })
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop();

                for (const line of lines) {
                    if (!line.trim()) continue;
                    try {
                        const chunk = JSON.parse(line);
                        handleTerminalChunk(chunk);
                    } catch(e) {}
                }
            }

            if (buffer.trim()) {
                try {
                    const chunk = JSON.parse(buffer);
                    handleTerminalChunk(chunk);
                } catch(e) {}
            }

        } catch(err) {
            appendTerminalLog(`❌ [Engine Error] Streaming failed: ${err.message}`, 'error');
        }
    }

    function handleTerminalChunk(chunk) {
        const statusBadge = document.getElementById('terminalStatusBadge');
        const progressBar = document.getElementById('terminalProgressBar');
        const progressPercent = document.getElementById('terminalProgressPercent');

        if (chunk.type === 'log') {
            if (chunk.percent !== undefined && progressBar) {
                progressBar.style.width = `${chunk.percent}%`;
                if (progressPercent) progressPercent.innerText = `${chunk.percent}%`;
            }
            appendTerminalLog(chunk.message, chunk.status);

            // Real-time table seat increment
            if (chunk.status === 'success') {
                incrementVaultTableSeats(chunk.center_name, chunk.city);
            }

        } else if (chunk.type === 'start') {
            appendTerminalLog(chunk.message, 'info');
        } else if (chunk.type === 'complete') {
            if (progressBar) progressBar.style.width = `100%`;
            if (progressPercent) progressPercent.innerText = `100%`;
            statusBadge.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-400"></i> COMPLETED 🎉`;
            statusBadge.className = 'px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold border border-emerald-500/30 flex items-center gap-1';
            appendTerminalLog(chunk.message, 'complete');

            setTimeout(() => {
                window.location.reload();
            }, 1200);
        }
    }

    // Increment Active Holds & Table Seats live in real-time
    function incrementVaultTableSeats(centerName, city) {
        const topEl = document.getElementById('topActiveHoldsCount');
        if (topEl) {
            const current = parseInt(topEl.innerText || '0', 10);
            topEl.innerText = current + 1;
        }

        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(tr => {
            const text = tr.innerText || '';
            if (centerName && (text.includes(centerName) || text.toLowerCase().includes(centerName.toLowerCase()))) {
                const badges = tr.querySelectorAll('span');
                badges.forEach(b => {
                    if (b.innerText.includes('Held Seat(s)')) {
                        const match = b.innerText.match(/\d+/);
                        if (match) {
                            const count = parseInt(match[0], 10) + 1;
                            b.innerHTML = `<i class="fa-solid fa-chair text-purple-400 text-[11px] mr-1"></i> ${count} Held Seat(s)`;
                        }
                    }
                });
            }
        });
    }

    function appendTerminalLog(msg, type = 'info') {
        const time = new Date().toLocaleTimeString();
        appendLogElementToScreen(msg, type, time, true);
    }

    function appendLogElementToScreen(msg, type, time, saveToHistory = true) {
        const screen = document.getElementById('terminalLogScreen');
        if (!screen) return;

        const div = document.createElement('div');
        let colorClass = 'text-slate-300';
        if (type === 'success' || msg.includes('🟢')) colorClass = 'text-emerald-400 font-bold';
        else if (type === 'error' || msg.includes('❌') || msg.includes('⚠️')) colorClass = 'text-rose-400';
        else if (type === 'complete' || msg.includes('🎉')) colorClass = 'text-purple-300 font-bold';

        div.className = `leading-relaxed py-0.5 flex items-start gap-2 ${colorClass}`;
        div.innerHTML = `<span class="text-slate-500 shrink-0">[${time}]</span> <span>${msg}</span>`;

        screen.appendChild(div);
        screen.scrollTop = screen.scrollHeight;

        if (saveToHistory) {
            terminalLogsHistory.push({ msg: msg, type: type, time: time });
            if (terminalLogsHistory.length > 200) terminalLogsHistory.shift();
            try {
                localStorage.setItem('terminal_log_history', JSON.stringify(terminalLogsHistory));
            } catch(e) {}
        }
    }

    function clearTerminalLogs() {
        const screen = document.getElementById('terminalLogScreen');
        if (screen) screen.innerHTML = '<div class="text-slate-500 italic">[Console Cleared] Waiting for next process...</div>';
        terminalLogsHistory = [];
        try { localStorage.removeItem('terminal_log_history'); } catch(e) {}
    }

    function toggleTerminalExpand() {
        const screen = document.getElementById('terminalLogScreen');
        const icon = document.getElementById('toggleTerminalIcon');
        if (screen.classList.contains('hidden')) {
            screen.classList.remove('hidden');
            icon.className = 'fa-solid fa-chevron-up text-[10px]';
        } else {
            screen.classList.add('hidden');
            icon.className = 'fa-solid fa-chevron-down text-[10px]';
        }
    }
</script>
@endpush
@endsection
