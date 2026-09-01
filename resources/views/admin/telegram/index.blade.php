@extends('layouts.app')

@section('title', 'Telegram Automation & Instant Alerts - Taqamul')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl md:text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-brands fa-telegram text-sky-400 text-2xl"></i>
                <span>Telegram Instant Notifications</span>
            </h2>
            <p class="text-sm text-slate-400 mt-1">Receive automated instant alerts with candidate passport, login email & password upon registration.</p>
        </div>

        <div>
            @if($isConnected)
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Telegram Bot Configured</span>
            </span>
            @else
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <span>Not Configured Yet</span>
            </span>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-3 text-emerald-400 text-sm shadow-lg">
        <i class="fa-solid fa-circle-check text-lg"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Configuration Form (Left 2 Columns) -->
        <div class="lg:col-span-2 space-y-6">
            <form action="{{ route('admin.telegram.update') }}" method="POST" class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
                @csrf

                <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 text-xl">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Bot API Credentials</h3>
                            <p class="text-xs text-slate-400">Connect your Telegram Bot and Destination Chat/Group ID</p>
                        </div>
                    </div>
                </div>

                <!-- Bot Token -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Telegram Bot API Token <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <input type="text" name="telegram_bot_token" id="botTokenInput" value="{{ $settings['telegram_bot_token'] }}" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-white text-sm outline-none font-mono">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1.5">Created via <b>@BotFather</b> on Telegram</p>
                </div>

                <!-- Chat ID -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Target Chat ID or Group ID <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" name="telegram_chat_id" id="chatIdInput" value="{{ $settings['telegram_chat_id'] }}" placeholder="987654321 or -1001234567890" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-white text-sm outline-none font-mono">
                    <p class="text-[11px] text-slate-400 mt-1.5">Your User ID from <b>@userinfobot</b>, or Group/Channel ID (starting with -100)</p>
                </div>

                <!-- Notification Options -->
                <div class="pt-2 border-t border-slate-800 space-y-4">
                    <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Alert Preferences</h4>

                    <div class="space-y-3">
                        <label class="flex items-center justify-between p-3.5 rounded-xl bg-slate-800/60 border border-slate-700/70 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-bell text-emerald-400 text-base"></i>
                                <div>
                                    <p class="text-sm font-bold text-white">Enable Telegram Notifications</p>
                                    <p class="text-xs text-slate-400">Master toggle for sending candidate alerts</p>
                                </div>
                            </div>
                            <input type="checkbox" name="telegram_notify_enabled" value="1" {{ $settings['telegram_notify_enabled'] === '1' ? 'checked' : '' }} class="w-5 h-5 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-emerald-500">
                        </label>

                        <label class="flex items-center justify-between p-3.5 rounded-xl bg-slate-800/60 border border-slate-700/70 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
                                <div>
                                    <p class="text-sm font-bold text-white">Send on Registration Success</p>
                                    <p class="text-xs text-slate-400">Includes candidate credentials, passport, email & password</p>
                                </div>
                            </div>
                            <input type="checkbox" name="telegram_notify_on_success" value="1" {{ $settings['telegram_notify_on_success'] === '1' ? 'checked' : '' }} class="w-5 h-5 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-emerald-500">
                        </label>

                        <label class="flex items-center justify-between p-3.5 rounded-xl bg-slate-800/60 border border-slate-700/70 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-triangle-exclamation text-rose-400 text-base"></i>
                                <div>
                                    <p class="text-sm font-bold text-white">Send on Registration Failure</p>
                                    <p class="text-xs text-slate-400">Alerts when automated process encounters error</p>
                                </div>
                            </div>
                            <input type="checkbox" name="telegram_notify_on_failed" value="1" {{ $settings['telegram_notify_on_failed'] === '1' ? 'checked' : '' }} class="w-5 h-5 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-emerald-500">
                        </label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="sendTestTelegramMessage()" id="testBtn"
                        class="w-full sm:w-auto px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-sky-400 hover:text-sky-300 border border-slate-700 text-xs font-bold transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Send Test Message</span>
                    </button>

                    <button type="submit"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-gradient-to-r from-sky-600 to-blue-500 hover:from-sky-500 hover:to-blue-400 text-white font-bold text-sm shadow-xl shadow-sky-600/20 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Telegram Settings</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Telegram Preview & Setup Guide (Right 1 Column) -->
        <div class="space-y-6">
            <!-- Sample Message Card -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-3">
                <div class="flex items-center gap-2.5 text-xs font-bold text-slate-300 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-eye text-sky-400"></i>
                    <span>Message Preview in Telegram</span>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-xs font-mono space-y-2 text-slate-300 leading-relaxed shadow-inner">
                    <p class="text-emerald-400 font-bold">🎉 TAQAMUL ACCOUNT CREATED SUCCESSFULLY!</p>
                    <p class="text-slate-600">━━━━━━━━━━━━━━━━━━━━</p>
                    <p>👤 <b>Candidate:</b> <span class="text-white">MD KARIM HOSSAIN</span></p>
                    <p>🛂 <b>Passport No:</b> <span class="text-amber-300">ZXN542109</span></p>
                    <p>📧 <b>Taqamul Email:</b> <span class="text-sky-300">mdkarim_zxn542109@wafidmaster.com</span></p>
                    <p>🔑 <b>Password:</b> <span class="text-emerald-300">Taqamul@2026!</span></p>
                    <p>🎯 <b>Verified OTP:</b> <span class="text-yellow-400">433522</span></p>
                    <p class="text-slate-600">━━━━━━━━━━━━━━━━━━━━</p>
                    <p>👨‍💻 <b>Submitted By:</b> Super Admin</p>
                    <p>⏰ <b>Time:</b> {{ now()->format('d M, Y - h:i A') }}</p>
                </div>
            </div>

            <!-- Setup Steps Guide -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-3">
                <div class="flex items-center gap-2 text-xs font-bold text-slate-300 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-circle-question text-amber-400"></i>
                    <span>Quick Setup Guide (3 Simple Steps)</span>
                </div>

                <ol class="space-y-3 text-xs text-slate-400">
                    <li class="flex gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-slate-800 text-sky-400 font-bold flex items-center justify-center shrink-0 text-[11px]">1</span>
                        <div>
                            <p class="text-white font-bold">Create Telegram Bot:</p>
                            <p>Search for <a href="https://t.me/BotFather" target="_blank" class="text-sky-400 underline font-mono">@BotFather</a> on Telegram, send <code>/newbot</code>, follow the instructions, and copy your API Token.</p>
                        </div>
                    </li>
                    <li class="flex gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-slate-800 text-sky-400 font-bold flex items-center justify-center shrink-0 text-[11px]">2</span>
                        <div>
                            <p class="text-white font-bold">Retrieve Your Chat ID:</p>
                            <p>Start <a href="https://t.me/userinfobot" target="_blank" class="text-sky-400 underline font-mono">@userinfobot</a> on Telegram to obtain your Chat/User ID, or add your bot to a group and get the Group Chat ID.</p>
                        </div>
                    </li>
                    <li class="flex gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-slate-800 text-sky-400 font-bold flex items-center justify-center shrink-0 text-[11px]">3</span>
                        <div>
                            <p class="text-white font-bold">Start the Bot & Test:</p>
                            <p>Send <b>/start</b> to your newly created bot in Telegram, paste the Token & Chat ID here, send a test notification, and save.</p>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    async function sendTestTelegramMessage() {
        const botToken = document.getElementById('botTokenInput').value.trim();
        const chatId = document.getElementById('chatIdInput').value.trim();

        if (!botToken || !chatId) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Fields',
                text: 'Please enter both Bot API Token and Chat ID first.',
                background: '#1e293b',
                color: '#fff'
            });
            return;
        }

        const btn = document.getElementById('testBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending Test...';

        try {
            const res = await fetch("{{ route('admin.telegram.test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    telegram_bot_token: botToken,
                    telegram_chat_id: chatId
                })
            });

            const data = await res.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Test Message Sent!',
                    text: 'Check your Telegram inbox or group.',
                    background: '#1e293b',
                    color: '#fff'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Telegram Error',
                    text: data.message || 'Could not send test message.',
                    background: '#1e293b',
                    color: '#fff'
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: e.message,
                background: '#1e293b',
                color: '#fff'
            });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Send Test Message</span>';
        }
    }
</script>
@endpush
@endsection
