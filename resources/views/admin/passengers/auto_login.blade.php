@extends('layouts.app')

@section('title', 'Autonomous Taqamul Login Assistant - ' . $passenger->full_name)

@section('content')
<div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-600 to-blue-500 flex items-center justify-center text-white text-2xl shadow-xl shadow-sky-500/20">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </div>
            <div>
                <h2 class="text-xl md:text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                    <span>Autonomous Taqamul Portal Login</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 font-mono font-bold">1-Click Bot</span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Automated login for candidate <b class="text-white">{{ $passenger->full_name }}</b> (Passport: <span class="text-amber-400 font-mono font-bold">{{ $passenger->passport_number }}</span>)</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.passengers.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold transition-all flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Passengers</span>
            </a>

            <a href="https://svp-international.pacc.sa/auth/login?role=labor" target="_blank" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/20 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>Open Login Portal</span>
            </a>
        </div>
    </div>

    <!-- Main Automation Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Automation Controller (Left 2 Columns) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- ⚡ 1-Click Bot Launcher Card -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-900 to-slate-950 border border-sky-500/30 rounded-2xl p-6 shadow-2xl space-y-6 relative overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Full-Autonomous On-Screen Login</h3>
                            <p class="text-xs text-slate-400">Chrome opens &rarr; Fills Credentials &rarr; Solves Captcha &rarr; Fetches OTP &rarr; Submits Login!</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <span class="text-[10px] text-slate-500 font-bold uppercase">Step 1</span>
                        <p class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                            <i class="fa-solid fa-keyboard text-sky-400"></i> Auto-Type Email & Pass
                        </p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <span class="text-[10px] text-slate-500 font-bold uppercase">Step 2</span>
                        <p class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                            <i class="fa-solid fa-brain text-amber-400"></i> CapSolver reCAPTCHA
                        </p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <span class="text-[10px] text-slate-500 font-bold uppercase">Step 3</span>
                        <p class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                            <i class="fa-solid fa-inbox text-teal-400"></i> Auto-Fetch Mail OTP
                        </p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <span class="text-[10px] text-slate-500 font-bold uppercase">Step 4</span>
                        <p class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-check text-emerald-400"></i> Land in Dashboard
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-4 pt-2">
                    <button type="button" onclick="startLoginBot()" id="startBotBtn"
                        class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-sky-600 via-sky-500 to-blue-500 hover:from-sky-500 hover:to-blue-400 text-white font-extrabold text-sm shadow-xl shadow-sky-600/30 transition-all flex items-center justify-center gap-3 active:scale-95">
                        <i class="fa-solid fa-play text-amber-300"></i>
                        <span>Start Autonomous Login Bot Now</span>
                    </button>

                    <p id="botStatusText" class="text-xs text-slate-400 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-sky-400"></i>
                        <span>Ready to launch autonomous bot.</span>
                    </p>
                </div>
            </div>

            <!-- Live Mailbox & OTP Monitoring Box -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-envelope-open-text text-teal-400 text-lg"></i>
                        <h3 class="text-sm font-bold text-white">Live Candidate Mailbox & OTP Tracker</h3>
                    </div>
                    <button type="button" onclick="pollOtpNow()" id="pollBtn" class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-teal-300 text-xs font-bold border border-slate-700 flex items-center gap-1.5 transition-all">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span>Check Inbox Now</span>
                    </button>
                </div>

                <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <p class="text-xs text-slate-400">Latest Login OTP Code:</p>
                        <p id="liveOtpDisplay" class="text-3xl font-extrabold text-emerald-400 font-mono tracking-widest mt-1">
                            {{ $passenger->otp_code ?: 'Waiting for OTP...' }}
                        </p>
                    </div>

                    <button type="button" onclick="copyOtpFromDisplay()" class="px-4 py-2.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold flex items-center gap-2 transition-all">
                        <i class="fa-regular fa-copy"></i>
                        <span>Copy OTP Code</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Candidate Credentials Card (Right 1 Column) -->
        <div class="space-y-6">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
                <div class="flex items-center gap-2.5 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-id-card text-emerald-400 text-base"></i>
                    <h3 class="text-sm font-bold text-white">Candidate Login Data</h3>
                </div>

                <div class="space-y-3 font-mono text-xs">
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Candidate Name:</span>
                            <span class="text-white font-sans font-bold">{{ $passenger->full_name }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Passport Number:</span>
                            <span class="text-amber-400 font-bold">{{ $passenger->passport_number }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Taqamul Login Email:</span>
                            <button type="button" onclick="copyValue('{{ $passenger->email }}', this)" title="Copy" class="text-slate-500 hover:text-emerald-400">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-white font-bold truncate select-all">{{ $passenger->email }}</p>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Taqamul Password:</span>
                            <button type="button" onclick="copyValue('{{ $passenger->password }}', this)" title="Copy" class="text-slate-500 hover:text-emerald-400">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-emerald-400 font-bold select-all">{{ $passenger->password }}</p>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Mailbox Type:</span>
                            <span class="text-teal-400 font-bold">
                                {{ str_contains($passenger->email, '@wafidmaster.com') ? 'Private Server' : 'YOPmail' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="https://svp-international.pacc.sa/auth/login?role=labor" target="_blank"
                        class="w-full py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-arrow-up-right-from-square text-emerald-400"></i>
                        <span>Manual Portal Login</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    async function startLoginBot() {
        const btn = document.getElementById('startBotBtn');
        const statusText = document.getElementById('botStatusText');

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-amber-300"></i> <span>Launching Autonomous Bot...</span>';
        statusText.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-sky-400"></i> <span>Browser opening on screen... solving CAPTCHA and fetching OTP...</span>';

        try {
            const res = await fetch("{{ route('admin.passengers.start_login_bot', $passenger->id) }}", {
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
                    title: 'Autonomous Login Running!',
                    text: 'Chrome is opening on your screen to perform automated login.',
                    timer: 2500,
                    showConfirmButton: false,
                    background: '#1e293b',
                    color: '#fff'
                });
                statusText.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-400"></i> <span class="text-emerald-300">Bot is filling credentials & solving reCAPTCHA...</span>';
                
                // Start live OTP monitoring
                startOtpPollingLoop();
            } else {
                Swal.fire({ icon: 'error', title: 'Bot Error', text: data.message, background: '#1e293b', color: '#fff' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network Error', text: e.message, background: '#1e293b', color: '#fff' });
        } finally {
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-play text-amber-300"></i> <span>Re-Launch Autonomous Bot</span>';
            }, 4000);
        }
    }

    let pollTimer = null;
    function startOtpPollingLoop() {
        if (pollTimer) clearInterval(pollTimer);
        let count = 0;
        pollTimer = setInterval(async () => {
            count++;
            if (count > 30) clearInterval(pollTimer);
            await pollOtpNow(false);
        }, 3000);
    }

    async function pollOtpNow(showToast = true) {
        try {
            const res = await fetch("{{ route('admin.passengers.check_inbox', $passenger->id) }}");
            const data = await res.json();
            if (data.success && data.otp) {
                document.getElementById('liveOtpDisplay').innerText = data.otp;
                if (showToast) {
                    Swal.fire({
                        icon: 'success',
                        title: 'OTP Code Received!',
                        text: `Code: ${data.otp}`,
                        timer: 1500,
                        showConfirmButton: false,
                        background: '#1e293b',
                        color: '#fff'
                    });
                }
            }
        } catch (e) {}
    }

    function copyOtpFromDisplay() {
        const otp = document.getElementById('liveOtpDisplay').innerText.trim();
        if (otp && otp.length === 6) {
            navigator.clipboard.writeText(otp);
            Swal.fire({ icon: 'success', title: 'OTP Copied!', text: otp, timer: 1200, showConfirmButton: false, background: '#1e293b', color: '#fff' });
        }
    }

    function copyValue(text, btn) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i>';
            setTimeout(() => btn.innerHTML = orig, 1500);
        }
    }

    // Auto-poll on load if opened
    document.addEventListener('DOMContentLoaded', () => {
        pollOtpNow(false);
    });
</script>
@endpush
@endsection
