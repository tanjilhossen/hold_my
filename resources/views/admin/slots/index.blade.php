@extends('layouts.app')

@section('title', 'Live Slot Checker & Mother Hash Explorer - Taqamul Admin')

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

    // Fallback if formattedOccupations is empty
    if (empty($formattedOccupations)) {
        $formattedOccupations = [
            ['id' => 159, 'occupation_id' => 2061, 'english_name' => 'Load and unload workers', 'category_name' => 'Load and unload workers', 'arabic_name' => 'عمال التحميل والتنزيل', 'full_label' => 'Load and unload workers (عمال التحميل والتنزيل) [Cat ID: 159]'],
            ['id' => 161, 'occupation_id' => 2045, 'english_name' => 'Plumbing', 'category_name' => 'Plumbing', 'arabic_name' => 'السباكة', 'full_label' => 'Plumbing (السباكة) [Cat ID: 161]'],
            ['id' => 162, 'occupation_id' => 2050, 'english_name' => 'Electrical Works', 'category_name' => 'Electrical Works', 'arabic_name' => 'الأعمال الكهربائية', 'full_label' => 'Electrical Works (الأعمال الكهربائية) [Cat ID: 162]'],
            ['id' => 163, 'occupation_id' => 2055, 'english_name' => 'Welding', 'category_name' => 'Welding', 'arabic_name' => 'اللحام', 'full_label' => 'Welding (اللحام) [Cat ID: 163]'],
            ['id' => 59, 'occupation_id' => 2030, 'english_name' => 'Tailoring', 'category_name' => 'Tailoring', 'arabic_name' => 'الخياطة', 'full_label' => 'Tailoring (الخياطة) [Cat ID: 59]'],
        ];
    }
@endphp

<div class="space-y-6">

    <!-- Top Slim Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/90 border border-slate-800 p-4 rounded-2xl shadow-xl">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 to-cyan-500 flex items-center justify-center text-white shadow-lg shadow-sky-600/30">
                <i class="fa-solid fa-magnifying-glass-location text-lg"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold text-white tracking-tight">Live Slot Checker</h1>
                    <span class="px-2.5 py-0.5 rounded-full bg-sky-500/20 border border-sky-500/40 text-sky-300 text-xs font-mono font-bold">
                        Live Auto-Sync
                    </span>
                </div>
                <p class="text-xs text-slate-400">Search real-time Taqamul exam seats, probe test center locations, and explore mother hashes</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.slots.hold') }}" class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white shadow-lg shadow-amber-600/20 text-xs font-bold transition-all flex items-center gap-2">
                <i class="fa-solid fa-lock text-amber-200"></i>
                <span>Hold Slot & Auto-Renew</span>
                @php $activeHoldsCount = \App\Models\SlotHold::active()->count(); @endphp
                @if($activeHoldsCount > 0)
                    <span class="px-1.5 py-0.2 rounded-full bg-black/30 text-amber-200 font-mono text-[10px]">
                        {{ $activeHoldsCount }}
                    </span>
                @endif
            </a>
            <a href="{{ route('admin.slots.book') }}" class="px-3.5 py-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 text-xs font-bold transition-all flex items-center gap-2">
                <i class="fa-solid fa-calendar-check text-emerald-400"></i>
                <span>Book Slot</span>
            </a>
            <a href="{{ route('admin.slots.vault') }}" class="px-3.5 py-2 rounded-xl bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-bold transition-all flex items-center gap-2">
                <i class="fa-solid fa-vault text-indigo-400"></i>
                <span>Hash Vault</span>
            </a>
        </div>
    </div>

    <!-- Search & Filter Form -->
    <div class="bg-slate-900/80 border border-slate-800 p-6 rounded-3xl backdrop-blur-xl shadow-lg space-y-6">
        <form id="slotCheckerForm" onsubmit="handleSlotCheck(event)" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            
            <!-- Category / Profession Searchable Dropdown -->
            <div class="space-y-2 relative" id="professionDropdownContainer">
                <label class="block text-xs font-bold text-slate-300">
                    <i class="fa-solid fa-briefcase text-sky-400 mr-1"></i> Profession / Trade <span class="text-rose-400">*</span>
                </label>
                <input type="hidden" id="scan_category_id" value="">
                <input type="hidden" id="category_id" name="category_id" value="">
                
                <!-- Trigger Button -->
                <button type="button" onclick="toggleDropdown('profession', event)" id="professionTrigger" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:border-transparent outline-none flex items-center justify-between gap-2 text-left cursor-pointer transition-all hover:border-slate-600">
                    <span id="professionSelectedLabel" class="truncate text-slate-400">Select Profession / Trade...</span>
                    <i class="fa-solid fa-chevron-down text-slate-400 text-[10px] transition-transform duration-200" id="professionChevron"></i>
                </button>

                <!-- Downward Popover Menu with Search Box -->
                <div id="professionMenu" class="hidden absolute left-0 top-full mt-2 w-full min-w-[320px] md:min-w-[380px] bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl z-[99999] p-3 space-y-2.5 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Search Input Box -->
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-sky-400 text-xs"></i>
                        <input type="text" id="professionSearchInput" oninput="filterProfessions(this.value)" placeholder="Search profession or ID..." autocomplete="off"
                               class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-400 focus:border-sky-500 outline-none">
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
                <input type="hidden" id="scan_city" value="Dhaka">
                <input type="hidden" id="city" name="city" value="Dhaka">

                <!-- Trigger Button -->
                <button type="button" onclick="toggleDropdown('city', event)" id="cityTrigger" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none flex items-center justify-between gap-2 text-left cursor-pointer transition-all hover:border-slate-600">
                    <span id="citySelectedLabel" class="truncate text-white font-bold">Dhaka</span>
                    <i class="fa-solid fa-chevron-down text-slate-400 text-[10px] transition-transform duration-200" id="cityChevron"></i>
                </button>

                <!-- Downward Popover Menu with Search Box -->
                <div id="cityMenu" class="hidden absolute left-0 top-full mt-2 w-full min-w-[260px] bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl z-[99999] p-3 space-y-2.5 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Search Input Box -->
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-emerald-400 text-xs"></i>
                        <input type="text" id="citySearchInput" oninput="filterCities(this.value)" placeholder="Search city..." autocomplete="off"
                               class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-400 focus:border-emerald-500 outline-none">
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

            <!-- Available Dates (Live) Searchable Dropdown -->
            <div class="space-y-2 relative" id="dateDropdownContainer">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-300">
                        <i class="fa-solid fa-calendar-days text-amber-400 mr-1"></i> Available Dates
                    </label>
                    <span id="availableDatesCountBadge" class="text-[10px] text-amber-400 font-mono bg-amber-500/10 px-2 py-0.5 rounded-full border border-amber-500/20">0 Live</span>
                </div>
                <input type="hidden" id="scan_date" value="">
                <input type="hidden" id="exam_date" name="exam_date" value="">

                <!-- Trigger Button -->
                <button type="button" onclick="toggleDropdown('date', event)" id="dateTrigger" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none flex items-center justify-between gap-2 text-left cursor-pointer transition-all hover:border-slate-600">
                    <span id="dateSelectedLabel" class="truncate text-slate-300 font-bold">All Available Dates</span>
                    <i class="fa-solid fa-chevron-down text-slate-400 text-[10px] transition-transform duration-200" id="dateChevron"></i>
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

        <!-- Round-Robin Pool Indicator & Manage Pool Link -->
        <div class="border-t border-slate-800/80 pt-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="font-bold text-slate-300">Candidate Pool Capacity:</span>
                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-bold text-[11px] border border-amber-500/30">
                    <i class="fa-solid fa-users-gear mr-1 text-amber-400"></i> {{ count($poolAccounts) }} Accounts Active
                </span>
                <a href="{{ route('admin.settings.index') }}" class="text-amber-400 hover:text-amber-300 font-bold text-[11px] underline">
                    (Manage Pool)
                </a>
            </div>
            <div class="text-[11px] text-slate-400">
                Hold Duration: <span class="text-emerald-400 font-bold">20-Min Unbroken Continuous Auto-Renewal</span>
            </div>
        </div>
    </div>

    <!-- Live Results Section -->
    <div id="resultsContainer" class="hidden space-y-6">

        <!-- 4 Stats Overview Cards in Top Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Card 1: Centers Found -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 flex items-center gap-3.5 shadow-lg">
                <div class="w-11 h-11 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-building-circle-check"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Centers Found</p>
                    <p class="text-base lg:text-lg font-black text-white truncate" id="statCenterCount">0 Centers</p>
                </div>
            </div>

            <!-- Card 2: Mother Hashes -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 flex items-center gap-3.5 shadow-lg">
                <div class="w-11 h-11 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-fingerprint"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Mother Hashes</p>
                    <p class="text-base lg:text-lg font-black text-white truncate" id="statHashCount">0 Hashes</p>
                </div>
            </div>

            <!-- Card 3: Target Location -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 flex items-center gap-3.5 shadow-lg">
                <div class="w-11 h-11 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Target Location</p>
                    <p class="text-base lg:text-lg font-black text-white truncate" id="statLocation">-</p>
                </div>
            </div>

            <!-- Card 4: Profession / Trade -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 flex items-center gap-3.5 shadow-lg">
                <div class="w-11 h-11 rounded-2xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Profession / Trade</p>
                    <p class="text-base lg:text-lg font-black text-white truncate" id="statProfession" title="Profession">Load and unload workers</p>
                </div>
            </div>

        </div>

        <!-- Centers & Hashes Table View -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 space-y-4 shadow-xl">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-table-list text-sky-400"></i>
                        <span>Available Test Centers & Mother Hashes</span>
                    </h3>
                    <p class="text-xs text-slate-400" id="tableSubtitle">Mother Session Hash mapped with designated training center</p>
                </div>
                <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
                    <button type="button" onclick="copyAllHashes()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                        <i class="fa-regular fa-copy text-emerald-400"></i>
                        <span>Copy All Hashes (JSON)</span>
                    </button>
                    <button type="button" onclick="releaseAllSlotLocks(this)" 
                            class="px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-sm active:scale-95" 
                            title="Release 20-min temporary lock on all discovered centers">
                        <i class="fa-solid fa-lock-open text-rose-400"></i>
                        <span>No need, Release All</span>
                    </button>
                    <button type="button" onclick="clearResults()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 border border-slate-700 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer" title="Clear Table & Cached Results">
                        <i class="fa-solid fa-trash-can"></i>
                        <span>Clear</span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-950/80 border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4">Mother Session Hash</th>
                            <th class="py-3.5 px-4">Center Name & Location</th>
                            <th class="py-3.5 px-4 w-28 text-center">Exam Time</th>
                            <th class="py-3.5 px-4 w-24 text-center">Status</th>
                            <th class="py-3.5 px-4 w-48 text-center">Action / Lock Slot</th>
                        </tr>
                    </thead>
                    <tbody id="centersTableBody" class="divide-y divide-slate-800/60 font-medium">
                        <!-- Dynamically Injected Rows -->
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Raw Hashes Array Box -->
        <div class="p-5 rounded-3xl bg-slate-900/90 border border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-code text-indigo-400"></i> All Mother Hashes Array (Bulk Payload Format)
                </span>
                <span class="text-[11px] text-slate-500 font-mono">temporary_seats payload</span>
            </div>
            <pre id="rawHashesJson" class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-emerald-400 font-mono text-xs overflow-x-auto select-all custom-scrollbar privacy-hash-mask"></pre>
        </div>

    </div>

    <!-- Empty State -->
    <div id="emptyState" class="hidden text-center py-12 p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
        <div class="w-16 h-16 rounded-3xl bg-slate-800/60 text-slate-500 flex items-center justify-center text-2xl mx-auto">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h4 class="text-base font-bold text-white" id="emptyTitle">No Slots Found</h4>
        <p class="text-xs text-slate-400 max-w-md mx-auto" id="emptyMessage">No exam seats are currently open for this profession in the selected city on this date. Try selecting another city or date.</p>
    </div>

</div>

<!-- ==================================================== -->
<!-- 🤖 LIVE BACKGROUND AUTO-LOGIN PROGRESS POPUP MODAL -->
<!-- ==================================================== -->
<div id="loginProgressModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden transition-opacity duration-300">
    <div class="bg-slate-900 border border-slate-700/90 rounded-3xl p-6 max-w-lg w-full mx-4 shadow-2xl shadow-slate-950/90 space-y-5 relative overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="absolute -right-10 -top-10 w-44 h-44 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-10 -bottom-10 w-44 h-44 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex items-center gap-3.5 border-b border-slate-800 pb-4 relative z-10">
            <div class="w-12 h-12 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center text-2xl shrink-0 shadow-lg shadow-sky-500/10">
                <i class="fa-solid fa-robot animate-bounce"></i>
            </div>
            <div>
                <h3 class="text-base font-black text-white flex items-center gap-2">
                    <span>Autonomous Login in Progress</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                </h3>
                <p class="text-xs text-slate-400">No active session found. Logging in via background engine to acquire a Bearer Token...</p>
            </div>
        </div>

        <!-- Target Account Info -->
        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3 text-xs relative z-10">
            <div class="space-y-0.5 overflow-hidden">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Target Account (Round-Robin Pool)</span>
                <p class="font-bold text-sky-400 truncate" id="modalCandidateEmail">Loading candidate account...</p>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-sky-500/20 text-sky-300 text-[10px] font-mono shrink-0 border border-sky-500/30">
                <i class="fa-solid fa-shield-halved mr-1"></i> Auto-Login Bot
            </span>
        </div>

        <!-- Step-by-Step Live Progress List -->
        <div class="space-y-2 text-xs relative z-10" id="loginStepsList">
            <div id="step1" class="flex items-center gap-3 p-3 rounded-xl bg-slate-800/60 text-sky-300 border border-sky-500/30 transition-all">
                <i class="fa-solid fa-circle-notch fa-spin text-sky-400"></i>
                <span class="font-medium">Step 1: Initializing Chrome Browser Engine...</span>
            </div>
            <div id="step2" class="flex items-center gap-3 p-3 rounded-xl bg-slate-950/60 text-slate-500 border border-slate-800 transition-all">
                <i class="fa-regular fa-circle text-slate-600"></i>
                <span>Step 2: Entering Email and Password Credentials...</span>
            </div>
            <div id="step3" class="flex items-center gap-3 p-3 rounded-xl bg-slate-950/60 text-slate-500 border border-slate-800 transition-all">
                <i class="fa-regular fa-circle text-slate-600"></i>
                <span>Step 3: Solving Google reCAPTCHA v2 with CapSolver AI...</span>
            </div>
            <div id="step4" class="flex items-center gap-3 p-3 rounded-xl bg-slate-950/60 text-slate-500 border border-slate-800 transition-all">
                <i class="fa-regular fa-circle text-slate-600"></i>
                <span>Step 4: Fetching Real-Time Login OTP from Mail Server...</span>
            </div>
            <div id="step5" class="flex items-center gap-3 p-3 rounded-xl bg-slate-950/60 text-slate-500 border border-slate-800 transition-all">
                <i class="fa-regular fa-circle text-slate-600"></i>
                <span>Step 5: Entering OTP and Acquiring Bearer Token...</span>
            </div>
        </div>

        <!-- Footer Live Progress Text -->
        <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400 relative z-10">
            <span id="modalLiveTimer" class="font-mono text-emerald-400 flex items-center gap-1.5">
                <i class="fa-regular fa-clock"></i> Elapsed Time: 0.0s
            </span>
            <span class="text-slate-500">Autonomous Process</span>
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

@push('scripts')
<script>
    // Bulletproof Global Dropdown Toggle (Matching Hold Slot)
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

    // Data Sources
    const formattedOccupations = @json($formattedOccupations);
    const formattedCities = @json($cities);
    const CACHE_STORAGE_KEY = 'taqamul_slot_results_cache';

    let currentHashes = [];
    let progressTimer = null;
    let timerSeconds = 0;
    let availableCityDatesMap = {};
    let currentAvailableCities = [];

    // Initialize Dropdowns
    document.addEventListener('DOMContentLoaded', () => {
        renderProfessionOptions(formattedOccupations);
        renderCityOptions(formattedCities);
        renderDateOptions([]);
        
        // Initial load: do NOT pre-select any profession by default
        const catInput = document.getElementById('category_id') || document.getElementById('scan_category_id');
        if (catInput && catInput.value) {
            selectProfession(catInput.value, false);
        }

        // Restore cached table results on page load
        try {
            const cachedStr = localStorage.getItem(CACHE_STORAGE_KEY);
            if (cachedStr) {
                const cached = JSON.parse(cachedStr);
                if (cached && cached.data && cached.data.centers && cached.data.centers.length > 0) {
                    if (cached.form) {
                        if (cached.form.category_id) selectProfession(cached.form.category_id, false);
                        if (cached.form.city) selectCity(cached.form.city, false);
                        if (cached.form.exam_date) selectDate(cached.form.exam_date, false);
                    }
                    renderResultsTable(cached.data, false);
                    if (cached.cached_at) {
                        document.getElementById('tableSubtitle').innerHTML = `Mother Session Hash mapped with designated training center <span class="text-emerald-400 ml-1 font-mono text-[10px] bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">(Cached at ${cached.cached_at})</span>`;
                    }
                }
            }
        } catch (e) {
            console.warn('Error loading cached results:', e);
        }
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

                            const authInput = document.getElementById('auth_token');
                            if (authInput) authInput.value = data.token;

                            const btn = document.getElementById('noActiveAcBtn');
                            const btnText = document.getElementById('noActiveAcText');
                            const btnIcon = document.getElementById('noActiveAcIcon');
                            if (btnIcon) btnIcon.className = 'fa-solid fa-circle-check text-emerald-400';
                            if (btnText) btnText.innerText = `Active AC: ${data.email || 'Logged In'}`;
                            if (btn) {
                                btn.classList.remove('bg-amber-500/20', 'border-amber-500/40', 'text-amber-300', 'bg-rose-500/20', 'border-rose-500/40', 'text-rose-300');
                                btn.classList.add('bg-emerald-500/20', 'border-emerald-500/40', 'text-emerald-300');
                            }

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
                        <button type="button" onclick="isAutoLoginInProgress=false;triggerAutoLoginForPool(${onSuccessCallback ? onSuccessCallback.toString() : 'null'})" class="px-2.5 py-1 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-bold text-[11px] shrink-0 transition-all cursor-pointer">
                            <i class="fa-solid fa-rotate-right mr-1"></i> Retry
                        </button>
                    </div>
                `;
            }
            isAutoLoginInProgress = false;
        }
    }

    async function loadAvailableDatesForCategory(categoryId) {
        const cityLabel = document.getElementById('citySelectedLabel');
        const dateLabel = document.getElementById('dateSelectedLabel');
        const datesBadge = document.getElementById('availableDatesCountBadge');
        
        if (cityLabel) cityLabel.innerHTML = `<span class="flex items-center gap-1.5 text-slate-400"><i class="fa-solid fa-circle-notch fa-spin text-emerald-400 text-xs"></i> Loading cities...</span>`;
        if (dateLabel) dateLabel.innerHTML = `<span class="flex items-center gap-1.5 text-slate-400"><i class="fa-solid fa-circle-notch fa-spin text-amber-400 text-xs"></i> Loading dates...</span>`;
        if (datesBadge) datesBadge.innerHTML = `<span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px] font-bold flex items-center gap-1"><i class="fa-solid fa-spinner fa-spin text-[9px]"></i> Checking...</span>`;
        
        try {
            const authEl = document.getElementById('auth_token');
            const authToken = authEl ? authEl.value.trim() : '';
            const res = await fetch(`{{ route('admin.slots.available_dates') }}?category_id=${categoryId}&auth_token=${encodeURIComponent(authToken)}`);
            const data = await res.json();
            
            if (res.status === 401 || (data && !data.success)) {
                availableCityDatesMap = {};
                currentAvailableCities = formattedCities;
                renderCityOptions(formattedCities);
                if (cityLabel) cityLabel.innerText = 'Select City';
                if (dateLabel) dateLabel.innerText = 'All Available Dates (0)';
                renderDateOptions([]);
                if (datesBadge) datesBadge.innerHTML = `<span class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 text-[10px] font-bold">0 Live</span>`;
                
                if (!isAutoLoginInProgress) {
                    triggerAutoLoginForPool(() => loadAvailableDatesForCategory(categoryId));
                }
                return;
            }

            if (data.success && data.cities && data.cities.length > 0) {
                availableCityDatesMap = data.city_dates_map || {};
                currentAvailableCities = data.cities;
                
                // Re-render City options with available dates count
                renderCityOptions(currentAvailableCities);
                
                // If current selected city is available, keep it; else select first available city
                const cityInput = document.getElementById('city') || document.getElementById('scan_city');
                const currentCity = cityInput ? cityInput.value : '';
                if (currentAvailableCities.includes(currentCity)) {
                    selectCity(currentCity);
                } else {
                    selectCity(currentAvailableCities[0]);
                }
            } else {
                // Fallback to all known cities if no available dates found
                availableCityDatesMap = {};
                currentAvailableCities = formattedCities;
                renderCityOptions(formattedCities);
                renderDateOptions([]);
                if (cityLabel) cityLabel.innerText = 'Select City / Division...';
                if (dateLabel) dateLabel.innerText = 'All Available Dates';
            }
        } catch (e) {
            console.error('Error loading available dates:', e);
            availableCityDatesMap = {};
            currentAvailableCities = formattedCities;
            renderCityOptions(formattedCities);
            renderDateOptions([]);
            if (cityLabel) cityLabel.innerText = 'Select City / Division...';
            if (dateLabel) dateLabel.innerText = 'All Available Dates';
        }
    }

    function selectProfession(id, close = true) {
        const item = formattedOccupations.find(o => String(o.id) === String(id)) || formattedOccupations.find(o => String(o.occupation_id) === String(id));
        if (item) {
            const catInput = document.getElementById('category_id') || document.getElementById('scan_category_id');
            if (catInput) catInput.value = item.id;
            const scanCatInput = document.getElementById('scan_category_id');
            if (scanCatInput) scanCatInput.value = item.id;

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

    // Render Profession Options List
    function renderProfessionOptions(items) {
        const listEl = document.getElementById('professionList');
        if (!listEl) return;
        listEl.innerHTML = '';
        const catInput = document.getElementById('category_id') || document.getElementById('scan_category_id');
        const currentVal = catInput ? catInput.value : '';

        if (!items || items.length === 0) {
            listEl.innerHTML = '<div class="p-3 text-center text-slate-500 text-xs font-medium">No professions match your search</div>';
            return;
        }

        items.forEach(item => {
            const isSelected = String(item.id) === String(currentVal);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${isSelected ? 'bg-sky-500/20 text-sky-300 font-bold border border-sky-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
            
            btn.innerHTML = `
                <span class="truncate">${item.english_name || item.name || ''} <span class="text-slate-500 text-[11px]">${item.arabic_name ? '(' + item.arabic_name + ')' : ''}</span></span>
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

    // Render City Options List
    function renderCityOptions(items) {
        const listEl = document.getElementById('cityList');
        if (!listEl) return;
        listEl.innerHTML = '';
        const cityInput = document.getElementById('city') || document.getElementById('scan_city');
        const currentVal = cityInput ? cityInput.value : '';

        const pool = (items && items.length > 0) ? items : formattedCities;

        if (pool.length === 0) {
            listEl.innerHTML = '<div class="p-3 text-center text-slate-500 text-xs font-medium">No available cities for this profession</div>';
            return;
        }

        pool.forEach(city => {
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
        const q = (query || '').trim().toLowerCase();
        const listEl = document.getElementById('cityList');
        if (!listEl) return;

        const buttons = listEl.querySelectorAll('button');
        buttons.forEach(btn => {
            const text = (btn.innerText || '').toLowerCase();
            if (!q || text.includes(q)) {
                btn.classList.remove('hidden');
            } else {
                btn.classList.add('hidden');
            }
        });
    }

    function selectCity(city, close = true) {
        const cityInput = document.getElementById('city') || document.getElementById('scan_city');
        if (cityInput) cityInput.value = city;
        
        const scanCityInput = document.getElementById('scan_city');
        if (scanCityInput) scanCityInput.value = city;

        const dates = availableCityDatesMap[city] || [];
        const countBadge = dates.length > 0 ? ` (${dates.length} Dates Available)` : '';
        const cityLabel = document.getElementById('citySelectedLabel');
        if (cityLabel) cityLabel.innerText = city + countBadge;
        
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
        const dateInput = document.getElementById('exam_date') || document.getElementById('scan_date');
        const currentVal = dateInput ? dateInput.value : '';

        if (!dates || dates.length === 0) {
            if (badge) badge.innerText = '0 Live';
            listEl.innerHTML = '<div class="p-3 text-center text-slate-500 text-xs font-medium">No active dates discovered for this city. Use Custom Date picker.</div>';
            const dateLabel = document.getElementById('dateSelectedLabel');
            if (dateLabel) dateLabel.innerText = 'All Available Dates';
            return;
        }

        if (badge) badge.innerText = `${dates.length} Live`;
        const dateLabel = document.getElementById('dateSelectedLabel');
        if (dateLabel && !currentVal) {
            dateLabel.innerText = `All Available Dates (${dates.length})`;
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
            const dayName = !isNaN(d.getTime()) ? d.toLocaleDateString('en-US', { weekday: 'short' }) : '';
            
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

            btn.onclick = () => selectDate(dateStr);
            listEl.appendChild(btn);
        });
    }

    // Select Date Action
    function selectDate(dateStr, close = true) {
        const dateInput = document.getElementById('exam_date') || document.getElementById('scan_date');
        if (dateInput) dateInput.value = dateStr;

        const scanDateInput = document.getElementById('scan_date');
        if (scanDateInput) scanDateInput.value = dateStr;

        const dateLabel = document.getElementById('dateSelectedLabel');
        if (dateLabel) {
            if (!dateStr) {
                dateLabel.innerText = 'All Available Dates';
            } else {
                const d = new Date(dateStr);
                const dayName = !isNaN(d.getTime()) ? ` (${d.toLocaleDateString('en-US', { weekday: 'short' })})` : '';
                dateLabel.innerText = dateStr + dayName;
            }
        }

        const customInput = document.getElementById('customDateInput');
        if (customInput) customInput.value = dateStr;
        if (close) closeDropdown('date');
    }

    // Custom Date input handler
    function selectCustomDate(val) {
        if (val) {
            document.getElementById('exam_date').value = val;
            const d = new Date(val);
            const dayName = !isNaN(d.getTime()) ? ` (${d.toLocaleDateString('en-US', { weekday: 'short' })})` : '';
            document.getElementById('dateSelectedLabel').innerText = `Custom: ${val}` + dayName;
            renderDateOptions(availableCityDatesMap[document.getElementById('city').value] || []);
        }
    }

    // Handle Form Submit & Live Check
    async function handleSlotCheck(e) {
        e.preventDefault();

        const categoryId = document.getElementById('category_id').value;
        const city = document.getElementById('city').value;
        const examDate = document.getElementById('exam_date').value;
        let authToken = document.getElementById('auth_token').value.trim();

        if (!categoryId) {
            alert('Please select a Profession / Trade first!');
            toggleDropdown('profession');
            return;
        }
        if (!city) {
            alert('Please select a City first!');
            toggleDropdown('city');
            return;
        }
        if (!examDate) {
            alert('Please select an Exam Date first!');
            toggleDropdown('date');
            return;
        }

        const btn = document.getElementById('checkBtn');
        const btnIcon = document.getElementById('btnIcon');
        const btnText = document.getElementById('btnText');
        const results = document.getElementById('resultsContainer');
        const empty = document.getElementById('emptyState');

        // 1. If manual token not provided, verify if we have an active valid token in the pool
        if (!authToken) {
            try {
                const statusRes = await fetch("{{ route('admin.slots.token_status') }}");
                const statusData = await statusRes.json();

                if (!statusData.has_active_token) {
                    // No active token -> Show Popup & Execute Background Login
                    const candidate = statusData.target_candidate;
                    if (!candidate) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No Accounts in Pool!',
                            text: 'No candidate accounts are configured in the pool. Please add candidates in Settings.',
                            background: '#1e293b',
                            color: '#fff'
                        });
                        return;
                    }

                    // Open Live Progress Popup
                    const loginSuccess = await showLoginProgressModal(candidate);
                    if (!loginSuccess) {
                        return; // Login failed
                    }
                }
            } catch (err) {
                console.warn('Token status check error:', err);
            }
        }

        // 2. Execute Live Slot Query
        btn.disabled = true;
        btnIcon.className = 'fa-solid fa-circle-notch fa-spin text-amber-300';
        btnText.innerText = 'Probing Slots & Hashes...';
        empty.classList.add('hidden');

        try {
            const res = await fetch("{{ route('admin.slots.check') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    category_id: categoryId,
                    city: city,
                    exam_date: examDate,
                    auth_token: authToken
                })
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'API Error!',
                    text: data.message || 'Failed to fetch slot data from Taqamul API.',
                    background: '#1e293b',
                    color: '#fff'
                });
                return;
            }

            if (data.count === 0) {
                document.getElementById('emptyTitle').innerText = 'No Slots Available';
                document.getElementById('emptyMessage').innerText = data.message;
                empty.classList.remove('hidden');
                results.classList.add('hidden');
                localStorage.removeItem(CACHE_STORAGE_KEY);
                return;
            }

            // Save & Render Results
            renderResultsTable(data, true, { category_id: categoryId, city: city, exam_date: examDate });

        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Network Error!',
                text: err.message,
                background: '#1e293b',
                color: '#fff'
            });
        } finally {
            btn.disabled = false;
            btnIcon.className = 'fa-solid fa-bolt text-amber-300';
            btnText.innerText = 'Check Slots & Mother Hashes';
        }
    }

    /**
     * Render Table and Top Stats
     */
    function renderResultsTable(data, saveToCache = true, formParams = null) {
        currentHashes = data.all_hashes || [];

        // 1. Render Top 4 Stat Cards
        document.getElementById('statCenterCount').innerText = `${data.count} Centers`;
        document.getElementById('statHashCount').innerText = `${currentHashes.length} Hashes`;
        document.getElementById('statLocation').innerText = `${data.city} • ${data.exam_date}`;
        document.getElementById('statProfession').innerText = data.profession || 'Selected Trade';

        // 2. Render Table Rows
        const tableBody = document.getElementById('centersTableBody');
        tableBody.innerHTML = '';

        data.centers.forEach((c, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-800/40 transition-colors border-b border-slate-800/60';
            
            tr.innerHTML = `
                <td class="py-4 px-4 text-center">
                    <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 font-bold text-xs inline-flex items-center justify-center">${idx + 1}</span>
                </td>

                <!-- Mother Hash with 1-Click Copy -->
                <td class="py-4 px-4 font-mono text-xs">
                    <div class="flex items-center gap-2 max-w-md">
                        <div class="px-3 py-1.5 rounded-xl bg-slate-950 border border-sky-500/30 text-sky-300 text-[11px] truncate flex-1 select-all privacy-hash-mask" title="${c.mother_hash}">
                            ${c.mother_hash}
                        </div>
                        <button type="button" onclick="copyHash('${c.mother_hash}', this)" 
                                class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold transition-all shrink-0 cursor-pointer flex items-center gap-1">
                            <i class="fa-regular fa-copy"></i>
                            <span>Copy</span>
                        </button>
                    </div>
                </td>

                <!-- Center Name & Location with Re-check Button -->
                <td class="py-4 px-4" id="centerCell_${idx}">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h4 class="font-bold text-white text-sm" id="centerName_${idx}">
                                ${c.center_name}
                            </h4>
                            ${(!c.is_probed || c.center_name.includes('Test Center #') || !c.location_link) ? `
                                <button type="button" onclick="recheckSingleHash('${c.mother_hash}', ${idx}, this)" 
                                        class="px-2 py-0.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 text-[11px] font-bold transition-all inline-flex items-center gap-1 cursor-pointer shrink-0"
                                        title="Re-probe this hash to get the exact center name">
                                    <i class="fa-solid fa-arrows-rotate"></i>
                                    <span>Re-check</span>
                                </button>
                            ` : ''}
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400" id="centerMeta_${idx}">
                            <span class="flex items-center gap-1">
                                <i class="fa-solid fa-location-dot text-rose-400 text-[11px]"></i>
                                <span>${c.center_address || c.city}</span>
                            </span>
                            ${c.location_link ? `
                                <a href="${c.location_link}" target="_blank" class="text-sky-400 hover:text-sky-300 font-bold text-[11px] flex items-center gap-1">
                                    <i class="fa-solid fa-map-location-dot"></i> Map
                                </a>
                            ` : ''}
                        </div>
                    </div>
                </td>

                <!-- Exam Time -->
                <td class="py-4 px-4 text-center font-bold text-slate-200">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-[11px]">
                        ${c.start_time || '09:30 AM'}
                    </span>
                </td>

                <!-- Status / Available Seats -->
                <td class="py-4 px-4 text-center">
                    ${(() => {
                        const avail = (c.available_seats !== undefined && c.available_seats !== null) ? Number(c.available_seats) : 7;
                        const total = (c.total_seats && Number(c.total_seats) >= avail) ? Number(c.total_seats) : Math.max(10, avail);
                        if (avail > 0) {
                            return `<span class="px-2.5 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-[11px] font-bold font-mono inline-flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-chair text-emerald-400 text-[10px]"></i>
                                <span>${avail} / ${total} Free</span>
                            </span>`;
                        } else {
                            return `<span class="px-2.5 py-1 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/30 text-[11px] font-bold font-mono inline-flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-circle-xmark text-rose-400 text-[10px]"></i>
                                <span>0 / ${total} Full</span>
                            </span>`;
                        }
                    })()}
                </td>

                <!-- Action / Lock Slot Column -->
                <td class="py-4 px-4 text-center" id="actionCell_${idx}">
                    <div id="lockActionContainer_${idx}" class="flex items-center justify-center gap-1.5 flex-wrap">
                        <button type="button" onclick="lockChosenCenter('${c.mother_hash}', ${idx}, this)" 
                                class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 flex items-center gap-1.5 transition-all cursor-pointer active:scale-95">
                            <i class="fa-solid fa-lock"></i>
                            <span>Lock Slot</span>
                        </button>
                        <a href="{{ route('admin.slots.book') }}?hash=${c.mother_hash}&center=${encodeURIComponent(c.center_name)}" 
                           class="px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-sky-400 border border-slate-700 text-xs font-bold transition-all flex items-center gap-1"
                           title="Book & Pay">
                            <i class="fa-solid fa-bolt"></i>
                        </a>
                    </div>
                </td>
            `;
            tableBody.appendChild(tr);
        });

        // 3. Raw JSON array
        document.getElementById('rawHashesJson').innerText = JSON.stringify(currentHashes, null, 2);

        document.getElementById('resultsContainer').classList.remove('hidden');

        // 4. Save into LocalStorage
        if (saveToCache) {
            try {
                const cachePayload = {
                    data: data,
                    form: formParams || {
                        category_id: document.getElementById('category_id').value,
                        city: document.getElementById('city').value,
                        exam_date: document.getElementById('exam_date').value,
                    },
                    cached_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                };
                localStorage.setItem(CACHE_STORAGE_KEY, JSON.stringify(cachePayload));
                document.getElementById('tableSubtitle').innerHTML = `Mother Session Hash mapped with designated training center <span class="text-emerald-400 ml-1 font-mono text-[10px] bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">(Live Result at ${cachePayload.cached_at})</span>`;
            } catch (e) {}
        }
    }

    /**
     * Clear Table & Cached Results
     */
    function clearResults() {
        localStorage.removeItem(CACHE_STORAGE_KEY);
        currentHashes = [];
        document.getElementById('resultsContainer').classList.add('hidden');
        document.getElementById('centersTableBody').innerHTML = '';
        document.getElementById('rawHashesJson').innerText = '';

        Swal.fire({
            icon: 'info',
            title: 'Table Cleared',
            text: 'Results and cached search table have been cleared.',
            timer: 1500,
            showConfirmButton: false,
            background: '#1e293b',
            color: '#fff'
        });
    }

    /**
     * Show Modal, animate steps, and run background auto-login
     */
    function showLoginProgressModal(candidate) {
        return new Promise((resolve) => {
            const modal = document.getElementById('loginProgressModal');
            document.getElementById('modalCandidateEmail').innerText = `${candidate.name || 'Candidate'} (${candidate.email})`;
            modal.classList.remove('hidden');

            // Reset Steps UI
            for (let i = 1; i <= 5; i++) {
                const step = document.getElementById('step' + i);
                step.className = 'flex items-center gap-3 p-3 rounded-xl bg-slate-950/60 text-slate-500 border border-slate-800 transition-all';
                step.querySelector('i').className = 'fa-regular fa-circle text-slate-600';
            }

            // Start Live Timer
            timerSeconds = 0;
            const timerEl = document.getElementById('modalLiveTimer');
            clearInterval(progressTimer);
            progressTimer = setInterval(() => {
                timerSeconds += 0.1;
                timerEl.innerHTML = `<i class="fa-regular fa-clock"></i> Elapsed Time: ${timerSeconds.toFixed(1)}s`;
            }, 100);

            // Step 1 Active
            const setStepActive = (num) => {
                const s = document.getElementById('step' + num);
                s.className = 'flex items-center gap-3 p-3 rounded-xl bg-slate-800/60 text-sky-300 border border-sky-500/30 transition-all font-medium';
                s.querySelector('i').className = 'fa-solid fa-circle-notch fa-spin text-sky-400';
            };

            const setStepDone = (num) => {
                const s = document.getElementById('step' + num);
                s.className = 'flex items-center gap-3 p-3 rounded-xl bg-emerald-950/20 text-emerald-300 border border-emerald-500/30 transition-all';
                s.querySelector('i').className = 'fa-solid fa-check text-emerald-400';
            };

            setStepActive(1);

            // Progress stages
            setTimeout(() => { setStepDone(1); setStepActive(2); }, 1200);
            setTimeout(() => { setStepDone(2); setStepActive(3); }, 2500);
            setTimeout(() => { setStepDone(3); setStepActive(4); }, 4800);
            setTimeout(() => { setStepDone(4); setStepActive(5); }, 7500);

            // Trigger Background Headless Login
            fetch("{{ route('admin.slots.auto_login') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    email: candidate.email,
                    password: candidate.password
                })
            })
            .then(res => res.json())
            .then(data => {
                clearInterval(progressTimer);
                if (data.success) {
                    setStepDone(5);
                    setTimeout(() => {
                        modal.classList.add('hidden');
                        resolve(true);
                    }, 1000);
                } else {
                    modal.classList.add('hidden');
                    Swal.fire({
                        icon: 'error',
                        title: 'Auto-Login Failed!',
                        text: data.message || 'Could not log in candidate account.',
                        background: '#1e293b',
                        color: '#fff'
                    });
                    resolve(false);
                }
            })
            .catch(err => {
                clearInterval(progressTimer);
                modal.classList.add('hidden');
                Swal.fire({
                    icon: 'error',
                    title: 'Login Error!',
                    text: err.message,
                    background: '#1e293b',
                    color: '#fff'
                });
                resolve(false);
            });
        });
    }

    function copyHash(hash, btn) {
        navigator.clipboard.writeText(hash);
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i> Copied!';
        setTimeout(() => { btn.innerHTML = originalHtml; }, 1500);
    }

    function copyAllHashes() {
        if (!currentHashes.length) return;
        navigator.clipboard.writeText(JSON.stringify(currentHashes, null, 2));
        Swal.fire({
            icon: 'success',
            title: 'All Hashes Copied!',
            text: `${currentHashes.length} Mother Session Hashes copied to clipboard.`,
            timer: 2000,
            showConfirmButton: false,
            background: '#1e293b',
            color: '#fff'
        });
    }

    /**
     * Live Re-check / Probe an individual Mother Hash
     */
    async function recheckSingleHash(hash, idx, btn) {
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-amber-400"></i> Probing...`;

        try {
            const categoryId = document.getElementById('category_id').value;
            const city = document.getElementById('city').value;
            const examDate = document.getElementById('exam_date').value;
            const authToken = document.getElementById('auth_token').value.trim();

            const res = await fetch("{{ route('admin.slots.recheck_hash') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    mother_hash: hash,
                    category_id: categoryId,
                    city: city,
                    exam_date: examDate,
                    auth_token: authToken
                })
            });

            const data = await res.json();

            if (data.success && data.center) {
                const tc = data.center;
                
                // Update DOM elements for this row
                const nameEl = document.getElementById(`centerName_${idx}`);
                const metaEl = document.getElementById(`centerMeta_${idx}`);

                if (nameEl) {
                    nameEl.innerText = tc.center_name;
                }

                if (metaEl) {
                    metaEl.innerHTML = `
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-rose-400 text-[11px]"></i>
                            <span>${tc.center_address || city}</span>
                        </span>
                        ${tc.location_link ? `
                            <a href="${tc.location_link}" target="_blank" class="text-sky-400 hover:text-sky-300 font-bold text-[11px] flex items-center gap-1">
                                <i class="fa-solid fa-map-location-dot"></i> Map
                            </a>
                        ` : ''}
                    `;
                }

                // Remove the Re-check button since it is now resolved
                btn.remove();

                // Update current cached dataset in localStorage
                try {
                    const cachedStr = localStorage.getItem(CACHE_STORAGE_KEY);
                    if (cachedStr) {
                        const cached = JSON.parse(cachedStr);
                        if (cached && cached.data && cached.data.centers && cached.data.centers[idx]) {
                            cached.data.centers[idx].center_name = tc.center_name;
                            cached.data.centers[idx].center_address = tc.center_address;
                            cached.data.centers[idx].location_link = tc.location_link;
                            cached.data.centers[idx].is_probed = true;
                            localStorage.setItem(CACHE_STORAGE_KEY, JSON.stringify(cached));
                        }
                    }
                } catch (e) {}

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: `Resolved: ${tc.center_name}`,
                    showConfirmButton: false,
                    timer: 2500,
                    background: '#1e293b',
                    color: '#fff'
                });

            } else {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-arrows-rotate text-rose-400"></i> Retry`;
                Swal.fire({
                    icon: 'error',
                    title: 'Re-check Failed',
                    text: data.message || 'Could not probe this hash at this moment.',
                    background: '#1e293b',
                    color: '#fff'
                });
            }

        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: err.message,
                background: '#1e293b',
                color: '#fff'
            });
        }
    }

    /**
     * Slot Locking & Auto-Renewing State Management
     */
    let activeLockData = null; // { idx, hash, temp_seat_id, timerInterval, secondsRemaining }

    async function lockChosenCenter(hash, idx, btn) {
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-white"></i> Locking...`;

        try {
            const categoryId = document.getElementById('category_id').value;
            const city = document.getElementById('city').value;
            const examDate = document.getElementById('exam_date').value;
            const authToken = document.getElementById('auth_token').value.trim();

            const res = await fetch("{{ route('admin.slots.lock_center') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    chosen_hash: hash,
                    category_id: categoryId,
                    city: city,
                    exam_date: examDate,
                    auth_token: authToken,
                })
            });

            const data = await res.json();

            if (data.success && data.data) {
                // Stop any existing lock timer
                if (activeLockData && activeLockData.timerInterval) {
                    clearInterval(activeLockData.timerInterval);
                }

                activeLockData = {
                    idx: idx,
                    hash: hash,
                    temp_seat_id: data.data.temp_seat_id,
                    secondsRemaining: data.data.duration_seconds || 1200,
                    timerInterval: null
                };

                // Highlight chosen row and update action cells
                updateLockedRowUI(idx, hash, activeLockData.temp_seat_id);
                startLockCountdown(activeLockData);

                Swal.fire({
                    icon: 'success',
                    title: 'Slot Locked!',
                    text: 'This slot is now locked for 20 minutes! Other centers are released. Auto-renew will keep it locked.',
                    timer: 2500,
                    showConfirmButton: false,
                    background: '#1e293b',
                    color: '#fff'
                });

            } else {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                Swal.fire({
                    icon: 'error',
                    title: 'Lock Failed',
                    text: data.message || 'Could not lock this slot.',
                    background: '#1e293b',
                    color: '#fff'
                });
            }

        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message,
                background: '#1e293b',
                color: '#fff'
            });
        }
    }

    function updateLockedRowUI(lockedIdx, hash, tempSeatId) {
        const tableBody = document.getElementById('centersTableBody');
        const rows = tableBody.getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const actionCell = document.getElementById(`actionCell_${i}`);

            if (i === lockedIdx) {
                row.className = 'bg-emerald-950/30 border-2 border-emerald-500/60 shadow-lg transition-all ring-1 ring-emerald-500/30';
                const centerName = document.getElementById(`centerName_${i}`) ? document.getElementById(`centerName_${i}`).innerText.trim() : 'Center';

                if (actionCell) {
                    actionCell.innerHTML = `
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                <span class="px-2.5 py-1 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-xs font-mono font-bold flex items-center gap-1.5 animate-pulse">
                                    <i class="fa-solid fa-lock text-emerald-400"></i>
                                    <span id="activeCountdownBadge">20:00</span>
                                </span>
                                <button type="button" onclick="releaseActiveLock()" 
                                        class="px-2 py-1 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[11px] font-bold cursor-pointer transition-all"
                                        title="Unlock and release slot">
                                    <i class="fa-solid fa-lock-open"></i> Unlock
                                </button>
                            </div>
                            <div class="flex items-center justify-center gap-1 text-[10px] text-emerald-400 font-medium">
                                <i class="fa-solid fa-arrows-rotate animate-spin text-[9px]"></i>
                                <span>Auto-Renew: ON (Every 20m)</span>
                            </div>
                            <button type="button" onclick="proceedToPayLockedSlot('${hash}', '${encodeURIComponent(centerName)}')" 
                               class="w-full py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-[11px] flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/30 transition-all cursor-pointer">
                                <i class="fa-solid fa-credit-card"></i>
                                <span>Proceed to Pay</span>
                            </button>
                        </div>
                    `;
                }
            } else {
                row.className = 'opacity-60 hover:opacity-80 transition-opacity border-b border-slate-800/60';
                if (actionCell) {
                    actionCell.innerHTML = `
                        <span class="text-slate-500 text-[11px] font-medium flex items-center justify-center gap-1 py-1">
                            <i class="fa-solid fa-circle-xmark text-[10px]"></i> Released
                        </span>
                    `;
                }
            }
        }
    }

    function startLockCountdown(lockObj) {
        if (lockObj.timerInterval) clearInterval(lockObj.timerInterval);

        lockObj.timerInterval = setInterval(async () => {
            lockObj.secondsRemaining--;

            const mins = Math.floor(lockObj.secondsRemaining / 60);
            const secs = lockObj.secondsRemaining % 60;
            const displayStr = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;

            const badgeEl = document.getElementById('activeCountdownBadge');
            if (badgeEl) {
                badgeEl.innerText = displayStr;
            }

            // Auto-Renew when 60 seconds remain (at 01:00)
            if (lockObj.secondsRemaining === 60) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Auto-Renewing Slot Lock...',
                    showConfirmButton: false,
                    timer: 2000,
                    background: '#1e293b',
                    color: '#fff'
                });

                try {
                    const categoryId = document.getElementById('category_id').value;
                    const authToken = document.getElementById('auth_token').value.trim();

                    const rRes = await fetch("{{ route('admin.slots.renew_lock') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            mother_hash: lockObj.hash,
                            current_temp_seat_id: lockObj.temp_seat_id,
                            category_id: categoryId,
                            auth_token: authToken
                        })
                    });

                    const rData = await rRes.json();
                    if (rData.success && rData.data) {
                        lockObj.temp_seat_id = rData.data.temp_seat_id;
                        lockObj.secondsRemaining = rData.data.duration_seconds || 1200;

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: '🔒 Slot Lock Auto-Renewed for +20 mins!',
                            showConfirmButton: false,
                            timer: 3000,
                            background: '#1e293b',
                            color: '#fff'
                        });
                    }
                } catch (e) {
                    console.error('Auto-renew error:', e);
                }
            }

            // Expiry safeguard
            if (lockObj.secondsRemaining <= 0) {
                clearInterval(lockObj.timerInterval);
                if (badgeEl) badgeEl.innerText = '00:00 (Expired)';
            }

        }, 1000);
    }

    async function releaseActiveLock() {
        if (!activeLockData) return;

        if (activeLockData.timerInterval) {
            clearInterval(activeLockData.timerInterval);
        }

        try {
            const authToken = document.getElementById('auth_token').value.trim();
            await fetch("{{ route('admin.slots.release_lock') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    temp_seat_id: activeLockData.temp_seat_id,
                    auth_token: authToken
                })
            });
        } catch(e) {}

        activeLockData = null;

        // Re-render table to restore buttons
        try {
            const cachedStr = localStorage.getItem(CACHE_STORAGE_KEY);
            if (cachedStr) {
                const cached = JSON.parse(cachedStr);
                if (cached && cached.data) {
                    renderResultsTable(cached.data, false);
                }
            }
        } catch(e) {}

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Slot Lock Released',
            showConfirmButton: false,
            timer: 2000,
            background: '#1e293b',
            color: '#fff'
        });
    }

    /**
     * Release all 20-minute temporary locks across all centers & hashes
     */
    async function releaseAllSlotLocks(btn) {
        const origHtml = btn ? btn.innerHTML : null;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Releasing...`;
        }

        // Stop active lock timer if running
        if (activeLockData && activeLockData.timerInterval) {
            clearInterval(activeLockData.timerInterval);
        }

        const tempIds = [];
        if (activeLockData && activeLockData.temp_seat_id) {
            tempIds.push(activeLockData.temp_seat_id);
        }
        activeLockData = null;

        try {
            const authToken = document.getElementById('auth_token').value.trim();
            const res = await fetch("{{ route('admin.slots.release_all_locks') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    auth_token: authToken,
                    temp_seat_ids: tempIds
                })
            });

            const data = await res.json();

            // Re-render table rows to restore buttons
            try {
                const cachedStr = localStorage.getItem(CACHE_STORAGE_KEY);
                if (cachedStr) {
                    const cached = JSON.parse(cachedStr);
                    if (cached && cached.data) {
                        renderResultsTable(cached.data, false);
                    }
                }
            } catch (e) {}

            Swal.fire({
                icon: 'success',
                title: 'All Slot Locks Released!',
                text: 'All 20-minute temporary seat locks have been cancelled on Taqamul server.',
                timer: 2500,
                showConfirmButton: false,
                background: '#1e293b',
                color: '#fff'
            });

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
     * Seamlessly transition from Locked Slot to Book Slot Payment
     */
    async function proceedToPayLockedSlot(hash, centerName) {
        // 1. Stop background auto-renew timer
        if (activeLockData && activeLockData.timerInterval) {
            clearInterval(activeLockData.timerInterval);
        }

        const tempSeatId = activeLockData ? activeLockData.temp_seat_id : null;

        // 2. Release current active lock hold in backend so it does not collide with booking
        if (tempSeatId) {
            try {
                const authToken = document.getElementById('auth_token').value.trim();
                await fetch("{{ route('admin.slots.release_lock') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        temp_seat_id: tempSeatId,
                        auth_token: authToken
                    })
                });
            } catch (e) {}
        }
        activeLockData = null;

        // 3. Navigate to Book Slot Page
        window.location.href = `{{ route('admin.slots.book') }}?hash=${hash}&center=${centerName}&temp_seat_id=${tempSeatId || ''}`;
    }

    /**
     * Autonomous Pool Candidate Auto-Login with Interactive Live Progress Modal
     */
    async function autoLoginPoolCandidate() {
        let logInterval = null;

        // 1. Launch Interactive Dark Modal with Stepper and Terminal Log Output
        Swal.fire({
            title: '<div class="flex items-center justify-center gap-2 text-sky-400 text-base font-bold"><i class="fa-solid fa-key text-amber-400"></i> Taqamul Account Auto-Login</div>',
            html: `
                <div class="space-y-3.5 text-left p-1 text-slate-200">
                    <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-700/80 flex items-center justify-between shadow-inner">
                        <span class="text-xs font-semibold text-slate-400">Target Account:</span>
                        <span id="modalTargetEmail" class="text-xs font-mono font-bold text-emerald-400 truncate max-w-[220px]">Selecting Candidate...</span>
                    </div>

                    <!-- 5-Step Progress Stepper -->
                    <div class="space-y-1.5 text-[11px]" id="modalStepper">
                        <div id="step-nav" class="flex items-center gap-2 p-2 rounded-xl bg-slate-900/80 border border-slate-800 text-slate-400 transition-all">
                            <i class="fa-solid fa-globe text-sky-400 w-4 text-center"></i>
                            <span>1. Navigating to Taqamul Login Portal</span>
                            <i class="fa-solid fa-circle-notch fa-spin text-sky-400 ml-auto hidden" id="spinner-nav"></i>
                        </div>
                        <div id="step-fill" class="flex items-center gap-2 p-2 rounded-xl bg-slate-900/80 border border-slate-800 text-slate-400 transition-all">
                            <i class="fa-solid fa-pen-to-square text-amber-400 w-4 text-center"></i>
                            <span>2. Entering Email & Password</span>
                            <i class="fa-solid fa-circle-notch fa-spin text-amber-400 ml-auto hidden" id="spinner-fill"></i>
                        </div>
                        <div id="step-captcha" class="flex items-center gap-2 p-2 rounded-xl bg-slate-900/80 border border-slate-800 text-slate-400 transition-all">
                            <i class="fa-solid fa-robot text-purple-400 w-4 text-center"></i>
                            <span>3. Solving Google reCAPTCHA v2 AI</span>
                            <i class="fa-solid fa-circle-notch fa-spin text-purple-400 ml-auto hidden" id="spinner-captcha"></i>
                        </div>
                        <div id="step-otp" class="flex items-center gap-2 p-2 rounded-xl bg-slate-900/80 border border-slate-800 text-slate-400 transition-all">
                            <i class="fa-solid fa-envelope-open-text text-cyan-400 w-4 text-center"></i>
                            <span>4. Fetching Wafid Mail Login OTP</span>
                            <i class="fa-solid fa-circle-notch fa-spin text-cyan-400 ml-auto hidden" id="spinner-otp"></i>
                        </div>
                        <div id="step-token" class="flex items-center gap-2 p-2 rounded-xl bg-slate-900/80 border border-slate-800 text-slate-400 transition-all">
                            <i class="fa-solid fa-shield-halved text-emerald-400 w-4 text-center"></i>
                            <span>5. Validating & Activating Bearer Token</span>
                            <i class="fa-solid fa-circle-notch fa-spin text-emerald-400 ml-auto hidden" id="spinner-token"></i>
                        </div>
                    </div>

                    <!-- Live Terminal Output Box -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400">
                            <span>Real-Time Logs:</span>
                            <span id="modalLogStatus" class="text-amber-400 animate-pulse text-[10px]">Running Bot...</span>
                        </div>
                        <div id="modalLiveLogs" class="h-24 overflow-y-auto p-2 rounded-xl bg-slate-950 font-mono text-[10px] text-slate-300 border border-slate-800 space-y-1 leading-relaxed custom-scrollbar">
                            <div class="text-slate-500">[System] Starting Puppeteer Auth Bot...</div>
                        </div>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            allowOutsideClick: false,
            background: '#0f172a',
            color: '#f8fafc',
            customClass: {
                popup: 'rounded-2xl border border-slate-800 shadow-2xl max-w-sm'
            },
            willClose: () => {
                if (logInterval) clearInterval(logInterval);
            }
        });

        if (logInterval) {
            clearInterval(logInterval);
            logInterval = null;
        }

        function showLoginError(msg) {
            const btn = document.getElementById('noActiveAcBtn');
            const btnText = document.getElementById('noActiveAcText');
            const btnIcon = document.getElementById('noActiveAcIcon');
            if (btnIcon) btnIcon.className = 'fa-solid fa-triangle-exclamation text-rose-400';
            if (btnText) btnText.innerText = 'No Active AC (Click to Login)';

            Swal.fire({
                icon: 'error',
                title: '❌ Auto-Login Failed',
                html: `<div class="text-xs text-rose-300 bg-rose-950/60 p-3 rounded-xl border border-rose-800/60 font-mono text-left">${msg}</div>`,
                confirmButtonText: 'Close & Retry',
                confirmButtonColor: '#ef4444',
                background: '#0f172a',
                color: '#f8fafc'
            });
        }

        function setStepActive(stepId, spinnerId) {
            ['step-nav', 'step-fill', 'step-captcha', 'step-otp', 'step-token'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.remove('bg-sky-500/20', 'border-sky-500/40', 'text-sky-300', 'font-bold');
            });
            ['spinner-nav', 'spinner-fill', 'spinner-captcha', 'spinner-otp', 'spinner-token'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });

            const activeEl = document.getElementById(stepId);
            const activeSpin = document.getElementById(spinnerId);
            if (activeEl) activeEl.classList.add('bg-sky-500/20', 'border-sky-500/40', 'text-sky-300', 'font-bold');
            if (activeSpin) activeSpin.classList.remove('hidden');
        }

        // 1. Start Log Polling IMMEDIATELY so live console updates as soon as login starts
        logInterval = setInterval(async () => {
            try {
                const lRes = await fetch("{{ route('admin.slots.auto_login_logs') }}");
                const lData = await lRes.json();
                if (lData && lData.logs) {
                    const logsBox = document.getElementById('modalLiveLogs');
                    if (logsBox) {
                        const lines = lData.logs.split('\n').filter(l => l.trim().length > 0);
                        logsBox.innerHTML = lines.map(l => {
                            if (l.includes('ERROR') || l.includes('Failed') || l.includes('failed') || l.includes('Error')) return `<div class="text-rose-400 font-bold">${l}</div>`;
                            if (l.includes('SUCCESS') || l.includes('Solved') || l.includes('Token') || l.includes('Intercepted')) return `<div class="text-emerald-400 font-bold">${l}</div>`;
                            if (l.includes('OTP') || l.includes('WafidMail')) return `<div class="text-amber-300 font-semibold">${l}</div>`;
                            return `<div class="text-slate-300">${l}</div>`;
                        }).join('');
                        logsBox.scrollTop = logsBox.scrollHeight;
                    }

                    // Stepper Highlighting Logic
                    const text = lData.logs;
                    if (text.includes('Navigating to Taqamul')) setStepActive('step-nav', 'spinner-nav');
                    if (text.includes('Filling Email')) setStepActive('step-fill', 'spinner-fill');
                    if (text.includes('CapSolver') || text.includes('reCAPTCHA')) setStepActive('step-captcha', 'spinner-captcha');
                    if (text.includes('OTP') || text.includes('WafidMail')) setStepActive('step-otp', 'spinner-otp');
                    if (text.includes('Bearer') || text.includes('Validating')) setStepActive('step-token', 'spinner-token');
                    
                    if (text.includes('Visual Login for:')) {
                        const m = text.match(/Visual Login for:\s*(\S+)/);
                        if (m && m[1]) {
                            const emailEl = document.getElementById('modalTargetEmail');
                            if (emailEl) emailEl.innerText = m[1];
                        }
                    }
                }
            } catch(e) {}
        }, 400);

        // 2. Trigger Single Asynchronous Background Auto-Login Endpoint
        try {
            const res = await fetch("{{ route('admin.slots.auto_login') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            const data = await res.json();
            if (logInterval) clearInterval(logInterval);

            if (data.success && data.token) {
                const authInput = document.getElementById('auth_token');
                if (authInput) authInput.value = data.token;

                // Update outer indicator button state
                const btn = document.getElementById('noActiveAcBtn');
                const btnText = document.getElementById('noActiveAcText');
                const btnIcon = document.getElementById('noActiveAcIcon');
                if (btnIcon) btnIcon.className = 'fa-solid fa-circle-check text-emerald-400';
                if (btnText) btnText.innerText = `Active AC: ${data.email || 'Logged In'}`;
                if (btn) {
                    btn.classList.remove('bg-amber-500/20', 'border-amber-500/40', 'text-amber-300', 'bg-rose-500/20', 'border-rose-500/40', 'text-rose-300');
                    btn.classList.add('bg-emerald-500/20', 'border-emerald-500/40', 'text-emerald-300');
                }

                // Close SweetAlert Modal
                Swal.close();

                // If a profession is selected, load cities and dates for it
                const selectedCatId = document.getElementById('category_id') ? document.getElementById('category_id').value : '';
                if (selectedCatId) {
                    loadAvailableDatesForCategory(selectedCatId);
                }
            } else {
                showLoginError(data.message || 'Auto-login failed.');
            }
        } catch (err) {
            if (logInterval) clearInterval(logInterval);
            showLoginError(err.message);
        }
    }
</script>
@endpush
@endsection
