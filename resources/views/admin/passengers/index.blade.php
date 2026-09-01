@extends('layouts.app')

@section('title', 'Passengers List')

@section('content')
<div class="space-y-8">

    <!-- Stats Summary Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Candidates</p>
                <div class="w-8 h-8 rounded-lg bg-slate-800 text-slate-300 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <p id="statTotal" class="text-2xl font-extrabold text-white mt-2 font-mono">{{ $passengers->total() }}</p>
            <p class="text-[11px] text-slate-500 mt-1">Total registered</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-400">Completed</p>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <p id="statCompleted" class="text-2xl font-extrabold text-emerald-400 mt-2 font-mono">
                {{ \App\Models\Passenger::where('status', 'Completed')->count() }}
            </p>
            <p class="text-[11px] text-emerald-400/70 mt-1">Paid & exam booked</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-sky-400">AC Done</p>
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
            <p id="statAcDone" class="text-2xl font-extrabold text-sky-400 mt-2 font-mono">
                {{ \App\Models\Passenger::whereIn('status', ['AC Done', 'ac_done'])->count() }}
            </p>
            <p class="text-[11px] text-sky-400/70 mt-1">Accounts created</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-teal-400">Mailbox Accounts</p>
                <div class="w-8 h-8 rounded-lg bg-teal-500/20 text-teal-400 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-inbox"></i>
                </div>
            </div>
            <p id="statMailbox" class="text-2xl font-extrabold text-teal-400 mt-2 font-mono">
                {{ \App\Models\Passenger::whereNotNull('email')->count() }}
            </p>
            <p class="text-[11px] text-teal-400/70 mt-1">Permanent mail active</p>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
        <form action="{{ route('admin.passengers.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500 text-sm">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Candidate Name, Passport No, Email, NID..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-xs outline-none">
            </div>

            <div>
                <select name="status" class="w-full px-3 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none">
                    <option value="">All Statuses</option>
                    <option value="AC Done" {{ request('status') == 'AC Done' ? 'selected' : '' }}>AC Done</option>
                    <option value="Completed" {{ request('status') == 'Completed' ? 'selected' : '' }}>Completed</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('admin.passengers.index') }}" class="px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs flex items-center justify-center">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Passengers Table -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/80 uppercase text-[11px] font-bold text-slate-400 tracking-wider border-b border-slate-800">
                    <tr>
                        <th class="py-4 px-4">Candidate</th>
                        <th class="py-4 px-4">Passport No</th>
                        <th class="py-4 px-4">Taqamul Login Email</th>
                        <th class="py-4 px-4">Password</th>
                        <th class="py-4 px-4 text-center">Temp-Mail / OTP</th>
                        <th class="py-4 px-4 text-center">Status</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse($passengers as $p)
                        <tr id="passenger-row-{{ $p->id }}" data-status="{{ $p->status }}" class="hover:bg-slate-800/40 transition-colors">
                            <!-- Candidate Name & Details -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700/60 flex items-center justify-center text-emerald-400 font-bold overflow-hidden shrink-0">
                                        @if($p->personal_photo_path)
                                            <img src="{{ asset('storage/' . $p->personal_photo_path) }}" alt="Photo" class="w-full h-full object-cover">
                                        @else
                                            <i class="fa-solid fa-user"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.passengers.show', $p->id) }}" class="font-bold text-white hover:text-emerald-400 text-sm transition-colors block">
                                            {{ $p->full_name }}
                                        </a>
                                        <p class="text-[11px] text-slate-500 font-mono">
                                            {{ $p->country_name }} &bull; {{ ucfirst($p->gender) }} &bull; {{ $p->created_at->format('d M, Y') }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Passport Number -->
                            <td class="py-4 px-4 font-mono font-bold text-amber-400 text-sm">
                                <span>{{ $p->passport_number }}</span>
                                @if($p->national_id)
                                    <p class="text-[10px] text-slate-500 font-mono font-normal">NID: {{ $p->national_id }}</p>
                                @endif
                            </td>

                            <!-- Taqamul Email -->
                            <td id="email-cell-{{ $p->id }}" class="py-4 px-4">
                                <div class="flex items-center gap-2 font-mono">
                                    <span class="text-slate-200 select-all">{{ $p->email }}</span>
                                    <button type="button" onclick="copyText('{{ $p->email }}', this)" title="Copy Email" class="text-slate-500 hover:text-emerald-400 transition-all p-1 rounded">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </td>

                            <!-- Taqamul Password -->
                            <td id="pass-cell-{{ $p->id }}" class="py-4 px-4">
                                <div class="flex items-center gap-2 font-mono">
                                    <span class="text-emerald-400 font-bold select-all">{{ $p->password }}</span>
                                    <button type="button" onclick="copyText('{{ $p->password }}', this)" title="Copy Password" class="text-slate-500 hover:text-emerald-400 transition-all p-1 rounded">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </td>

                            <!-- Live OTP / Mailbox Action -->
                            <td class="py-4 px-4 text-center">
                                <button type="button" onclick="openInboxModal({{ $p->id }}, '{{ $p->email }}', '{{ $p->temp_mail_password ?: 'Direct Access (YOPmail)' }}')" class="px-3.5 py-1.5 rounded-xl bg-teal-500/10 hover:bg-teal-500/20 text-teal-300 border border-teal-500/30 text-[11px] font-bold font-mono inline-flex items-center gap-1.5 transition-all shadow-sm">
                                    <i class="fa-solid fa-envelope-open-text text-teal-400"></i>
                                    <span>Get Live OTP</span>
                                </button>
                            </td>

                            <!-- Status Badge -->
                            <td id="status-cell-{{ $p->id }}" class="py-4 px-4 text-center">
                                @if(strtolower($p->status) === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-[11px] font-extrabold shadow-sm">
                                        <i class="fa-solid fa-circle-check text-[10px] text-emerald-400"></i> Completed
                                    </span>
                                @elseif(strtolower($p->status) === 'ac done' || strtolower($p->status) === 'ac_done')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-500/15 border border-sky-500/30 text-sky-300 text-[11px] font-extrabold shadow-sm">
                                        <i class="fa-solid fa-user-check text-[10px] text-sky-400"></i> AC Done
                                    </span>
                                @elseif($p->status === 'processing')
                                    @php
                                        $progFile = base_path("bot/progress_{$p->id}.txt");
                                        $progText = file_exists($progFile) ? trim(file_get_contents($progFile)) : 'Processing...';
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold shadow-sm shadow-cyan-500/10 animate-pulse whitespace-nowrap">
                                        <i class="fa-solid fa-spinner fa-spin text-[10px] text-cyan-400"></i>
                                        <span id="progress-text-{{ $p->id }}">{{ $progText }}</span>
                                    </span>
                                @elseif($p->status === 'otp_sent')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[11px] font-bold">
                                        <i class="fa-solid fa-clock text-[10px]"></i> OTP Sent
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[11px] font-bold">
                                        <i class="fa-solid fa-circle-xmark text-[10px]"></i> {{ ucfirst($p->status ?: 'Failed') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td id="actions-cell-{{ $p->id }}" class="py-4 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(strtolower($p->status) !== 'ac done' && strtolower($p->status) !== 'completed')
                                        <button type="button" onclick="retryPassenger({{ $p->id }}, '{{ addslashes($p->full_name) }}')" title="Retry / Restart Bot Automation" class="w-8 h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500/25 text-amber-400 border border-amber-500/30 flex items-center justify-center transition-all">
                                            <i class="fa-solid fa-rotate-right"></i>
                                        </button>
                                    @endif

                                    <!-- Direct Taqamul Portal Auto-Login Tab -->
                                    <a href="https://svp-international.pacc.sa/auth/login?role=labor#auto_id={{ $p->id }}&email={{ urlencode($p->email) }}&pass={{ urlencode($p->password) }}&server={{ urlencode(url('/')) }}&ck={{ urlencode(\App\Models\Setting::get('capsolver_api_key', 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133')) }}" 
                                       target="_blank" 
                                       title="Auto-Login to Taqamul Portal (Opens in Next Tab)" 
                                       class="w-8 h-8 rounded-lg bg-sky-500/10 hover:bg-sky-500/25 text-sky-400 border border-sky-500/30 flex items-center justify-center transition-all shadow-sm">
                                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                    </a>

                                    <a href="{{ route('admin.passengers.show', $p->id) }}" title="View Full Profile" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>

                                    <form action="{{ route('admin.passengers.destroy', $p->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this passenger record?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete Record" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 flex items-center justify-center transition-colors">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-600 flex items-center justify-center mx-auto text-xl mb-3">
                                    <i class="fa-solid fa-user-slash"></i>
                                </div>
                                <p class="text-sm font-semibold text-slate-400">No candidate records found</p>
                                <a href="{{ route('registration.form') }}" class="inline-block mt-3 text-xs text-emerald-400 hover:underline">
                                    Click here to register a new candidate &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($passengers->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $passengers->links() }}
            </div>
        @endif
    </div>
</div>

<!-- INBOX & OTP INSPECTOR MODAL -->
<div id="inboxModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-500/20 text-teal-400 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Mailbox & OTP Inspector</h3>
                    <p id="inboxEmailHeader" class="text-xs font-mono text-emerald-400"></p>
                </div>
            </div>
            <button type="button" onclick="closeInboxModal()" class="text-slate-400 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Mail Credentials Row -->
        <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 font-mono text-xs flex items-center justify-between">
            <div>
                <span class="text-slate-500">Mail.tm Password:</span>
                <span id="inboxMailPass" class="text-teal-400 font-bold ml-2"></span>
            </div>
            <button type="button" onclick="refreshInboxData()" id="refreshInboxBtn" class="px-3 py-1 rounded bg-slate-800 hover:bg-slate-700 text-emerald-400 text-xs font-bold flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>Refresh Inbox</span>
            </button>
        </div>

        <!-- OTP Highlight Box -->
        <div class="p-5 rounded-2xl bg-gradient-to-b from-emerald-500/15 to-emerald-500/5 border border-emerald-500/30 text-center space-y-2">
            <p class="text-xs uppercase font-bold text-emerald-400 tracking-wider">Latest Detected Taqamul OTP</p>
            <div class="flex items-center justify-center gap-3">
                <p id="inboxOtpCode" class="text-3xl font-mono font-extrabold text-white tracking-[0.25em]">Checking...</p>
                <button type="button" onclick="copyCurrentOtp()" title="Copy OTP" class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition-all shadow-md">
                    <i class="fa-regular fa-copy"></i>
                    <span>Copy</span>
                </button>
            </div>
        </div>

        <!-- Messages List -->
        <div class="space-y-2">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Received Messages (<span id="inboxCount">0</span>)</p>
            <div id="inboxMessagesList" class="max-h-48 overflow-y-auto space-y-2 custom-scrollbar">
                <p class="text-xs text-slate-500 text-center py-4">Loading messages...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let currentInboxPassengerId = null;

    function copyText(text, btnElement = null) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        
        // Find button element
        let btn = btnElement;
        if (!btn && window.event) {
            btn = window.event.currentTarget || (window.event.target ? window.event.target.closest('button') : null);
        }

        if (btn) {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i>';
            btn.classList.add('text-emerald-400');
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('text-emerald-400');
            }, 1500);
        }
    }

    function copyCurrentOtp() {
        const otp = document.getElementById('inboxOtpCode').innerText.trim();
        if (otp && otp !== 'Checking...' && otp !== 'No OTP detected yet' && otp !== 'Error loading') {
            copyText(otp);
        }
    }

    async function openInboxModal(passengerId, email, password) {
        currentInboxPassengerId = passengerId;
        document.getElementById('inboxEmailHeader').innerText = email;
        document.getElementById('inboxMailPass').innerText = password || 'N/A';
        document.getElementById('inboxModal').classList.remove('hidden');
        await refreshInboxData();
    }

    function closeInboxModal() {
        document.getElementById('inboxModal').classList.add('hidden');
    }

    async function refreshInboxData() {
        if (!currentInboxPassengerId) return;

        const refreshBtn = document.getElementById('refreshInboxBtn');
        refreshBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Refreshing...';

        const otpBox = document.getElementById('inboxOtpCode');
        const list = document.getElementById('inboxMessagesList');

        try {
            const res = await fetch(`/admin/passengers/${currentInboxPassengerId}/check-inbox`);
            const data = await res.json();

            if (data.success) {
                otpBox.innerText = data.otp || 'No OTP detected yet';
                document.getElementById('inboxCount').innerText = data.messages_count;

                if (data.messages && data.messages.length > 0) {
                    list.innerHTML = data.messages.map(m => `
                        <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-bold text-white">${m.subject || 'No Subject'}</span>
                                <span class="text-[10px] text-slate-500 font-mono">${new Date(m.createdAt).toLocaleTimeString()}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 truncate">${m.intro || ''}</p>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<p class="text-xs text-slate-500 text-center py-4">No emails found (Inbox empty)</p>';
                }
            } else {
                otpBox.innerText = 'Error loading';
                list.innerHTML = `<p class="text-xs text-rose-400 text-center py-4">${data.message}</p>`;
            }
        } catch (e) {
            otpBox.innerText = 'Network Error';
        } finally {
            refreshBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Refresh Inbox';
        }
    }

    async function retryPassenger(id, name) {
        const { isConfirmed } = await Swal.fire({
            title: 'Retry Registration?',
            html: `Do you want to re-run the autonomous bot for <b>${name}</b> in background?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Yes, Retry Now',
            background: '#1e293b',
            color: '#fff'
        });

        if (!isConfirmed) return;

        try {
            const response = await fetch(`/admin/passengers/${id}/retry`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            const result = await response.json();
            if (result.success) {
                // Instantly update cell to processing
                const statusCell = document.getElementById(`status-cell-${id}`);
                if (statusCell) {
                    statusCell.innerHTML = `
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold shadow-sm shadow-cyan-500/10 animate-pulse whitespace-nowrap">
                            <i class="fa-solid fa-spinner fa-spin text-[10px] text-cyan-400"></i>
                            <span id="progress-text-${id}">Starting Bot...</span>
                        </span>`;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Retrying in Background!',
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false,
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
        }
    }

    // 🚀 Direct Autonomous On-Screen Login to https://svp-international.pacc.sa/auth/login?role=labor
    async function triggerDirectAutoLogin(id, name) {
        Swal.fire({
            icon: 'info',
            title: 'Starting Auto-Login...',
            text: `Opening Chrome to auto-login for ${name} at https://svp-international.pacc.sa/auth/login?role=labor...`,
            timer: 2000,
            showConfirmButton: false,
            background: '#1e293b',
            color: '#fff'
        });

        try {
            const res = await fetch(`/admin/passengers/${id}/start-login-bot`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Auto-Login Running on Screen!',
                    text: 'Chrome is filling credentials, solving CAPTCHA and verifying OTP.',
                    timer: 3000,
                    showConfirmButton: false,
                    background: '#1e293b',
                    color: '#fff'
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Login Error', text: data.message, background: '#1e293b', color: '#fff' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network Error', text: e.message, background: '#1e293b', color: '#fff' });
        }
    }

    // ⚡ Real-Time Auto-Updating Live Status Poller (Zero Page Refresh Needed)
    let knownStatuses = {};

    async function pollLiveStatuses() {
        try {
            const res = await fetch("{{ route('admin.passengers.live_statuses') }}");
            const data = await res.json();
            if (!data.success || !data.passengers) return;

            // Update Header Stats
            if (data.stats) {
                const sTotal = document.getElementById('statTotal');
                const sCompleted = document.getElementById('statCompleted');
                const sAcDone = document.getElementById('statAcDone');
                const sMailbox = document.getElementById('statMailbox');
                if (sTotal) sTotal.innerText = data.stats.total;
                if (sCompleted) sCompleted.innerText = data.stats.completed;
                if (sAcDone) sAcDone.innerText = data.stats.ac_done;
                if (sMailbox) sMailbox.innerText = data.stats.mailboxes;
            }

            // Update Row Data Dynamically
            data.passengers.forEach(p => {
                const row = document.getElementById(`passenger-row-${p.id}`);
                if (!row) return;

                // Live update progress text if in processing state
                if (p.status === 'processing') {
                    const progEl = document.getElementById(`progress-text-${p.id}`);
                    const progressMsg = p.progress_text || 'Processing...';
                    if (progEl) {
                        progEl.innerText = progressMsg;
                    } else {
                        const statusCell = document.getElementById(`status-cell-${p.id}`);
                        if (statusCell) {
                            statusCell.innerHTML = `
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold shadow-sm shadow-cyan-500/10 animate-pulse whitespace-nowrap">
                                    <i class="fa-solid fa-spinner fa-spin text-[10px] text-cyan-400"></i>
                                    <span id="progress-text-${p.id}">${progressMsg}</span>
                                </span>`;
                        }
                    }
                }

                const currentDomStatus = row.getAttribute('data-status');
                if (currentDomStatus !== p.status) {
                    row.setAttribute('data-status', p.status);

                    // Update Status Badge
                    const statusCell = document.getElementById(`status-cell-${p.id}`);
                    if (statusCell) {
                        if (p.status === 'completed') {
                            statusCell.innerHTML = `
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-[11px] font-extrabold shadow-sm">
                                    <i class="fa-solid fa-circle-check text-[10px] text-emerald-400"></i> Completed
                                </span>`;
                        } else if (p.status === 'AC Done' || p.status === 'ac_done') {
                            statusCell.innerHTML = `
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-500/15 border border-sky-500/30 text-sky-300 text-[11px] font-extrabold shadow-sm">
                                    <i class="fa-solid fa-user-check text-[10px] text-sky-400"></i> AC Done
                                </span>`;

                            // Highlight Row with sky glow effect
                            row.classList.add('bg-sky-500/15');
                            setTimeout(() => row.classList.remove('bg-sky-500/15'), 5000);

                            // Trigger Pop-up Toast
                            Swal.fire({
                                icon: 'success',
                                title: 'Account Created (AC Done)!',
                                text: `${p.first_name} ${p.last_name || ''} account is ready on Taqamul.`,
                                timer: 3500,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end',
                                background: '#1e293b',
                                color: '#fff'
                            });
                        } else if (p.status === 'processing') {
                            const progressMsg = p.progress_text || 'Processing...';
                            statusCell.innerHTML = `
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-[11px] font-bold shadow-sm shadow-cyan-500/10 animate-pulse whitespace-nowrap">
                                    <i class="fa-solid fa-spinner fa-spin text-[10px] text-cyan-400"></i>
                                    <span id="progress-text-${p.id}">${progressMsg}</span>
                                </span>`;
                        } else if (p.status === 'failed') {
                            statusCell.innerHTML = `
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[11px] font-bold">
                                    <i class="fa-solid fa-circle-xmark text-[10px]"></i> Failed
                                </span>`;
                        }
                    }

                    // Update Email Cell
                    const emailCell = document.getElementById(`email-cell-${p.id}`);
                    if (emailCell && p.email) {
                        emailCell.innerHTML = `
                            <div class="flex items-center gap-2 font-mono">
                                <span class="text-slate-200 select-all">${p.email}</span>
                                <button type="button" onclick="copyText('${p.email}', this)" title="Copy Email" class="text-slate-500 hover:text-emerald-400 transition-all p-1 rounded">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>`;
                    }

                    // Update Password Cell
                    const passCell = document.getElementById(`pass-cell-${p.id}`);
                    if (passCell && p.password) {
                        passCell.innerHTML = `
                            <div class="flex items-center gap-2 font-mono">
                                <span class="text-emerald-400 font-bold select-all">${p.password}</span>
                                <button type="button" onclick="copyText('${p.password}', this)" title="Copy Password" class="text-slate-500 hover:text-emerald-400 transition-all p-1 rounded">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>`;
                    }

                    // Update Actions Cell
                    const actionsCell = document.getElementById(`actions-cell-${p.id}`);
                    if (actionsCell) {
                        const statusLower = String(p.status || '').toLowerCase();
                        const retryBtn = (statusLower !== 'ac done' && statusLower !== 'completed') ? `
                            <button type="button" onclick="retryPassenger(${p.id}, '${p.first_name} ${p.last_name || ''}')" title="Retry / Restart Bot Automation" class="w-8 h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500/25 text-amber-400 border border-amber-500/30 flex items-center justify-center transition-all">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>` : '';
                        
                        actionsCell.innerHTML = `
                            <div class="flex items-center justify-end gap-2">
                                ${retryBtn}
                                <a href="https://svp-international.pacc.sa/auth/login?role=labor#auto_id=${p.id}&email=${encodeURIComponent(p.email)}&pass=${encodeURIComponent(p.password)}&server=${encodeURIComponent(window.location.origin)}&ck={{ urlencode(\App\Models\Setting::get('capsolver_api_key', 'CAP-1C910649B8AEADE973B68571F5449DA4ACE38F5A22ADE82D2596BE826B28C133')) }}" 
                                   target="_blank" 
                                   title="Auto-Login to Taqamul Portal (Opens in Next Tab)" 
                                   class="w-8 h-8 rounded-lg bg-sky-500/10 hover:bg-sky-500/25 text-sky-400 border border-sky-500/30 flex items-center justify-center transition-all shadow-sm">
                                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                </a>
                                <a href="/admin/passengers/${p.id}" title="View Full Profile" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <form action="/admin/passengers/${p.id}" method="POST" onsubmit="return confirm('Are you sure you want to delete this passenger record?');" class="inline">
                                    <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" title="Delete Record" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 flex items-center justify-center transition-colors">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>`;
                    }
                }
            });
        } catch (e) {}
    }

    // Run background live polling every 2.5 seconds
    setInterval(pollLiveStatuses, 2500);
</script>
@endpush
