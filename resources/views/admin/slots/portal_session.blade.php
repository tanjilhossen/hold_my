<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Live Taqamul Booking & 3DS Payment Bridge - {{ $selectedCenter }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.6); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.8); border-radius: 9999px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- Top Automation Floating Command Header -->
    <header class="bg-slate-900/95 backdrop-blur-xl border-b border-slate-800 px-6 py-3.5 flex flex-wrap items-center justify-between gap-4 sticky top-0 z-50 shadow-2xl">
        <!-- Candidate Data Display: Name, Passport Number, Occupation & Center -->
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white text-lg font-bold shadow-lg shadow-emerald-500/20 shrink-0">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-sm font-black text-white tracking-tight flex items-center gap-1.5">
                        <span id="headerCandidateName">{{ $candidateName }}</span>
                    </h1>
                    <span class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        Passport: <span id="headerCandidatePassport">{{ $candidatePassport }}</span>
                    </span>
                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        {{ $candidateOccupation ?? 'Plumber (General)' }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 font-medium flex items-center gap-2">
                    <span class="text-sky-300 font-bold flex items-center gap-1">
                        <i class="fa-solid fa-location-dot text-rose-400"></i> <span id="headerCenterDisplay">{{ $selectedCenter }}</span>
                    </span>
                    <span class="text-slate-600">•</span>
                    <span class="text-slate-400 font-mono text-[11px]"><i class="fa-regular fa-envelope text-slate-500"></i> {{ $activeEmail }}</span>
                </p>
            </div>
        </div>

        <!-- Live 5-Step Progress Indicators -->
        <div class="flex items-center gap-2 bg-slate-950/80 border border-slate-800 px-3.5 py-1.5 rounded-2xl flex-wrap">
            <div id="stepBadge1" class="flex items-center gap-1.5 text-xs font-bold text-slate-500 px-2.5 py-1 rounded-xl transition-all">
                <i class="fa-regular fa-circle text-[10px]"></i>
                <span>1. Login</span>
            </div>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-700"></i>
            <div id="stepBadge2" class="flex items-center gap-1.5 text-xs font-bold text-slate-500 px-2.5 py-1 rounded-xl transition-all">
                <i class="fa-regular fa-circle text-[10px]"></i>
                <span>2. Hold Seat</span>
            </div>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-700"></i>
            <div id="stepBadge3" class="flex items-center gap-1.5 text-xs font-bold text-slate-500 px-2.5 py-1 rounded-xl transition-all">
                <i class="fa-regular fa-circle text-[10px]"></i>
                <span>3. Reserve</span>
            </div>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-700"></i>
            <div id="stepBadge4" class="flex items-center gap-1.5 text-xs font-bold text-slate-500 px-2.5 py-1 rounded-xl transition-all">
                <i class="fa-regular fa-circle text-[10px]"></i>
                <span>4. IFIC Card Submit</span>
            </div>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-700"></i>
            <div id="stepBadge5" class="flex items-center gap-1.5 text-xs font-bold text-slate-500 px-2.5 py-1 rounded-xl transition-all">
                <i class="fa-regular fa-circle text-[10px]"></i>
                <span>5. 3DS SMS OTP</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5">
            <button type="button" id="copyLinkBtn" onclick="copyActivePaymentLink()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold font-mono flex items-center gap-1.5 transition-all cursor-pointer hidden">
                <i class="fa-regular fa-copy text-emerald-400"></i>
                <span>Copy Link</span>
            </button>
            <button type="button" onclick="cancelCurrentBooking(this)" id="cancelBookingBtn" class="px-3.5 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/40 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-md shadow-rose-500/10">
                <i class="fa-solid fa-ban text-rose-400"></i>
                <span>Cancel Process</span>
            </button>
            <button type="button" onclick="window.close()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-bold flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-xmark"></i>
                <span>Close Tab</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace (Wide Side-by-Side Responsive Layout) -->
    <main class="flex-1 p-6 bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 flex flex-col justify-start items-center">
        <div class="max-w-7xl w-full mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- LEFT COLUMN (lg:col-span-5): Terminal Pipeline & Candidate Invoice Details -->
            <div class="lg:col-span-5 space-y-5">
                
                <!-- Live Automation Pipeline Terminal (Moved to Left Side) -->
                <div id="liveProgressContainer" class="p-4 rounded-3xl bg-slate-900/95 border border-slate-800 text-left space-y-3 shadow-xl backdrop-blur-xl">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 border-b border-slate-800 pb-2.5">
                        <span class="flex items-center gap-2 text-white font-bold"><i class="fa-solid fa-terminal text-emerald-400"></i> Live Automation Pipeline</span>
                        <span id="liveProgressStatus" class="font-mono text-[11px] text-sky-400 flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-sky-500/10 border border-sky-500/20">
                            <i class="fa-solid fa-circle-notch fa-spin text-[9px]"></i> Active
                        </span>
                    </div>
                    <div id="liveActivityLog" class="space-y-2 font-mono text-xs text-slate-300 min-h-[160px] max-h-64 overflow-y-auto custom-scrollbar p-3 rounded-2xl bg-slate-950 border border-slate-800 shadow-inner">
                        <div class="text-emerald-400 flex items-center gap-2 text-[11.5px]">
                            <i class="fa-solid fa-circle-check text-[11px] text-emerald-400"></i>
                            <span>Candidate identified: <strong class="text-white">{{ $candidateName }}</strong> ({{ $candidatePassport }})</span>
                        </div>
                        <div class="text-sky-300 flex items-center gap-2 text-[11.5px]">
                            <i class="fa-solid fa-location-dot text-[11px] text-rose-400"></i>
                            <span>Target center: <strong class="text-white">{{ $selectedCenter }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Active Card & Invoice Summary Card -->
                <div id="bookingSummaryCard" class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 text-left space-y-3 shadow-xl backdrop-blur-xl">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-file-invoice-dollar text-emerald-400"></i> Candidate & Exam Details
                        </h3>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">Verified</span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-user text-emerald-400"></i> Candidate Name:</span>
                        <span id="cardCandidateName" class="text-white font-extrabold text-sm">{{ $candidateName }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-passport text-amber-400"></i> Passport Number:</span>
                        <span id="cardCandidatePassport" class="text-amber-300 font-mono font-bold px-2 py-0.5 rounded bg-amber-500/15 border border-amber-500/30">{{ $candidatePassport }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-briefcase text-sky-400"></i> Profession / Occupation:</span>
                        <span id="cardCandidateOccupation" class="text-emerald-400 font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30">{{ $candidateOccupation ?? 'Plumber (General)' }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-location-dot text-rose-400"></i> Designated Center:</span>
                        <span id="cardCenterDisplay" class="text-white font-bold">{{ $selectedCenter }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-building-circle-check text-emerald-400"></i> Assigned Center:</span>
                        <span id="cardAssignedCenterDisplay" class="text-sky-300 font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-notch fa-spin text-[10px] text-sky-400"></i>
                            <span class="text-slate-400 font-normal">Awaiting Server Lock...</span>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-receipt text-teal-400"></i> Reservation ID:</span>
                        <span id="cardReservationId" class="text-sky-300 font-mono font-bold">-</span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-credit-card text-emerald-400"></i> Payment Card:</span>
                        <span class="text-amber-300 font-bold flex items-center gap-1.5 font-mono">
                            <i class="fa-solid fa-credit-card text-emerald-400"></i>
                            <span>{{ $defaultCard ? $defaultCard->bank_name . ' (' . $defaultCard->masked_number . ')' : 'IFIC Bank Card' }}</span>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-xs border-b border-slate-800 pb-2">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-coins text-amber-400"></i> Official Exam Fee:</span>
                        <span id="cardAmount" class="text-emerald-400 font-bold font-mono">SAR 50.00</span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5"><i class="fa-solid fa-clock text-amber-400"></i> Seat Hold Expiration:</span>
                        <span id="cardExpiration" class="text-amber-300 font-bold font-mono">~20 Minutes</span>
                    </div>
                </div>

                <!-- Error Fallback Action -->
                <div id="errorActionBox" class="p-4 rounded-3xl bg-rose-950/40 border border-rose-500/40 space-y-3 hidden text-left shadow-xl">
                    <div class="flex items-center gap-2 text-rose-300 font-bold text-xs">
                        <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                        <span id="errorBoxTitle">Booking Execution Failed</span>
                    </div>
                    <p id="errorBoxMessage" class="text-xs text-slate-300"></p>
                    <div id="errorBtnHolder" class="pt-1"></div>
                </div>

            </div>

            <!-- RIGHT COLUMN (lg:col-span-7): IFIC Bank 3DS OTP Portal & Standby Awaiting -->
            <div class="lg:col-span-7 space-y-5">
                
                <!-- Standby Awaiting Card (Shown before 3DS loads) -->
                <div id="threeDsAwaitingBox" class="bg-slate-900/80 border border-slate-800 rounded-3xl p-12 text-center space-y-4 shadow-xl">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-2xl animate-pulse shadow-lg shadow-emerald-500/10">
                        <i id="statusMainIcon" class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 id="statusTitle" class="text-base font-black text-white">Preparing IFIC Bank 3D-Secure 2.0 Gateway</h3>
                        <p id="statusSubtitle" class="text-xs text-slate-400 max-w-md mx-auto">Please wait while candidate login, temporary seat hold, and exam reservation are being processed.</p>
                    </div>
                    <div class="flex justify-center gap-2 pt-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span class="w-2 h-2 rounded-full bg-sky-400 animate-ping delay-100"></span>
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping delay-200"></span>
                    </div>
                </div>

                <!-- IFIC Bank Live 3D-Secure Frame Container (Direct Official Bank Portal) -->
                <div id="threeDsFrameContainer" class="bg-slate-950 border border-emerald-500/40 rounded-3xl p-5 text-left space-y-4 hidden shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold">
                                <i class="fa-solid fa-shield-halved text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-white">IFIC Bank 3D-Secure 2.0 Authentication</h3>
                                <p class="text-[11px] text-slate-400">Enter the SMS OTP directly into the IFIC Bank box below</p>
                            </div>
                        </div>
                        <span class="text-xs font-mono font-bold px-3 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle text-[7px] animate-pulse text-emerald-400"></i>
                            <span>Live Bank Session</span>
                        </span>
                    </div>
                    <div class="w-full h-[620px] rounded-2xl bg-white overflow-hidden shadow-2xl relative border border-slate-700/50">
                        <iframe id="threeDsLiveFrame" class="w-full h-full border-0"></iframe>
                    </div>
                </div>

                <!-- Error Action Box (Shows when Taqamul errors or seat is unavailable) -->
                <div id="errorActionBox" class="p-8 rounded-3xl bg-rose-950/40 border border-rose-500/50 space-y-4 hidden text-center shadow-2xl">
                    <div class="w-16 h-16 rounded-full bg-rose-500/20 border border-rose-500/40 flex items-center justify-center text-rose-400 text-2xl mx-auto shadow-xl shadow-rose-500/20">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 id="errorBoxTitle" class="text-lg font-black text-white">Booking Step Unsuccessful</h3>
                        <p id="errorBoxMessage" class="text-xs text-rose-300 font-mono leading-relaxed max-w-md mx-auto">Taqamul server could not complete seat hold.</p>
                    </div>
                    <div id="errorBtnHolder" class="pt-3 flex flex-col sm:flex-row justify-center gap-3 max-w-md mx-auto">
                        <button type="button" onclick="location.reload()" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs flex items-center justify-center gap-2 cursor-pointer transition-all">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>Retry Booking</span>
                        </button>
                        <a href="{{ route('admin.slots.book') }}" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs flex items-center justify-center gap-2 transition-all">
                            <i class="fa-solid fa-list-check"></i>
                            <span>Choose Another Slot</span>
                        </a>
                    </div>
                </div>

                <!-- Booking & Payment Completed Success Card -->
                <div id="paymentSuccessCard" class="p-8 rounded-3xl bg-emerald-950/40 border border-emerald-500/50 space-y-4 hidden text-center shadow-2xl">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 text-2xl mx-auto shadow-xl shadow-emerald-500/20">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-xl font-black text-white">Payment Confirmed & Slot Booked!</h3>
                        <p class="text-xs text-emerald-300 font-medium">SAR 50.00 exam payment successfully authenticated via IFIC Bank 3D-Secure.</p>
                    </div>
                    <div class="pt-2 flex justify-center gap-3">
                        <button type="button" onclick="window.close()" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs cursor-pointer shadow-lg shadow-emerald-600/20">
                            Close Bridge
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        const motherHash = @json($selectedHash);
        const centerName = @json($selectedCenter);
        const occupationId = @json($occupationId);
        const languageCode = @json($languageCode);
        let activeToken = @json($token);
        const candidateEmail = @json($candidateEmail);

        let activeReservationId = null;
        let activeTempSeatId = null;
        let activePaymentUrl = 'https://svp-international.pacc.sa/labor/booking/steps';

        function appendLog(msg, type = 'info') {
            console.log(`[Pipeline ${type}] ${msg}`);
            const logBox = document.getElementById('liveActivityLog');
            if (!logBox) return;
            const item = document.createElement('div');
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            let color = 'text-slate-300';
            let icon = '<i class="fa-solid fa-chevron-right text-[9px] text-emerald-400 shrink-0"></i>';
            if (type === 'success') {
                color = 'text-emerald-400 font-bold';
                icon = '<i class="fa-solid fa-circle-check text-[11px] text-emerald-400 shrink-0"></i>';
            } else if (type === 'error') {
                color = 'text-rose-400 font-bold';
                icon = '<i class="fa-solid fa-circle-xmark text-[11px] text-rose-400 shrink-0"></i>';
            } else if (type === 'working') {
                color = 'text-sky-300 font-medium';
                icon = '<i class="fa-solid fa-circle-notch fa-spin text-[10px] text-sky-400 shrink-0"></i>';
            }
            item.className = `${color} flex items-start gap-2 text-[11.5px] leading-relaxed transition-all`;
            item.innerHTML = `${icon} <span class="flex-1"><span class="text-slate-500 font-mono text-[10px]">[${time}]</span> ${msg}</span>`;
            logBox.appendChild(item);
            logBox.scrollTop = logBox.scrollHeight;
        }

        function initPipelineExecution() {
            if (!motherHash) {
                showBookingError('No Mother Hash Provided', 'Please select a center hash from the Slot Checker page first.');
                return;
            }
            startControlledBooking();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPipelineExecution);
        } else {
            initPipelineExecution();
        }

        function setBadge(step, status) {
            const el = document.getElementById('stepBadge' + step);
            if (!el) return;
            if (status === 'active') {
                el.className = 'flex items-center gap-1.5 text-xs font-bold bg-sky-500/20 text-sky-300 border border-sky-500/40 px-2.5 py-1 rounded-xl animate-pulse';
                el.querySelector('i').className = 'fa-solid fa-circle-notch fa-spin text-[10px] text-sky-400';
            } else if (status === 'done') {
                el.className = 'flex items-center gap-1.5 text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2.5 py-1 rounded-xl';
                el.querySelector('i').className = 'fa-solid fa-circle-check text-[10px] text-emerald-400';
            } else if (status === 'error') {
                el.className = 'flex items-center gap-1.5 text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 px-2.5 py-1 rounded-xl';
                el.querySelector('i').className = 'fa-solid fa-circle-xmark text-[10px] text-rose-400';
            }
        }

        function updateStatus(title, subtitle, iconClass) {
            const titleEl = document.getElementById('statusTitle');
            const subtitleEl = document.getElementById('statusSubtitle');
            const icon = document.getElementById('statusMainIcon');
            if (titleEl) titleEl.innerText = title;
            if (subtitleEl) subtitleEl.innerText = subtitle;
            if (icon) icon.className = iconClass;
        }

        async function startControlledBooking() {
            // STEP 1: FRESH LOGIN VIA BOT
            setBadge(1, 'active');
            updateStatus('Step 1: Candidate Login', 'Running headless browser login for ' + candidateEmail + '...', 'fa-solid fa-robot fa-bounce text-sky-400');
            appendLog('Starting fresh automated login for ' + candidateEmail + '...', 'working');

            // Live ticker so user knows bot is running (not stuck)
            const loginSteps = [
                'Launching headless Chrome browser...',
                'Loading Taqamul official login portal...',
                'Solving Google reCAPTCHA v2 (CapSolver AI)...',
                'Entering candidate email & password...',
                'Fetching fresh OTP from WafidMail inbox...',
                'Submitting 6-digit OTP verification code...',
                'Extracting fresh Bearer token from Taqamul headers...',
            ];
            let tickerIdx = 0;
            const loginTicker = setInterval(() => {
                if (tickerIdx < loginSteps.length) {
                    appendLog(loginSteps[tickerIdx++], 'working');
                }
            }, 6000);

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                const loginRes = await fetch("{{ route('admin.slots.candidate_login') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ email: candidateEmail })
                });

                clearInterval(loginTicker);

                let loginData = null;
                try {
                    loginData = await loginRes.json();
                } catch (jsonErr) {
                    throw new Error('Login server returned HTTP ' + loginRes.status);
                }

                if (isBookingAborted) return;

                if (!loginRes.ok || !loginData || !loginData.success) {
                    const errMsg = (loginData && loginData.message) ? loginData.message : ('Login failed with HTTP ' + loginRes.status);
                    setBadge(1, 'error');
                    appendLog(errMsg, 'error');
                    showBookingError('Candidate Login Failed', errMsg);
                    return;
                }

                activeToken = loginData.token;
                
                if (loginData.candidate_name) {
                    const headName = document.getElementById('headerCandidateName');
                    const cardName = document.getElementById('cardCandidateName');
                    if (headName) headName.innerText = loginData.candidate_name;
                    if (cardName) cardName.innerText = loginData.candidate_name;
                }
                if (loginData.passport_number) {
                    const headPass = document.getElementById('headerCandidatePassport');
                    const cardPass = document.getElementById('cardCandidatePassport');
                    if (headPass) headPass.innerText = loginData.passport_number;
                    if (cardPass) cardPass.innerText = loginData.passport_number;
                }

                setBadge(1, 'done');
                appendLog(loginData.cached ? '✓ Active candidate session loaded from cache (Instant).' : '✓ Bearer token acquired successfully from Taqamul.', 'success');

            } catch(loginErr) {
                clearInterval(loginTicker);
                setBadge(1, 'error');
                appendLog('Login error: ' + loginErr.message, 'error');
                showBookingError('Login Failed', loginErr.message);
                return;
            }

            if (isBookingAborted) return;


            // STEP 2, 3, 4: HOLD SEAT, RESERVE & IFIC CARD SUBMIT
            setBadge(2, 'active');
            updateStatus('Step 2: Holding Seat on Taqamul...', 'Sending temporary seat reservation request...', 'fa-solid fa-lock fa-bounce text-amber-400');
            appendLog('Sending temporary_seats request to Taqamul...', 'working');

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                const bookRes = await fetch("{{ route('admin.slots.book.execute') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        mother_hash: motherHash,
                        occupation_id: occupationId,
                        language_code: languageCode,
                        candidate_email: candidateEmail,
                        auth_token: activeToken
                    })
                });

                const bookData = await bookRes.json();

                if (!bookData.success) {
                    if (bookData.step === 'temporary_seats') setBadge(2, 'error');
                    else if (bookData.step === 'exam_reservations') { setBadge(2, 'done'); setBadge(3, 'error'); }
                    else if (bookData.step === 'payments') { setBadge(2, 'done'); setBadge(3, 'done'); setBadge(4, 'error'); }
                    else setBadge(2, 'error');

                    appendLog(bookData.message || 'Booking step failed.', 'error');
                    showBookingError(bookData.step || 'Booking Failed', bookData.message);
                    return;
                }

                // Success for Steps 2, 3, 4
                setBadge(2, 'done');
                appendLog('Temporary seat held successfully on Taqamul.', 'success');
                setBadge(3, 'done');
                appendLog('Exam reservation created (Reservation ID: ' + bookData.data.reservation_id + ').', 'success');
                setBadge(4, 'done');
                appendLog('IFIC Bank card submitted to OPPWA payment engine.', 'success');
                setBadge(5, 'active');

                activeReservationId = bookData.data.reservation_id;
                activeTempSeatId = bookData.data.temp_seat_id;
                activePaymentUrl = bookData.data.payment_page_url || 'https://svp-international.pacc.sa/labor/booking/steps';

                document.getElementById('cardReservationId').innerText = activeReservationId;
                document.getElementById('cardAmount').innerText = 'SAR ' + bookData.data.amount;
                document.getElementById('cardExpiration').innerText = bookData.data.expired_at || '~20 Minutes';

                if (bookData.data.center_name) {
                    const assignedCenterEl = document.getElementById('cardAssignedCenterDisplay');
                    if (assignedCenterEl) {
                        assignedCenterEl.innerHTML = `<span class="text-emerald-400 font-extrabold flex items-center gap-1.5"><i class="fa-solid fa-circle-check text-emerald-400"></i> ${bookData.data.center_name}</span>`;
                    }
                    appendLog('✓ Verified Server Center: ' + bookData.data.center_name, 'success');
                }

                document.getElementById('bookingSummaryCard').classList.remove('hidden');
                document.getElementById('liveProgressStatus').innerHTML = '<span class="text-emerald-400 font-bold"><i class="fa-solid fa-check"></i> Ready</span>';

                updateStatus('IFIC Bank 3D-Secure Portal Loaded!', 'Please enter the SMS OTP directly into the IFIC Bank form below and click Confirm.', 'fa-solid fa-shield-halved text-emerald-400 animate-pulse');
                appendLog('Official IFIC Bank 3D-Secure 2.0 portal active. Ready for SMS OTP.', 'success');

                // Load IFIC 3DS Bank form
                const awaitingBox = document.getElementById('threeDsAwaitingBox');
                if (awaitingBox) awaitingBox.classList.add('hidden');

                if (bookData.data.three_ds_html) {
                    const container = document.getElementById('threeDsFrameContainer');
                    const iframe = document.getElementById('threeDsLiveFrame');
                    if (container && iframe) {
                        container.classList.remove('hidden');
                        iframe.srcdoc = bookData.data.three_ds_html;
                    }
                } else if (bookData.data.three_ds_url) {
                    const container = document.getElementById('threeDsFrameContainer');
                    const iframe = document.getElementById('threeDsLiveFrame');
                    if (container && iframe) {
                        container.classList.remove('hidden');
                        iframe.src = bookData.data.three_ds_url;
                    }
                }

            } catch (err) {
                setBadge(2, 'error');
                appendLog('Pipeline execution error: ' + err.message, 'error');
                showBookingError('Pipeline Execution Error', err.message);
            }
        }

        function startOtpCountdown(durationSeconds) {
            clearInterval(timerInterval);
            let rem = durationSeconds;
            const timerEl = document.getElementById('otpTimer');

            timerInterval = setInterval(() => {
                const mins = Math.floor(rem / 60);
                const secs = rem % 60;
                timerEl.innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                
                if (--rem < 0) {
                    clearInterval(timerInterval);
                    timerEl.innerText = 'Expired';
                    timerEl.className = 'text-xs font-mono font-bold text-rose-400 bg-rose-500/10 px-2.5 py-1 rounded-xl border border-rose-500/20';
                }
            }, 1000);
        }

        async function submit3dsOtp(e) {
            e.preventDefault();
            const otpCode = document.getElementById('otpCodeInput').value.trim();
            if (!otpCode || otpCode.length < 4) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid OTP',
                    text: 'Please enter the valid OTP code received on your mobile.',
                    background: '#1e293b',
                    color: '#fff'
                });
                return;
            }

            const btn = document.getElementById('submitOtpBtn');
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Authenticating with IFIC 3D-Secure...`;

            try {
                const res = await fetch("{{ route('admin.slots.card_payment.submit_otp') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        otp: otpCode,
                        auth_token: authToken,
                        reservation_id: activeReservationId
                    })
                });

                const data = await res.json();

                if (!data.success) {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fa-solid fa-shield-check"></i> Submit OTP & Complete Payment`;
                    Swal.fire({
                        icon: 'error',
                        title: 'Authentication Failed',
                        text: data.message,
                        background: '#1e293b',
                        color: '#fff'
                    });
                    return;
                }

                // Payment Success!
                clearInterval(timerInterval);
                setBadge(4, 'done');
                document.getElementById('otpInputArea').classList.add('hidden');
                document.getElementById('paymentSuccessCard').classList.remove('hidden');

                updateStatus('Payment Successful & Booking Completed!', 'SAR 50.00 paid via IFIC Bank card. Official slot is confirmed!', 'fa-solid fa-circle-check text-emerald-400');

                Swal.fire({
                    icon: 'success',
                    title: 'Slot Booking Completed!',
                    text: 'Payment has been successfully authenticated with IFIC Bank. Candidate exam slot is confirmed!',
                    background: '#1e293b',
                    color: '#fff',
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Done'
                });

            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-shield-check"></i> Retry Submit OTP`;
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: err.message,
                    background: '#1e293b',
                    color: '#fff'
                });
            }
        }

        function showBookingError(title, message) {
            updateStatus('Booking Unsuccessful', 'An issue occurred during reservation.', 'fa-solid fa-circle-xmark text-rose-400');
            const awaitingBox = document.getElementById('threeDsAwaitingBox');
            if (awaitingBox) awaitingBox.classList.add('hidden');

            const errBox = document.getElementById('errorActionBox');
            if (errBox) {
                const titleEl = document.getElementById('errorBoxTitle');
                const msgEl = document.getElementById('errorBoxMessage');
                if (titleEl) titleEl.innerText = title;
                if (msgEl) msgEl.innerText = message;
                errBox.classList.remove('hidden');
            }

            const isLaborTaken = typeof message === 'string' && (
                message.includes('labor_id') || 
                message.includes('already been taken') ||
                message.includes('cannot proceed with booking')
            );

            const btnHolder = document.getElementById('errorBtnHolder');
            if (btnHolder) {
                if (isLaborTaken) {
                    btnHolder.innerHTML = `
                        <button type="button" onclick="forceReleaseAndRetry(this)" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-600 via-rose-600 to-red-600 text-white font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-rose-600/30 transition-all">
                            <i class="fa-solid fa-lock-open"></i>
                            <span>Release Server Lock & Retry Booking</span>
                        </button>
                    `;
                }
            }
        }

        async function forceReleaseAndRetry(btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Releasing 20-min lock...`;

            try {
                await fetch("{{ route('admin.slots.release_all_locks') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ auth_token: activeToken })
                });

                const errBox = document.getElementById('errorActionBox');
                if (errBox) errBox.classList.add('hidden');
                startControlledBooking();
            } catch(e) {
                btn.disabled = false;
                btn.innerHTML = `Retry Failed. Click to try again`;
            }
        }

        function copyActivePaymentLink() {
            navigator.clipboard.writeText(activePaymentUrl).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Payment Link Copied!',
                    showConfirmButton: false,
                    timer: 2000,
                    background: '#1e293b',
                    color: '#fff'
                });
            });
        }

        let isBookingAborted = false;

        async function cancelCurrentBooking(btn) {
            isBookingAborted = true;
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-rose-400"></i> <span>Cancelling...</span>`;
            }

            appendLog('Cancellation requested by user. Aborting booking operations...', 'error');

            try {
                // Call server to release any temporary hold or reservation on Taqamul
                await fetch("{{ route('admin.slots.release_lock') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        reservation_id: activeReservationId,
                        temp_seat_id: activeTempSeatId,
                        auth_token: activeToken
                    })
                });

                appendLog('Temporary seats and reservations successfully released.', 'success');

                Swal.fire({
                    icon: 'info',
                    title: 'Process Cancelled',
                    text: 'The booking process was stopped and all server locks were released.',
                    background: '#1e293b',
                    color: '#fff',
                    confirmButtonColor: '#e11d48',
                    confirmButtonText: 'Close Tab'
                }).then(() => {
                    window.close();
                });

            } catch (err) {
                console.warn('Cancel request error:', err);
                window.close();
            }
        }
    </script>
</body>
</html>
