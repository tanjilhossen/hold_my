@extends('layouts.app')

@section('title', 'Settings Dashboard - Taqamul')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl md:text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-sliders text-emerald-400"></i>
                <span>System Automation Settings</span>
            </h2>
            <p class="text-sm text-slate-400 mt-1">Configure default email provisioning, OTP verification methods, private server API, and CapSolver.</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if($wafidStatus)
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                <span>Private Mail Server Connected</span>
            </span>
            @else
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                <span>Mail Server Keys Missing</span>
            </span>
            @endif

            <!-- Privacy Mode: Hide Hashes Toggle -->
            <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-slate-900/90 border border-slate-700/80 shadow-md">
                <div class="flex items-center gap-1.5">
                    <i id="privacyModeIcon" class="fa-solid {{ ($settings['hide_hashes'] ?? '0') === '1' ? 'fa-eye-slash text-amber-400' : 'fa-eye text-slate-400' }} text-xs transition-colors"></i>
                    <span class="text-xs font-bold text-slate-200">Hide Hashes</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="hideHashesToggle" onchange="togglePrivacyMode(this.checked)" {{ ($settings['hide_hashes'] ?? '0') === '1' ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                </label>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-3 text-emerald-400 text-sm shadow-lg">
        <i class="fa-solid fa-circle-check text-lg"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6">
        @csrf

        <!-- TAB NAVIGATION BAR -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-2 shadow-xl flex flex-wrap gap-2">
            <!-- Tab 1 Button -->
            <button type="button" onclick="switchTab('email-otp')" id="tabBtn-email-otp"
                class="tab-btn flex-1 min-w-[200px] flex items-center justify-center gap-3 px-5 py-3.5 rounded-xl font-bold text-sm transition-all bg-emerald-600 text-white shadow-lg shadow-emerald-600/30">
                <i class="fa-solid fa-envelope-circle-check text-base"></i>
                <div class="text-left">
                    <p class="leading-none">Email & OTP Defaults</p>
                    <p class="text-[11px] font-normal opacity-80 mt-1">Default provision method</p>
                </div>
            </button>

            <!-- Tab 2 Button -->
            <button type="button" onclick="switchTab('mail-server')" id="tabBtn-mail-server"
                class="tab-btn flex-1 min-w-[200px] flex items-center justify-center gap-3 px-5 py-3.5 rounded-xl font-bold text-sm transition-all text-slate-400 hover:text-slate-200 hover:bg-slate-800/60">
                <i class="fa-solid fa-server text-base text-blue-400"></i>
                <div class="text-left">
                    <p class="leading-none">Private Mail Server API</p>
                    <p class="text-[11px] font-normal opacity-80 mt-1">@wafidmaster.com cluster</p>
                </div>
            </button>

            <!-- Tab 3 Button -->
            <button type="button" onclick="switchTab('security')" id="tabBtn-security"
                class="tab-btn flex-1 min-w-[180px] flex items-center justify-center gap-3 px-5 py-3.5 rounded-xl font-bold text-sm transition-all text-slate-400 hover:text-slate-200 hover:bg-slate-800/60">
                <i class="fa-solid fa-shield-halved text-base text-amber-400"></i>
                <div class="text-left">
                    <p class="leading-none">CapSolver & Security</p>
                    <p class="text-[11px] font-normal opacity-80 mt-1">AI Captcha & Passwords</p>
                </div>
            </button>

            <!-- Tab 4 Button: Slot Checker Pool -->
            <button type="button" onclick="switchTab('checker-pool')" id="tabBtn-checker-pool"
                class="tab-btn flex-1 min-w-[200px] flex items-center justify-center gap-3 px-5 py-3.5 rounded-xl font-bold text-sm transition-all text-slate-400 hover:text-slate-200 hover:bg-slate-800/60">
                <i class="fa-solid fa-rotate text-base text-sky-400"></i>
                <div class="text-left">
                    <p class="leading-none">Slot Checker Pool</p>
                    <p class="text-[11px] font-normal opacity-80 mt-1">Round-Robin Token Pool</p>
                </div>
            </button>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1 CONTENT: EMAIL & OTP DEFAULTS -->
        <!-- ========================================== -->
        <div id="tabContent-email-otp" class="tab-content bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
            <div class="border-b border-slate-800 pb-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-envelope-circle-check text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Default Email & OTP Provisioning</h3>
                    <p class="text-xs text-slate-400">These settings will be applied automatically when creating or registering candidates</p>
                </div>
            </div>

            <!-- Email Provision Method -->
            <div class="space-y-3">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                    Default Email Provision Method <span class="text-rose-400">*</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Option 1: Private Mail (@wafidmaster.com) -->
                    <label class="relative flex flex-col p-4 rounded-xl bg-slate-800/80 border cursor-pointer transition-all {{ $settings['default_email_provision_method'] === 'auto_wafidmail' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-950/20' : 'border-slate-700/80 hover:border-slate-600' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-white text-sm flex items-center gap-2">
                                <i class="fa-solid fa-bolt text-amber-400"></i> Private Server
                            </span>
                            <input type="radio" name="default_email_provision_method" value="auto_wafidmail" {{ $settings['default_email_provision_method'] === 'auto_wafidmail' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                        </div>
                        <p class="text-xs font-semibold text-emerald-400 font-mono">@wafidmaster.com</p>
                        <p class="text-[11px] text-slate-400 mt-2">Zero CAPTCHA, fastest instant REST API OTP extraction in 0.5s.</p>
                        <span class="mt-3 inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 self-start">RECOMMENDED</span>
                    </label>

                    <!-- Option 2: YOPmail (@yopmail.com) -->
                    <label class="relative flex flex-col p-4 rounded-xl bg-slate-800/80 border cursor-pointer transition-all {{ $settings['default_email_provision_method'] === 'auto_yopmail' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-950/20' : 'border-slate-700/80 hover:border-slate-600' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-white text-sm flex items-center gap-2">
                                <i class="fa-solid fa-envelope text-blue-400"></i> Auto YOPmail
                            </span>
                            <input type="radio" name="default_email_provision_method" value="auto_yopmail" {{ $settings['default_email_provision_method'] === 'auto_yopmail' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                        </div>
                        <p class="text-xs font-semibold text-blue-400 font-mono">@yopmail.com</p>
                        <p class="text-[11px] text-slate-400 mt-2">Public disposable mailbox. Uses browser tab auto-reader with captcha bypass.</p>
                    </label>

                    <!-- Option 3: Custom / Own Email -->
                    <label class="relative flex flex-col p-4 rounded-xl bg-slate-800/80 border cursor-pointer transition-all {{ $settings['default_email_provision_method'] === 'custom_mail' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-950/20' : 'border-slate-700/80 hover:border-slate-600' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-white text-sm flex items-center gap-2">
                                <i class="fa-solid fa-user-pen text-slate-300"></i> Custom / Manual
                            </span>
                            <input type="radio" name="default_email_provision_method" value="custom_mail" {{ $settings['default_email_provision_method'] === 'custom_mail' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                        </div>
                        <p class="text-xs font-semibold text-slate-300 font-mono">custom_email</p>
                        <p class="text-[11px] text-slate-400 mt-2">Allows entering candidate's own email on the registration form.</p>
                    </label>
                </div>
            </div>

            <!-- OTP Delivery Method -->
            <div class="space-y-3 pt-2">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                    Default OTP Delivery Method <span class="text-rose-400">*</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex items-center justify-between p-4 rounded-xl bg-slate-800/80 border cursor-pointer transition-all {{ $settings['default_otp_delivery_method'] === 'email' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-950/20' : 'border-slate-700/80 hover:border-slate-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                                <i class="fa-solid fa-at"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white text-sm">Email Verification</p>
                                <p class="text-xs text-slate-400">Taqamul sends 6-digit OTP to candidate's email address</p>
                            </div>
                        </div>
                        <input type="radio" name="default_otp_delivery_method" value="email" {{ $settings['default_otp_delivery_method'] === 'email' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                    </label>

                    <label class="flex items-center justify-between p-4 rounded-xl bg-slate-800/80 border cursor-pointer transition-all {{ $settings['default_otp_delivery_method'] === 'phone' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-950/20' : 'border-slate-700/80 hover:border-slate-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-teal-500/20 flex items-center justify-center text-teal-400">
                                <i class="fa-solid fa-mobile-screen-button"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white text-sm">SMS / Phone Verification</p>
                                <p class="text-xs text-slate-400">Taqamul sends OTP via SMS to candidate's phone number</p>
                            </div>
                        </div>
                        <input type="radio" name="default_otp_delivery_method" value="phone" {{ $settings['default_otp_delivery_method'] === 'phone' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                    </label>
                </div>
            </div>

            <!-- Browser Mode (Headless vs Headed) -->
            <div class="space-y-3 pt-2 border-t border-slate-800/60">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                    Browser Automation Display Mode (Headless / On-Screen) <span class="text-rose-400">*</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Option 1: Headless / Background Mode -->
                    <label class="flex items-center justify-between p-4 rounded-xl bg-slate-800/80 border cursor-pointer transition-all {{ $settings['browser_mode'] === 'headless' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-950/20' : 'border-slate-700/80 hover:border-slate-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                <i class="fa-solid fa-ghost"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white text-sm">Background Mode (Headless)</p>
                                <p class="text-xs text-slate-400">Runs silently in background with no visible browser window (Default)</p>
                            </div>
                        </div>
                        <input type="radio" name="browser_mode" value="headless" {{ $settings['browser_mode'] === 'headless' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                    </label>

                    <!-- Option 2: Headed / On-Screen Visual Mode -->
                    <label class="flex items-center justify-between p-4 rounded-xl bg-slate-800/80 border cursor-pointer transition-all {{ $settings['browser_mode'] === 'headed' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-950/20' : 'border-slate-700/80 hover:border-slate-600' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center text-purple-400">
                                <i class="fa-solid fa-desktop"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white text-sm">On-Screen Visual Mode (Headed)</p>
                                <p class="text-xs text-slate-400">Opens real Chrome browser window on screen to show live automation</p>
                            </div>
                        </div>
                        <input type="radio" name="browser_mode" value="headed" {{ $settings['browser_mode'] === 'headed' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500">
                    </label>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2 CONTENT: PRIVATE MAIL SERVER -->
        <!-- ========================================== -->
        <div id="tabContent-mail-server" class="tab-content hidden bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
            <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                        <i class="fa-solid fa-server text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Private Mail Server API (@wafidmaster.com)</h3>
                        <p class="text-xs text-slate-400">HMAC-SHA256 authenticated disposable mailbox cluster</p>
                    </div>
                </div>

                <div>
                    @if($wafidStatus)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        API Active & Connected
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                        <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                        Credentials Missing
                    </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Mail Server Base URL
                    </label>
                    <input type="text" name="wafid_mail_base_url" value="{{ $settings['wafid_mail_base_url'] }}" placeholder="https://mail.wafidmaster.com" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        API Key ID (X-API-Key)
                    </label>
                    <input type="text" name="wafid_mail_key_id" value="{{ $settings['wafid_mail_key_id'] }}" placeholder="ak_live_xxxxxxxx" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        API Secret Key
                    </label>
                    <input type="password" name="wafid_mail_secret_key" value="{{ $settings['wafid_mail_secret_key'] }}" placeholder="sk_live_xxxxxxxx" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/60 text-xs text-slate-400 space-y-2">
                <p class="font-bold text-slate-200 flex items-center gap-1.5">
                    <i class="fa-solid fa-info-circle text-blue-400"></i> Server Features:
                </p>
                <ul class="list-disc list-inside space-y-1 text-[11px] text-slate-400">
                    <li>Supports unlimited instantaneous disposable email creation (`name@wafidmaster.com`).</li>
                    <li>Automated HMAC-SHA256 signature verification per request for top security.</li>
                    <li>Zero-delay inbound email parser directly captures Taqamul verification OTPs.</li>
                </ul>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3 CONTENT: CAPSOLVER & SECURITY -->
        <!-- ========================================== -->
        <div id="tabContent-security" class="tab-content hidden bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
            <div class="border-b border-slate-800 pb-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i class="fa-solid fa-shield-halved text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">CapSolver & Account Security</h3>
                    <p class="text-xs text-slate-400">AI reCAPTCHA v2 Enterprise solver configuration and account security defaults</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        CapSolver API Key
                    </label>
                    <input type="text" name="capsolver_api_key" value="{{ $settings['capsolver_api_key'] }}" placeholder="CAP-xxxxxxxx" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                    <p class="text-[11px] text-slate-400 mt-1.5">Pre-solves reCAPTCHA for Steps 1, 2, 3, and 4 concurrently in 0s delay.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Default Password Template
                    </label>
                    <input type="text" name="default_password" value="{{ $settings['default_password'] }}" placeholder="Taqamul@2026!" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                    <p class="text-[11px] text-slate-400 mt-1.5">Used as candidate's default password when creating accounts.</p>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4 CONTENT: SLOT CHECKER POOL (ROUND-ROBIN) -->
        <!-- ========================================== -->
        <div id="tabContent-checker-pool" class="tab-content hidden bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
            <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400">
                        <i class="fa-solid fa-rotate text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Candidate Pool for Slot Checking (Round-Robin)</h3>
                        <p class="text-xs text-slate-400">Configure dedicated accounts for slot checking. When a token expires, the system automatically rotates to the next candidate.</p>
                    </div>
                </div>
            </div>

            <!-- Manual Backup Token Input -->
            <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 space-y-2">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                    <i class="fa-solid fa-key text-sky-400 mr-1"></i> Static Fallback Bearer Token (Optional Backup)
                </label>
                <input type="text" name="slot_checker_manual_token" value="{{ $settings['slot_checker_manual_token'] }}" placeholder="Bearer eyJhbGciOiJIUzI1NiJ9... (Optional fallback token)" class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-sky-500 text-white text-xs outline-none font-mono">
                <p class="text-[11px] text-slate-500">If candidate tokens fail, this manual token will be used directly as a fallback.</p>
            </div>

            <!-- Bulk Candidate Pool Auto-Generator Tool -->
            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-950/40 border border-indigo-500/30 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                            <i class="fa-solid fa-wand-magic-sparkles text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <span>⚡ Bulk Candidate Pool Creator</span>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold">1-by-1 Live Stream</span>
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">Auto-generates Taqamul accounts with instant private mail OTP & adds directly to pool</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span id="bulkStatusBadge" class="hidden px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Generating...
                        </span>
                    </div>
                </div>

                <!-- Input & Settings Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">
                            Number of Accounts to Generate:
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number" id="bulkAccountCount" value="50" min="1" max="500" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-amber-300 font-bold text-sm focus:border-amber-500 outline-none">
                            <div class="flex gap-1">
                                <button type="button" onclick="setBulkCount(20)" class="px-2 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">20</button>
                                <button type="button" onclick="setBulkCount(50)" class="px-2 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-400 text-xs font-bold">50</button>
                                <button type="button" onclick="setBulkCount(100)" class="px-2 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">100</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">
                            Auto Attached Passport & Photo:
                        </label>
                        <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-[11px] text-slate-400 font-mono flex items-center gap-2 truncate">
                            <i class="fa-solid fa-folder-check text-emerald-400"></i>
                            <span class="truncate">Desktop\New folder (6)\PASSPORT & PHOTO</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" id="startBulkBtn" onclick="startBulkPoolCreation()" class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-600 via-orange-600 to-amber-500 hover:from-amber-500 hover:to-orange-500 text-white font-extrabold text-xs shadow-lg shadow-amber-600/30 flex items-center justify-center gap-2 transition-all cursor-pointer">
                            <i class="fa-solid fa-play"></i>
                            <span>Start Bulk Generator</span>
                        </button>
                        <button type="button" id="stopBulkBtn" onclick="stopBulkPoolCreation()" class="hidden py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <i class="fa-solid fa-stop"></i>
                            <span>Stop</span>
                        </button>
                    </div>
                </div>

                <!-- Live Progress Panel -->
                <div id="bulkProgressPanel" class="hidden space-y-3 pt-3 border-t border-slate-800">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-300 flex items-center gap-2">
                            <i class="fa-solid fa-bolt text-amber-400"></i>
                            <span id="bulkProgressText">Creating 0 / 50 accounts (0%)...</span>
                        </span>
                        <span id="bulkSuccessBadge" class="font-mono text-emerald-400 font-bold">0 Succeeded</span>
                    </div>

                    <div class="w-full bg-slate-950 rounded-full h-2.5 overflow-hidden border border-slate-800">
                        <div id="bulkProgressBar" class="bg-gradient-to-r from-amber-500 via-orange-500 to-emerald-500 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>

                    <!-- Terminal Logs Console -->
                    <div class="bg-slate-950 rounded-xl border border-slate-800 p-3 max-h-36 overflow-y-auto custom-scrollbar font-mono text-[11px] text-slate-400 space-y-1" id="bulkLogsContainer">
                        <div class="text-slate-600">[Ready] Bulk generator ready. Click Start to begin.</div>
                    </div>
                </div>
            </div>

            <!-- Pool Accounts Table -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-users text-emerald-400"></i> Active Candidate Accounts in Pool (<span id="totalPoolCountBadge">{{ count($poolAccounts) }}</span>)
                    </h4>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-800">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
                            <tr>
                                <th class="p-3">#</th>
                                <th class="p-3">Candidate Name</th>
                                <th class="p-3">Email & Password</th>
                                <th class="p-3">Token Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="poolAccountsTableBody" class="divide-y divide-slate-800/60 bg-slate-900/60 font-medium">
                            @forelse($poolAccounts as $idx => $acc)
                            <tr class="hover:bg-slate-800/30 transition-colors" id="pool-row-{{ md5($acc['email']) }}">
                                <td class="p-3 font-mono text-slate-500 row-index">{{ $idx + 1 }}</td>
                                <td class="p-3 font-bold text-white">{{ $acc['name'] ?? 'Candidate' }}</td>
                                <td class="p-3 font-mono text-xs">
                                    <div class="text-slate-200">{{ $acc['email'] }}</div>
                                    <div class="text-[10px] text-slate-500">Pass: {{ $acc['password'] }}</div>
                                </td>
                                <td class="p-3">
                                    @if(!empty($acc['token']))
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Active Token
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700 text-[10px] font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Auto-Login Ready
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 text-right space-x-2">
                                    <button type="button" onclick="testAccountLogin('{{ $acc['email'] }}', this)" class="px-2.5 py-1 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 text-[11px] font-bold transition-all" title="Test Headless Login & Capture Token">
                                        <i class="fa-solid fa-bolt"></i> Login & Fetch
                                    </button>

                                    <button type="button" onclick="removePoolCandidate('{{ $acc['email'] }}')" class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[11px] font-bold transition-all" title="Remove from pool">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr id="emptyPoolRow">
                                <td colspan="5" class="p-4 text-center text-slate-500">No candidate accounts found in pool. Add candidates below.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Candidate to Pool Section -->
            <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-4">
                <h4 class="text-xs font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-sky-400"></i> Add Candidate to Slot Checker Pool
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[11px] text-slate-400 mb-1">Select from Registered Passengers:</label>
                        <select id="quickPassengerSelect" onchange="fillPassengerDetails(this)" class="w-full px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-medium focus:border-sky-500 outline-none">
                            <option value="">-- Select Candidate --</option>
                            @foreach($allPassengers as $p)
                                <option value="{{ $p->id }}" data-name="{{ $p->full_name }}" data-email="{{ $p->email }}" data-password="{{ $p->password }}">
                                    {{ $p->full_name }} ({{ $p->email }}) [Pass: {{ $p->password }}]
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="button" onclick="submitAddPoolCandidate()" class="w-full py-2 px-4 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <i class="fa-solid fa-plus"></i>
                            <span>Add to Pool</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button (Always accessible) -->
        <div class="flex items-center justify-end gap-4 pt-2">
            <button type="submit" class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-bold text-sm shadow-xl shadow-emerald-600/20 transition-all flex items-center gap-2.5 transform active:scale-95">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save All Settings</span>
            </button>
        </div>
    </form>
</div>
</div>

@push('scripts')
<script>
    function switchTab(tabId) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });

        // Deactivate all tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('bg-emerald-600', 'text-white', 'shadow-lg', 'shadow-emerald-600/30');
            btn.classList.add('text-slate-400', 'hover:text-slate-200', 'hover:bg-slate-800/60');
        });

        // Show active tab content
        const targetContent = document.getElementById('tabContent-' + tabId);
        if (targetContent) {
            targetContent.classList.remove('hidden');
        }

        // Activate active tab button
        const targetBtn = document.getElementById('tabBtn-' + tabId);
        if (targetBtn) {
            targetBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-lg', 'shadow-emerald-600/30');
            targetBtn.classList.remove('text-slate-400', 'hover:text-slate-200', 'hover:bg-slate-800/60');
        }
    }

    function submitAddPoolCandidate() {
        const select = document.getElementById('quickPassengerSelect');
        const selected = select.options[select.selectedIndex];
        if (!selected || !selected.value) {
            Swal.fire({ icon: 'warning', title: 'Select Candidate', text: 'Please select a candidate from the dropdown.' });
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = "{{ route('admin.settings.pool.add') }}";
        form.innerHTML = `
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
            <input type="hidden" name="passenger_id" value="${selected.value}">
            <input type="hidden" name="email" value="${selected.getAttribute('data-email')}">
            <input type="hidden" name="password" value="${selected.getAttribute('data-password')}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function removePoolCandidate(email) {
        if (!confirm('Are you sure you want to remove this account from the Slot Checker Pool?')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = "{{ route('admin.settings.pool.remove') }}";
        form.innerHTML = `
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
            <input type="hidden" name="email" value="${email}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    async function testAccountLogin(email, btn) {
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Logging in...';

        try {
            const res = await fetch("{{ route('admin.settings.pool.test_login') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ email: email })
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Token Acquired Successfully!',
                    text: data.message,
                    background: '#1e293b',
                    color: '#fff'
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed!',
                    text: data.message,
                    background: '#1e293b',
                    color: '#fff'
                });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network Error!', text: e.message, background: '#1e293b', color: '#fff' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }

    async function togglePrivacyMode(isChecked) {
        const icon = document.getElementById('privacyModeIcon');
        if (icon) {
            icon.className = `fa-solid ${isChecked ? 'fa-eye-slash text-amber-400' : 'fa-eye text-slate-400'} text-xs transition-colors`;
        }
        
        // Instant Live UI Toggle across all elements
        document.body.classList.toggle('privacy-hide-hashes', isChecked);
        window.HIDE_HASHES = isChecked;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const res = await fetch("{{ route('admin.settings.toggle_hide_hashes') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ hide_hashes: isChecked })
            });

            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: isChecked ? 'warning' : 'success',
                    title: data.message,
                    showConfirmButton: false,
                    timer: 2500,
                    background: '#1e293b',
                    color: '#fff'
                });
            }
        } catch (e) {
            console.error('Failed to update privacy mode:', e);
        }
    }

    // ==========================================
    // BULK CANDIDATE POOL GENERATOR JAVASCRIPT
    // ==========================================
    let bulkPollTimer = null;

    function setBulkCount(cnt) {
        const input = document.getElementById('bulkAccountCount');
        if (input) input.value = cnt;
    }

    async function startBulkPoolCreation() {
        const count = parseInt(document.getElementById('bulkAccountCount').value) || 50;

        const confirmRes = await Swal.fire({
            title: `Generate ${count} Pool Accounts?`,
            text: `This will launch autonomous background bot to register ${count} accounts on Taqamul with private mail OTP and add them directly to Candidate Pool.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Start Generator',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#334155',
            background: '#1e293b',
            color: '#fff'
        });

        if (!confirmRes.isConfirmed) return;

        const startBtn = document.getElementById('startBulkBtn');
        const stopBtn = document.getElementById('stopBulkBtn');
        const badge = document.getElementById('bulkStatusBadge');
        const panel = document.getElementById('bulkProgressPanel');

        if (startBtn) startBtn.classList.add('hidden');
        if (stopBtn) stopBtn.classList.remove('hidden');
        if (badge) badge.classList.remove('hidden');
        if (panel) panel.classList.remove('hidden');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch("{{ route('admin.settings.bulk_pool.start') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ count: count })
            });

            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: data.message,
                    showConfirmButton: false,
                    timer: 3000,
                    background: '#1e293b',
                    color: '#fff'
                });

                startPollingBulkStatus();
            } else {
                Swal.fire({ icon: 'error', title: 'Start Failed', text: data.message, background: '#1e293b', color: '#fff' });
                resetBulkButtons();
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#1e293b', color: '#fff' });
            resetBulkButtons();
        }
    }

    async function stopBulkPoolCreation() {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch("{{ route('admin.settings.bulk_pool.stop') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await res.json();
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: data.message, showConfirmButton: false, timer: 2500, background: '#1e293b', color: '#fff' });
            resetBulkButtons();
        } catch (e) {}
    }

    function resetBulkButtons() {
        const startBtn = document.getElementById('startBulkBtn');
        const stopBtn = document.getElementById('stopBulkBtn');
        const badge = document.getElementById('bulkStatusBadge');
        if (startBtn) startBtn.classList.remove('hidden');
        if (stopBtn) stopBtn.classList.add('hidden');
        if (badge) badge.classList.add('hidden');
    }

    function startPollingBulkStatus() {
        if (bulkPollTimer) clearInterval(bulkPollTimer);
        bulkPollTimer = setInterval(pollBulkStatus, 1500);
        pollBulkStatus();
    }

    async function pollBulkStatus() {
        try {
            const res = await fetch("{{ route('admin.settings.bulk_pool.status') }}");
            const data = await res.json();

            if (data.success && data.status) {
                const s = data.status;
                const panel = document.getElementById('bulkProgressPanel');
                if (panel) panel.classList.remove('hidden');

                const progText = document.getElementById('bulkProgressText');
                const progBar = document.getElementById('bulkProgressBar');
                const succBadge = document.getElementById('bulkSuccessBadge');
                const totalBadge = document.getElementById('totalPoolCountBadge');

                const target = s.target || 50;
                const current = s.current || 0;
                const succ = s.success_count || 0;
                const pct = target > 0 ? Math.min(100, Math.round((current / target) * 100)) : 0;

                if (progText) progText.innerText = `Generating ${current} / ${target} accounts (${pct}%)...`;
                if (progBar) progBar.style.width = `${pct}%`;
                if (succBadge) succBadge.innerText = `${succ} Succeeded`;
                if (totalBadge && data.total_pool_accounts !== undefined) totalBadge.innerText = data.total_pool_accounts;

                // Update Logs
                const logContainer = document.getElementById('bulkLogsContainer');
                if (logContainer && s.logs && s.logs.length > 0) {
                    logContainer.innerHTML = s.logs.map(l => `<div class="${l.includes('✅') ? 'text-emerald-400 font-bold' : (l.includes('❌') || l.includes('⚠️') ? 'text-rose-400' : 'text-slate-300')}">${l}</div>`).join('');
                }

                // Dynamically Render / Update Table Rows
                if (data.pool_accounts && Array.isArray(data.pool_accounts)) {
                    renderPoolTableRows(data.pool_accounts);
                }

                if (!s.running && current > 0) {
                    clearInterval(bulkPollTimer);
                    bulkPollTimer = null;
                    resetBulkButtons();
                }
            }
        } catch (e) {}
    }

    function renderPoolTableRows(accounts) {
        const tbody = document.getElementById('poolAccountsTableBody');
        if (!tbody) return;

        if (accounts.length === 0) {
            tbody.innerHTML = `
                <tr id="emptyPoolRow">
                    <td colspan="5" class="p-4 text-center text-slate-500">No candidate accounts found in pool. Add candidates below.</td>
                </tr>
            `;
            return;
        }

        let html = '';
        accounts.forEach((acc, idx) => {
            const hasToken = Boolean(acc.token);
            html += `
                <tr class="hover:bg-slate-800/30 transition-colors" id="pool-row-${idx}">
                    <td class="p-3 font-mono text-slate-500 row-index">${idx + 1}</td>
                    <td class="p-3 font-bold text-white">${acc.name || 'Candidate'}</td>
                    <td class="p-3 font-mono text-xs">
                        <div class="text-slate-200">${acc.email}</div>
                        <div class="text-[10px] text-slate-500">Pass: ${acc.password}</div>
                    </td>
                    <td class="p-3">
                        ${hasToken 
                            ? `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">
                                 <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Active Token
                               </span>`
                            : `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700 text-[10px] font-bold">
                                 <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Auto-Login Ready
                               </span>`
                        }
                    </td>
                    <td class="p-3 text-right space-x-2">
                        <button type="button" onclick="testAccountLogin('${acc.email}', this)" class="px-2.5 py-1 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 text-[11px] font-bold transition-all" title="Test Headless Login & Capture Token">
                            <i class="fa-solid fa-bolt"></i> Login & Fetch
                        </button>
                        <button type="button" onclick="removePoolCandidate('${acc.email}')" class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[11px] font-bold transition-all" title="Remove from pool">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // Check if generator is currently active on page load
    document.addEventListener('DOMContentLoaded', () => {
        pollBulkStatus();
    });
</script>
@endpush
@endsection
