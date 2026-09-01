@extends('layouts.app')

@section('title', 'Passenger Details - ' . $passenger->full_name)

@section('content')
<div class="max-w-5xl mx-auto space-y-8">

    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-slate-800 border border-slate-700/60 flex items-center justify-center text-emerald-400 font-bold overflow-hidden shrink-0 text-2xl">
                @if($passenger->personal_photo_path)
                    <img src="{{ asset('storage/' . $passenger->personal_photo_path) }}" alt="Photo" class="w-full h-full object-cover">
                @else
                    <i class="fa-solid fa-user"></i>
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-extrabold text-white">{{ $passenger->full_name }}</h1>
                    @if($passenger->status === 'completed')
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-bold font-mono">
                            <i class="fa-solid fa-circle-check text-[10px]"></i> Completed
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold font-mono">
                            {{ ucfirst($passenger->status) }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-400 font-mono mt-1">
                    Passport: <span class="text-amber-400 font-bold">{{ $passenger->passport_number }}</span> &bull; Created: {{ $passenger->created_at->format('d M Y, h:i A') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.passengers.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold transition-all flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Passengers</span>
            </a>
        </div>
    </div>

    <!-- Credentials Highlight Box -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-900/90 border border-emerald-500/30 rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-2">
                <i class="fa-solid fa-key"></i> Taqamul Portal Login Credentials
            </h3>
            <button type="button" onclick="copyCreds()" class="text-xs text-slate-400 hover:text-emerald-400 font-mono flex items-center gap-1">
                <i class="fa-regular fa-copy"></i> Copy All
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 font-mono text-xs">
            <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                <p class="text-slate-500 text-[11px]">Taqamul Login Email</p>
                <p id="credEmail" class="text-sm font-bold text-white select-all">{{ $passenger->email }}</p>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                <p class="text-slate-500 text-[11px]">Taqamul Password</p>
                <p id="credPass" class="text-sm font-bold text-emerald-400 select-all">{{ $passenger->password }}</p>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-1">
                <p class="text-slate-500 text-[11px]">Mail.tm Mailbox Password</p>
                <p id="credMailPass" class="text-sm font-bold text-teal-400 select-all">{{ $passenger->temp_mail_password ?: 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Candidate Detailed Information Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Personal & Passport Details -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-address-card text-emerald-400 mr-2"></i> Bio Data & Passport Details
            </h3>

            <div class="divide-y divide-slate-800 text-xs font-medium space-y-2.5 pt-1">
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">First Name:</span>
                    <span class="text-white font-bold">{{ $passenger->first_name }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Last Name:</span>
                    <span class="text-white font-bold">{{ $passenger->last_name ?: '(No Last Name)' }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Passport Number:</span>
                    <span class="text-amber-400 font-mono font-bold">{{ $passenger->passport_number }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">National ID (NID):</span>
                    <span class="text-slate-200 font-mono">{{ $passenger->national_id ?: 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Gender:</span>
                    <span class="text-white capitalize">{{ $passenger->gender }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Date of Birth:</span>
                    <span class="text-slate-200">{{ $passenger->date_of_birth ? $passenger->date_of_birth->format('d M, Y') : 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Passport Expiry:</span>
                    <span class="text-slate-200">{{ $passenger->passport_expiration_date ? $passenger->passport_expiration_date->format('d M, Y') : 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Assessment Country:</span>
                    <span class="text-white">{{ $passenger->country_name }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Nationality:</span>
                    <span class="text-white">{{ $passenger->nationality_name }}</span>
                </div>
            </div>
        </div>

        <!-- Qualifications & Contact Details -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-graduation-cap text-teal-400 mr-2"></i> Qualification & Contact
            </h3>

            <div class="divide-y divide-slate-800 text-xs font-medium space-y-2.5 pt-1">
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Education Level:</span>
                    <span class="text-white">{{ ucwords(str_replace('_', ' ', $passenger->education_level)) }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Experience Level:</span>
                    <span class="text-white">{{ ucwords(str_replace('_', ' ', $passenger->experience_level)) }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Training Institute:</span>
                    <span class="text-white">{{ $passenger->institute_name }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Phone Number:</span>
                    <span class="text-slate-200 font-mono">{{ $passenger->country_code }} {{ $passenger->phone_number }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Preferred Contact:</span>
                    <span class="text-white capitalize">{{ $passenger->preferable_contact }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Registered By:</span>
                    <span class="text-emerald-400">{{ $passenger->user ? $passenger->user->name : 'System Admin' }}</span>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Last OTP Code:</span>
                    <span class="text-amber-400 font-mono font-bold">{{ $passenger->otp_code ?: 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Attached Documents Viewer -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800 pb-3">
            <i class="fa-solid fa-file-invoice text-emerald-400 mr-2"></i> Attached Documents
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Passport Copy -->
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                <p class="text-xs font-bold text-white">Passport Document</p>
                @if($passenger->passport_file_path)
                    <div class="rounded-lg overflow-hidden bg-slate-900 border border-slate-800 max-h-64 flex items-center justify-center">
                        <img src="{{ asset('storage/' . $passenger->passport_file_path) }}" alt="Passport" class="max-h-64 object-contain">
                    </div>
                    <a href="{{ asset('storage/' . $passenger->passport_file_path) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:underline">
                        <i class="fa-solid fa-download"></i> View / Download Full File
                    </a>
                @else
                    <p class="text-xs text-slate-500 py-6 text-center">No passport file uploaded</p>
                @endif
            </div>

            <!-- Candidate Photo -->
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                <p class="text-xs font-bold text-white">Candidate Photo</p>
                @if($passenger->personal_photo_path)
                    <div class="rounded-lg overflow-hidden bg-slate-900 border border-slate-800 max-h-64 flex items-center justify-center">
                        <img src="{{ asset('storage/' . $passenger->personal_photo_path) }}" alt="Photo" class="max-h-64 object-contain">
                    </div>
                    <a href="{{ asset('storage/' . $passenger->personal_photo_path) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:underline">
                        <i class="fa-solid fa-download"></i> View / Download Full Image
                    </a>
                @else
                    <p class="text-xs text-slate-500 py-6 text-center">No photo uploaded</p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function copyCreds() {
        const email = document.getElementById('credEmail').innerText;
        const pass = document.getElementById('credPass').innerText;
        const mailPass = document.getElementById('credMailPass').innerText;
        const text = `Taqamul Candidate: {{ $passenger->full_name }}\nPassport: {{ $passenger->passport_number }}\nEmail: ${email}\nPassword: ${pass}\nTemp-Mail Password: ${mailPass}\nLogin: https://svp-international.pacc.sa/auth/login`;

        navigator.clipboard.writeText(text);
        Swal.fire({
            icon: 'success',
            title: 'Credentials Copied!',
            timer: 1200,
            showConfirmButton: false,
            background: '#1e293b',
            color: '#fff'
        });
    }
</script>
@endpush
@endsection
