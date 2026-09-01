@extends('layouts.app')

@section('title', 'User Dashboard')

@section('content')
<div class="space-y-8">

    <!-- User Header Banner -->
    <div class="bg-gradient-to-r from-teal-950/60 via-slate-900 to-slate-900 border border-teal-500/20 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
        <div class="relative z-10 max-w-2xl space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 text-xs font-mono font-bold">
                <i class="fa-solid fa-user-check"></i> Agency Staff Portal
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Welcome, {{ Auth::user()->name }}</h1>
            <p class="text-xs text-slate-300 leading-relaxed">
                Quickly register candidates to the Saudi Skill Verification Program. View all your submitted candidate credentials below.
            </p>
            <div class="pt-2">
                <a href="{{ route('registration.form') }}" class="px-5 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-bold text-xs shadow-lg shadow-emerald-600/20 flex items-center gap-2 transition-all w-fit">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Register New Candidate</span>
                </a>
            </div>
        </div>
    </div>

    <!-- User Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">My Candidates</p>
                <div class="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-white mt-3 font-mono">{{ $totalMyPassengers }}</p>
            <p class="text-xs text-slate-500 mt-1">Total candidates registered by you</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-400">Success Verified</p>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-emerald-400 mt-3 font-mono">{{ $successfulCount }}</p>
            <p class="text-xs text-emerald-400/70 mt-1">Ready to login on Taqamul</p>
        </div>
    </div>

    <!-- My Candidates Table -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <h3 class="text-base font-bold text-white">My Created Candidates</h3>
                <p class="text-xs text-slate-400">Credentials and status of candidates you submitted</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/80 uppercase text-[10px] font-bold text-slate-400 tracking-wider">
                    <tr>
                        <th class="py-3 px-3">Candidate</th>
                        <th class="py-3 px-3">Passport No</th>
                        <th class="py-3 px-3">Login Email</th>
                        <th class="py-3 px-3">Password</th>
                        <th class="py-3 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse($recentPassengers as $p)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-3.5 px-3">
                                <div class="font-bold text-white text-sm">{{ $p->full_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $p->country_name }}</div>
                            </td>
                            <td class="py-3.5 px-3 font-mono font-bold text-amber-400">{{ $p->passport_number }}</td>
                            <td class="py-3.5 px-3 font-mono text-slate-200">{{ $p->email }}</td>
                            <td class="py-3.5 px-3 font-mono text-emerald-400 font-bold">{{ $p->password }}</td>
                            <td class="py-3.5 px-3 text-center">
                                @if($p->status === 'completed')
                                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 text-[11px] font-bold">
                                        Completed
                                    </span>
                                @elseif($p->status === 'processing')
                                    <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 text-[11px] font-bold animate-pulse">
                                        <i class="fa-solid fa-spinner fa-spin text-[10px]"></i> Processing
                                    </span>
                                @elseif($p->status === 'failed')
                                    <span class="px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-400 text-[11px] font-bold">
                                        Failed
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 text-[11px] font-bold">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">
                                No candidate records created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
