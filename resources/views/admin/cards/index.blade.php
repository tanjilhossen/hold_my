@extends('layouts.app')

@section('title', 'Payment Cards Management - Taqamul Automation')

@push('styles')
<style>
    /* 3D Interactive Credit Card Styles */
    .credit-card-wrapper {
        perspective: 1000px;
    }
    .credit-card-inner {
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        transform-style: preserve-3d;
    }
    .credit-card-wrapper.flipped .credit-card-inner {
        transform: rotateY(180deg);
    }
    .card-front, .card-back {
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
    }
    .card-back {
        transform: rotateY(180deg);
    }
    .card-bg-gradient {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
    }
    .card-bg-ific {
        background: linear-gradient(135deg, #064e3b 0%, #065f46 40%, #0f766e 80%, #134e4a 100%);
    }
</style>
@endpush

@section('content')
<div class="p-6 md:p-8 max-w-7xl mx-auto space-y-8">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/60 p-6 rounded-3xl border border-slate-800 backdrop-blur-xl shadow-xl">
        <div class="space-y-1">
            <h1 class="text-xl md:text-2xl font-black text-white tracking-tight flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-emerald-500 flex items-center justify-center text-white text-lg shadow-lg shadow-amber-500/20">
                    <i class="fa-solid fa-credit-card"></i>
                </span>
                <span>Payment Cards</span>
            </h1>
            <p class="text-xs text-slate-400">Manage bank cards (IFIC Bank / Visa / Mastercard) for automated 3D-Secure background slot payment.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" onclick="openAddCardModal()" class="px-4 py-2.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-emerald-600/20 transition-all cursor-pointer">
                <i class="fa-solid fa-plus"></i>
                <span>Add New Card</span>
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-check text-base text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Left Column: Interactive 3D Card Preview & Quick Info -->
        <div class="lg:col-span-5 space-y-6 sticky top-28">
            <div class="credit-card-wrapper w-full max-w-md mx-auto h-56" id="interactiveCard">
                <div class="credit-card-inner relative w-full h-full rounded-3xl shadow-2xl transition-all duration-500">
                    
                    <!-- Card Front -->
                    <div class="card-front absolute inset-0 rounded-3xl card-bg-ific p-6 text-white flex flex-col justify-between border border-emerald-500/30 overflow-hidden shadow-2xl">
                        <!-- Hologram lines -->
                        <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-emerald-400/10 blur-2xl pointer-events-none"></div>
                        <div class="absolute -left-16 -bottom-16 w-56 h-56 rounded-full bg-teal-400/10 blur-2xl pointer-events-none"></div>

                        <div class="flex justify-between items-start z-10">
                            <div>
                                <p id="previewBankName" class="text-base font-black tracking-wider uppercase text-emerald-200">IFIC BANK</p>
                                <p class="text-[10px] text-emerald-300/70 uppercase tracking-widest font-mono">Debit / Credit Card</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="previewCardType" class="text-xs font-extrabold uppercase px-2.5 py-1 rounded-lg bg-white/10 backdrop-blur border border-white/20">Visa</span>
                            </div>
                        </div>

                        <!-- Chip -->
                        <div class="flex items-center gap-3 z-10">
                            <div class="w-11 h-8 rounded-lg bg-gradient-to-tr from-amber-300 via-amber-200 to-yellow-400 border border-amber-400/60 shadow-inner flex items-center justify-center">
                                <div class="w-7 h-5 border border-amber-600/40 rounded flex flex-col justify-between p-0.5 opacity-75">
                                    <div class="border-b border-amber-600/40"></div>
                                    <div class="border-b border-amber-600/40"></div>
                                </div>
                            </div>
                            <i class="fa-solid fa-wifi text-slate-300/80 rotate-90 text-sm"></i>
                        </div>

                        <!-- Card Number -->
                        <div class="z-10">
                            <p id="previewCardNumber" class="font-mono text-lg md:text-xl font-bold tracking-widest text-white drop-shadow">
                                {{ $defaultCard ? $defaultCard->masked_number : '•••• •••• •••• ••••' }}
                            </p>
                        </div>

                        <div class="flex justify-between items-end z-10 text-xs">
                            <div>
                                <p class="text-[9px] uppercase tracking-wider text-emerald-300/70 font-semibold">Card Holder</p>
                                <p id="previewCardHolder" class="font-bold tracking-wide uppercase text-white truncate max-w-[180px]">
                                    {{ $defaultCard ? $defaultCard->card_holder_name : 'CARDHOLDER NAME' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] uppercase tracking-wider text-emerald-300/70 font-semibold">Expires</p>
                                <p id="previewCardExpiry" class="font-mono font-bold text-white">
                                    {{ $defaultCard ? $defaultCard->formatted_expiry : 'MM/YY' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Back -->
                    <div class="card-back absolute inset-0 rounded-3xl bg-slate-900 p-6 text-white flex flex-col justify-between border border-slate-800 overflow-hidden shadow-2xl">
                        <!-- Black magnetic stripe -->
                        <div class="absolute top-6 left-0 right-0 h-10 bg-slate-950 border-y border-slate-800"></div>

                        <div class="mt-14 z-10">
                            <div class="flex items-center justify-end bg-slate-200 text-slate-900 px-3 py-1.5 rounded-lg">
                                <p class="text-[10px] text-slate-500 font-mono mr-2">CVV / CVC</p>
                                <p id="previewCardCvv" class="font-mono font-bold tracking-widest text-slate-900">
                                    {{ $defaultCard ? '•••' : '•••' }}
                                </p>
                            </div>
                        </div>

                        <div class="z-10 text-right">
                            <p class="text-[9px] text-slate-500 leading-relaxed">
                                Authorized signature • Not valid unless signed • 3D Secure Protection
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Automation Info Card -->
            <div class="p-5 rounded-3xl bg-slate-900/60 border border-slate-800 space-y-3">
                <div class="flex items-center gap-2.5 text-xs font-bold text-amber-400">
                    <i class="fa-solid fa-shield-halved text-sm"></i>
                    <span>Automatic 3D-Secure Background Engine</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    During slot booking, this primary card details will be submitted to the Taqamul gateway in the background, triggering the 3D-Secure SMS OTP to your registered phone number.
                </p>
            </div>
        </div>

        <!-- Right Column: Saved Cards Table / Cards Grid -->
        <div class="lg:col-span-7 space-y-5">
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-wallet text-emerald-400"></i>
                            <span>Saved Payment Cards</span>
                        </h2>
                        <p class="text-xs text-slate-400">Active cards configured for automated 1-click slot checkout</p>
                    </div>
                    <span class="text-xs font-mono px-3 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700 font-bold">
                        {{ $cards->count() }} Total
                    </span>
                </div>

                @if($cards->isEmpty())
                    <div class="text-center py-12 space-y-4">
                        <div class="w-16 h-16 rounded-2xl bg-slate-800/60 border border-slate-700/60 flex items-center justify-center text-slate-500 text-2xl mx-auto">
                            <i class="fa-regular fa-credit-card"></i>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-bold text-slate-300">No payment cards added yet</p>
                            <p class="text-xs text-slate-500">Add your IFIC Bank card to enable automated slot booking and 3D Secure checkout.</p>
                        </div>
                        <button type="button" onclick="openAddCardModal()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs inline-flex items-center gap-2 cursor-pointer shadow-lg shadow-emerald-600/20 transition-all">
                            <i class="fa-solid fa-plus"></i> Add IFIC Card
                        </button>
                    </div>
                @else
                    <div class="space-y-3.5">
                        @foreach($cards as $card)
                            <div class="p-4 rounded-2xl border transition-all flex flex-col md:flex-row md:items-center justify-between gap-4 {{ $card->is_default ? 'bg-emerald-950/20 border-emerald-500/40 ring-1 ring-emerald-500/30' : 'bg-slate-950/60 border-slate-800 hover:border-slate-700' }}">
                                
                                <div class="flex items-center gap-3.5">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-xl shrink-0 {{ $card->is_default ? 'text-emerald-400 shadow-lg shadow-emerald-500/10' : 'text-slate-400' }}">
                                        @if(stripos($card->card_type, 'master') !== false)
                                            <i class="fa-brands fa-cc-mastercard text-rose-400"></i>
                                        @else
                                            <i class="fa-brands fa-cc-visa text-sky-400"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-sm font-bold text-white">{{ $card->bank_name }}</h3>
                                            @if($card->is_default)
                                                <span class="text-[10px] uppercase font-mono font-extrabold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                                    <i class="fa-solid fa-circle-check text-[9px]"></i> Default Card
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs font-mono text-slate-300 tracking-wider mt-0.5">{{ $card->masked_number }}</p>
                                        <div class="flex items-center gap-3 text-[11px] text-slate-400 mt-1">
                                            <span><i class="fa-regular fa-user text-slate-500"></i> {{ $card->card_holder_name }}</span>
                                            <span>•</span>
                                            <span><i class="fa-regular fa-calendar text-slate-500"></i> Exp: {{ $card->formatted_expiry }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 self-end md:self-center">
                                    @if(!$card->is_default)
                                        <form action="{{ route('admin.cards.default', $card->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-emerald-600/20 text-slate-300 hover:text-emerald-400 border border-slate-700 hover:border-emerald-500/30 text-xs font-bold transition-all cursor-pointer" title="Set as Primary Default Card">
                                                <i class="fa-regular fa-star"></i> Set Default
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" onclick="openEditCardModal({{ json_encode($card) }})" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center transition-all cursor-pointer" title="Edit Card">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>

                                    <form action="{{ route('admin.cards.destroy', $card->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this card?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-8 h-8 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-all cursor-pointer" title="Delete Card">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>

</div>

<!-- Modal: Add / Edit Card -->
<div id="cardModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl space-y-6 relative">
        
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <h3 id="modalTitle" class="text-base font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-credit-card text-emerald-400"></i>
                <span>Add New Payment Card</span>
            </h3>
            <button type="button" onclick="closeCardModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="cardForm" action="{{ route('admin.cards.store') }}" method="POST" class="space-y-4">
            @csrf
            <div id="formMethod"></div>

            <!-- Bank Name -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Bank Name</label>
                <input type="text" name="bank_name" id="formBankName" required value="IFIC Bank"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-bold focus:border-emerald-500 focus:outline-none transition-all placeholder:text-slate-600"
                       placeholder="IFIC Bank, City Bank, etc."
                       oninput="document.getElementById('previewBankName').innerText = this.value || 'BANK NAME'">
            </div>

            <!-- Cardholder Name -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Cardholder Name</label>
                <input type="text" name="card_holder_name" id="formCardHolder" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-bold focus:border-emerald-500 focus:outline-none transition-all uppercase placeholder:text-slate-600"
                       placeholder="MD ABDUR RAHMAN"
                       oninput="document.getElementById('previewCardHolder').innerText = (this.value || 'CARDHOLDER NAME').toUpperCase()">
            </div>

            <!-- Card Number -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Card Number (16-Digit Number)</label>
                <div class="relative">
                    <input type="text" name="card_number" id="formCardNumber" required maxlength="19"
                           class="w-full pl-3.5 pr-12 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono font-bold focus:border-emerald-500 focus:outline-none transition-all placeholder:text-slate-600 tracking-widest"
                           placeholder="4000 1234 5678 9010"
                           oninput="formatCardNumberInput(this)">
                    <span id="formCardTypeBadge" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">
                        <i class="fa-solid fa-credit-card"></i>
                    </span>
                </div>
            </div>

            <!-- Expiry & CVV Grid -->
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Exp Month</label>
                    <input type="text" name="expiry_month" id="formExpMonth" required maxlength="2"
                           class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono font-bold text-center focus:border-emerald-500 focus:outline-none transition-all placeholder:text-slate-600"
                           placeholder="MM (05)"
                           oninput="updateCardExpiryPreview()">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Exp Year</label>
                    <input type="text" name="expiry_year" id="formExpYear" required maxlength="4"
                           class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono font-bold text-center focus:border-emerald-500 focus:outline-none transition-all placeholder:text-slate-600"
                           placeholder="YYYY (2028)"
                           oninput="updateCardExpiryPreview()">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">CVV / CVC</label>
                    <input type="password" name="cvv" id="formCvv" required maxlength="4"
                           class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono font-bold text-center focus:border-emerald-500 focus:outline-none transition-all placeholder:text-slate-600"
                           placeholder="•••"
                           onfocus="document.getElementById('interactiveCard').classList.add('flipped')"
                           onblur="document.getElementById('interactiveCard').classList.remove('flipped')"
                           oninput="document.getElementById('previewCardCvv').innerText = this.value || '•••'">
                </div>
            </div>

            <!-- Default Card Checkbox -->
            <div class="pt-2 flex items-center justify-between">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_default" id="formIsDefault" value="1" class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-emerald-600 focus:ring-emerald-500">
                    <span>Set as Primary Default Card for Automated Booking</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2.5">
                <button type="button" onclick="closeCardModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-emerald-600/20 cursor-pointer">
                    <i class="fa-solid fa-check"></i>
                    <span id="submitBtnText">Save Card Details</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function formatCardNumberInput(input) {
        let v = input.value.replace(/\D/g, '');
        let formatted = v.match(/.{1,4}/g)?.join(' ') || v;
        input.value = formatted;

        // Auto-detect Visa / Mastercard badge
        const badge = document.getElementById('formCardTypeBadge');
        const previewType = document.getElementById('previewCardType');
        if (v.startsWith('4')) {
            badge.innerHTML = `<i class="fa-brands fa-cc-visa text-sky-400 text-base"></i>`;
            previewType.innerText = 'VISA';
        } else if (v.startsWith('5') || v.startsWith('2')) {
            badge.innerHTML = `<i class="fa-brands fa-cc-mastercard text-rose-400 text-base"></i>`;
            previewType.innerText = 'MASTERCARD';
        } else {
            badge.innerHTML = `<i class="fa-solid fa-credit-card text-slate-400"></i>`;
            previewType.innerText = 'CARD';
        }

        document.getElementById('previewCardNumber').innerText = formatted || '•••• •••• •••• ••••';
    }

    function updateCardExpiryPreview() {
        const m = document.getElementById('formExpMonth').value || 'MM';
        const y = document.getElementById('formExpYear').value || 'YY';
        const shortY = y.length === 4 ? y.slice(-2) : y;
        document.getElementById('previewCardExpiry').innerText = `${m}/${shortY}`;
    }

    function openAddCardModal() {
        document.getElementById('modalTitle').innerText = 'Add New Payment Card (IFIC Bank)';
        document.getElementById('cardForm').action = "{{ route('admin.cards.store') }}";
        document.getElementById('formMethod').innerHTML = '';
        document.getElementById('formBankName').value = 'IFIC Bank';
        document.getElementById('formCardHolder').value = '';
        document.getElementById('formCardNumber').value = '';
        document.getElementById('formCardNumber').required = true;
        document.getElementById('formExpMonth').value = '';
        document.getElementById('formExpYear').value = '';
        document.getElementById('formCvv').value = '';
        document.getElementById('formCvv').required = true;
        document.getElementById('formIsDefault').checked = true;
        document.getElementById('submitBtnText').innerText = 'Save Card Details';
        document.getElementById('cardModal').classList.remove('hidden');
    }

    function openEditCardModal(card) {
        document.getElementById('modalTitle').innerText = 'Edit Payment Card - ' + card.bank_name;
        document.getElementById('cardForm').action = `/admin/cards/${card.id}`;
        document.getElementById('formMethod').innerHTML = '@method("PUT")';
        document.getElementById('formBankName').value = card.bank_name;
        document.getElementById('formCardHolder').value = card.card_holder_name;
        document.getElementById('formCardNumber').value = '';
        document.getElementById('formCardNumber').required = false;
        document.getElementById('formCardNumber').placeholder = 'Leave blank to keep (' + card.card_number.slice(-4) + ')';
        document.getElementById('formExpMonth').value = card.expiry_month;
        document.getElementById('formExpYear').value = card.expiry_year;
        document.getElementById('formCvv').value = '';
        document.getElementById('formCvv').required = false;
        document.getElementById('formCvv').placeholder = 'Leave blank to keep';
        document.getElementById('formIsDefault').checked = card.is_default;
        document.getElementById('submitBtnText').innerText = 'Update Card Details';
        document.getElementById('cardModal').classList.remove('hidden');
    }

    function closeCardModal() {
        document.getElementById('cardModal').classList.add('hidden');
    }
</script>
@endpush
