@extends('layouts.app')

@section('title', 'Taqamul Candidate Registration Form')

@section('content')
<div class="max-w-5xl mx-auto space-y-8">

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div>
            <div class="flex items-center gap-2 text-emerald-400 text-xs font-mono font-semibold uppercase tracking-wider mb-1">
                <i class="fa-solid fa-bolt"></i> Taqamul Account Automation Engine
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Candidate Registration Form</h1>
            <p class="text-xs text-slate-400 mt-1">Fill in the required candidate details to register for Taqamul examination.</p>
        </div>
    </div>

    <!-- Registration Form -->
    <form id="taqamulRegForm" onsubmit="handleRegistrationSubmit(event)" enctype="multipart/form-data" class="space-y-8">
        @csrf

        <!-- SECTION 1: DOCUMENT UPLOADS -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
            <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm font-bold">
                    1
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">Document & Photo Uploads</h2>
                    <p class="text-xs text-slate-400">Attach passport copy and candidate photo</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Passport Upload -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Passport Copy / Scan <span class="text-rose-400 font-bold">* (Required)</span>
                    </label>
                    <div class="relative border-2 border-dashed border-slate-700 hover:border-emerald-500/60 rounded-2xl p-4 text-center transition-colors bg-slate-800/40 min-h-[160px] flex items-center justify-center">
                        <input type="file" name="passport_file" id="passportFile" accept="image/jpeg,image/png,image/jpg" required onchange="previewFile(this, 'passportPreview')" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div id="passportPreview" class="space-y-2 w-full">
                            <div class="w-12 h-12 rounded-xl bg-slate-800 text-emerald-400 flex items-center justify-center mx-auto text-xl">
                                <i class="fa-solid fa-passport"></i>
                            </div>
                            <p class="text-xs font-medium text-slate-300">Click to select passport image <span class="text-rose-400">*</span></p>
                            <p class="text-[11px] text-slate-400">JPG, PNG (Max 5 MB)</p>
                        </div>
                    </div>
                </div>

                <!-- Personal Photo Upload -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Candidate Photo <span class="text-rose-400 font-bold">* (Required)</span>
                    </label>
                    <div class="relative border-2 border-dashed border-slate-700 hover:border-emerald-500/60 rounded-2xl p-4 text-center transition-colors bg-slate-800/40 min-h-[160px] flex items-center justify-center">
                        <input type="file" name="personal_photo" id="photoFile" accept="image/jpeg,image/png,image/jpg" required onchange="previewFile(this, 'photoPreview')" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div id="photoPreview" class="space-y-2 w-full">
                            <div class="w-12 h-12 rounded-xl bg-slate-800 text-teal-400 flex items-center justify-center mx-auto text-xl">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <p class="text-xs font-medium text-slate-300">Click to select candidate photo <span class="text-rose-400">*</span></p>
                            <p class="text-[11px] text-slate-400">JPG, PNG (Max 5 MB)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: PERSONAL & PASSPORT INFO -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
            <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm font-bold">
                    2
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">Personal & Passport Details</h2>
                    <p class="text-xs text-slate-400">Provide official details as listed in the passport</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- First Name -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        First Name <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" name="first_name" id="firstName" required placeholder="MD"
                        class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none uppercase font-medium">
                </div>

                <!-- Last Name / Surname -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            Last Name / Surname
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-slate-400 hover:text-emerald-400">
                            <input type="checkbox" name="no_last_name" id="noLastName" onchange="toggleLastName(this)" class="rounded bg-slate-800 border-slate-700 text-emerald-600 focus:ring-emerald-500">
                            <span>No Last Name</span>
                        </label>
                    </div>
                    <input type="text" name="last_name" id="lastName" placeholder="RAHMAN"
                        class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none uppercase font-medium">
                </div>

                <!-- Passport Number -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Passport Number <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" name="passport_number" id="passportNumber" required placeholder="A01234567"
                        class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none uppercase font-mono font-bold tracking-wider">
                </div>

                <!-- National ID / NID -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        National ID / NID
                    </label>
                    <input type="text" name="national_id" id="nationalId" placeholder="1995123456789"
                        class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                </div>

                <!-- Date of Birth -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Date of Birth <span class="text-rose-400">*</span>
                    </label>
                    <input type="date" name="date_of_birth" id="dob" required value="1998-05-15"
                        class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                </div>

                <!-- Passport Expiry Date -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Passport Expiry Date <span class="text-rose-400">*</span>
                    </label>
                    <input type="date" name="passport_expiration_date" id="passportExp" required value="2032-10-20"
                        class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                </div>

                <!-- Gender -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Gender <span class="text-rose-400">*</span>
                    </label>
                    <select name="gender" id="gender" required class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                        <option value="male" selected>Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <!-- Country Selection -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Testing Country <span class="text-rose-400">*</span>
                    </label>
                    <select name="country_id" id="countrySelect" onchange="updateCountryName(this)" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                        <option value="1" data-name="Bangladesh" data-code="+880" selected>Bangladesh (+880)</option>
                        <option value="2" data-name="Pakistan" data-code="+92">Pakistan (+92)</option>
                        <option value="3" data-name="India" data-code="+91">India (+91)</option>
                        <option value="4" data-name="Saudi Arabia" data-code="+966">Saudi Arabia (+966)</option>
                        <option value="5" data-name="Egypt" data-code="+20">Egypt (+20)</option>
                        <option value="6" data-name="Sri Lanka" data-code="+94">Sri Lanka (+94)</option>
                        <option value="7" data-name="Philippines" data-code="+63">Philippines (+63)</option>
                    </select>
                    <input type="hidden" name="country_name" id="countryName" value="Bangladesh">
                </div>

                <!-- Nationality Selection -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Nationality <span class="text-rose-400">*</span>
                    </label>
                    <select name="nationality_id" id="nationalitySelect" onchange="updateNationalityName(this)" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                        <option value="1" data-name="Bangladeshi" selected>Bangladeshi</option>
                        <option value="2" data-name="Pakistani">Pakistani</option>
                        <option value="3" data-name="Indian">Indian</option>
                        <option value="4" data-name="Saudi">Saudi</option>
                        <option value="5" data-name="Egyptian">Egyptian</option>
                    </select>
                    <input type="hidden" name="nationality_name" id="nationalityName" value="Bangladeshi">
                </div>
            </div>
        </div>

        <!-- SECTION 3: OTHER DETAILS & EDUCATION -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
            <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm font-bold">
                    3
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">Qualifications & Experience</h2>
                    <p class="text-xs text-slate-400">Education background, work experience and training certificate</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Education Level -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Education Level <span class="text-rose-400">*</span>
                    </label>
                    <select name="education_level" id="educationLevel" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                        <option value="no_educational_qualification">No Educational Qualification</option>
                        <option value="learned">Learned</option>
                        <option value="primary">Primary (6-8 Years)</option>
                        <option value="middle">Middle (10-12 Years)</option>
                        <option value="secondary" selected>Secondary / SSC (12+ Years)</option>
                        <option value="diploma">Diploma</option>
                        <option value="bachelors">Bachelor's Degree or higher</option>
                    </select>
                </div>

                <!-- Experience Level -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Experience Level <span class="text-rose-400">*</span>
                    </label>
                    <select name="experience_level" id="experienceLevel" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                        <option value="no_experience">No experience</option>
                        <option value="less_than_3">Less than 3 years</option>
                        <option value="between_3_and_5" selected>Between 3 and 5 years</option>
                        <option value="greater_than_5">More than 5 years</option>
                    </select>
                </div>

                <!-- Training Institute -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Training Institute / Certification <span class="text-rose-400">*</span>
                    </label>
                    <select name="institute_name" id="instituteSelect" class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none">
                        <option value="No, I don’t have any certifications or training" selected>No Certification / Training</option>
                        <option value="bmet">BMET (Bangladesh)</option>
                        <option value="navttc">NAVTTC (Pakistan)</option>
                        <option value="nsdc">NSDC (India)</option>
                        <option value="tvec">TVEC (Sri Lanka)</option>
                        <option value="other">Other Institute</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SECTION 4: CONTACT & PASSWORD -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
            <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm font-bold">
                    4
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">Contact Information & Security</h2>
                    <p class="text-xs text-slate-400">Candidate phone number & account password (Mail & OTP configured via Settings)</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Phone Number -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Phone Number <span class="text-rose-400">*</span>
                    </label>
                    <div class="flex">
                        <input type="text" name="country_code" id="countryCodeInput" value="+880" readonly class="w-20 px-3 py-3 rounded-l-xl bg-slate-800 border border-r-0 border-slate-700 text-emerald-400 font-mono text-sm text-center">
                        <input type="text" name="phone_number" id="phoneNumber" placeholder="1712345678" required
                            class="flex-1 px-4 py-3 rounded-r-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            Taqamul Password <span class="text-rose-400">*</span>
                        </label>
                        <button type="button" onclick="generateNewPassword()" class="text-[11px] text-emerald-400 hover:text-emerald-300 flex items-center gap-1 font-mono">
                            <i class="fa-solid fa-arrows-rotate"></i> Auto
                        </button>
                    </div>
                    <div class="relative">
                        <input type="text" name="password" id="regPassword" value="{{ \App\Models\Setting::get('default_password', 'Taqamul@2026!') }}" required
                            class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-sm outline-none font-mono">
                    </div>
                </div>
            </div>
        </div>

        <!-- SUBMISSION & CONTROLS -->
        <div class="flex items-center justify-end gap-4 pt-4">
            <button type="submit" id="submitBtn" class="px-8 py-4 rounded-xl bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-extrabold text-base shadow-xl shadow-emerald-600/30 transition-all flex items-center gap-3 transform active:scale-95">
                <i class="fa-solid fa-bolt text-amber-300"></i>
                <span>Start Fast Auto-Register</span>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    let activePassengerId = null;
    let pollInterval = null;

    function toggleLastName(checkbox) {
        const lastNameInput = document.getElementById('lastName');
        if (checkbox.checked) {
            lastNameInput.value = '';
            lastNameInput.disabled = true;
            lastNameInput.classList.add('opacity-50');
        } else {
            lastNameInput.disabled = false;
            lastNameInput.classList.remove('opacity-50');
        }
    }

    function toggleEmailMode(mode) {
        const container = document.getElementById('customEmailContainer');
        if (container) {
            if (mode === 'custom_mail') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }
    }

    function updateCountryName(select) {
        const selected = select.options[select.selectedIndex];
        document.getElementById('countryName').value = selected.getAttribute('data-name') || 'Bangladesh';
        const code = selected.getAttribute('data-code') || '+880';
        document.getElementById('countryCodeInput').value = code;
    }

    function updateNationalityName(select) {
        const selected = select.options[select.selectedIndex];
        document.getElementById('nationalityName').value = selected.getAttribute('data-name') || 'Bangladeshi';
    }

    function generateNewPassword() {
        const randomNum = Math.floor(1000 + Math.random() * 9000);
        document.getElementById('regPassword').value = `Taqamul@${randomNum}!`;
    }

    function previewFile(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const isPassport = previewId.includes('passport');
                preview.innerHTML = `
                    <div class="space-y-2.5 py-1">
                        <div class="relative inline-block group">
                            <img src="${e.target.result}" alt="Preview" class="${isPassport ? 'h-32 w-auto max-w-[260px]' : 'h-32 w-28'} object-cover rounded-xl shadow-lg border-2 border-emerald-500/60 mx-auto transition-transform group-hover:scale-105">
                            <span class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs shadow-md">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-white truncate max-w-[220px] mx-auto">${file.name}</p>
                            <p class="text-[11px] text-emerald-400 font-mono">${(file.size / 1024).toFixed(1)} KB • <span class="text-slate-300 hover:text-white underline cursor-pointer">Click to change</span></p>
                        </div>
                    </div>
                `;
            };
            
            reader.readAsDataURL(file);
        }
    }



    async function handleRegistrationSubmit(e) {
        e.preventDefault();
        
        const form = document.getElementById('taqamulRegForm');
        const formData = new FormData(form);
        
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Submitting & Redirecting...</span>';
        submitBtn.classList.add('opacity-75', 'cursor-not-allowed');

        try {
            const response = await fetch("{{ route('registration.process') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = result.redirect_url || "{{ route('admin.passengers.index') }}";
                return;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: result.message,
                    background: '#1e293b',
                    color: '#fff'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-bolt text-amber-300"></i> <span>Start Fast Auto-Register</span>';
                submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: err.message,
                background: '#1e293b',
                color: '#fff'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-bolt text-amber-300"></i> <span>Start Fast Auto-Register</span>';
            submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    }
</script>
@endpush
