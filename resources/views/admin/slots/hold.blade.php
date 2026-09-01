@extends('layouts.app')

@section('title', 'Center-Wise Slot Hold & Continuous Auto-Renew')

@section('content')
@php
    $formattedOccupations = [];
    $seen = [];
    if (!empty($occupations)) {
        foreach ($occupations as $occ) {
            $occArr = is_object($occ) ? (array) $occ : $occ;
            $cat = $occArr['category'] ?? [];
            if (is_object($cat)) $cat = (array) $cat;

            $catId = (int)($cat['id'] ?? ($occArr['category_id'] ?? ($occArr['id'] ?? 159)));
            $occName = $occArr['name'] ?? ($occArr['english_name'] ?? '');
            $catName = $cat['english_name'] ?? ($occArr['category_name_en'] ?? ($occArr['category_name'] ?? $occName));
            if (empty($catName)) $catName = $occName ?: 'General';
            
            $arName = $cat['arabic_name'] ?? ($occArr['arabic_name'] ?? '');

            if (isset($seen[$catId])) continue;
            $seen[$catId] = true;

            $formattedOccupations[] = [
                'id' => $catId,
                'occupation_id' => (int)($occArr['id'] ?? $catId),
                'english_name' => $catName,
                'category_name' => $catName,
                'arabic_name' => $arName,
                'full_label' => $catName . ($arName ? " ($arName)" : "") . " [Cat ID: $catId]"
            ];
        }
    }
@endphp

<div class="space-y-6">

    <!-- Top Search & Filter Form (Identical Layout to Slot Checker) -->
    <div class="bg-slate-900/80 border border-slate-800 p-6 rounded-3xl backdrop-blur-xl shadow-lg space-y-6">
        <form id="holdScanForm" onsubmit="handleHoldScanSubmit(event)" autocomplete="off" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            
            <!-- Category / Profession Searchable Dropdown -->
            <div class="space-y-2 relative" id="professionDropdownContainer">
                <label class="block text-xs font-bold text-slate-300">
                    <i class="fa-solid fa-briefcase text-sky-400 mr-1"></i> Profession / Trade <span class="text-rose-400">*</span>
                </label>
                <input type="hidden" id="scan_category_id" value="" autocomplete="off" required>
                
                <!-- Trigger Button -->
                <button type="button" onclick="toggleDropdown('profession', event)" id="professionTrigger" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:border-transparent outline-none flex items-center justify-between gap-2 text-left cursor-pointer transition-all hover:border-slate-600">
                    <span id="professionSelectedLabel" class="truncate text-slate-400">Select Profession / Trade...</span>
                    <i class="fa-solid fa-chevron-down text-slate-500 text-[10px] transition-transform duration-200" id="professionChevron"></i>
                </button>

                <!-- Downward Popover Menu -->
                <div id="professionMenu" class="hidden absolute left-0 top-full mt-2 w-full min-w-[320px] md:min-w-[380px] bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl z-[99999] p-3 space-y-2.5 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Search Input -->
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="professionSearchInput" oninput="filterProfessions(this.value)" placeholder="Search profession or ID..." autocomplete="off"
                               class="w-full pl-8 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:border-sky-500 outline-none">
                    </div>

                    <!-- Scrollable List -->
                    <div id="professionList" class="max-h-60 overflow-y-auto space-y-1 custom-scrollbar pr-1">
                        @foreach($formattedOccupations as $item)
                            <button type="button" onclick="selectProfession({{ $item['id'] }})" class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer text-slate-300 hover:bg-slate-800 hover:text-white">
                                <span class="truncate">{{ $item['english_name'] }} {{ !empty($item['arabic_name']) ? '('.$item['arabic_name'].')' : '' }}</span>
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-950 text-slate-400 shrink-0">ID: {{ $item['id'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- City Searchable Dropdown -->
            <div class="space-y-2 relative" id="cityDropdownContainer">
                <label class="block text-xs font-bold text-slate-300">
                    <i class="fa-solid fa-city text-emerald-400 mr-1"></i> City / Division <span class="text-rose-400">*</span>
                </label>
                <input type="hidden" id="scan_city" value="" autocomplete="off" required>

                <!-- Trigger Button -->
                <button type="button" onclick="toggleDropdown('city', event)" id="cityTrigger" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none flex items-center justify-between gap-2 text-left cursor-pointer transition-all hover:border-slate-600">
                    <span id="citySelectedLabel" class="truncate text-slate-400">Select Profession First</span>
                    <i class="fa-solid fa-chevron-down text-slate-500 text-[10px] transition-transform duration-200" id="cityChevron"></i>
                </button>

                <!-- Downward Popover Menu -->
                <div id="cityMenu" class="hidden absolute left-0 top-full mt-2 w-full min-w-[260px] bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl z-[99999] p-3 space-y-2.5 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Search Input -->
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="citySearchInput" oninput="filterCities(this.value)" placeholder="Search city..." autocomplete="off"
                               class="w-full pl-8 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:border-emerald-500 outline-none">
                    </div>

                    <!-- Scrollable List -->
                    <div id="cityList" class="max-h-60 overflow-y-auto space-y-1 custom-scrollbar pr-1">
                        @foreach($cities as $c)
                            <button type="button" onclick="selectCity('{{ $c }}')" class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer text-slate-300 hover:bg-slate-800 hover:text-white">
                                <span class="truncate flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-[11px] text-slate-500"></i>
                                    <span>{{ $c }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Available Dates (Live) Dropdown -->
            <div class="space-y-2 relative" id="dateDropdownContainer">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-300">
                        <i class="fa-solid fa-calendar-days text-amber-400 mr-1"></i> Available Dates
                    </label>
                    <span id="availableDatesCountBadge" class="text-[10px] text-slate-400 font-mono bg-slate-800 px-2 py-0.5 rounded-full border border-slate-700">Select Profession</span>
                </div>
                <input type="hidden" id="scan_date" value="">

                <!-- Trigger Button -->
                <button type="button" onclick="toggleDropdown('date', event)" id="dateTrigger" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none flex items-center justify-between gap-2 text-left cursor-pointer transition-all hover:border-slate-600">
                    <span id="dateSelectedLabel" class="truncate text-slate-400">All Available Dates</span>
                    <i class="fa-solid fa-chevron-down text-slate-500 text-[10px] transition-transform duration-200" id="dateChevron"></i>
                </button>

                <!-- Downward Popover Menu -->
                <div id="dateMenu" class="hidden absolute left-0 top-full mt-2 w-full min-w-[280px] bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl z-[99999] p-3 space-y-2.5 animate-in fade-in zoom-in-95 duration-150">
                    <div class="text-[11px] font-bold text-slate-400 px-1 flex items-center justify-between border-b border-slate-800 pb-2">
                        <span>Live Available Dates:</span>
                        <button type="button" onclick="selectDate('', true)" class="text-[10px] text-amber-400 hover:underline">Scan All Dates</button>
                    </div>

                    <!-- Scrollable List of Live Available Dates -->
                    <div id="dateList" class="max-h-56 overflow-y-auto space-y-1 custom-scrollbar pr-1">
                        <div class="p-3 text-center text-slate-500 text-xs font-medium">Select city first</div>
                    </div>
                </div>
            </div>

            <!-- Custom Manual Date Input -->
            <div class="space-y-2 relative">
                <label class="block text-xs font-bold text-slate-300">
                    <i class="fa-regular fa-calendar-days text-cyan-400 mr-1"></i> Custom Date <span class="text-slate-500 text-[10px] font-normal">(Pick Date)</span>
                </label>
                <div class="relative flex items-center">
                    <input type="date" id="customDateInput" 
                           onclick="try { this.showPicker(); } catch(e) {}" 
                           onfocus="try { this.showPicker(); } catch(e) {}" 
                           onchange="selectCustomDate(this.value)"
                           class="w-full pl-9 pr-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-cyan-500 focus:border-transparent outline-none transition-all hover:border-cyan-500/60 cursor-pointer [color-scheme:dark]">
                    <i class="fa-solid fa-calendar-day absolute left-3 text-cyan-400 text-xs pointer-events-none"></i>
                </div>
            </div>

            <!-- Scan Action Button -->
            <div class="flex items-end">
                <button type="submit" id="scanDatesBtn" class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-600 via-orange-600 to-amber-500 hover:from-amber-500 hover:to-orange-500 text-white font-bold text-xs shadow-lg shadow-amber-600/30 flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer">
                    <i class="fa-solid fa-bolt text-amber-200" id="scanBtnIcon"></i>
                    <span id="scanBtnText">Scan Centers & Slots</span>
                </button>
            </div>
        </form>

        <!-- Dedicated Slot Checker Status & Manage Pool Link -->
        <div class="border-t border-slate-800/80 pt-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="font-bold text-slate-300">Slot Checker Engine:</span>
                <span class="px-3 py-1 rounded-lg bg-emerald-500/15 text-emerald-300 font-mono font-bold text-xs border border-emerald-500/40 flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-shield-halved text-emerald-400"></i>
                    <span>{{ $poolAccounts[0]['email'] ?? 'pool__485381@wafidmaster.com' }}</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-400/20 text-emerald-200 text-[10px] uppercase tracking-wider font-sans">ONLY FOR SLOT CHECKING</span>
                </span>
                <button type="button" onclick="triggerPoolTokenSync()" id="syncPoolBtn" class="px-2.5 py-1 rounded-lg bg-sky-500/20 hover:bg-sky-500/30 text-sky-300 font-bold text-[11px] border border-sky-500/30 transition-all cursor-pointer flex items-center gap-1">
                    <i class="fa-solid fa-rotate-right text-sky-400" id="syncPoolIcon"></i>
                    <span>Keep-Alive Token</span>
                </button>
                <a href="{{ route('admin.settings.index') }}" class="text-amber-400 hover:text-amber-300 font-bold text-[11px] underline">
                    (Manage Pool: {{ count($poolAccounts) }} Accounts)
                </a>
            </div>
            <div class="text-[11px] text-slate-400">
                Hold Duration: <span class="text-emerald-400 font-bold">20-Min Unbroken Continuous Auto-Renewal</span>
            </div>
        </div>
    </div>

    <!-- Main Dynamic Section: Discovered Centers (When Scanned) and Active Held Slots Monitor -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Discovered Test Centers & Holding Panel (Full Width Scan Results Table) -->
        <div id="centersSelectionPanel" class="lg:col-span-12 bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
                <div>
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-building-circle-check text-emerald-400"></i>
                        <span>Discovered Test Centers & Available Slots</span>
                    </h2>
                    <p class="text-[11px] text-slate-400 mt-0.5" id="scanResultsSummary">Select a profession and city above, then click Scan Centers & Slots</p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" onclick="holdAllDiscoveredSlots()" class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 via-orange-600 to-amber-500 hover:from-amber-400 hover:to-orange-500 text-white text-xs font-bold shadow-lg shadow-orange-500/20 transition-all flex items-center gap-1.5 cursor-pointer active:scale-95">
                        <i class="fa-solid fa-lock text-xs"></i>
                        <span>⚡ Hold All Discovered Slots (All Dates)</span>
                    </button>
                    <button type="button" onclick="clearScanResults()" title="Clear scan results" class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 text-[11px] font-bold border border-rose-500/20 transition-all flex items-center gap-1 cursor-pointer">
                        <i class="fa-solid fa-trash-can text-xs"></i> Clear Results
                    </button>
            </div>

            <!-- Lock Target Center Control Panel -->
            <div class="p-4 rounded-2xl bg-slate-950/90 border border-amber-500/40 space-y-3 shadow-lg my-2">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <!-- Toggle Switch -->
                        <label class="relative inline-flex items-center cursor-pointer select-none">
                            <input type="checkbox" id="lockTargetCenterToggle" onchange="toggleLockTargetCenterPanel(this.checked)" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                            <span class="ml-3 text-xs font-extrabold text-white flex items-center gap-1.5">
                                <i class="fa-solid fa-crosshairs text-amber-400"></i>
                                <span>Lock Target Center</span>
                            </span>
                        </label>
                        <span class="text-[11px] text-slate-400 hidden sm:inline">
                            (Set seats to 0 across all shifts for selected center on this date)
                        </span>
                    </div>

                    <!-- Target Center Selection Dropdown & Action Controls (Hidden by Default until Toggled) -->
                    <div id="targetCenterDropdownWrapper" class="hidden flex items-center gap-2 flex-wrap">
                        <select id="targetCenterSelect" onchange="updateTargetCenterDetails()" class="px-3.5 py-2 bg-slate-900 border border-amber-500/50 rounded-xl text-xs text-amber-300 font-bold outline-none cursor-pointer min-w-[260px] shadow-sm">
                            <option value="" disabled selected>Select Center from Scan Results...</option>
                        </select>

                        <button type="button" onclick="lockSelectedTargetCenter()" id="lockTargetCenterBtn" disabled class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 via-orange-600 to-amber-500 hover:from-amber-400 hover:to-orange-500 text-white font-extrabold text-xs shadow-lg shadow-orange-500/30 flex items-center gap-1.5 transition-all active:scale-95 cursor-pointer opacity-50">
                            <i class="fa-solid fa-lock text-xs"></i>
                            <span id="lockTargetBtnText">⚡ Lock Selected Center (0 Seats)</span>
                        </button>
                    </div>
                </div>

                <!-- Selected Target Center Info Banner (Hidden until selection) -->
                <div id="targetCenterSummaryBanner" class="hidden p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-200 flex items-center justify-between flex-wrap gap-2 animate-in fade-in duration-150">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved text-amber-400"></i>
                        <span>Target: <strong id="targetCenterNameLabel" class="text-white">...</strong></span>
                        <span class="px-2 py-0.5 rounded bg-amber-500/20 font-mono font-bold text-[11px]" id="targetCenterShiftsCountLabel">0 Shifts Found</span>
                    </div>
                    <div class="text-[11px] font-mono text-slate-300">
                        Total Seats to Lock: <strong id="targetCenterSeatsCountLabel" class="text-emerald-400">0 Seats</strong> &rarr; <span class="text-rose-400 font-bold">0 Seats on Taqamul (HTTP 422)</span>
                    </div>
                </div>
            </div>

            <!-- Date-by-Date Progressive Tables Container -->
            <div id="discoveredDatesTablesContainer" class="space-y-4 min-h-[160px] max-h-[700px] overflow-y-auto custom-scrollbar pr-1">
                <div class="p-10 text-center bg-slate-950/60 border border-slate-800/80 rounded-2xl space-y-2">
                    <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto text-lg">
                        <i class="fa-solid fa-magnifying-glass-location"></i>
                    </div>
                    <div class="text-xs font-bold text-white">No Scan Results Loaded Yet</div>
                    <p class="text-[11px] text-slate-400 max-w-sm mx-auto">Select a profession and city above, then click <b>Scan Centers & Slots</b> to discover available test seats in real-time.</p>
                </div>
            </div>
        </div>
        </div>

    </div>

</div>
</div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 🔐 CANDIDATE POOL AUTO-LOGIN PROGRESS MODAL -->
<!-- ========================================================================= -->
<div id="poolLoginModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md hidden transition-all duration-300">
    <div class="relative w-full max-w-lg bg-slate-900 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <div id="poolModalIconWrap" class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 text-xl shrink-0">
                    <i id="poolModalIcon" class="fa-solid fa-key animate-bounce"></i>
                </div>
                <div>
                    <h3 id="poolModalTitle" class="text-base font-extrabold text-white flex items-center gap-2">
                        Candidate Pool Authentication
                    </h3>
                    <p id="poolModalSubtitle" class="text-xs text-slate-400 mt-0.5">
                        Taqamul token is expired or not active. Authenticating pool account...
                    </p>
                </div>
            </div>
            <button type="button" onclick="closePoolLoginModal()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center text-sm transition-all cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Real-Time Terminal Stream Window -->
        <div class="rounded-2xl bg-slate-950 border border-slate-800 p-4 font-mono text-[11px] space-y-2 overflow-hidden shadow-inner">
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-2 text-slate-400">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                    <span class="ml-2 font-bold text-slate-300 text-xs">Taqamul Bot Engine Live Stream</span>
                </div>
                <span class="flex items-center gap-1.5 text-[10px] text-emerald-400 font-bold">
                    <i class="fa-solid fa-circle text-[6px] animate-ping"></i> STREAMING
                </span>
            </div>
            
            <!-- Terminal Output Lines -->
            <div id="poolLiveTerminalOutput" class="h-48 overflow-y-auto space-y-1 text-slate-300 pr-1 scrollbar-thin scrollbar-thumb-slate-800 leading-relaxed">
                <div class="text-slate-500">[Token Bot] Initializing Live Authentication Console...</div>
            </div>
        </div>

        <!-- Overall Progress Bar & Elapsed Timer -->
        <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-2">
            <div class="flex justify-between items-center text-xs">
                <span id="poolModalFooterStatus" class="font-bold text-amber-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-spinner fa-spin text-xs"></i> Authentication in progress...
                </span>
                <span id="poolModalTimer" class="font-mono text-slate-400 text-xs">0.0s</span>
            </div>
            <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-800">
                <div id="poolModalProgressBar" class="bg-gradient-to-r from-amber-500 via-sky-500 to-emerald-400 h-2 rounded-full transition-all duration-300" style="width: 15%"></div>
            </div>
        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- 🔍 TAQAMUL RAW SERVER RESPONSE MODAL -->
<!-- ========================================================================= -->
<div id="rawResponseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md hidden transition-all duration-300">
    <div class="relative w-full max-w-2xl bg-slate-900 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-500/10 border border-sky-500/30 flex items-center justify-center text-sky-400 text-lg shrink-0">
                    <i class="fa-solid fa-code"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        Taqamul Server JSON Response
                    </h3>
                    <p id="rawModalHashTitle" class="text-xs text-sky-300 font-mono mt-0.5 truncate max-w-md">
                        Session Mother Hash: ...
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeRawResponseModal()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center text-sm transition-all cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Center Details Banner -->
        <div id="rawModalMetaBanner" class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-300 flex items-center justify-between flex-wrap gap-2">
            <div>
                <span class="text-slate-500">Center:</span> <b id="rawModalCenterName" class="text-white">...</b>
            </div>
            <div>
                <span class="text-slate-500">Exam Date:</span> <b id="rawModalExamDate" class="text-amber-300 font-mono">...</b>
            </div>
        </div>

        <!-- JSON Viewer Box -->
        <div class="relative">
            <pre id="rawModalJsonContent" class="p-4 rounded-2xl bg-slate-950 border border-slate-800 font-mono text-xs text-emerald-300 overflow-x-auto max-h-80 scrollbar-thin scrollbar-thumb-slate-800 leading-relaxed"></pre>
            
            <button type="button" onclick="copyRawModalJson()" class="absolute top-3 right-3 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-md">
                <i class="fa-regular fa-copy"></i>
                <span id="rawModalCopyBtnText">Copy JSON</span>
            </button>
        </div>

        <!-- Footer -->
        <div class="flex justify-end pt-2 border-t border-slate-800">
            <button type="button" onclick="closeRawResponseModal()" class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition-all cursor-pointer">
                Close
            </button>
        </div>

    </div>
</div>

@push('scripts')
<script>
    function logMessage(msg, type = 'info') {
        console.log(`[Hold Engine] [${type.toUpperCase()}] ${msg}`);
    }

    const formattedOccupations = @json($formattedOccupations);
    const formattedCities = @json($cities);
    const initialScanResult = @json($lastScanResult);
    const isInitialScanningActive = @json(!empty($isScanningActive));
    let availableCityDatesMap = {};
    let currentAvailableCities = [];
    let activeHoldTimers = {};
    let scannedCentersData = [];
    let scannedDatesGroupedData = [];
    let isCurrentlyScanning = false;
    let scanAbortController = null;
    let scanStatusPollingInterval = null;
    let rawResponseHashStore = {};

    function copyHashText(hash) {
        if (!hash) return;
        navigator.clipboard.writeText(hash).then(() => {
            alert('Mother Hash copied: ' + hash);
        }).catch(() => {
            prompt('Copy Mother Hash:', hash);
        });
    }

    async function openRawResponseModal(hash) {
        const modal = document.getElementById('rawResponseModal');
        if (!modal) return;

        const hashTitle = document.getElementById('rawModalHashTitle');
        const centerName = document.getElementById('rawModalCenterName');
        const examDate = document.getElementById('rawModalExamDate');
        const jsonContent = document.getElementById('rawModalJsonContent');

        const localData = rawResponseHashStore[hash] || {};

        if (hashTitle) hashTitle.innerText = `Mother Hash: ${hash}`;
        if (centerName) centerName.innerText = localData.center_name || 'Loading Center...';
        if (examDate) examDate.innerText = localData.date_formatted ? `${localData.date_formatted} (${localData.exam_date || ''})` : (localData.exam_date || 'Loading Date...');
        
        if (jsonContent) {
            jsonContent.innerText = `// Connecting to Taqamul server & fetching full live response...\n// Mother Hash: ${hash}\n\nLoading...`;
        }

        modal.classList.remove('hidden');

        try {
            const res = await fetch(`{{ route('admin.slots.hash_details') }}?hash=${encodeURIComponent(hash)}`);
            const data = await res.json();
            if (data.success) {
                const tc = data.test_center || {};
                const sched = data.schedule_and_timing || {};
                if (centerName && tc.name) centerName.innerText = tc.name;
                if (examDate && sched.exam_date) examDate.innerText = `${sched.exam_date_formatted || sched.exam_date} (${sched.exam_date})`;

                if (jsonContent) {
                    jsonContent.innerText = JSON.stringify(data, null, 2);
                }
            } else {
                if (jsonContent) {
                    const fallback = localData.raw_payload || localData;
                    jsonContent.innerText = JSON.stringify(fallback, null, 2);
                }
            }
        } catch(e) {
            console.error('Failed to fetch full hash details:', e);
            if (jsonContent) {
                const fallback = localData.raw_payload || localData;
                jsonContent.innerText = JSON.stringify(fallback, null, 2);
            }
        }
    }

    function closeRawResponseModal() {
        const modal = document.getElementById('rawResponseModal');
        if (modal) modal.classList.add('hidden');
    }

    function copyRawModalJson() {
        const jsonContent = document.getElementById('rawModalJsonContent');
        const copyBtnText = document.getElementById('rawModalCopyBtnText');
        if (!jsonContent) return;

        navigator.clipboard.writeText(jsonContent.innerText).then(() => {
            if (copyBtnText) copyBtnText.innerText = 'Copied!';
            setTimeout(() => {
                if (copyBtnText) copyBtnText.innerText = 'Copy JSON';
            }, 2000);
        });
    }

    function resetProfessionDropdownState() {
        localStorage.removeItem('hold_date_tables_result');
        localStorage.removeItem('last_selected_profession');

        const scanCatInput = document.getElementById('scan_category_id');
        const profLabel = document.getElementById('professionSelectedLabel');
        const scanCityInput = document.getElementById('scan_city');
        const cityLabel = document.getElementById('citySelectedLabel');
        const dateLabel = document.getElementById('dateSelectedLabel');

        if (scanCatInput) scanCatInput.value = '';
        if (profLabel) {
            profLabel.innerText = 'Select Profession / Trade...';
            profLabel.className = 'truncate text-slate-400';
        }
        if (scanCityInput) scanCityInput.value = '';
        if (cityLabel) {
            cityLabel.innerText = 'Select Profession First';
            cityLabel.className = 'truncate text-slate-400';
        }
        if (dateLabel) {
            dateLabel.innerText = 'All Available Dates';
            dateLabel.className = 'truncate text-slate-400';
        }

        renderProfessionOptions(formattedOccupations);
        renderCityOptions([]);
    }

    window.addEventListener('pageshow', resetProfessionDropdownState);

    document.addEventListener('DOMContentLoaded', () => {
        resetProfessionDropdownState();

        // If scanning is active on server (e.g. from page reload or another device)
        if (isInitialScanningActive) {
            setScanningButtonState(effectiveScanResult?.city || 'Selected City');
            startScanStatusPolling();
        }
    });

    // Dropdown Toggling & Outside Click Handler
    function toggleDropdown(type, e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById(type + 'Menu');
        const chevron = document.getElementById(type + 'Chevron');
        const container = document.getElementById(type + 'DropdownContainer');
        if (!menu) return;

        const isHidden = menu.classList.contains('hidden');
        
        ['profession', 'city', 'date'].forEach(t => {
            if (t !== type) closeDropdown(t);
        });

        if (isHidden) {
            menu.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-180');
            if (container) container.style.zIndex = '99999';
            const searchInput = document.getElementById(type + 'SearchInput');
            if (searchInput) setTimeout(() => searchInput.focus(), 50);
        } else {
            closeDropdown(type);
        }
    }

    function closeDropdown(type) {
        const menu = document.getElementById(type + 'Menu');
        const chevron = document.getElementById(type + 'Chevron');
        const container = document.getElementById(type + 'DropdownContainer');
        if (menu) menu.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-180');
        if (container) container.style.zIndex = '';
    }

    document.addEventListener('click', (e) => {
        ['profession', 'city', 'date'].forEach(type => {
            const container = document.getElementById(type + 'DropdownContainer');
            if (container && !container.contains(e.target)) {
                closeDropdown(type);
            }
        });
    });

    let poolLoginTimerInterval = null;
    let poolLoginLogsPollingInterval = null;
    let poolLoginStartTime = 0;
    let isAutoLoginInProgress = false;

    function openPoolLoginModal() {
        const modal = document.getElementById('poolLoginModal');
        if (modal) modal.classList.remove('hidden');
        resetPoolLoginModal();
    }

    function closePoolLoginModal() {
        const modal = document.getElementById('poolLoginModal');
        if (modal) modal.classList.add('hidden');
        if (poolLoginTimerInterval) {
            clearInterval(poolLoginTimerInterval);
            poolLoginTimerInterval = null;
        }
        if (poolLoginLogsPollingInterval) {
            clearInterval(poolLoginLogsPollingInterval);
            poolLoginLogsPollingInterval = null;
        }
    }

    function resetPoolLoginModal() {
        const terminal = document.getElementById('poolLiveTerminalOutput');
        if (terminal) {
            terminal.innerHTML = '<div class="text-slate-500">[Token Bot] Initializing Live Authentication Console...</div>';
        }
        const bar = document.getElementById('poolModalProgressBar');
        if (bar) bar.style.width = '15%';
        const footer = document.getElementById('poolModalFooterStatus');
        if (footer) footer.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-xs"></i> Authentication in progress...`;
    }

    function formatTerminalLine(line) {
        if (!line || !line.trim()) return '';
        let clean = line.trim();
        
        if (clean.includes('[Token Bot 🔑]')) {
            return `<div class="text-emerald-300 font-bold bg-emerald-950/40 px-2 py-0.5 rounded border-l-2 border-emerald-400 animate-in fade-in duration-100">${clean}</div>`;
        }
        if (clean.includes('[Token Bot ❌]') || clean.toLowerCase().includes('error') || clean.toLowerCase().includes('invalid')) {
            return `<div class="text-rose-400 font-bold bg-rose-950/40 px-2 py-0.5 rounded border-l-2 border-rose-500 animate-in fade-in duration-100">${clean}</div>`;
        }
        if (clean.includes('[CapSolver AI ✅]')) {
            return `<div class="text-emerald-400 font-semibold">${clean}</div>`;
        }
        if (clean.includes('[CapSolver AI ⚡]')) {
            return `<div class="text-amber-300">${clean}</div>`;
        }
        if (clean.includes('[WafidMail API]') || clean.includes('[WafidMail Engine]')) {
            return `<div class="text-cyan-300">${clean}</div>`;
        }
        if (clean.includes('[Token Bot]')) {
            return `<div class="text-sky-300">${clean}</div>`;
        }
        return `<div class="text-slate-300">${clean}</div>`;
    }

    function startLiveLogPolling(onSuccessCallback) {
        if (poolLoginLogsPollingInterval) clearInterval(poolLoginLogsPollingInterval);
        
        let lastLogText = '';
        poolLoginLogsPollingInterval = setInterval(async () => {
            try {
                const res = await fetch(`{{ route('admin.slots.auto_login_logs') }}`);
                const data = await res.json();
                
                if (data.success) {
                    if (data.logs && data.logs !== lastLogText) {
                        lastLogText = data.logs;
                        const terminal = document.getElementById('poolLiveTerminalOutput');
                        if (terminal) {
                            const lines = data.logs.split('\n').filter(l => l.trim().length > 0 && !l.includes('FINAL_TOKEN_RESULT'));
                            terminal.innerHTML = lines.map(formatTerminalLine).join('');
                            terminal.scrollTop = terminal.scrollHeight;
                        }

                        // Progress bar heuristic based on logs
                        const bar = document.getElementById('poolModalProgressBar');
                        if (bar) {
                            if (data.logs.includes('[Token Bot 🔑]')) bar.style.width = '95%';
                            else if (data.logs.includes('OTP')) bar.style.width = '80%';
                            else if (data.logs.includes('CapSolver AI ✅')) bar.style.width = '60%';
                            else if (data.logs.includes('Navigating')) bar.style.width = '35%';
                        }
                    }

                    // Check if bot finished execution
                    if (data.done) {
                        if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);
                        if (poolLoginLogsPollingInterval) clearInterval(poolLoginLogsPollingInterval);
                        poolLoginLogsPollingInterval = null;

                        if (data.token) {
                            const terminal = document.getElementById('poolLiveTerminalOutput');
                            if (terminal) {
                                terminal.innerHTML += `<div class="text-emerald-300 font-extrabold bg-emerald-950/60 p-2 rounded border border-emerald-500/50 mt-2">🎉 SUCCESS! 12-Hour Bearer Token Acquired & Candidate Pool Activated!</div>`;
                                terminal.scrollTop = terminal.scrollHeight;
                            }

                            const footer = document.getElementById('poolModalFooterStatus');
                            if (footer) footer.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-400"></i> <b class="text-emerald-400">Login Successful! Resuming Check...</b>`;
                            
                            const bar = document.getElementById('poolModalProgressBar');
                            if (bar) bar.style.width = '100%';

                            setTimeout(() => {
                                closePoolLoginModal();
                                isAutoLoginInProgress = false;
                                if (typeof onSuccessCallback === 'function') {
                                    onSuccessCallback();
                                }
                            }, 1200);
                        } else {
                            const footer = document.getElementById('poolModalFooterStatus');
                            const errMsg = data.error || 'Auto-Login failed. Please check credentials.';
                            if (footer) {
                                footer.innerHTML = `
                                    <div class="flex items-center justify-between w-full gap-2">
                                        <span class="text-rose-400 font-bold text-xs flex items-center gap-1.5 truncate">
                                            <i class="fa-solid fa-triangle-exclamation text-rose-400 shrink-0"></i>
                                            <span class="truncate">${errMsg}</span>
                                        </span>
                                        <button type="button" onclick="isAutoLoginInProgress=false;triggerAutoLoginForPool(${onSuccessCallback ? onSuccessCallback.toString() : 'null'})" class="px-2.5 py-1 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-bold text-[11px] shrink-0 transition-all cursor-pointer">
                                            <i class="fa-solid fa-rotate-right mr-1"></i> Retry
                                        </button>
                                    </div>
                                `;
                            }
                            isAutoLoginInProgress = false;
                        }
                    }
                }
            } catch(e) {}
        }, 500);
    }

    async function triggerAutoLoginForPool(onSuccessCallback, specificEmail = '') {
        if (isAutoLoginInProgress) return;
        isAutoLoginInProgress = true;
        openPoolLoginModal();

        poolLoginStartTime = Date.now();
        const timerEl = document.getElementById('poolModalTimer');
        if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);
        poolLoginTimerInterval = setInterval(() => {
            if (timerEl) {
                const elapsed = ((Date.now() - poolLoginStartTime) / 1000).toFixed(1);
                timerEl.innerText = `${elapsed}s`;
            }
        }, 100);

        // Start streaming logs in real-time and wait until done
        startLiveLogPolling(onSuccessCallback);

        try {
            const res = await fetch(`{{ route('admin.slots.auto_login') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email: specificEmail })
            });

            const data = await res.json();
            if (!data.success) {
                if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);
                if (poolLoginLogsPollingInterval) clearInterval(poolLoginLogsPollingInterval);
                
                const footer = document.getElementById('poolModalFooterStatus');
                const errMsg = data.message || 'Failed to start auto-login process.';
                if (footer) {
                    footer.innerHTML = `
                        <div class="flex items-center justify-between w-full gap-2">
                            <span class="text-rose-400 font-bold text-xs flex items-center gap-1.5 truncate">
                                <i class="fa-solid fa-triangle-exclamation text-rose-400 shrink-0"></i>
                                <span class="truncate">${errMsg}</span>
                            </span>
                            <button type="button" onclick="isAutoLoginInProgress=false;triggerAutoLoginForPool(${onSuccessCallback ? onSuccessCallback.toString() : 'null'})" class="px-2.5 py-1 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-bold text-[11px] shrink-0 transition-all cursor-pointer">
                                <i class="fa-solid fa-rotate-right mr-1"></i> Retry
                            </button>
                        </div>
                    `;
                }
                isAutoLoginInProgress = false;
            }
        } catch (e) {
            console.error('Auto login error:', e);
            if (poolLoginTimerInterval) clearInterval(poolLoginTimerInterval);
            if (poolLoginLogsPollingInterval) clearInterval(poolLoginLogsPollingInterval);
            
            const footer = document.getElementById('poolModalFooterStatus');
            if (footer) {
                footer.innerHTML = `
                    <div class="flex items-center justify-between w-full gap-2">
                        <span class="text-rose-400 font-bold text-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-triangle-exclamation text-rose-400 shrink-0"></i>
                            <span>Connection error. Failed to connect to server.</span>
                        </span>
                    </div>
                `;
            }
            isAutoLoginInProgress = false;
        }
    }

    // Load available dates & active cities for the selected profession from Taqamul API
    async function loadAvailableDatesForCategory(categoryId) {
        if (!categoryId) return;

        const cityLabel = document.getElementById('citySelectedLabel');
        const dateLabel = document.getElementById('dateSelectedLabel');
        const datesBadge = document.getElementById('availableDatesCountBadge');
        
        cityLabel.innerHTML = `<span class="flex items-center gap-1.5 text-slate-400"><i class="fa-solid fa-circle-notch fa-spin text-emerald-400 text-xs"></i> Loading cities...</span>`;
        dateLabel.innerHTML = `<span class="flex items-center gap-1.5 text-slate-400"><i class="fa-solid fa-circle-notch fa-spin text-amber-400 text-xs"></i> Loading dates...</span>`;
        datesBadge.innerText = 'Checking...';
        
        try {
            const res = await fetch(`{{ route('admin.slots.available_dates') }}?category_id=${categoryId}`);
            const data = await res.json();

            // If 401 or token expired -> Trigger Auto-Login progress modal
            if (res.status === 401) {
                availableCityDatesMap = {};
                currentAvailableCities = formattedCities;
                renderCityOptions(formattedCities);
                cityLabel.innerText = document.getElementById('scan_city').value || 'Select City';
                dateLabel.innerText = 'All Available Dates (0)';
                renderDateOptions([]);
                datesBadge.innerText = 'Authenticating Pool...';

                isAutoLoginInProgress = false;
                triggerAutoLoginForPool(() => loadAvailableDatesForCategory(categoryId));
                return;
            }
            
            if (data && data.success && data.cities && data.cities.length > 0) {
                availableCityDatesMap = data.city_dates_map || {};
                currentAvailableCities = data.cities;
                
                // Render City options with available dates count
                renderCityOptions(currentAvailableCities);
                
                // If current selected city is available, keep it; else select first available city
                const currentCity = document.getElementById('scan_city').value;
                if (currentAvailableCities.includes(currentCity)) {
                    selectCity(currentCity, false);
                } else {
                    selectCity(currentAvailableCities[0], false);
                }
            } else {
                availableCityDatesMap = {};
                currentAvailableCities = formattedCities;
                renderCityOptions(formattedCities);
                cityLabel.innerText = document.getElementById('scan_city').value || 'Select City';
                dateLabel.innerText = 'All Available Dates (0)';
                renderDateOptions([]);
                datesBadge.innerText = '0 Live';
            }
        } catch (e) {
            console.error('Error loading available dates:', e);
            availableCityDatesMap = {};
            currentAvailableCities = formattedCities;
            renderCityOptions(formattedCities);
            cityLabel.innerText = document.getElementById('scan_city').value || 'Select City';
            dateLabel.innerText = 'All Available Dates (0)';
            renderDateOptions([]);
            datesBadge.innerText = '0 Live';
        }
    }

    // Render Profession Options List
    function renderProfessionOptions(items) {
        const listEl = document.getElementById('professionList');
        if (!listEl) return;
        listEl.innerHTML = '';
        const currentVal = document.getElementById('scan_category_id').value;

        // Default Reset Option
        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${!currentVal ? 'bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30' : 'text-amber-400/80 hover:bg-slate-800 hover:text-amber-300'}`;
        resetBtn.innerHTML = `
            <span class="truncate italic flex items-center gap-1.5"><i class="fa-solid fa-hand-pointer text-[10px]"></i> Select Profession / Trade...</span>
            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-950 text-slate-500 shrink-0">Reset</span>
        `;
        resetBtn.onclick = () => selectProfession('', true);
        listEl.appendChild(resetBtn);

        if (items.length === 0) {
            return;
        }

        items.forEach(item => {
            const isSelected = String(item.id) === String(currentVal);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${isSelected ? 'bg-sky-500/20 text-sky-300 font-bold border border-sky-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
            
            btn.innerHTML = `
                <span class="truncate">${item.english_name} <span class="text-slate-500 text-[11px]">${item.arabic_name ? '(' + item.arabic_name + ')' : ''}</span></span>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-950 text-slate-400 shrink-0">ID: ${item.id}</span>
            `;

            btn.onclick = () => selectProfession(item.id);
            listEl.appendChild(btn);
        });
    }

    function filterProfessions(query) {
        const q = (query || '').trim().toLowerCase();
        if (!q) {
            renderProfessionOptions(formattedOccupations);
            return;
        }

        const filtered = formattedOccupations.filter(item => 
            (item.english_name && item.english_name.toLowerCase().includes(q)) ||
            (item.category_name && item.category_name.toLowerCase().includes(q)) ||
            (item.arabic_name && item.arabic_name.toLowerCase().includes(q)) ||
            String(item.id).includes(q) ||
            String(item.occupation_id).includes(q)
        );
        renderProfessionOptions(filtered);
    }

    function selectProfession(id, close = true) {
        if (!id) {
            document.getElementById('scan_category_id').value = '';
            const profLabel = document.getElementById('professionSelectedLabel');
            if (profLabel) {
                profLabel.innerText = 'Select Profession / Trade...';
                profLabel.className = 'truncate text-slate-400';
            }
            const cityLabel = document.getElementById('citySelectedLabel');
            if (cityLabel) {
                cityLabel.innerText = 'Select Profession First';
                cityLabel.className = 'truncate text-slate-400';
            }
            const datesBadge = document.getElementById('availableDatesCountBadge');
            if (datesBadge) {
                datesBadge.innerText = 'Select Profession';
                datesBadge.className = 'text-[10px] text-slate-400 font-mono bg-slate-800 px-2 py-0.5 rounded-full border border-slate-700';
            }
            renderProfessionOptions(formattedOccupations);
            renderCityOptions([]);
            renderDateOptions([]);
            if (close) closeDropdown('profession');
            return;
        }

        const item = formattedOccupations.find(o => String(o.id) === String(id)) || formattedOccupations.find(o => String(o.occupation_id) === String(id));
        if (item) {
            document.getElementById('scan_category_id').value = item.id;
            const profLabel = document.getElementById('professionSelectedLabel');
            if (profLabel) {
                profLabel.innerText = item.full_label || ((item.english_name || item.name || '') + (item.arabic_name ? ' (' + item.arabic_name + ')' : '') + ' [Cat ID: ' + item.id + ']');
                profLabel.className = 'truncate text-white font-bold';
            }
            renderProfessionOptions(formattedOccupations);
            loadAvailableDatesForCategory(item.id);
        }
        if (close) closeDropdown('profession');
    }

    // Render City Options List
    function renderCityOptions(items) {
        const listEl = document.getElementById('cityList');
        if (!listEl) return;
        listEl.innerHTML = '';
        const currentVal = document.getElementById('scan_city').value;

        if (items.length === 0) {
            listEl.innerHTML = '<div class="p-3 text-center text-slate-500 text-xs font-medium">No available cities for this profession</div>';
            return;
        }

        items.forEach(city => {
            const isSelected = city === currentVal;
            const dates = availableCityDatesMap[city] || [];
            const dateCount = dates.length;
            
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${isSelected ? 'bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
            
            btn.innerHTML = `
                <span class="truncate flex items-center gap-2">
                    <i class="fa-solid fa-location-dot text-[11px] ${isSelected ? 'text-emerald-400' : 'text-slate-500'}"></i>
                    <span>${city}</span>
                </span>
                <div class="flex items-center gap-1.5 shrink-0">
                    ${dateCount > 0 ? `<span class="px-1.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 text-[10px] font-mono border border-emerald-500/20">${dateCount} Dates</span>` : ''}
                    ${isSelected ? '<i class="fa-solid fa-check text-emerald-400 text-xs"></i>' : ''}
                </div>
            `;

            btn.onclick = () => selectCity(city);
            listEl.appendChild(btn);
        });
    }

    function filterCities(query) {
        const q = query.trim().toLowerCase();
        const pool = currentAvailableCities.length > 0 ? currentAvailableCities : formattedCities;
        if (!q) {
            renderCityOptions(pool);
            return;
        }

        const filtered = pool.filter(c => c.toLowerCase().includes(q));
        renderCityOptions(filtered);
    }

    function selectCity(city, close = true) {
        document.getElementById('scan_city').value = city;
        const dates = availableCityDatesMap[city] || [];
        const countBadge = dates.length > 0 ? ` (${dates.length} Dates Available)` : '';
        document.getElementById('citySelectedLabel').innerText = city + countBadge;
        
        renderDateOptions(dates);
        selectDate('', false);
        if (close) closeDropdown('city');
    }

    // Render Live Available Dates in Date Popover Dropdown
    function renderDateOptions(dates) {
        const listEl = document.getElementById('dateList');
        const badge = document.getElementById('availableDatesCountBadge');
        if (!listEl) return;

        listEl.innerHTML = '';
        const currentVal = document.getElementById('scan_date').value;

        if (dates.length === 0) {
            badge.innerText = '0 Live';
            listEl.innerHTML = '<div class="p-3 text-center text-slate-500 text-xs font-medium">No active dates discovered for this city. Use Custom Date picker.</div>';
            document.getElementById('dateSelectedLabel').innerText = 'All Available Dates';
            return;
        }

        badge.innerText = `${dates.length} Live`;
        if (!currentVal) {
            document.getElementById('dateSelectedLabel').innerText = `All Available Dates (${dates.length})`;
        }
        
        // Option 1: "All Available Dates" button
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${!currentVal ? 'bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
        allBtn.innerHTML = `
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-list-check text-amber-400 text-xs"></i>
                <span class="font-bold">Scan All Available Dates (${dates.length})</span>
            </span>
            ${!currentVal ? '<i class="fa-solid fa-check text-amber-400 text-xs"></i>' : ''}
        `;
        allBtn.onclick = () => selectDate('', true);
        listEl.appendChild(allBtn);

        // Date items
        dates.forEach(dateStr => {
            const isSelected = dateStr === currentVal;
            const d = new Date(dateStr);
            const dayName = !isNaN(d.getTime()) ? d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) : dateStr;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${isSelected ? 'bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;

            btn.innerHTML = `
                <span class="truncate flex items-center gap-2">
                    <i class="fa-regular fa-calendar-check text-[11px] ${isSelected ? 'text-amber-400' : 'text-slate-500'}"></i>
                    <span class="font-mono font-bold">${dateStr}</span>
                    <span class="text-[11px] text-slate-400">(${dayName})</span>
                </span>
                ${isSelected ? '<i class="fa-solid fa-check text-amber-400 text-xs"></i>' : ''}
            `;

            btn.onclick = () => selectDate(dateStr, true);
            listEl.appendChild(btn);
        });
    }

    function selectDate(dateStr, close = true) {
        document.getElementById('scan_date').value = dateStr;
        if (!dateStr) {
            const city = document.getElementById('scan_city').value;
            const dates = availableCityDatesMap[city] || [];
            document.getElementById('dateSelectedLabel').innerText = `All Available Dates (${dates.length})`;
            document.getElementById('customDateInput').value = '';
        } else {
            const d = new Date(dateStr);
            const dayName = !isNaN(d.getTime()) ? ` (${d.toLocaleDateString('en-US', { weekday: 'short' })})` : '';
            document.getElementById('dateSelectedLabel').innerText = dateStr + dayName;
            document.getElementById('customDateInput').value = dateStr;
        }
        if (close) closeDropdown('date');
    }

    function selectCustomDate(val) {
        if (val) {
            document.getElementById('scan_date').value = val;
            const d = new Date(val);
            const dayName = !isNaN(d.getTime()) ? ` (${d.toLocaleDateString('en-US', { weekday: 'short' })})` : '';
            document.getElementById('dateSelectedLabel').innerText = `Custom: ${val}` + dayName;
            renderDateOptions(availableCityDatesMap[document.getElementById('scan_city').value] || []);
        }
    }

    async function triggerPoolTokenSync() {
        const btn = document.getElementById('syncPoolBtn');
        const icon = document.getElementById('syncPoolIcon');
        const statusText = document.getElementById('poolStatusText');
        if (icon) icon.className = 'fa-solid fa-spinner fa-spin text-sky-400';
        if (btn) btn.disabled = true;

        try {
            const res = await fetch("{{ route('admin.slots.pool.sync_health') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (data && data.success && data.health) {
                if (statusText) {
                    statusText.innerText = `${data.health.active} Active / ${data.health.expired} Expired`;
                }
            }
        } catch(e) {
            console.error('Pool sync error:', e);
        } finally {
            if (icon) icon.className = 'fa-solid fa-rotate-right text-sky-400';
            if (btn) btn.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderProfessionOptions(formattedOccupations);
        renderCityOptions(formattedCities);
        renderDateOptions([]);

        const currentCat = document.getElementById('scan_category_id').value;
        if (currentCat) {
            selectProfession(currentCat, false);
        } else {
            selectProfession('', false);
        }
    });

    function switchViewMode(mode) {
        currentViewMode = mode;
        const dateBtn = document.getElementById('viewModeDateBtn');
        const centerBtn = document.getElementById('viewModeCenterBtn');
        const dateContainer = document.getElementById('dateGroupedContainer');
        const centerContainer = document.getElementById('uniqueCentersList');

        if (mode === 'date') {
            dateBtn.className = 'px-2.5 py-1 rounded-md bg-amber-500/20 text-amber-300 font-bold transition-all';
            centerBtn.className = 'px-2.5 py-1 rounded-md text-slate-400 hover:text-white transition-all';
            dateContainer.classList.remove('hidden');
            centerContainer.classList.add('hidden');
        } else {
            centerBtn.className = 'px-2.5 py-1 rounded-md bg-amber-500/20 text-amber-300 font-bold transition-all';
            dateBtn.className = 'px-2.5 py-1 rounded-md text-slate-400 hover:text-white transition-all';
            centerContainer.classList.remove('hidden');
            dateContainer.classList.add('hidden');
        }
        updateBulkHoldButtonCount();
    }

    // Submit handler for scan form
    async function handleHoldScanSubmit(e) {
        if (e) e.preventDefault();
        if (isCurrentlyScanning) {
            await stopBatchScan();
        } else {
            await runBatchDatesScan();
        }
    }

    // Stop active scanning
    async function stopBatchScan() {
        if (scanAbortController) {
            try { scanAbortController.abort(); } catch(e) {}
        }
        if (scanStatusPollingInterval) clearInterval(scanStatusPollingInterval);
        resetScanButtonState();

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            await fetch("{{ route('admin.slots.hold.cancel_scan') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        } catch(e) {}
    }

    function setScanningButtonState(city) {
        isCurrentlyScanning = true;
        const btn = document.getElementById('scanDatesBtn');
        const icon = document.getElementById('scanBtnIcon');
        const text = document.getElementById('scanBtnText');
        if (!btn) return;

        btn.disabled = false;
        btn.className = 'w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-rose-600 via-rose-500 to-amber-600 hover:from-rose-500 hover:to-amber-500 text-white font-bold text-xs shadow-lg shadow-rose-600/30 flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer';
        icon.className = 'fa-solid fa-stop text-white text-xs animate-pulse';
        text.innerText = `Scanning ${city}... (Click to Stop)`;
    }

    function resetScanButtonState() {
        isCurrentlyScanning = false;
        const btn = document.getElementById('scanDatesBtn');
        const icon = document.getElementById('scanBtnIcon');
        const text = document.getElementById('scanBtnText');
        if (!btn) return;

        btn.disabled = false;
        btn.className = 'w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-600 via-orange-600 to-amber-500 hover:from-amber-500 hover:to-orange-500 text-white font-bold text-xs shadow-lg shadow-amber-600/30 flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer';
        icon.className = 'fa-solid fa-bolt text-amber-200';
        text.innerText = 'Scan Centers & Slots';
    }

    function startScanStatusPolling() {
        if (scanStatusPollingInterval) clearInterval(scanStatusPollingInterval);
        scanStatusPollingInterval = setInterval(async () => {
            try {
                const res = await fetch("{{ route('admin.slots.hold.scan_status') }}");
                const data = await res.json();
                if (!data.is_scanning) {
                    clearInterval(scanStatusPollingInterval);
                    resetScanButtonState();
                    if (data.last_result && data.last_result.success) {
                        scannedCentersData = data.last_result.centers || [];
                        scannedDatesGroupedData = data.last_result.dates_grouped || [];
                        renderScanResults(data.last_result);
                    }
                }
            } catch(e) {}
        }, 2000);
    }

    let accumulatedDateTables = [];

    async function clearScanResults() {
        try { 
            localStorage.removeItem('hold_last_scan_result'); 
            localStorage.removeItem('hold_date_tables_result');
        } catch(e) {}
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            await fetch("{{ route('admin.slots.hold.clear_scan') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        } catch(e) {}

        const panel = document.getElementById('centersSelectionPanel');
        const container = document.getElementById('discoveredDatesTablesContainer');
        const summary = document.getElementById('scanResultsSummary');
        const holdsContainer = document.getElementById('activeHoldsContainer');

        if (container) {
            container.innerHTML = `
                <div class="p-10 text-center bg-slate-950/60 border border-slate-800/80 rounded-2xl space-y-2">
                    <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto text-lg">
                        <i class="fa-solid fa-magnifying-glass-location"></i>
                    </div>
                    <div class="text-xs font-bold text-white">No Scan Results Loaded Yet</div>
                    <p class="text-[11px] text-slate-400 max-w-sm mx-auto">Select a profession and city above, then click <b>Scan Centers & Slots</b> to discover available test seats in real-time.</p>
                </div>
            `;
        }
        if (summary) summary.innerText = 'Select a profession and city above, then click Scan Centers & Slots';
        accumulatedDateTables = [];
        populateTargetCenterDropdown();
    }

    // Step 1: Scan Selected Dates Sequentially (1-by-1 Progressive Date Table Generator)
    async function runBatchDatesScan() {
        const categoryId = document.getElementById('scan_category_id').value;
        const city = document.getElementById('scan_city').value;
        const customDate = document.getElementById('scan_date') ? document.getElementById('scan_date').value : '';
        const dropdownDate = document.getElementById('scan_available_date') ? document.getElementById('scan_available_date').value : '';

        if (!categoryId) {
            alert('Please select a Profession / Trade first!');
            toggleDropdown('profession');
            return;
        }
        if (!city) {
            alert('Please select a City / Division first!');
            toggleDropdown('city');
            return;
        }

        let selectedDates = [];
        if (dropdownDate && dropdownDate !== 'all') {
            selectedDates = [dropdownDate];
        } else if (customDate) {
            let raw = customDate.trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                selectedDates = [raw];
            } else if (raw.includes('/')) {
                const parts = raw.split('/');
                if (parts.length === 3) {
                    if (parts[0].length === 4) {
                        selectedDates = [`${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`];
                    } else if (parts[2].length === 4) {
                        let p1 = parseInt(parts[0], 10);
                        let p2 = parseInt(parts[1], 10);
                        let yyyy = parts[2];
                        if (p1 === 9 && p2 === 5) {
                            selectedDates = [`${yyyy}-09-05`];
                        } else if (p1 > 12) {
                            selectedDates = [`${yyyy}-${String(p2).padStart(2, '0')}-${String(p1).padStart(2, '0')}`];
                        } else {
                            selectedDates = [`${yyyy}-${String(p1).padStart(2, '0')}-${String(p2).padStart(2, '0')}`];
                        }
                    }
                }
            } else {
                selectedDates = [raw];
            }
        } else {
            selectedDates = availableCityDatesMap[city] || [];
        }

        if (selectedDates.length === 0) {
            alert(`No live available dates found for ${city}. Please check the calendar or pick a custom date.`);
            return;
        }

        // Open panel immediately
        const panel = document.getElementById('centersSelectionPanel');
        const container = document.getElementById('discoveredDatesTablesContainer');
        const summary = document.getElementById('scanResultsSummary');

        panel.classList.remove('hidden');
        panel.className = 'lg:col-span-12 bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200';

        container.innerHTML = `
            <div id="scanningProgressNotice" class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-between text-xs text-amber-300 animate-pulse">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-spinner fa-spin text-amber-400"></i>
                    <span>Starting sequential scan across <strong>${selectedDates.length} date(s)</strong> in <strong>${city}</strong>...</span>
                </div>
            </div>
        `;
        accumulatedDateTables = [];

        setScanningButtonState(city);
        scanAbortController = new AbortController();

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            for (let i = 0; i < selectedDates.length; i++) {
                if (scanAbortController.signal.aborted) break;

                const targetDate = selectedDates[i];
                summary.innerText = `Scanning Date ${i + 1} of ${selectedDates.length} (${targetDate})...`;
                
                const progEl = document.getElementById('scanningProgressNotice');
                if (progEl) {
                    progEl.innerHTML = `
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-spinner fa-spin text-amber-400"></i>
                            <span>Scanning Date <strong>${i + 1}/${selectedDates.length}</strong> (${targetDate}) in <strong>${city}</strong>...</span>
                        </div>
                    `;
                }

                try {
                    const res = await fetch("{{ route('admin.slots.hold.scan_date') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ category_id: categoryId, city: city, date: targetDate }),
                        signal: scanAbortController.signal
                    });

                    const data = await res.json();

                    if (data && data.success) {
                        accumulatedDateTables.push(data);
                        appendDateTableCard(data, i + 1);
                        try {
                            localStorage.setItem('hold_date_tables_result', JSON.stringify({
                                category_id: categoryId,
                                city: city,
                                tables: accumulatedDateTables
                            }));
                        } catch(e) {}
                    }
                } catch(err) {
                    if (err.name === 'AbortError') break;
                    console.error(`Error scanning date ${targetDate}:`, err);
                }
            }

            // Finished all dates
            const totalCentersFound = accumulatedDateTables.reduce((acc, curr) => acc + (curr.total_centers || (curr.centers ? curr.centers.length : 0)), 0);
            const scannedDatesCount = selectedDates.length;

            const progEl = document.getElementById('scanningProgressNotice');
            if (progEl) {
                progEl.className = 'p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-between text-xs text-emerald-300';
                progEl.innerHTML = `
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-400"></i>
                        <span>Scan Complete: <strong>${scannedDatesCount} date(s)</strong> checked in <strong>${city}</strong> (Found <strong>${totalCentersFound} test center(s)</strong>).</span>
                    </div>
                `;
            }
            summary.innerText = `Completed scan: Found ${totalCentersFound} test center(s) across ${scannedDatesCount} date(s).`;

        } catch (e) {
            if (e.name !== 'AbortError') {
                console.error('Scan error:', e);
            }
        } finally {
            resetScanButtonState();
        }
    }

    // Append single Date Table Card immediately upon receipt
    function appendDateTableCard(data, index) {
        const container = document.getElementById('discoveredDatesTablesContainer');
        if (!container) return;

        const profEl = document.getElementById('professionSelectedLabel');
        const currentProfName = (profEl ? profEl.innerText : 'Worker').split('[')[0].trim() || 'Worker';

        const centers = data.centers || [];
        const dateFormatted = data.date_formatted || data.date;
        const targetDate = data.date;

        let rowsHtml = '';
        if (centers.length === 0) {
            rowsHtml = `
                <tr>
                    <td colspan="6" class="py-4 px-4 text-center text-slate-500 italic text-[11px]">
                        <i class="fa-solid fa-triangle-exclamation text-amber-400 mr-1"></i> No test centers open on this date.
                    </td>
                </tr>
            `;
        } else {
            rowsHtml = centers.map((c, cIdx) => {
                const hash = c.mother_hash || c.session_id || '';
                // Store in memory for instant modal viewing
                if (hash) {
                    rawResponseHashStore[hash] = {
                        center_name: c.center_name,
                        exam_date: targetDate,
                        date_formatted: dateFormatted,
                        city: c.city,
                        address: c.center_address,
                        mother_hash: hash,
                        raw_payload: c.raw_response || c
                    };
                }

                const shortHash = hash ? (hash.length > 24 ? hash.substring(0, 14) + '...' + hash.substring(hash.length - 8) : hash) : 'N/A';
                const mapLink = c.location_link || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(c.center_name + ', ' + (c.city || 'Bangladesh'))}`;

                return `
                    <tr class="hover:bg-slate-900/90 transition-all group">
                        <td class="py-3 px-4 text-slate-400 font-mono text-[11px] align-top">${cIdx + 1}</td>
                        <td class="py-3 px-4 align-top">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                    <span class="font-bold text-white text-xs">${c.center_name}</span>
                                    <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-300 border border-amber-500/20 font-bold font-mono text-[10px] flex items-center gap-1 shrink-0" title="Exam Start Time">
                                        <i class="fa-regular fa-clock text-[9px] text-amber-400"></i>
                                        <span>${c.start_time || '09:30 AM'}</span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 font-mono text-[10px] pl-4 flex-wrap">
                                    <span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-sky-300 font-semibold flex items-center gap-1" title="${hash}">
                                        <i class="fa-solid fa-hashtag text-[9px] text-sky-400"></i>
                                        <span>${shortHash}</span>
                                    </span>
                                    ${hash ? `
                                        <button type="button" onclick="copyHashText('${hash}')" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-[10px] transition-all cursor-pointer" title="Copy Full Hash">
                                            <i class="fa-regular fa-copy"></i>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-purple-300 font-bold text-[11px] align-top">
                            <div class="truncate max-w-[200px]" title="${currentProfName}">
                                <span>${currentProfName}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-slate-300 text-[11px] align-top">
                            <div class="space-y-1">
                                <div>${c.center_address || 'N/A'}</div>
                                <div>
                                    <a href="${mapLink}" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-500/10 text-amber-300 border border-amber-500/20 hover:bg-amber-500/20 transition-all font-bold text-[10px]">
                                        <i class="fa-solid fa-map-location-dot text-[10px]"></i> Map
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 align-top">
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    ${(c.already_held || c.available_seats === 0) ? `
                                        <span class="px-2.5 py-1 rounded-xl bg-rose-500/15 text-rose-300 border border-rose-500/30 text-[11px] font-bold font-mono inline-flex items-center gap-1.5 shadow-sm">
                                            <i class="fa-solid fa-lock text-rose-400 text-[10px]"></i>
                                            <span>0 / ${c.total_seats || 10} Free (Locked)</span>
                                        </span>
                                    ` : `
                                        <span class="px-2.5 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/25 text-[11px] font-bold font-mono inline-flex items-center gap-1.5 shadow-sm">
                                            <i class="fa-solid fa-chair text-emerald-400 text-[10px]"></i>
                                            <span>${(c.available_seats !== undefined && c.available_seats !== null && c.available_seats !== 'Available') ? c.available_seats : 7} / ${c.total_seats || 10} Free</span>
                                        </span>
                                    `}
                                    ${c.already_held ? '<span class="px-2 py-0.5 rounded bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[10px] font-bold flex items-center gap-1"><i class="fa-solid fa-lock text-[9px]"></i> System Locked</span>' : ''}
                                </div>
                                <div class="text-[10px] font-mono text-slate-400 pl-0.5">
                                    Capacity: ${c.total_seats || 10} Seats, Status: ${(c.available_seats !== undefined && c.available_seats !== null && c.available_seats !== 'Available') ? c.available_seats : 7} Free Seats
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 align-top">
                            <span class="px-2.5 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300 text-[10px] font-bold">
                                ${c.city || 'N/A'}
                            </span>
                        </td>
                        <td class="py-3 px-4 align-top text-right">
                            <div class="flex items-center justify-end gap-2.5 whitespace-nowrap">
                                <select id="seat-qty-${index}-${cIdx}" class="px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-amber-300 font-mono font-bold outline-none cursor-pointer" title="Select number of seats to hold">
                                    <option value="all" selected>All Available Seats</option>
                                    <option value="1">1 Seat Only</option>
                                    <option value="2">2 Seats</option>
                                    <option value="3">3 Seats</option>
                                    <option value="5">5 Seats</option>
                                    <option value="10">10 Seats</option>
                                </select>
                                <button type="button" onclick="holdSingleCenterFromTable('${targetDate}', '${c.center_name.replace(/'/g, "\\'")}', '${hash}', ${index}, ${cIdx}, ${c.available_seats || 7})" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-bold text-xs shadow-md transition-all active:scale-95 cursor-pointer">
                                    <i class="fa-solid fa-lock text-xs"></i>
                                    <span class="whitespace-nowrap">⚡ Hold All Seats</span>
                                </button>
                                <button type="button" onclick="openRawResponseModal('${hash}')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-sky-300 hover:text-white text-xs font-bold transition-all cursor-pointer" title="View Response JSON">
                                    <i class="fa-solid fa-code text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        const tableCardHtml = `
            <div class="date-table-card rounded-2xl bg-slate-950/80 border border-slate-800 overflow-hidden shadow-lg animate-in fade-in slide-in-from-bottom-2 duration-300" id="date-table-${index}">
                <!-- Date Header -->
                <div class="px-4 py-3 bg-gradient-to-r from-slate-900 via-slate-850 to-slate-900 border-b border-slate-800 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xs">
                            <i class="fa-regular fa-calendar-check"></i>
                        </span>
                        <div>
                            <h4 class="text-xs font-bold text-white tracking-wide">${dateFormatted}</h4>
                            <p class="text-[10px] font-mono text-slate-400">${targetDate}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        ${centers.length > 0 ? `
                            <button type="button" onclick="holdAllCentersForDate('${targetDate}')" class="px-3 py-1 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 font-bold text-[11px] inline-flex items-center gap-1.5 transition-all cursor-pointer active:scale-95">
                                <i class="fa-solid fa-lock text-[10px]"></i>
                                <span>⚡ Hold All Centers For This Date</span>
                            </button>
                        ` : ''}
                        <span class="px-2.5 py-0.5 rounded-full ${centers.length > 0 ? 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-300' : 'bg-slate-800 border border-slate-700 text-slate-400'} text-[10px] font-bold">
                            ${centers.length} Center(s) Found
                        </span>
                    </div>
                </div>

                <!-- Centers Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/60 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider border-b border-slate-800/80">
                            <tr>
                                <th class="py-2.5 px-4 w-12">#</th>
                                <th class="py-2.5 px-4">Test Center & Mother Hash</th>
                                <th class="py-2.5 px-4">Profession / Trade</th>
                                <th class="py-2.5 px-4">Location / Address</th>
                                <th class="py-2.5 px-4">Available Seats</th>
                                <th class="py-2.5 px-4 w-24">City</th>
                                <th class="py-2.5 px-4 w-96 text-right">Action Controls</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-850">
                            ${rowsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', tableCardHtml);
        populateTargetCenterDropdown();
    }

    // Target Center Lock Panel Helper Functions
    function populateTargetCenterDropdown() {
        const targetSelect = document.getElementById('targetCenterSelect');
        if (!targetSelect) return;

        const centersMap = {};
        accumulatedDateTables.forEach(table => {
            const list = table.centers || [];
            list.forEach(c => {
                const name = c.center_name || 'Test Center';
                if (!centersMap[name]) {
                    centersMap[name] = {
                        name: name,
                        shiftsCount: 0,
                        totalFreeSeats: 0,
                        dates: new Set()
                    };
                }
                centersMap[name].shiftsCount++;
                centersMap[name].totalFreeSeats += Math.max(1, parseInt(c.available_seats) || 10);
                centersMap[name].dates.add(table.date);
            });
        });

        const centerNames = Object.keys(centersMap);
        
        targetSelect.innerHTML = '';
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.disabled = true;
        defaultOpt.selected = true;
        defaultOpt.innerText = centerNames.length > 0 
            ? `রেজাল্ট থেকে সেন্টার সিলেক্ট করুন (${centerNames.length} Centers)...`
            : `প্রথমে স্লট চেক/স্ক্যান করুন...`;
        targetSelect.appendChild(defaultOpt);

        if (centerNames.length === 0) {
            updateTargetCenterDetails();
            return;
        }

        centerNames.forEach(name => {
            const info = centersMap[name];
            const opt = document.createElement('option');
            opt.value = name;
            opt.innerText = `${name} (${info.shiftsCount} shift(s), ${info.totalFreeSeats} seat(s) total)`;
            targetSelect.appendChild(opt);
        });

        if (centerNames.length > 0) {
            targetSelect.selectedIndex = 1;
            updateTargetCenterDetails();
        }
    }

    function toggleLockTargetCenterPanel(isCheck) {
        const wrapper = document.getElementById('targetCenterDropdownWrapper');
        if (wrapper) {
            if (isCheck) {
                wrapper.classList.remove('hidden');
                populateTargetCenterDropdown();
            } else {
                wrapper.classList.add('hidden');
                const summary = document.getElementById('targetCenterSummaryBanner');
                if (summary) summary.classList.add('hidden');
            }
        }
    }

    function updateTargetCenterDetails() {
        const targetSelect = document.getElementById('targetCenterSelect');
        const lockBtn = document.getElementById('lockTargetCenterBtn');
        const summaryBanner = document.getElementById('targetCenterSummaryBanner');
        const nameLabel = document.getElementById('targetCenterNameLabel');
        const shiftsLabel = document.getElementById('targetCenterShiftsCountLabel');
        const seatsLabel = document.getElementById('targetCenterSeatsCountLabel');
        const btnText = document.getElementById('lockTargetBtnText');

        if (!targetSelect || !targetSelect.value) {
            if (lockBtn) {
                lockBtn.disabled = true;
                lockBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            if (summaryBanner) summaryBanner.classList.add('hidden');
            return;
        }

        const selectedCenterName = targetSelect.value;
        let shiftsCount = 0;
        let totalFreeSeats = 0;

        accumulatedDateTables.forEach(table => {
            const list = table.centers || [];
            list.forEach(c => {
                if (c.center_name === selectedCenterName) {
                    shiftsCount++;
                    totalFreeSeats += Math.max(1, parseInt(c.available_seats) || 10);
                }
            });
        });

        if (nameLabel) nameLabel.innerText = selectedCenterName;
        if (shiftsLabel) shiftsLabel.innerText = `${shiftsCount} Shift(s)`;
        if (seatsLabel) seatsLabel.innerText = `${totalFreeSeats} Total Seats`;
        if (btnText) btnText.innerText = `⚡ Lock ${selectedCenterName} (0 Seats)`;

        if (lockBtn) {
            lockBtn.disabled = false;
            lockBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (summaryBanner) summaryBanner.classList.remove('hidden');
    }

    async function lockSelectedTargetCenter() {
        const targetSelect = document.getElementById('targetCenterSelect');
        if (!targetSelect || !targetSelect.value) {
            alert('অনুগ্রহ করে ড্রপডাউন থেকে একটি টার্গেট সেন্টার সিলেক্ট করুন!');
            return;
        }

        const selectedCenterName = targetSelect.value;
        const categoryId = document.getElementById('scan_category_id').value;
        const city = document.getElementById('scan_city').value;
        const profLabel = document.getElementById('professionSelectedLabel').innerText || 'Worker';
        const categoryName = profLabel.split('[')[0].trim() || 'Worker';

        let targetSlots = [];

        accumulatedDateTables.forEach(table => {
            const dateCenters = table.centers || [];
            dateCenters.forEach(c => {
                if (c.center_name === selectedCenterName && (c.mother_hash || c.session_id)) {
                    const hash = c.mother_hash || c.session_id;
                    const seatsToLock = Math.max(1, parseInt(c.available_seats) || 10);
                    for (let k = 0; k < seatsToLock; k++) {
                        targetSlots.push({
                            mother_hash: hash,
                            center_name: c.center_name,
                            city: city,
                            category_id: categoryId,
                            category_name: categoryName,
                            exam_date: table.date
                        });
                    }
                }
            });
        });

        if (targetSlots.length === 0) {
            alert(`এই সেন্টার (${selectedCenterName}) এর জন্য কোনো সিফট হ্যাশ পাওয়া যায়নি!`);
            return;
        }

        const confirmRes = await Swal.fire({
            title: `Lock All Shifts for ${selectedCenterName}?`,
            html: `
                <div class="text-left text-xs space-y-2 text-slate-300">
                    <p>সিস্টেম ক্যান্ডিডেট পুল একাউন্ট ব্যবহার করে টেকামুল সার্ভারে <b>${selectedCenterName}</b> এর সকল সিফটের সিট লক করে সিট সংখ্যা সম্পূর্ণ <b>০ (Zero)</b> করে ফেলবে।</p>
                    <div class="p-2.5 rounded bg-slate-900 border border-amber-500/40 text-amber-300 font-mono space-y-1">
                        <div>• Target Center: ${selectedCenterName}</div>
                        <div>• Total Seats to Hold: ${targetSlots.length} Seats</div>
                        <div>• Auto-Renew Engine: 20-Minute Continuous Unbroken Loop</div>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '⚡ Lock Target Center Now (0 Seats)',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#334155',
            background: '#1e293b',
            color: '#fff'
        });

        if (!confirmRes.isConfirmed) return;

        await executeBulkHolding(targetSlots, `Holding all shifts for ${selectedCenterName} (0 Seats Lock)`);
    }

    // Dedicated In-Table Live Seat Locking Generator
    async function holdSingleCenterFromTable(targetDate, centerName, hash, index, cIdx, probedAvailSeats = 7) {
        const qtyEl = document.getElementById(`seat-qty-${index}-${cIdx}`);
        const qtyVal = qtyEl ? qtyEl.value : 'all';

        const categoryId = document.getElementById('scan_category_id').value;
        const city = document.getElementById('scan_city').value;
        const profLabel = document.getElementById('professionSelectedLabel').innerText || 'Worker';
        const categoryName = profLabel.split('[')[0].trim() || 'Worker';

        let repeatCount = (typeof probedAvailSeats === 'number' && probedAvailSeats > 0) ? probedAvailSeats : 7;
        if (qtyVal !== 'all') {
            repeatCount = parseInt(qtyVal, 10) || 1;
        }

        // Fetch pre-assigned candidate pool accounts for each seat row
        let accounts = [];
        try {
            const accRes = await fetch(`{{ route('admin.slots.hold.assigned_accounts') }}?count=${repeatCount}`);
            const accData = await accRes.json();
            if (accData.success && accData.accounts) {
                accounts = accData.accounts;
            }
        } catch(e) {}

        if (!accounts || accounts.length < repeatCount) {
            accounts = [];
            for (let i = 0; i < repeatCount; i++) {
                accounts.push({
                    seat_number: i + 1,
                    email: `pool__${100000 + i}@wafidmaster.com`,
                    token: '',
                    token_short: 'Candidate Token',
                    status: 'queued'
                });
            }
        }

        const hashPrefix = hash.substring(0, 10).replace(/[^a-zA-Z0-9]/g, '');
        const container = document.getElementById('discoveredDatesTablesContainer');
        if (!container) return;

        const shortHash = hash ? (hash.length > 24 ? hash.substring(0, 14) + '...' + hash.substring(hash.length - 8) : hash) : 'N/A';

        // Build Dedicated In-Table Live Seat Lock Card
        const cardHtml = `
            <div id="live-table-card-${hashPrefix}" class="bg-slate-900/95 border border-amber-500/40 rounded-2xl p-5 space-y-4 shadow-2xl mb-6 backdrop-blur-xl animate-fade-in">
                <!-- Header -->
                <div class="flex items-center justify-between flex-wrap gap-3 pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-amber-400 animate-ping shrink-0"></span>
                            <span>${centerName}</span>
                            <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-mono text-xs border border-amber-500/30">${repeatCount} Seats Requested</span>
                        </h3>
                        <p class="text-xs text-slate-400 font-mono mt-1 flex items-center gap-2 flex-wrap">
                            <span>Mother Hash: <strong class="text-sky-300">${shortHash}</strong></span>
                            <span>| Date: <strong class="text-slate-200">${targetDate}</strong></span>
                            <span>| City: <strong class="text-slate-200">${city}</strong></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="start-btn-${hashPrefix}" onclick="executeInTableSequentialLocking('${hash}', ${categoryId}, '${city}', '${targetDate}', '${centerName.replace(/'/g, "\\'")}', '${categoryName.replace(/'/g, "\\'")}', ${repeatCount}, '${hashPrefix}')" class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-600 via-orange-600 to-amber-600 hover:from-amber-500 hover:to-orange-500 text-white font-bold text-xs shadow-lg transition-all active:scale-95 cursor-pointer flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt text-xs"></i>
                            <span>⚡ Start Live Seat Lock</span>
                        </button>
                        <button type="button" onclick="document.getElementById('live-table-card-${hashPrefix}').remove()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs font-bold transition-all cursor-pointer">
                            <i class="fa-solid fa-xmark"></i> Close
                        </button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs font-mono font-bold">
                        <span id="status-label-${hashPrefix}" class="text-sky-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-spinner fa-spin text-sky-400"></i>
                            <span>Pre-assigned ${accounts.length} Candidate Pool Account(s) in Table...</span>
                        </span>
                        <span id="progress-percent-${hashPrefix}" class="text-amber-400">0%</span>
                    </div>
                    <div class="w-full bg-slate-950 h-3 rounded-full overflow-hidden border border-slate-800">
                        <div id="progress-bar-${hashPrefix}" class="bg-gradient-to-r from-amber-500 via-orange-500 to-emerald-500 h-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-950/90">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-900/90 text-[11px] font-bold text-slate-400 uppercase font-mono border-b border-slate-800">
                                <th class="py-3 px-4"># Seat</th>
                                <th class="py-3 px-4">Assigned Candidate Pool Account</th>
                                <th class="py-3 px-4">Authorization Bearer Token</th>
                                <th class="py-3 px-4 text-center">Seat Lock Status</th>
                                <th class="py-3 px-4 text-center">Temp Seat ID</th>
                                <th class="py-3 px-4 text-right">20-Min Countdown / Action</th>
                            </tr>
                        </thead>
                        <tbody id="live-tbody-${hashPrefix}">
                            ${accounts.map((acc, aIdx) => `
                                <tr id="row-${hashPrefix}-${aIdx}" class="border-b border-slate-800/60 hover:bg-slate-900/60 transition-all font-mono text-xs">
                                    <td class="py-3 px-4 font-bold text-amber-400">Seat #${aIdx + 1}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-1.5 text-slate-200 font-bold">
                                            <i class="fa-regular fa-user text-purple-400"></i>
                                            <span>${acc.email}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-400 font-mono text-[11px]" id="token-td-${hashPrefix}-${aIdx}">
                                        <span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-sky-300" title="${acc.token || ''}">
                                            ${acc.token_short || 'Pending Token'}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center" id="status-td-${hashPrefix}-${aIdx}">
                                        <span class="px-2.5 py-1 rounded-xl bg-slate-800 text-slate-400 border border-slate-700 text-[11px] font-bold inline-flex items-center gap-1">
                                            <i class="fa-regular fa-clock"></i> Queued
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center text-slate-500 font-mono text-[11px]" id="seatid-td-${hashPrefix}-${aIdx}">
                                        -
                                    </td>
                                    <td class="py-3 px-4 text-right" id="action-td-${hashPrefix}-${aIdx}">
                                        <span class="text-slate-600 text-[11px] italic">Waiting...</span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        // Prepend table card at the very top of discovered tables container
        container.insertAdjacentHTML('afterbegin', cardHtml);

        // Auto-scroll smooth to the new dedicated live lock table
        document.getElementById(`live-table-card-${hashPrefix}`).scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Auto-start live lock execution
        executeInTableSequentialLocking(hash, categoryId, city, targetDate, centerName, categoryName, repeatCount, hashPrefix, accounts);
    }

    // In-Table Sequential Real-Time Locking Engine
    async function executeInTableSequentialLocking(hash, categoryId, city, targetDate, centerName, categoryName, totalSeats, hashPrefix, preAssignedAccounts = []) {
        const startBtn = document.getElementById(`start-btn-${hashPrefix}`);
        if (startBtn) {
            startBtn.disabled = true;
            startBtn.className = 'px-4 py-2 rounded-xl bg-slate-800 text-slate-500 font-bold text-xs cursor-not-allowed';
            startBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Locking Seats...`;
        }

        let lockedCount = 0;
        let failedCount = 0;

        for (let i = 0; i < totalSeats; i++) {
            const rowEl = document.getElementById(`row-${hashPrefix}-${i}`);
            const tokenTd = document.getElementById(`token-td-${hashPrefix}-${i}`);
            const statusTd = document.getElementById(`status-td-${hashPrefix}-${i}`);
            const seatIdTd = document.getElementById(`seatid-td-${hashPrefix}-${i}`);
            const actionTd = document.getElementById(`action-td-${hashPrefix}-${i}`);
            const statusLabel = document.getElementById(`status-label-${hashPrefix}`);
            const progressBar = document.getElementById(`progress-bar-${hashPrefix}`);
            const progressPercent = document.getElementById(`progress-percent-${hashPrefix}`);

            const assignedAcc = preAssignedAccounts[i] || {};
            const email = assignedAcc.email || '';

            if (statusTd) {
                statusTd.innerHTML = `
                    <span class="px-2.5 py-1 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[11px] font-bold inline-flex items-center gap-1 animate-pulse">
                        <i class="fa-solid fa-spinner fa-spin text-amber-400"></i> Requesting Taqamul...
                    </span>
                `;
            }

            let isSuccess = false;
            let resData = null;

            // Retries on 429 Rate Limit
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    const lockRes = await fetch("{{ route('admin.slots.hold.lock_single_seat') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            mother_hash: hash,
                            category_id: categoryId,
                            city: city,
                            exam_date: targetDate,
                            center_name: centerName,
                            category_name: categoryName,
                            email: email
                        })
                    });

                    if (lockRes.ok) {
                        resData = await lockRes.json();
                        if (resData.success) {
                            isSuccess = true;
                            break;
                        }
                    } else if (lockRes.status === 429) {
                        if (statusTd) {
                            statusTd.innerHTML = `
                                <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px] font-bold inline-flex items-center gap-1">
                                    <i class="fa-solid fa-triangle-exclamation"></i> 429 Limit (Retrying 1s...)
                                </span>
                            `;
                        }
                        await new Promise(r => setTimeout(r, 1000));
                        continue;
                    }
                } catch(e) {}
            }

            if (isSuccess && resData) {
                lockedCount++;
                const tempSeatId = resData.temp_seat_id || 'Locked';
                const holdId = resData.hold ? resData.hold.id : null;

                if (tokenTd && resData.token_short) {
                    tokenTd.innerHTML = `<span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-sky-300 font-mono text-[11px]" title="${resData.token_short}">${resData.token_short}</span>`;
                }

                if (statusTd) {
                    statusTd.innerHTML = `
                        <span class="px-2.5 py-1 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[11px] font-bold inline-flex items-center gap-1 shadow-sm">
                            <i class="fa-solid fa-circle-check text-emerald-400"></i> LOCKED
                        </span>
                    `;
                }

                if (seatIdTd) {
                    seatIdTd.innerHTML = `<span class="px-2 py-0.5 rounded bg-slate-900 text-purple-300 font-mono font-bold border border-slate-800">ID: ${tempSeatId}</span>`;
                }

                if (actionTd) {
                    actionTd.innerHTML = `
                        <div class="flex items-center justify-end gap-2">
                            <span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-amber-300 font-mono text-[11px] font-bold" id="timer-${hashPrefix}-${i}">
                                ⏱️ 20:00
                            </span>
                            ${holdId ? `
                                <button type="button" onclick="releaseSingleInTableHold(${holdId}, '${hashPrefix}', ${i})" class="px-2 py-1 rounded bg-rose-950 hover:bg-rose-900 text-rose-300 hover:text-white text-[10px] font-bold transition-all cursor-pointer">
                                    <i class="fa-solid fa-trash"></i> Release
                                </button>
                            ` : ''}
                        </div>
                    `;
                }

                // Start 20-min countdown timer for this row
                startInTableRowTimer(`timer-${hashPrefix}-${i}`, 1200);

            } else {
                failedCount++;
                if (statusTd) {
                    statusTd.innerHTML = `
                        <span class="px-2.5 py-1 rounded-xl bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[11px] font-bold inline-flex items-center gap-1">
                            <i class="fa-solid fa-circle-xmark"></i> Failed
                        </span>
                    `;
                }
            }

            // Update Progress Bar
            const currentPercent = Math.round(((i + 1) / totalSeats) * 100);
            if (progressBar) progressBar.style.width = `${currentPercent}%`;
            if (progressPercent) progressPercent.innerText = `${currentPercent}%`;
            if (statusLabel) {
                statusLabel.innerHTML = `<i class="fa-solid fa-bolt text-amber-400"></i> Locking Seat #${i + 1} of ${totalSeats} (${lockedCount} Locked, ${failedCount} Failed)`;
            }

            // 400ms pause between seats
            if (i < totalSeats - 1) {
                await new Promise(r => setTimeout(r, 400));
            }
        }

        const statusLabelFinal = document.getElementById(`status-label-${hashPrefix}`);
        if (statusLabelFinal) {
            statusLabelFinal.innerHTML = `
                <span class="text-emerald-400 font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check text-emerald-400"></i>
                    🎉 All ${lockedCount} Seat(s) Locked Successfully inside Table!
                </span>
            `;
        }
    }

    // In-table row timer countdown
    function startInTableRowTimer(elementId, initialSeconds) {
        let seconds = initialSeconds;
        const interval = setInterval(() => {
            seconds--;
            const el = document.getElementById(elementId);
            if (!el) {
                clearInterval(interval);
                return;
            }

            if (seconds <= 0) {
                clearInterval(interval);
                el.innerText = '⏱️ Expired';
                el.className = 'px-2 py-0.5 rounded bg-rose-950 text-rose-400 font-mono text-[11px] font-bold';
                return;
            }

            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            el.innerText = `⏱️ ${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }, 1000);
    }

    // Release single hold from live table row
    async function releaseSingleInTableHold(holdId, hashPrefix, rowIdx) {
        try {
            const res = await fetch("{{ route('admin.slots.hold.release') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ hold_id: holdId })
            });

            const data = await res.json();
            if (data.success) {
                const statusTd = document.getElementById(`status-td-${hashPrefix}-${rowIdx}`);
                const actionTd = document.getElementById(`action-td-${hashPrefix}-${rowIdx}`);
                if (statusTd) {
                    statusTd.innerHTML = `
                        <span class="px-2.5 py-1 rounded-xl bg-slate-800 text-slate-500 border border-slate-700 text-[11px] font-bold">
                            Released
                        </span>
                    `;
                }
                if (actionTd) actionTd.innerHTML = `<span class="text-slate-600 italic">Released</span>`;
            }
        } catch(e) {}
    }

    // Helper: Distribute total seats (default 10) evenly across center hashes
    function buildDistributedSlotsForCenter(centerList, targetTotal = 10, categoryId, categoryName, city, defaultDate) {
        let slots = [];
        if (!centerList || centerList.length === 0) return slots;

        const validHashes = centerList.map(c => ({
            hash: c.mother_hash || c.session_id || '',
            center_name: c.center_name,
            city: c.city || city,
            exam_date: c.exam_date || defaultDate
        })).filter(c => c.hash !== '');

        if (validHashes.length === 0) return slots;

        const numHashes = validHashes.length;
        const basePerHash = Math.floor(targetTotal / numHashes);
        let remainder = targetTotal % numHashes;

        validHashes.forEach((item, idx) => {
            const countForThisHash = basePerHash + (idx < remainder ? 1 : 0);
            for (let k = 0; k < countForThisHash; k++) {
                slots.push({
                    mother_hash: item.hash,
                    center_name: item.center_name,
                    city: item.city,
                    category_id: categoryId,
                    category_name: categoryName,
                    exam_date: item.exam_date
                });
            }
        });

        return slots;
    }

    // Hold all centers for a specific date (Max 10 seats total per center)
    async function holdAllCentersForDate(targetDate) {
        const tableData = accumulatedDateTables.find(t => t.date === targetDate);
        if (!tableData || !tableData.centers || tableData.centers.length === 0) {
            alert(`No centers found for ${targetDate}`);
            return;
        }

        const categoryId = document.getElementById('scan_category_id').value;
        const city = document.getElementById('scan_city').value;
        const profLabel = document.getElementById('professionSelectedLabel').innerText || 'Worker';
        const categoryName = profLabel.split('[')[0].trim() || 'Worker';

        // Group centers by center_name
        const groupedByCenter = {};
        tableData.centers.forEach(c => {
            const name = c.center_name || 'Test Center';
            if (!groupedByCenter[name]) groupedByCenter[name] = [];
            groupedByCenter[name].push(c);
        });

        let slots = [];
        Object.keys(groupedByCenter).forEach(cName => {
            const cSlots = buildDistributedSlotsForCenter(groupedByCenter[cName], 10, categoryId, categoryName, city, targetDate);
            slots = slots.concat(cSlots);
        });

        if (slots.length === 0) {
            alert('No valid center hashes found to hold.');
            return;
        }

        // Remove date table card immediately from view
        document.querySelectorAll('.date-table-card').forEach(card => {
            if (card.innerHTML.includes(targetDate)) {
                card.remove();
            }
        });
        accumulatedDateTables = accumulatedDateTables.filter(t => t.date !== targetDate);
        try {
            localStorage.setItem('hold_date_tables_result', JSON.stringify({
                category_id: categoryId,
                city: city,
                tables: accumulatedDateTables
            }));
        } catch(e) {}

        await executeBulkHolding(slots, `Holding ${slots.length} total seats for centers on ${targetDate}`);
    }

    // Hold all discovered centers across all dates (Max 10 seats total per center/date)
    async function holdAllDiscoveredSlots() {
        if (accumulatedDateTables.length === 0) {
            alert('No scanned results available. Please scan centers first.');
            return;
        }

        const categoryId = document.getElementById('scan_category_id').value;
        const city = document.getElementById('scan_city').value;
        const profLabel = document.getElementById('professionSelectedLabel').innerText || 'Worker';
        const categoryName = profLabel.split('[')[0].trim() || 'Worker';

        let slots = [];
        accumulatedDateTables.forEach(table => {
            const dateCenters = table.centers || [];
            if (dateCenters.length > 0) {
                // Group by center_name
                const groupedByCenter = {};
                dateCenters.forEach(c => {
                    const name = c.center_name || 'Test Center';
                    if (!groupedByCenter[name]) groupedByCenter[name] = [];
                    groupedByCenter[name].push(c);
                });

                Object.keys(groupedByCenter).forEach(cName => {
                    const cSlots = buildDistributedSlotsForCenter(groupedByCenter[cName], 10, categoryId, categoryName, city, table.date);
                    slots = slots.concat(cSlots);
                });
            }
        });

        if (slots.length === 0) {
            alert('No valid center hashes discovered to hold.');
            return;
        }

        const confirmRes = await Swal.fire({
            title: `Hold All Discovered Slots?`,
            text: `System will lock all discovered seats across all centers using candidate pool tokens with 20-minute unbroken auto-renewal.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Lock All Discovered Seats',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#334155',
            background: '#1e293b',
            color: '#fff'
        });

        if (!confirmRes.isConfirmed) return;

        // Clear scan results table immediately from view
        const container = document.getElementById('discoveredDatesTablesContainer');
        if (container) container.innerHTML = '';
        accumulatedDateTables = [];
        try { localStorage.removeItem('hold_date_tables_result'); } catch(e) {}

        await executeBulkHolding(slots, `Holding all available seats across all scanned dates`);
    }

    // Save pending slots and redirect instantly to Slot Vault for live terminal stream
    async function executeBulkHolding(slots, label = 'Holding slots') {
        if (!slots || slots.length === 0) return;

        try {
            localStorage.setItem('pending_bulk_hold_slots', JSON.stringify({
                slots: slots,
                label: label,
                timestamp: Date.now()
            }));
        } catch(e) {}

        window.location.href = "{{ route('admin.slots.held_vault') }}?start_hold=1";
    }

    function toggleDateCheckboxGroup(dIdx) {
        const checkboxes = document.querySelectorAll(`.date-center-checkbox[data-didx="${dIdx}"]`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        updateBulkHoldButtonCount();
    }

    function selectAllItems(check) {
        document.querySelectorAll('.date-center-checkbox').forEach(cb => cb.checked = check);
        document.querySelectorAll('.center-checkbox').forEach(cb => cb.checked = check);
        updateBulkHoldButtonCount();
    }

    function getSelectedSlots() {
        let allSelectedSlots = [];

        if (currentViewMode === 'date') {
            const checkboxes = document.querySelectorAll('.date-center-checkbox:checked');
            checkboxes.forEach(cb => {
                const dIdx = parseInt(cb.dataset.didx);
                const cIdx = parseInt(cb.dataset.cidx);
                if (scannedDatesGroupedData[dIdx] && scannedDatesGroupedData[dIdx].centers[cIdx]) {
                    const centerObj = scannedDatesGroupedData[dIdx].centers[cIdx];
                    if (centerObj.slots && Array.isArray(centerObj.slots)) {
                        allSelectedSlots = allSelectedSlots.concat(centerObj.slots);
                    }
                }
            });
        } else {
            const checkboxes = document.querySelectorAll('.center-checkbox:checked');
            checkboxes.forEach(cb => {
                const idx = parseInt(cb.value);
                if (scannedCentersData[idx] && scannedCentersData[idx].all_slots) {
                    allSelectedSlots = allSelectedSlots.concat(scannedCentersData[idx].all_slots);
                }
            });
        }

        return allSelectedSlots;
    }

    function updateBulkHoldButtonCount() {
        const selectedSlots = getSelectedSlots();
        const selectedSlotsCount = selectedSlots.length;

        const btnText = document.getElementById('bulkHoldBtnText');
        const btn = document.getElementById('executeBulkHoldBtn');

        if (selectedSlotsCount > 0) {
            btnText.innerText = `Hold Selected Slots (${selectedSlotsCount} Slots)`;
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            btnText.innerText = `No Slots Selected`;
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    // Step 2: Execute Bulk Hold on Selected Centers & Dates
    async function executeBulkHold() {
        const allSelectedSlots = getSelectedSlots();

        if (allSelectedSlots.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Slot Selected', text: 'Please select at least 1 center / date to hold.', background: '#1e293b', color: '#fff' });
            return;
        }

        const duration = parseInt(document.getElementById('holdDurationSelect').value) || 0;

        const confirmRes = await Swal.fire({
            title: `Hold ${allSelectedSlots.length} Slots?`,
            text: `System will lock all ${allSelectedSlots.length} slots using candidate pool tokens with continuous 20-min auto-renewal loop.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Lock All Slots',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#334155',
            background: '#1e293b',
            color: '#fff'
        });

        if (!confirmRes.isConfirmed) return;

        const btn = document.getElementById('executeBulkHoldBtn');
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Locking ${allSelectedSlots.length} Slots...`;
        logMessage(`🔒 Initiating bulk lock for ${allSelectedSlots.length} slots with candidate pool tokens...`, 'info');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch("{{ route('admin.slots.hold.bulk_hold') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ slots: allSelectedSlots, duration_minutes: duration })
            });

            const data = await res.json();

            if (data.success) {
                clearScanResults();
                logMessage(`🎉 Successfully locked ${data.held_count} slots! Active 20-min auto-renew loop is running.`, 'success');
                Swal.fire({
                    icon: 'success',
                    title: 'Slots Locked Successfully!',
                    text: data.message,
                    background: '#1e293b',
                    color: '#fff'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                logMessage(`⚠️ Bulk hold failed: ${data.message}`, 'error');
                Swal.fire({ icon: 'error', title: 'Hold Failed', text: data.message, background: '#1e293b', color: '#fff' });
            }
        } catch (e) {
            logMessage(`❌ Network error during bulk hold: ${e.message}`, 'error');
            Swal.fire({ icon: 'error', title: 'Network Error', text: e.message, background: '#1e293b', color: '#fff' });
        } finally {
            btn.disabled = false;
            updateBulkHoldButtonCount();
        }
    }

    // Release All Active Holds
    async function releaseAllActiveHolds() {
        const confirmRes = await Swal.fire({
            title: 'Release All Active Holds?',
            text: 'This will immediately release all locked slots on Taqamul servers and terminate auto-renew countdowns.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Release All',
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#334155',
            background: '#1e293b',
            color: '#fff'
        });

        if (!confirmRes.isConfirmed) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch("{{ route('admin.slots.hold.release_all') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Released', text: data.message, background: '#1e293b', color: '#fff' }).then(() => {
                    window.location.reload();
                });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1e293b', color: '#fff' });
        }
    }

    // Active Holds Timers and Auto-Renew Engine
    function initExistingTimers() {
        document.querySelectorAll('.hold-card').forEach(card => {
            const holdId = card.dataset.holdId;
            const sec = parseInt(card.dataset.seconds) || 1200;
            activeHoldTimers[holdId] = {
                remainingSeconds: sec,
                isRenewing: false
            };
        });
    }

    function startHoldCountdown(holdId, seconds = 1200) {
        activeHoldTimers[holdId] = {
            remainingSeconds: seconds,
            isRenewing: false
        };
    }

    function tickAllTimers() {
        Object.keys(activeHoldTimers).forEach(holdId => {
            const t = activeHoldTimers[holdId];
            if (!t) return;

            if (t.remainingSeconds > 0) {
                t.remainingSeconds--;
            }

            const m = Math.floor(t.remainingSeconds / 60);
            const s = t.remainingSeconds % 60;
            const timeStr = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

            const cdEl = document.getElementById(`countdown-${holdId}`);
            if (cdEl) cdEl.innerText = timeStr;

            const progressEl = document.getElementById(`progress-bar-${holdId}`);
            if (progressEl) {
                const pct = Math.min(100, Math.max(0, (t.remainingSeconds / 1200) * 100));
                progressEl.style.width = `${pct}%`;
                if (t.remainingSeconds <= 120) {
                    progressEl.className = 'bg-gradient-to-r from-rose-500 to-amber-500 h-2.5 rounded-full transition-all duration-1000 animate-pulse';
                }
            }

            // Continuous Unbroken Auto-Renew Trigger: When less than 90 seconds remaining
            if (t.remainingSeconds <= 90 && !t.isRenewing) {
                triggerAutoRenew(holdId);
            }
        });
    }

    async function triggerAutoRenew(holdId) {
        if (!activeHoldTimers[holdId] || activeHoldTimers[holdId].isRenewing) return;
        activeHoldTimers[holdId].isRenewing = true;
        logMessage(`⚡ Hold #${holdId} countdown reached renewal threshold (<90s). Executing unbroken 20-min auto-renew...`, 'warn');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch("{{ route('admin.slots.hold.renew') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ hold_id: holdId })
            });

            const data = await res.json();

            if (data.success) {
                if (data.status === 'expired') {
                    logMessage(`ℹ️ Hold #${holdId} reached target hold duration and ended gracefully.`, 'info');
                    delete activeHoldTimers[holdId];
                    const card = document.getElementById(`hold-card-${holdId}`);
                    if (card) card.remove();
                    return;
                }

                activeHoldTimers[holdId].remainingSeconds = data.seconds_remaining || 1200;
                activeHoldTimers[holdId].isRenewing = false;
                
                const renewEl = document.getElementById(`renew-count-${holdId}`);
                if (renewEl && data.renew_count) {
                    renewEl.innerText = `${data.renew_count} times`;
                }

                logMessage(`✅ Hold #${holdId} successfully extended for another 20 minutes!`, 'success');
            } else {
                logMessage(`⚠️ Hold #${holdId} auto-renew failed: ${data.message}`, 'error');
                activeHoldTimers[holdId].isRenewing = false;
            }
        } catch (e) {
            logMessage(`❌ Network error while renewing hold #${holdId}: ${e.message}`, 'error');
            activeHoldTimers[holdId].isRenewing = false;
        }
    }

    async function releaseHold(holdId, centerName) {
        const confirmRes = await Swal.fire({
            title: `Release Hold for ${centerName}?`,
            text: 'This will unlock the reservation on Taqamul servers immediately. The button will change to Re-Hold so you can lock it again with 1 click.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Release Slot',
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#334155',
            background: '#1e293b',
            color: '#fff'
        });

        if (!confirmRes.isConfirmed) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch("{{ route('admin.slots.hold.release') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ hold_id: holdId })
            });

            const data = await res.json();

            if (data.success) {
                logMessage(`🔓 Hold #${holdId} (${centerName}) released. Ready for Re-Hold.`, 'info');
                
                // Clear active timer
                if (activeHoldTimers[holdId]) {
                    clearInterval(activeHoldTimers[holdId].interval);
                    delete activeHoldTimers[holdId];
                }

                // Update Badge to Released
                const badgeWrap = document.getElementById(`hold-badge-wrap-${holdId}`);
                if (badgeWrap) {
                    badgeWrap.innerHTML = `
                        <span id="hold-badge-${holdId}" class="px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 font-mono text-xs font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-lock-open text-[10px]"></i> Released / Free on Server
                        </span>
                    `;
                }

                // Update Countdown and Progress
                const countdownEl = document.getElementById(`countdown-${holdId}`);
                if (countdownEl) {
                    countdownEl.innerText = 'Slot Released (Free)';
                    countdownEl.className = 'font-mono font-extrabold text-slate-400 text-xs';
                }

                const progressBar = document.getElementById(`progress-bar-${holdId}`);
                if (progressBar) {
                    progressBar.style.width = '0%';
                    progressBar.className = 'bg-slate-700 h-2.5 rounded-full transition-all duration-500';
                }

                // Transform Release Button to ⚡ Re-Hold Slot Button
                const releaseBtnWrap = document.getElementById(`release-btn-wrap-${holdId}`);
                if (releaseBtnWrap) {
                    releaseBtnWrap.innerHTML = `
                        <button type="button" id="rehold-btn-${holdId}" onclick="reHoldSlot(${holdId})" class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 via-orange-600 to-amber-500 hover:from-amber-400 hover:to-orange-500 text-white font-extrabold text-xs shadow-lg shadow-orange-500/30 flex items-center gap-1.5 transition-all active:scale-95 cursor-pointer animate-pulse">
                            <i class="fa-solid fa-rotate-right" id="rehold-icon-${holdId}"></i>
                            <span id="rehold-text-${holdId}">⚡ Re-Hold Slot</span>
                        </button>
                    `;
                }

                // Update card border to amber
                const card = document.getElementById(`hold-card-${holdId}`);
                if (card) {
                    card.className = 'hold-card p-5 rounded-2xl bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border border-amber-500/60 shadow-xl space-y-4 relative overflow-hidden transition-all duration-300';
                }

                Swal.fire({ 
                    icon: 'success', 
                    title: 'Slot Released!', 
                    text: 'The slot is now free on Taqamul server. The button is now "⚡ Re-Hold Slot" so you can lock it again with 1 click anytime.', 
                    background: '#1e293b', 
                    color: '#fff' 
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Failed to Release', text: data.message, background: '#1e293b', color: '#fff' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1e293b', color: '#fff' });
        }
    }

    async function reHoldSlot(holdId) {
        const card = document.getElementById(`hold-card-${holdId}`);
        if (!card) return;

        const motherHash = card.getAttribute('data-mother-hash');
        const categoryId = card.getAttribute('data-category-id') || 59;
        const categoryName = card.getAttribute('data-category-name') || 'Tailoring';
        const city = card.getAttribute('data-city') || 'Rajshahi';
        const examDate = card.getAttribute('data-exam-date') || '2026-08-25';
        const centerName = card.getAttribute('data-center-name') || 'Test Center';

        const reholdBtn = document.getElementById(`rehold-btn-${holdId}`);
        const reholdText = document.getElementById(`rehold-text-${holdId}`);
        const reholdIcon = document.getElementById(`rehold-icon-${holdId}`);

        if (reholdBtn) {
            reholdBtn.disabled = true;
            if (reholdText) reholdText.innerText = 'Locking...';
            if (reholdIcon) reholdIcon.className = 'fa-solid fa-spinner fa-spin';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch("{{ route('admin.slots.hold.start') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    mother_hash: motherHash,
                    category_id: categoryId,
                    category_name: categoryName,
                    city: city,
                    exam_date: examDate,
                    center_name: centerName,
                    duration_minutes: 0
                })
            });

            const data = await res.json();

            if (data.success && data.hold) {
                const newHold = data.hold;
                const newHoldId = newHold.id;
                logMessage(`🔒 Slot re-held successfully on Taqamul (Hold #${newHoldId} / ${centerName})`, 'success');

                // Update card ID & attributes
                card.id = `hold-card-${newHoldId}`;
                card.setAttribute('data-hold-id', newHoldId);
                card.setAttribute('data-seconds', '1200');
                card.className = 'hold-card p-5 rounded-2xl bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border border-emerald-500/40 shadow-xl space-y-4 relative overflow-hidden transition-all duration-300';

                // Update Badge to Active
                const badgeWrap = card.querySelector('#hold-badge-wrap-' + holdId) || card.querySelector('[id^="hold-badge-wrap-"]');
                if (badgeWrap) {
                    badgeWrap.id = `hold-badge-wrap-${newHoldId}`;
                    badgeWrap.innerHTML = `
                        <span id="hold-badge-${newHoldId}" class="px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-mono text-xs font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-circle text-[8px] animate-pulse"></i> Auto-Renewing
                        </span>
                    `;
                }

                // Update Countdown
                const countdownEl = card.querySelector('#countdown-' + holdId) || card.querySelector('[id^="countdown-"]');
                if (countdownEl) {
                    countdownEl.id = `countdown-${newHoldId}`;
                    countdownEl.className = 'font-mono font-extrabold text-amber-300 text-sm';
                    countdownEl.innerText = '20:00';
                }

                const progressBar = card.querySelector('#progress-bar-' + holdId) || card.querySelector('[id^="progress-bar-"]');
                if (progressBar) {
                    progressBar.id = `progress-bar-${newHoldId}`;
                    progressBar.className = 'bg-gradient-to-r from-amber-500 to-emerald-400 h-2.5 rounded-full transition-all duration-1000';
                    progressBar.style.width = '100%';
                }

                // Update Actions / Switch back to Release button
                const releaseBtnWrap = card.querySelector('#release-btn-wrap-' + holdId) || card.querySelector('[id^="release-btn-wrap-"]');
                if (releaseBtnWrap) {
                    releaseBtnWrap.id = `release-btn-wrap-${newHoldId}`;
                    releaseBtnWrap.innerHTML = `
                        <button type="button" onclick="releaseHold(${newHoldId}, '${centerName.replace(/'/g, "\\'")}')" class="px-4 py-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer">
                            <i class="fa-solid fa-lock-open"></i>
                            <span>Release</span>
                        </button>
                    `;
                }

                // Start 20-minute countdown loop
                startHoldCountdown(newHoldId, 1200);

                Swal.fire({
                    icon: 'success',
                    title: '⚡ Slot Re-Held Successfully!',
                    text: 'Slot locked on Taqamul. Continuous 20-minute auto-renewal engine active.',
                    background: '#1e293b',
                    color: '#fff'
                });
            } else if (data.require_pool_login || res.status === 401 || (data.message && (data.message.includes('expired') || data.message.includes('Unable to hold')))) {
                if (reholdBtn) {
                    reholdBtn.disabled = false;
                    if (reholdText) reholdText.innerText = '⚡ Re-Hold Slot';
                    if (reholdIcon) reholdIcon.className = 'fa-solid fa-rotate-right';
                }
                logMessage(`🔑 Token expired during Re-Hold. Launching Automated Pool Authentication Modal...`, 'warning');
                
                // Open Login Modal with Live Streaming Terminal & Auto-Resume
                const heldEmail = card.getAttribute('data-held-email') || 'pool__790329@wafidmaster.com';
                triggerAutoLoginForPool(() => {
                    logMessage(`✅ Authentication complete! Resuming ⚡ Re-Hold Slot #${holdId}...`, 'info');
                    reHoldSlot(holdId);
                }, heldEmail);
            } else {
                if (reholdBtn) {
                    reholdBtn.disabled = false;
                    if (reholdText) reholdText.innerText = '⚡ Re-Hold Slot';
                    if (reholdIcon) reholdIcon.className = 'fa-solid fa-rotate-right';
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Re-Hold Failed',
                    text: data.message || 'Unable to re-lock slot on Taqamul.',
                    background: '#1e293b',
                    color: '#fff'
                });
            }
        } catch (e) {
            if (reholdBtn) {
                reholdBtn.disabled = false;
                if (reholdText) reholdText.innerText = '⚡ Re-Hold Slot';
                if (reholdIcon) reholdIcon.className = 'fa-solid fa-rotate-right';
            }
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1e293b', color: '#fff' });
        }
    }
</script>
@endpush
@endsection