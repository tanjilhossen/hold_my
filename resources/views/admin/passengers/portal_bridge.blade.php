<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Login Portal - {{ $passenger->full_name }} ({{ $passenger->passport_number }})</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Top Automation Floating Toolbar -->
    <header class="bg-slate-900/95 backdrop-blur border-b border-slate-800 px-6 py-3.5 flex flex-wrap items-center justify-between gap-4 sticky top-0 z-50 shadow-2xl">
        <!-- Candidate Info -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 to-blue-500 flex items-center justify-center text-white text-lg font-bold shadow-lg shadow-sky-500/20">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <h1 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <span>{{ $passenger->full_name }}</span>
                    <span class="text-[11px] px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono font-bold">{{ $passenger->passport_number }}</span>
                </h1>
                <p class="text-xs text-slate-400 font-mono">{{ $passenger->email }}</p>
            </div>
        </div>

        <!-- Live Status & OTP Notification -->
        <div class="flex items-center gap-3 bg-slate-950/80 border border-slate-800 px-4 py-2 rounded-xl">
            <div class="text-left">
                <p class="text-[10px] uppercase font-bold text-slate-500">Live Login OTP:</p>
                <p id="topOtpDisplay" class="text-base font-extrabold text-emerald-400 font-mono tracking-widest">
                    {{ $passenger->otp_code ?: 'Listening...' }}
                </p>
            </div>
            <button type="button" onclick="copyOtpCode()" title="Copy OTP" class="w-8 h-8 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 flex items-center justify-center transition-all">
                <i class="fa-regular fa-copy"></i>
            </button>
            <button type="button" onclick="pollOtpNow()" title="Refresh Mailbox" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-teal-400 flex items-center justify-center transition-all">
                <i class="fa-solid fa-arrows-rotate"></i>
            </button>
        </div>

        <!-- Quick Automation Actions -->
        <div class="flex items-center gap-2.5">
            <!-- Copy Email Button -->
            <button type="button" onclick="copyCreds('{{ $passenger->email }}', this, 'Email Copied!')" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold font-mono flex items-center gap-1.5 transition-all">
                <i class="fa-regular fa-copy text-sky-400"></i>
                <span>Copy Email</span>
            </button>

            <!-- Copy Password Button -->
            <button type="button" onclick="copyCreds('{{ $passenger->password }}', this, 'Password Copied!')" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold font-mono flex items-center gap-1.5 transition-all">
                <i class="fa-regular fa-copy text-emerald-400"></i>
                <span>Copy Password</span>
            </button>

            <!-- 1-Click Solve reCAPTCHA -->
            <button type="button" onclick="solveLoginCaptcha()" id="solveCaptchaBtn" class="px-3.5 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-bold flex items-center gap-1.5 transition-all shadow-sm">
                <i class="fa-solid fa-brain text-amber-400"></i>
                <span>Auto-Solve reCAPTCHA</span>
            </button>

            <!-- Open Direct Portal Tab -->
            <a href="https://svp-international.pacc.sa/auth/login?role=labor" target="_blank" class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-extrabold flex items-center gap-2 shadow-lg shadow-emerald-600/20 transition-all">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>Open Taqamul Tab</span>
            </a>
        </div>
    </header>

    <!-- Main Content Area: Embedded Live Portal or Direct Automation Window -->
    <main class="flex-1 flex flex-col relative w-full h-[calc(100vh-73px)] overflow-hidden">
        <!-- Live Taqamul Frame -->
        <iframe id="portalFrame" 
            src="https://svp-international.pacc.sa/auth/login?role=labor" 
            class="w-full flex-1 border-0 bg-white"
            allow="clipboard-read; clipboard-write">
        </iframe>

        <!-- Automation Helper Floating Drawer (Bottom Right) -->
        <div class="fixed bottom-6 right-6 z-40 bg-slate-900/95 backdrop-blur-xl border border-slate-700/80 rounded-2xl p-5 shadow-2xl max-w-sm w-full space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Live Auto-Fill Helper</h3>
                </div>
                <span class="text-[10px] font-mono text-slate-500">Candidate #{{ $passenger->id }}</span>
            </div>

            <!-- Credentials Fast-Click Blocks -->
            <div class="space-y-2 font-mono text-xs">
                <div onclick="copyCreds('{{ $passenger->email }}', this, 'Email Copied!')" class="p-2.5 rounded-xl bg-slate-950/90 border border-slate-800/80 hover:border-sky-500/50 cursor-pointer flex items-center justify-between transition-all group">
                    <div class="overflow-hidden pr-2">
                        <p class="text-[10px] text-slate-500 font-sans">Login Email (Click to Copy):</p>
                        <p class="text-white font-bold truncate">{{ $passenger->email }}</p>
                    </div>
                    <i class="fa-regular fa-copy text-slate-500 group-hover:text-sky-400"></i>
                </div>

                <div onclick="copyCreds('{{ $passenger->password }}', this, 'Password Copied!')" class="p-2.5 rounded-xl bg-slate-950/90 border border-slate-800/80 hover:border-emerald-500/50 cursor-pointer flex items-center justify-between transition-all group">
                    <div>
                        <p class="text-[10px] text-slate-500 font-sans">Password (Click to Copy):</p>
                        <p class="text-emerald-400 font-bold">{{ $passenger->password }}</p>
                    </div>
                    <i class="fa-regular fa-copy text-slate-500 group-hover:text-emerald-400"></i>
                </div>

                <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] text-emerald-400 font-sans font-bold">Auto-Captured OTP Code:</p>
                        <p id="drawerOtp" class="text-lg font-extrabold text-emerald-300 font-mono tracking-widest">
                            {{ $passenger->otp_code ?: 'Waiting...' }}
                        </p>
                    </div>
                    <button type="button" onclick="copyOtpCode()" class="px-2.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs shadow transition-all">
                        Copy OTP
                    </button>
                </div>
            </div>

            <!-- Auto-Solve Status Log -->
            <p id="helperStatusLog" class="text-[11px] text-slate-400 font-mono truncate">
                ⚡ Auto-OTP listener active for @wafidmaster.com / YOPmail.
            </p>
        </div>
    </main>

    <script>
        const PASSENGER_ID = {{ $passenger->id }};
        const CAPSOLVER_KEY = "{{ addslashes($capsolverKey) }}";

        // Auto-Copy Helper
        function copyCreds(text, el, toastMsg = 'Copied!') {
            navigator.clipboard.writeText(text);
            if (el) {
                const orig = el.innerHTML;
                el.classList.add('border-emerald-500');
                setTimeout(() => el.classList.remove('border-emerald-500'), 1200);
            }
            Swal.fire({
                icon: 'success',
                title: toastMsg,
                text: text,
                timer: 1200,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                background: '#1e293b',
                color: '#fff'
            });
        }

        function copyOtpCode() {
            const otp = document.getElementById('topOtpDisplay').innerText.trim();
            if (otp && otp.length === 6) {
                copyCreds(otp, null, 'OTP Code Copied!');
            }
        }

        // Live OTP Polling Loop
        async function pollOtpNow() {
            try {
                const res = await fetch(`/admin/passengers/${PASSENGER_ID}/check-inbox`);
                const data = await res.json();
                if (data.success && data.otp) {
                    document.getElementById('topOtpDisplay').innerText = data.otp;
                    document.getElementById('drawerOtp').innerText = data.otp;
                    document.getElementById('helperStatusLog').innerHTML = `🎯 <span class="text-emerald-400 font-bold">New Login OTP Received: ${data.otp}</span>`;
                }
            } catch (e) {}
        }

        setInterval(pollOtpNow, 2500);

        // CapSolver AI Solver
        async function solveLoginCaptcha() {
            if (!CAPSOLVER_KEY) {
                Swal.fire({ icon: 'warning', title: 'CapSolver Key Missing', text: 'Please set CapSolver API key in Settings.', background: '#1e293b', color: '#fff' });
                return;
            }

            const btn = document.getElementById('solveCaptchaBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-amber-400"></i> Solving CAPTCHA...';
            document.getElementById('helperStatusLog').innerText = '🧩 CapSolver AI is solving reCAPTCHA v2...';

            try {
                const taskRes = await fetch('https://api.capsolver.com/createTask', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        clientKey: CAPSOLVER_KEY,
                        task: {
                            type: 'ReCaptchaV2TaskProxyLess',
                            websiteURL: 'https://svp-international.pacc.sa/auth/login?role=labor',
                            websiteKey: '6Ld_AwktAAAAAKAPK-1BGolix7oeSFA7ibXEhYQy'
                        }
                    })
                });
                const taskData = await taskRes.json();
                if (taskData.errorId !== 0) throw new Error(taskData.errorDescription || 'Task creation failed');

                const taskId = taskData.taskId;
                for (let i = 0; i < 30; i++) {
                    await new Promise(r => setTimeout(r, 1500));
                    const res = await fetch('https://api.capsolver.com/getTaskResult', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ clientKey: CAPSOLVER_KEY, taskId: taskId })
                    });
                    const resData = await res.json();
                    if (resData.status === 'ready') {
                        const token = resData.solution.gRecaptchaResponse;
                        navigator.clipboard.writeText(token);
                        document.getElementById('helperStatusLog').innerHTML = '✅ <span class="text-emerald-400 font-bold">reCAPTCHA Solved & Token Copied!</span>';
                        Swal.fire({
                            icon: 'success',
                            title: 'reCAPTCHA Solved!',
                            text: 'Token is ready and copied to clipboard.',
                            timer: 1500,
                            showConfirmButton: false,
                            background: '#1e293b',
                            color: '#fff'
                        });
                        break;
                    }
                }
            } catch (err) {
                document.getElementById('helperStatusLog').innerText = '❌ Captcha Error: ' + err.message;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-brain text-amber-400"></i> <span>Auto-Solve reCAPTCHA</span>';
            }
        }
    </script>
</body>
</html>
