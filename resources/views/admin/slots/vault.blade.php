@extends('layouts.app')

@section('title', 'Mother Hash Vault - Taqamul Admin')

@section('content')
<div class="space-y-6">

    <!-- Sleek Compact Top Bar -->
    <div class="flex items-center justify-between gap-3 p-3 px-4 bg-slate-900/90 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-md">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-sm">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-sm font-black text-white">Mother Hash Vault</h1>
                <span class="px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-300 border border-indigo-500/25 font-mono text-[11px] font-bold">
                    {{ $totalCount }} Hashes
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.slots.index') }}" class="px-3 py-1.5 rounded-xl bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/20 text-xs font-bold transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-magnifying-glass-location text-[11px]"></i>
                <span>Slot Checker</span>
            </a>
            <a href="{{ route('admin.slots.vault.export', request()->query()) }}" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-xs font-bold transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-file-csv text-emerald-400 text-[11px]"></i>
                <span>Export CSV</span>
            </a>
            <button type="button" onclick="confirmClearVault()" class="px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                <i class="fa-solid fa-trash-can text-[11px]"></i>
                <span>Clear Vault</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Bar with Searchable Dropdowns -->
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-3xl backdrop-blur-xl shadow-lg">
        <form method="GET" id="vaultFilterForm" action="{{ route('admin.slots.vault') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            
            <!-- Filter by Profession (Custom Searchable Dropdown) -->
            <div class="space-y-1.5 relative" id="vaultProfessionContainer">
                <label class="block text-[11px] font-bold text-slate-300">
                    <i class="fa-solid fa-briefcase text-sky-400 mr-1"></i> Profession / Category
                </label>
                <input type="hidden" id="vault_category_id" name="category_id" value="{{ request('category_id') }}">

                <!-- Trigger Button -->
                <button type="button" onclick="toggleVaultDropdown('profession')" id="vaultProfessionTrigger" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-sky-500 outline-none flex items-center justify-between gap-2 text-left cursor-pointer hover:border-slate-600 transition-all">
                    <span id="vaultProfessionLabel" class="truncate text-slate-300">
                        {{ request('category_id') ? 'Category ID: ' . request('category_id') : 'All Professions' }}
                    </span>
                    <i class="fa-solid fa-chevron-down text-slate-500 text-[10px] transition-transform duration-200" id="vaultProfessionChevron"></i>
                </button>

                <!-- Downward Popover Menu -->
                <div id="vaultProfessionMenu" class="hidden absolute left-0 top-full mt-2 w-full min-w-[300px] md:min-w-[360px] bg-slate-900/95 backdrop-blur-xl border border-slate-700 rounded-2xl shadow-2xl z-50 p-3 space-y-2.5 animate-in fade-in zoom-in-95 duration-150">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="vaultProfessionSearchInput" oninput="filterVaultProfessions(this.value)" placeholder="Search profession or ID..." autocomplete="off"
                               class="w-full pl-8 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:border-sky-500 outline-none">
                    </div>
                    <div id="vaultProfessionList" class="max-h-60 overflow-y-auto space-y-1 custom-scrollbar pr-1">
                        <!-- Injected via JS -->
                    </div>
                </div>
            </div>

            <!-- Filter by City (Custom Searchable Dropdown) -->
            <div class="space-y-1.5 relative" id="vaultCityContainer">
                <label class="block text-[11px] font-bold text-slate-300">
                    <i class="fa-solid fa-city text-emerald-400 mr-1"></i> City
                </label>
                <input type="hidden" id="vault_city" name="city" value="{{ request('city') }}">

                <!-- Trigger Button -->
                <button type="button" onclick="toggleVaultDropdown('city')" id="vaultCityTrigger" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:ring-2 focus:ring-emerald-500 outline-none flex items-center justify-between gap-2 text-left cursor-pointer hover:border-slate-600 transition-all">
                    <span id="vaultCityLabel" class="truncate text-slate-300">
                        {{ request('city') ?: 'All Cities' }}
                    </span>
                    <i class="fa-solid fa-chevron-down text-slate-500 text-[10px] transition-transform duration-200" id="vaultCityChevron"></i>
                </button>

                <!-- Downward Popover Menu -->
                <div id="vaultCityMenu" class="hidden absolute left-0 top-full mt-2 w-full min-w-[240px] bg-slate-900/95 backdrop-blur-xl border border-slate-700 rounded-2xl shadow-2xl z-50 p-3 space-y-2.5 animate-in fade-in zoom-in-95 duration-150">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="vaultCitySearchInput" oninput="filterVaultCities(this.value)" placeholder="Search city..." autocomplete="off"
                               class="w-full pl-8 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div id="vaultCityList" class="max-h-60 overflow-y-auto space-y-1 custom-scrollbar pr-1">
                        <!-- Injected via JS -->
                    </div>
                </div>
            </div>

            <!-- Filter by Exam Date -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-slate-300">
                    <i class="fa-solid fa-calendar-days text-amber-400 mr-1"></i> Exam Date
                </label>
                <input type="date" name="exam_date" value="{{ request('exam_date') }}"
                       class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:border-amber-500 outline-none">
            </div>

            <!-- Search Keyword -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-slate-300">
                    <i class="fa-solid fa-magnifying-glass text-indigo-400 mr-1"></i> Search Hash / Center
                </label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Keyword, Center or Hash..."
                       class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-medium focus:border-indigo-500 outline-none">
            </div>

            <!-- Filter Buttons -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-1.5 transition-all cursor-pointer">
                    <i class="fa-solid fa-filter"></i>
                    <span>Apply Filter</span>
                </button>
                @if(request()->hasAny(['category_id', 'city', 'exam_date', 'search']))
                    <a href="{{ route('admin.slots.vault') }}" class="py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-all flex items-center justify-center" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </div>

        </form>
    </div>

    <!-- Hashes Table View -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 space-y-4 shadow-xl">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-database text-indigo-400"></i>
                    <span>Stored Mother Session Hashes</span>
                </h3>
                <p class="text-xs text-slate-400">Showing {{ $hashes->firstItem() ?? 0 }} - {{ $hashes->lastItem() ?? 0 }} of {{ $hashes->total() }} recorded hashes</p>
            </div>
            @if($hashes->count() > 0)
                <button type="button" onclick="copyCurrentPageHashes()" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold flex items-center gap-1.5 transition-all shrink-0 cursor-pointer">
                    <i class="fa-regular fa-copy text-emerald-400"></i>
                    <span>Copy Page Hashes (JSON)</span>
                </button>
            @endif
        </div>

        @if($hashes->count() > 0)
            <!-- Table -->
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-950/80 border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 w-12 text-center">#</th>
                            <th class="py-3.5 px-4">Discovered</th>
                            <th class="py-3.5 px-4">Mother Session Hash</th>
                            <th class="py-3.5 px-4">Profession & Category</th>
                            <th class="py-3.5 px-4">Center & Location</th>
                            <th class="py-3.5 px-4 text-center">Exam Date & Time</th>
                            <th class="py-3.5 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-medium">
                        @foreach($hashes as $index => $h)
                            <tr class="hover:bg-slate-800/40 transition-colors border-b border-slate-800/60" id="hash-row-{{ $h->id }}">
                                <td class="py-4 px-4 text-center">
                                    <span class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-300 font-bold text-xs inline-flex items-center justify-center">
                                        {{ $hashes->firstItem() + $index }}
                                    </span>
                                </td>

                                <!-- Discovered Timestamp -->
                                <td class="py-4 px-4 text-[11px] text-slate-400 whitespace-nowrap">
                                    <span class="text-slate-300 font-medium block">{{ $h->discovered_at ? $h->discovered_at->format('M d, Y') : '-' }}</span>
                                    <span class="text-slate-500 font-mono text-[10px]">{{ $h->discovered_at ? $h->discovered_at->format('h:i A') : '' }}</span>
                                </td>

                                <!-- Mother Hash with 1-Click Copy -->
                                <td class="py-4 px-4 font-mono text-xs">
                                    <div class="flex items-center gap-2 max-w-sm">
                                        <div class="px-3 py-1.5 rounded-xl bg-slate-950 border border-sky-500/30 text-sky-300 text-[11px] truncate flex-1 select-all privacy-hash-mask" title="{{ $h->mother_hash }}">
                                            {{ $h->mother_hash }}
                                        </div>
                                        <button type="button" onclick="copyHash('{{ $h->mother_hash }}', this)" 
                                                class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold transition-all shrink-0 cursor-pointer flex items-center gap-1">
                                            <i class="fa-regular fa-copy"></i>
                                            <span>Copy</span>
                                        </button>
                                    </div>
                                </td>

                                <!-- Profession / Category -->
                                <td class="py-4 px-4">
                                    <div class="space-y-0.5">
                                        <span class="font-bold text-white text-xs block truncate max-w-xs" title="{{ $h->category_name }}">
                                            {{ $h->category_name ?? 'Trade' }}
                                        </span>
                                        <span class="text-[10px] text-indigo-400 font-mono">Category ID: {{ $h->category_id }}</span>
                                    </div>
                                </td>

                                <!-- Center Name & Location -->
                                <td class="py-4 px-4">
                                    <div class="space-y-0.5">
                                        <h4 class="font-bold text-slate-200 text-xs truncate max-w-xs">{{ $h->center_name ?? 'Technical Training Centre' }}</h4>
                                        <div class="flex items-center gap-2 text-[11px] text-slate-400">
                                            <span class="flex items-center gap-1">
                                                <i class="fa-solid fa-location-dot text-rose-400 text-[10px]"></i>
                                                <span>{{ $h->city }}</span>
                                            </span>
                                            @if($h->location_link)
                                                <a href="{{ $h->location_link }}" target="_blank" class="text-sky-400 hover:text-sky-300 font-bold text-[10px] flex items-center gap-0.5">
                                                    <i class="fa-solid fa-map-location-dot"></i> Map
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- Exam Date & Time -->
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <span class="font-bold text-amber-300 text-xs block">{{ $h->exam_date ? $h->exam_date->format('Y-m-d') : '-' }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $h->start_time ?? '09:30 AM' }}</span>
                                </td>

                                <!-- Actions -->
                                <td class="py-4 px-4 text-center">
                                    <button type="button" onclick="deleteVaultItem({{ $h->id }})" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs inline-flex items-center justify-center transition-all cursor-pointer" title="Delete Hash">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pt-4 border-t border-slate-800">
                {{ $hashes->links() }}
            </div>

        @else
            <!-- Empty State -->
            <div class="text-center py-12 space-y-3">
                <div class="w-16 h-16 rounded-3xl bg-slate-800/60 text-slate-500 flex items-center justify-center text-2xl mx-auto">
                    <i class="fa-solid fa-vault"></i>
                </div>
                <h4 class="text-base font-bold text-white">No Hashes in Vault</h4>
                <p class="text-xs text-slate-400 max-w-md mx-auto">Whenever you search and probe slots from the Slot Checker page, discovered Mother Session Hashes will be automatically saved here.</p>
                <a href="{{ route('admin.slots.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition-all">
                    <i class="fa-solid fa-magnifying-glass-location"></i>
                    <span>Go to Slot Checker</span>
                </a>
            </div>
        @endif

    </div>

</div>

@push('scripts')
<script>
    const rawOccupations = @json($occupations);
    const rawCities = @json($cities);
    const currentPageHashes = @json($hashes->pluck('mother_hash'));

    let formattedOccupations = [];
    let formattedCities = [];

    function initVaultDataStructures() {
        const uniqueCatMap = new Map();
        rawOccupations.forEach(occ => {
            const catId = occ.category?.id || occ.category_id || occ.id;
            const engName = occ.name || occ.english_name || occ.category?.english_name || 'Trade';
            const arName = occ.arabic_name || occ.category?.arabic_name || '';

            if (!uniqueCatMap.has(catId)) {
                uniqueCatMap.set(catId, {
                    id: catId,
                    english_name: engName,
                    arabic_name: arName,
                    full_label: `${engName} [ID: ${catId}]`
                });
            }
        });

        formattedOccupations = Array.from(uniqueCatMap.values());
        formattedCities = rawCities.slice();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initVaultDataStructures();
        renderVaultProfessionOptions(formattedOccupations);
        renderVaultCityOptions(formattedCities);

        // Update trigger labels from existing query params
        const currentCat = document.getElementById('vault_category_id').value;
        if (currentCat) {
            const found = formattedOccupations.find(o => String(o.id) === String(currentCat));
            if (found) document.getElementById('vaultProfessionLabel').innerText = found.full_label;
        }

        const currentCity = document.getElementById('vault_city').value;
        if (currentCity) {
            document.getElementById('vaultCityLabel').innerText = currentCity;
        }

        // Click outside listener
        document.addEventListener('click', (e) => {
            const pCont = document.getElementById('vaultProfessionContainer');
            const cCont = document.getElementById('vaultCityContainer');
            if (pCont && !pCont.contains(e.target)) closeVaultDropdown('profession');
            if (cCont && !cCont.contains(e.target)) closeVaultDropdown('city');
        });
    });

    function toggleVaultDropdown(type) {
        if (type === 'profession') {
            const menu = document.getElementById('vaultProfessionMenu');
            const chevron = document.getElementById('vaultProfessionChevron');
            const isOpen = !menu.classList.contains('hidden');
            closeVaultDropdown('city');

            if (isOpen) {
                closeVaultDropdown('profession');
            } else {
                menu.classList.remove('hidden');
                chevron.classList.add('rotate-180');
                const inp = document.getElementById('vaultProfessionSearchInput');
                inp.value = '';
                filterVaultProfessions('');
                setTimeout(() => inp.focus(), 50);
            }
        } else if (type === 'city') {
            const menu = document.getElementById('vaultCityMenu');
            const chevron = document.getElementById('vaultCityChevron');
            const isOpen = !menu.classList.contains('hidden');
            closeVaultDropdown('profession');

            if (isOpen) {
                closeVaultDropdown('city');
            } else {
                menu.classList.remove('hidden');
                chevron.classList.add('rotate-180');
                const inp = document.getElementById('vaultCitySearchInput');
                inp.value = '';
                filterVaultCities('');
                setTimeout(() => inp.focus(), 50);
            }
        }
    }

    function closeVaultDropdown(type) {
        if (type === 'profession') {
            document.getElementById('vaultProfessionMenu').classList.add('hidden');
            document.getElementById('vaultProfessionChevron').classList.remove('rotate-180');
        } else if (type === 'city') {
            document.getElementById('vaultCityMenu').classList.add('hidden');
            document.getElementById('vaultCityChevron').classList.remove('rotate-180');
        }
    }

    function renderVaultProfessionOptions(items) {
        const listEl = document.getElementById('vaultProfessionList');
        listEl.innerHTML = '';
        const currentVal = document.getElementById('vault_category_id').value;

        // "All Professions" option
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${!currentVal ? 'bg-sky-500/20 text-sky-300 font-bold border border-sky-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
        allBtn.innerHTML = `<span>All Professions</span>`;
        allBtn.onclick = () => selectVaultProfession('', 'All Professions');
        listEl.appendChild(allBtn);

        items.forEach(item => {
            const isSelected = String(item.id) === String(currentVal);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${isSelected ? 'bg-sky-500/20 text-sky-300 font-bold border border-sky-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
            
            btn.innerHTML = `
                <span class="truncate">${item.english_name}</span>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-950 text-slate-400 shrink-0">ID: ${item.id}</span>
            `;

            btn.onclick = () => selectVaultProfession(item.id, item.full_label);
            listEl.appendChild(btn);
        });
    }

    function filterVaultProfessions(query) {
        const q = query.trim().toLowerCase();
        if (!q) {
            renderVaultProfessionOptions(formattedOccupations);
            return;
        }
        const filtered = formattedOccupations.filter(item => 
            item.english_name.toLowerCase().includes(q) ||
            item.arabic_name.toLowerCase().includes(q) ||
            String(item.id).includes(q)
        );
        renderVaultProfessionOptions(filtered);
    }

    function selectVaultProfession(id, label) {
        document.getElementById('vault_category_id').value = id;
        document.getElementById('vaultProfessionLabel').innerText = label;
        closeVaultDropdown('profession');
    }

    function renderVaultCityOptions(items) {
        const listEl = document.getElementById('vaultCityList');
        listEl.innerHTML = '';
        const currentVal = document.getElementById('vault_city').value;

        // "All Cities" option
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${!currentVal ? 'bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
        allBtn.innerHTML = `<span>All Cities</span>`;
        allBtn.onclick = () => selectVaultCity('', 'All Cities');
        listEl.appendChild(allBtn);

        items.forEach(city => {
            const isSelected = city === currentVal;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer ${isSelected ? 'bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'}`;
            
            btn.innerHTML = `
                <span class="truncate flex items-center gap-2">
                    <i class="fa-solid fa-location-dot text-[11px] ${isSelected ? 'text-emerald-400' : 'text-slate-500'}"></i>
                    <span>${city}</span>
                </span>
                ${isSelected ? '<i class="fa-solid fa-check text-emerald-400 text-xs"></i>' : ''}
            `;

            btn.onclick = () => selectVaultCity(city, city);
            listEl.appendChild(btn);
        });
    }

    function filterVaultCities(query) {
        const q = query.trim().toLowerCase();
        if (!q) {
            renderVaultCityOptions(formattedCities);
            return;
        }
        const filtered = formattedCities.filter(c => c.toLowerCase().includes(q));
        renderVaultCityOptions(filtered);
    }

    function selectVaultCity(city, label) {
        document.getElementById('vault_city').value = city;
        document.getElementById('vaultCityLabel').innerText = label;
        closeVaultDropdown('city');
    }

    function copyHash(hash, btn) {
        navigator.clipboard.writeText(hash);
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i> Copied!';
        setTimeout(() => { btn.innerHTML = originalHtml; }, 1500);
    }

    function copyCurrentPageHashes() {
        if (!currentPageHashes.length) return;
        navigator.clipboard.writeText(JSON.stringify(currentPageHashes, null, 2));
        Swal.fire({
            icon: 'success',
            title: 'Hashes Copied!',
            text: `${currentPageHashes.length} Mother Session Hashes copied to clipboard (JSON format).`,
            timer: 2000,
            showConfirmButton: false,
            background: '#1e293b',
            color: '#fff'
        });
    }

    function deleteVaultItem(id) {
        Swal.fire({
            title: 'Delete Hash Record?',
            text: 'Are you sure you want to delete this hash record from the Vault?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Yes, Delete',
            background: '#1e293b',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/admin/vault/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById(`hash-row-${id}`);
                        if (row) row.remove();
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false,
                            background: '#1e293b',
                            color: '#fff'
                        });
                    }
                });
            }
        });
    }

    function confirmClearVault() {
        Swal.fire({
            title: 'Clear Entire Vault?',
            text: 'This will permanently remove all stored Mother Session Hashes from the database.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Yes, Clear All',
            background: '#1e293b',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("{{ route('admin.slots.vault.clear') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
            }
        });
    }
</script>
@endpush
@endsection
