@extends('layouts.app')

@section('title', 'Book Slot - Taqamul Admin')

@section('content')
<div class="space-y-6">

    <!-- Booking Form Grid Layout (Main Area: Left 2/3, Hash List: Right 1/3) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left (Main Column 2/3): Selected Hash & Execute Booking Panel -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Selected Hash Confirmation Card (Ultra-Compact Sleek Bar) -->
            <div id="selectedHashBanner" class="p-3.5 px-4 rounded-2xl bg-emerald-950/25 border border-emerald-500/35 shadow-md backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/20">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-black text-white truncate max-w-[280px]" id="selectedCenterValue">-</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/25 shrink-0" id="selectedCityValue">-</span>
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-sky-500/15 text-sky-300 border border-sky-500/25 shrink-0" id="selectedDateValue">-</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] text-slate-500 font-mono font-bold uppercase">HASH:</span>
                                <span class="text-[11px] text-slate-400 font-mono truncate max-w-[320px] privacy-hash-mask" id="selectedHashValue">-</span>
                            </div>
                        </div>
                    </div>

                    <button type="button" onclick="clearHashSelection()" class="text-xs text-rose-400 hover:text-rose-300 font-bold cursor-pointer flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 transition-all shrink-0">
                        <i class="fa-solid fa-rotate-left text-[11px]"></i>
                        <span>Change</span>
                    </button>
                </div>
            </div>

            <!-- Execute Booking Main Card (Placed Right Under Selected Hash) -->
            <div class="bg-slate-900/80 border border-slate-800 p-6 rounded-3xl backdrop-blur-xl shadow-xl space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm font-black shadow-lg shadow-emerald-500/10">
                            <i class="fa-solid fa-rocket"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Execute Live Booking</h3>
                            <p class="text-[11px] text-slate-400">Lock Seat → Create Reservation → Launch 3DS Payment</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 font-mono text-xs font-bold border border-emerald-500/20 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle text-[7px] animate-pulse"></i>
                        <span>Auto-Pool Enabled</span>
                    </span>
                </div>

                <!-- Candidate Info & Selection Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- Candidate Details Card -->
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-3 flex flex-col justify-between">
                        <div class="flex items-center justify-between border-b border-slate-900 pb-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-check text-emerald-400"></i> Target Candidate
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 font-bold border border-emerald-500/20">
                                Verified
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="overflow-hidden space-y-0.5">
                                <p class="text-[10px] uppercase font-bold text-slate-500">Candidate Name:</p>
                                <p id="activeCandidateName" class="text-sm font-extrabold text-white truncate max-w-[190px]">
                                    {{ !empty($bookingCandidates) ? $bookingCandidates[0]['name'] : 'Select Candidate' }}
                                </p>
                            </div>
                            <div class="text-right shrink-0 space-y-0.5">
                                <p class="text-[10px] uppercase font-bold text-slate-500">Passport Number:</p>
                                <span id="activeCandidatePassport" class="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/30 inline-block">
                                    {{ !empty($bookingCandidates) ? $bookingCandidates[0]['passport_number'] : 'N/A' }}
                                </span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-900/80 flex justify-between items-center text-[11px]">
                            <span class="text-slate-500">Active Login Email:</span>
                            <span id="activeCandidateEmailDisplay" class="text-sky-300 font-mono font-bold truncate max-w-[180px]">{{ !empty($bookingCandidates) ? $bookingCandidates[0]['email'] : 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Candidate Selector & Payment Card -->
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-3 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-[10px] font-bold uppercase text-slate-400">
                                    <i class="fa-solid fa-address-card text-emerald-400 mr-1"></i> Select Candidate:
                                </label>
                                <span id="candidateSessionBadge" class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md bg-slate-800 text-slate-400 flex items-center gap-1">
                                    <i class="fa-solid fa-circle-notch fa-spin text-[8px] text-sky-400"></i>
                                    <span>Checking Session...</span>
                                </span>
                            </div>
                            <select id="candidateSelect" onchange="onCandidateSelected()" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-bold focus:border-emerald-500 outline-none truncate">
                                @forelse($bookingCandidates as $idx => $acc)
                                    <option value="{{ $acc['email'] }}" data-token="{{ $acc['token'] ?? '' }}" data-email="{{ $acc['email'] }}" data-name="{{ $acc['name'] }}" data-passport="{{ $acc['passport_number'] }}" {{ $idx === 0 ? 'selected' : '' }}>
                                        #{{ $idx + 1 }}: {{ $acc['name'] }} — [Passport: {{ $acc['passport_number'] }}]
                                    </option>
                                @empty
                                    <option value="">No registered candidates found</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex-1 p-2 rounded-xl bg-slate-900/80 border border-slate-800 text-[11px] flex justify-between items-center">
                                <span class="text-slate-400 font-medium">Card:</span>
                                <span class="text-amber-300 font-bold flex items-center gap-1 font-mono text-[11px]">
                                    <i class="fa-solid fa-credit-card text-emerald-400"></i>
                                    <span>{{ $defaultCard ? $defaultCard->bank_name . ' (' . $defaultCard->masked_number . ')' : 'IFIC Bank' }}</span>
                                </span>
                            </div>
                            <button type="button" id="preWarmBtn" onclick="manualPreWarmSession()" class="px-3 py-2 rounded-xl bg-sky-500/10 hover:bg-sky-500/20 text-sky-300 border border-sky-500/30 text-[11px] font-bold flex items-center gap-1.5 transition-all cursor-pointer shrink-0">
                                <i class="fa-solid fa-bolt text-amber-400"></i>
                                <span>Pre-Warm Session</span>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Live Summary Stats Row -->
                <div class="grid grid-cols-3 gap-3 text-xs">
                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 text-center">
                        <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">Registered Candidates</p>
                        <p class="text-emerald-400 font-extrabold text-sm flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-user-check text-[10px]"></i>
                            <span>{{ count($bookingCandidates) }} Candidates</span>
                        </p>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 text-center">
                        <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">Total Vault Hashes</p>
                        <p class="text-sky-400 font-extrabold text-sm">{{ $vaultHashes->count() }} Available</p>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 text-center">
                        <p class="text-[10px] text-slate-500 uppercase font-bold mb-1">Official Exam Fee</p>
                        <p class="text-emerald-400 font-mono font-extrabold text-sm">SAR 50.00</p>
                    </div>
                </div>

                <!-- Big Action Button -->
                <button type="button" onclick="executeBooking()" id="bookBtn"
                        class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-sky-600 hover:from-emerald-500 hover:to-sky-500 text-white font-black text-sm shadow-xl shadow-emerald-600/30 flex items-center justify-center gap-3 transition-all active:scale-98 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-rocket text-base" id="bookBtnIcon"></i>
                    <span id="bookBtnText" class="tracking-wide">Book The Slot Now</span>
                </button>

                <!-- Live Step Progress -->
                <div id="bookingProgress" class="hidden space-y-2">
                    <div id="bStep1" class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-500 transition-all">
                        <i class="fa-regular fa-circle text-slate-600 text-xs"></i>
                        <span>Step 1: Locking Seat (temporary_seats)</span>
                    </div>
                    <div id="bStep2" class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-500 transition-all">
                        <i class="fa-regular fa-circle text-slate-600 text-xs"></i>
                        <span>Step 2: Creating Reservation</span>
                    </div>
                    <div id="bStep3" class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-500 transition-all">
                        <i class="fa-regular fa-circle text-slate-600 text-xs"></i>
                        <span>Step 3: Initiating Payment Gateway</span>
                    </div>
                </div>

                <!-- Result Box -->
                <div id="bookingResultBox" class="hidden p-4 rounded-2xl border space-y-3">
                    <div class="flex items-center gap-2" id="bookingResultHeader">
                        <!-- Injected -->
                    </div>
                    <div id="bookingResultBody" class="space-y-2 text-xs">
                        <!-- Injected -->
                    </div>
                </div>

            </div>

            <!-- Booking Configuration Accordion (Minimized / Shutter Down by Default) -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl backdrop-blur-xl shadow-md overflow-hidden transition-all">
                <button type="button" onclick="toggleBookingConfig()" class="w-full p-4 px-6 flex items-center justify-between gap-3 text-left hover:bg-slate-800/40 transition-all cursor-pointer">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-sm font-bold shrink-0">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-white flex items-center gap-2">
                                <span>Advanced Booking Configuration</span>
                                <span class="text-[10px] font-normal px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">Occupation ID: 2061 • Bangla</span>
                            </h3>
                            <p class="text-[10px] text-slate-400">Click to customize occupation ID, language code, or manual token override</p>
                        </div>
                    </div>
                    <div class="w-7 h-7 rounded-xl bg-slate-800/80 flex items-center justify-center text-slate-400 text-xs transition-transform duration-300" id="configChevron">
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </button>

                <!-- Collapsible Body (Hidden by default) -->
                <div id="bookingConfigBody" class="hidden p-6 pt-2 border-t border-slate-800/60 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Occupation ID -->
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-bold text-slate-300">
                                <i class="fa-solid fa-briefcase text-sky-400 mr-1"></i> Occupation ID
                            </label>
                            <input type="number" id="bookOccupationId" value="2061"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:border-sky-500 outline-none">
                            <p class="text-[10px] text-slate-500">Default: 2061 (Load and Unload Worker)</p>
                        </div>

                        <!-- Language Code -->
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-bold text-slate-300">
                                <i class="fa-solid fa-language text-emerald-400 mr-1"></i> Exam Language Code
                            </label>
                            <select id="bookLanguageCode"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:border-emerald-500 outline-none">
                                <option value="LOABB" selected>Bangla (LOABB)</option>
                                <option value="LOAEN">English (LOAEN)</option>
                                <option value="LOAAR">Arabic (LOAAR)</option>
                                <option value="LOANN">Nepali (LOANN)</option>
                                <option value="LOAUR">Urdu (LOAUR)</option>
                                <option value="LOAHI">Hindi (LOAHI)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Optional Manual Token Override -->
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-slate-300">
                            <i class="fa-solid fa-key text-indigo-400 mr-1"></i> Manual Bearer Token (Optional)
                        </label>
                        <input type="text" id="bookAuthToken" placeholder="Leave empty to use candidate pool token..."
                               class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-mono focus:border-indigo-500 outline-none">
                    </div>
                </div>
            </div>

        </div>

        <!-- Right (Side Column 1/3): Select Mother Hash & Center -->
        <div class="space-y-6">

            <!-- Hashes Selector Card -->
            <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-3xl backdrop-blur-xl shadow-xl space-y-4 sticky top-24">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center text-sm font-black">
                            <i class="fa-solid fa-fingerprint"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-white">Center Hashes</h3>
                            <p class="text-[11px] text-slate-400">Click to select designated center</p>
                        </div>
                    </div>
                    <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-xl bg-sky-500/10 text-sky-400 border border-sky-500/20">
                        {{ $vaultHashes->count() }} Available
                    </span>
                </div>

                <!-- Hash Search & Selection -->
                <div class="space-y-3">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="hashSearchInput" placeholder="Search center, city, or date..." autocomplete="off"
                               oninput="filterBookingHashes(this.value)"
                               class="w-full pl-10 pr-3 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white placeholder-slate-500 focus:border-sky-500 outline-none transition-all">
                    </div>

                    <div id="hashSelectionList" class="max-h-[580px] overflow-y-auto space-y-2.5 custom-scrollbar pr-1">
                        <!-- Dynamically rendered hash items -->
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

@push('scripts')
<script>
    const allVaultHashes = @json($vaultHashes);
    let selectedBookingHash = null;
    let selectedBookingData = null;

    // Pre-select from query string
    const preSelectedHash = @json($selectedHash);
    const preSelectedCenter = @json($selectedCenter);

    function toggleBookingConfig() {
        const body = document.getElementById('bookingConfigBody');
        const chevron = document.getElementById('configChevron');
        const isHidden = body.classList.contains('hidden');

        if (isHidden) {
            body.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            body.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    function onCandidateSelected() {
        const select = document.getElementById('candidateSelect');
        const opt = select.options[select.selectedIndex];
        const email = opt.getAttribute('data-email') || select.value;
        const name = opt.getAttribute('data-name') || 'Candidate Account';
        const passport = opt.getAttribute('data-passport') || 'N/A';
        const token = opt.getAttribute('data-token') || '';

        document.getElementById('activeCandidateName').innerText = name;
        document.getElementById('activeCandidatePassport').innerText = passport;
        document.getElementById('activeCandidateEmailDisplay').innerText = email;
        document.getElementById('bookAuthToken').value = '';

        const badge = document.getElementById('candidateSessionBadge');
        if (badge) {
            badge.innerHTML = '<span class="text-sky-400 font-bold flex items-center gap-1"><i class="fa-solid fa-robot"></i> Fresh Auto-Login on Book</span>';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderHashList(allVaultHashes);

        const select = document.getElementById('candidateSelect');
        if (select && select.value) {
            onCandidateSelected();
        }

        if (preSelectedHash) {
            let item = allVaultHashes.find(h => h.mother_hash === preSelectedHash);
            if (!item) {
                item = {
                    mother_hash: preSelectedHash,
                    center_name: preSelectedCenter || 'Bogura Technical Training Centre',
                    city: 'Rajshahi',
                    exam_date: '2026-08-24'
                };
            }
            selectHash(item);
        } else if (allVaultHashes && allVaultHashes.length > 0) {
            selectHash(allVaultHashes[0]);
        }
    });

    function renderHashList(items) {
        const list = document.getElementById('hashSelectionList');
        list.innerHTML = '';

        if (items.length === 0) {
            list.innerHTML = `
                <div class="text-center py-8 space-y-2">
                    <i class="fa-solid fa-inbox text-slate-600 text-2xl"></i>
                    <p class="text-xs text-slate-500">No hashes found. Go to Slot Checker to discover hashes first.</p>
                </div>`;
            return;
        }

        items.forEach(item => {
            const isSelected = selectedBookingHash === item.mother_hash;
            const div = document.createElement('div');
            div.className = `p-3.5 rounded-2xl border cursor-pointer transition-all ${
                isSelected 
                    ? 'bg-emerald-950/40 border-emerald-500/40 ring-1 ring-emerald-500/20' 
                    : 'bg-slate-950/60 border-slate-800 hover:border-slate-600 hover:bg-slate-800/40'
            }`;
            div.onclick = () => selectHash(item);

            div.innerHTML = `
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 overflow-hidden space-y-1.5">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-fingerprint text-sky-400 text-xs shrink-0"></i>
                            <span class="font-mono text-[11px] text-sky-300 truncate privacy-hash-mask" title="${item.mother_hash}">${item.mother_hash}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-[11px]">
                            <span class="font-bold text-white">${item.center_name || 'Unknown Center'}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400">
                            <span class="flex items-center gap-1">
                                <i class="fa-solid fa-location-dot text-rose-400 text-[10px]"></i>
                                ${item.city}
                            </span>
                            <span class="flex items-center gap-1">
                                <i class="fa-solid fa-calendar text-amber-400 text-[10px]"></i>
                                ${item.exam_date ? item.exam_date.split('T')[0] : '-'}
                            </span>
                            <span class="text-slate-500">${item.category_name || 'Trade'}</span>
                        </div>
                    </div>
                    <div class="shrink-0">
                        ${isSelected 
                            ? '<span class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 inline-flex items-center justify-center text-xs"><i class="fa-solid fa-check"></i></span>'
                            : '<span class="w-7 h-7 rounded-lg bg-slate-800 text-slate-500 inline-flex items-center justify-center text-xs"><i class="fa-regular fa-circle"></i></span>'
                        }
                    </div>
                </div>
            `;
            list.appendChild(div);
        });
    }

    function filterBookingHashes(query) {
        const q = query.trim().toLowerCase();
        if (!q) {
            renderHashList(allVaultHashes);
            return;
        }
        const filtered = allVaultHashes.filter(h =>
            (h.mother_hash && h.mother_hash.toLowerCase().includes(q)) ||
            (h.center_name && h.center_name.toLowerCase().includes(q)) ||
            (h.city && h.city.toLowerCase().includes(q)) ||
            (h.category_name && h.category_name.toLowerCase().includes(q))
        );
        renderHashList(filtered);
    }

    function selectHash(item) {
        selectedBookingHash = item.mother_hash;
        selectedBookingData = item;

        // Update selection banner
        document.getElementById('selectedHashBanner').classList.remove('hidden');
        document.getElementById('selectedHashValue').innerText = item.mother_hash;
        document.getElementById('selectedCenterValue').innerText = item.center_name || 'Unknown';
        document.getElementById('selectedCityValue').innerText = item.city || '-';
        document.getElementById('selectedDateValue').innerText = item.exam_date ? item.exam_date.split('T')[0] : '-';

        // Re-render list to show selection state
        renderHashList(allVaultHashes);
    }

    function clearHashSelection() {
        selectedBookingHash = null;
        selectedBookingData = null;
        document.getElementById('selectedHashBanner').classList.add('hidden');
        renderHashList(allVaultHashes);
    }

    function setStepActive(num) {
        const s = document.getElementById('bStep' + num);
        s.className = 'flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-800/60 border border-sky-500/30 text-xs text-sky-300 font-medium transition-all';
        s.querySelector('i').className = 'fa-solid fa-circle-notch fa-spin text-sky-400 text-xs';
    }

    function setStepDone(num) {
        const s = document.getElementById('bStep' + num);
        s.className = 'flex items-center gap-2.5 p-2.5 rounded-xl bg-emerald-950/30 border border-emerald-500/30 text-xs text-emerald-300 transition-all';
        s.querySelector('i').className = 'fa-solid fa-check text-emerald-400 text-xs';
    }

    function setStepError(num) {
        const s = document.getElementById('bStep' + num);
        s.className = 'flex items-center gap-2.5 p-2.5 rounded-xl bg-rose-950/30 border border-rose-500/30 text-xs text-rose-300 transition-all';
        s.querySelector('i').className = 'fa-solid fa-xmark text-rose-400 text-xs';
    }

    async function executeBooking() {
        if (!selectedBookingHash) {
            Swal.fire({
                icon: 'warning',
                title: 'No Hash Selected!',
                text: 'Please select a Mother Hash from the list before booking.',
                background: '#1e293b',
                color: '#fff'
            });
            return;
        }

        const occId = parseInt(document.getElementById('bookOccupationId').value) || 2061;
        const langCode = document.getElementById('bookLanguageCode').value || 'LOABB';
        const authToken = document.getElementById('bookAuthToken') ? document.getElementById('bookAuthToken').value.trim() : '';
        const candidateSelect = document.getElementById('candidateSelect');
        const candidateEmail = candidateSelect ? candidateSelect.value : '';
        const center = (selectedBookingData && selectedBookingData.center_name) ? selectedBookingData.center_name : 'Selected Test Center';

        const sessionUrl = `{{ route('admin.slots.portal_session') }}?hash=${encodeURIComponent(selectedBookingHash)}&center=${encodeURIComponent(center)}&occupation_id=${occId}&language_code=${langCode}&candidate_email=${encodeURIComponent(candidateEmail)}&auth_token=${encodeURIComponent(authToken)}`;

        // Open live controlled booking tab in this browser immediately
        window.open(sessionUrl, '_blank');
    }

    async function cancelActiveBooking(reservationId, tempSeatId, btn) {
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Cancelling Reservation...`;

        try {
            const authToken = document.getElementById('bookAuthToken').value.trim();
            const res = await fetch("{{ route('admin.slots.release_lock') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    reservation_id: reservationId,
                    temp_seat_id: tempSeatId,
                    auth_token: authToken
                })
            });

            const data = await res.json();

            showResult('cancelled', 'Booking Cancelled & Seat Released', `
                <div class="space-y-2 text-xs text-slate-300">
                    <p class="text-amber-300 font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-check-circle"></i>
                        The temporary reservation (#${reservationId}) has been successfully cancelled on Taqamul server.
                    </p>
                    <p class="text-slate-400">The candidate profile and seat hold are now completely free and ready for new bookings.</p>
                </div>
            `);

            // Reset progress steps
            for (let i = 1; i <= 3; i++) {
                const s = document.getElementById('bStep' + i);
                s.className = 'flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-500 transition-all';
                s.querySelector('i').className = 'fa-regular fa-circle text-slate-600 text-xs';
            }

            Swal.fire({
                icon: 'success',
                title: 'Booking Cancelled!',
                text: 'The reservation and temporary seat lock have been released successfully.',
                timer: 2500,
                showConfirmButton: false,
                background: '#1e293b',
                color: '#fff'
            });

        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            Swal.fire({
                icon: 'error',
                title: 'Cancellation Error',
                text: err.message,
                background: '#1e293b',
                color: '#fff'
            });
        }
    }

    /**
     * Launch authenticated popup Chrome window with injected session
     */
    async function openPopupPaymentChrome(paymentUrl, btn) {
        const origHtml = btn ? btn.innerHTML : null;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Launching Chrome...`;
        }

        try {
            const authToken = document.getElementById('bookAuthToken').value.trim();
            const res = await fetch("{{ route('admin.slots.open_payment_browser') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    auth_token: authToken,
                    payment_url: paymentUrl
                })
            });

            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Authenticated Chrome Popup Opened!',
                    showConfirmButton: false,
                    timer: 3000,
                    background: '#1e293b',
                    color: '#fff'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Could Not Open Browser',
                    text: data.message || 'Error launching browser',
                    background: '#1e293b',
                    color: '#fff'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message,
                background: '#1e293b',
                color: '#fff'
            });
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    }

    /**
     * Force release 20-minute server locks when labor_id is already taken
     */
    async function forceReleaseTheLock(btn) {
        const origHtml = btn ? btn.innerHTML : null;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Releasing 20-min lock on server...`;
        }

        try {
            const authToken = document.getElementById('bookAuthToken').value.trim();
            const res = await fetch("{{ route('admin.slots.release_all_locks') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    auth_token: authToken
                })
            });

            const data = await res.json();

            // Reset step error styling
            for (let i = 1; i <= 3; i++) {
                const s = document.getElementById('bStep' + i);
                s.className = 'flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-500 transition-all';
                s.querySelector('i').className = 'fa-regular fa-circle text-slate-600 text-xs';
            }

            showResult('cancelled', '20-Minute Lock Released Successfully!', `
                <div class="space-y-3 text-xs text-slate-300">
                    <p class="text-emerald-400 font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-check-circle text-sm"></i>
                        All prior temporary seat locks and holds have been released on the server.
                    </p>
                    <p class="text-slate-400">The candidate profile is now completely free. You can proceed with a fresh booking immediately.</p>
                    <button type="button" onclick="executeBooking()" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-sky-600 hover:from-emerald-500 hover:to-sky-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30 transition-all cursor-pointer active:scale-95">
                        <i class="fa-solid fa-rocket"></i>
                        <span>Retry Booking Now</span>
                    </button>
                </div>
            `);

            Swal.fire({
                icon: 'success',
                title: 'Lock Released!',
                text: '20-minute lock has been cancelled on server. You can now retry booking.',
                timer: 2500,
                showConfirmButton: false,
                background: '#1e293b',
                color: '#fff'
            });

        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message,
                background: '#1e293b',
                color: '#fff'
            });
        }
    }
</script>
@endpush
@endsection
