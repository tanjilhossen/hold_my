<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Taqamul Bot Engine</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #030712;
        }
        .font-mono-code {
            font-family: 'JetBrains Mono', monospace;
        }
        .cyber-grid {
            background-image: radial-gradient(rgba(16, 185, 129, 0.12) 1px, transparent 1px);
            background-size: 24px 24px;
        }
        .glow-box {
            box-shadow: 0 0 40px -10px rgba(16, 185, 129, 0.25);
        }
        .glow-box:hover {
            box-shadow: 0 0 50px -5px rgba(56, 189, 248, 0.35);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 selection:bg-emerald-500 selection:text-white cyber-grid relative overflow-hidden">

    <!-- Ambient Glowing Light Orbs -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-[350px] h-[350px] bg-sky-500/10 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- AI Bot Avatar & Engine Header -->
        <div class="text-center mb-6">
            <div class="relative w-20 h-20 mx-auto mb-4 flex items-center justify-center">
                <div class="absolute inset-0 rounded-2xl bg-gradient-to-tr from-emerald-500 to-sky-500 opacity-30 blur-md animate-pulse"></div>
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-slate-900 via-slate-800 to-slate-900 border border-emerald-500/40 flex items-center justify-center shadow-xl shadow-emerald-500/20 text-emerald-400 relative z-10">
                    <i class="fa-solid fa-robot text-3xl animate-bounce"></i>
                </div>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[11px] font-semibold tracking-wide uppercase mb-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span>TAQAMUL BOT ENGINE 2.0</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Ultimate Slot Sniper</h1>
        </div>

        <!-- Cyberpunk Login Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-8 shadow-2xl backdrop-blur-xl glow-box transition-all duration-300">
            @if($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-3">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400 text-base"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form action="{{ route('login.submit') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-user text-emerald-400 me-1"></i> Admin Email / Username
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500 text-sm">
                            <i class="fa-regular fa-envelope"></i>
                        </span>
                        <input type="email" name="email" id="loginEmail" required value="{{ old('email') }}" 
                            class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-950/80 border border-slate-700/80 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 text-white font-mono-code text-sm outline-none transition-all placeholder:text-slate-600"
                            placeholder="admin@taqamul.com">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-key text-sky-400 me-1"></i> Password
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500 text-sm">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="loginPassword" required 
                            class="w-full pl-10 pr-10 py-3 rounded-xl bg-slate-950/80 border border-slate-700/80 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 text-white font-mono-code text-sm outline-none transition-all placeholder:text-slate-600"
                            placeholder="••••••••">
                        <button type="button" onclick="toggleLoginPassword()" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-emerald-400 text-sm focus:outline-none transition-colors" title="Toggle Password">
                            <i class="fa-solid fa-eye" id="toggleLoginPasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-400 hover:text-slate-200">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-emerald-500 focus:ring-emerald-400">
                        <span>Remember credentials</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-600 via-teal-500 to-sky-600 hover:from-emerald-500 hover:to-sky-500 text-white font-extrabold text-sm shadow-lg shadow-emerald-500/20 hover:shadow-sky-500/30 transition-all flex items-center justify-center gap-2 uppercase tracking-wide">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Initialize Bot Engine Login</span>
                </button>
            </form>
        </div>
    </div>

<script>
    function toggleLoginPassword() {
        const pwdInput = document.getElementById('loginPassword');
        const pwdIcon = document.getElementById('toggleLoginPasswordIcon');
        if (pwdInput.type === 'password') {
            pwdInput.type = 'text';
            pwdIcon.classList.remove('fa-eye');
            pwdIcon.classList.add('fa-eye-slash');
        } else {
            pwdInput.type = 'password';
            pwdIcon.classList.remove('fa-eye-slash');
            pwdIcon.classList.add('fa-eye');
        }
    }
</script>
</body>
</html>
