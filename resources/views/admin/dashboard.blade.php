@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="space-y-8">

    <!-- Analytics Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Created</p>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-passport"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-white mt-3 font-mono">{{ $totalPassengers }}</p>
            <p class="text-xs text-slate-500 mt-1 font-medium">All registered accounts</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-400">Successful Accounts</p>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-emerald-400 mt-3 font-mono">{{ $successfulRegistrations }}</p>
            <p class="text-xs text-emerald-400/70 mt-1 font-medium">Verified & ready to login</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-amber-400">Today Created</p>
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-amber-400 mt-3 font-mono">{{ $todayRegistrations }}</p>
            <p class="text-xs text-amber-400/70 mt-1 font-medium">Candidates created today</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-teal-400">Active Users</p>
                <div class="w-10 h-10 rounded-xl bg-teal-500/20 text-teal-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-teal-400 mt-3 font-mono">{{ $totalUsers }}</p>
            <p class="text-xs text-teal-400/70 mt-1 font-medium">Staff & admin accounts</p>
        </div>
    </div>

    <!-- Recent Passengers Table -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <h3 class="text-base font-bold text-white">Recent Candidate Registrations</h3>
                <p class="text-xs text-slate-400">Last 8 created candidates</p>
            </div>
            <a href="{{ route('admin.passengers.index') }}" class="text-xs text-emerald-400 hover:text-emerald-300 font-bold flex items-center gap-1">
                <span>View All</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
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
                        <th class="py-3 px-3 text-right">Action</th>
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
                                @if(strtolower($p->status) === 'completed')
                                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/25 text-[11px] font-bold">
                                        Completed
                                    </span>
                                @elseif(strtolower($p->status) === 'ac done' || strtolower($p->status) === 'ac_done')
                                    <span class="px-2.5 py-0.5 rounded-full bg-sky-500/15 text-sky-300 border border-sky-500/25 text-[11px] font-bold">
                                        AC Done
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 text-[11px] font-bold">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 text-right">
                                <a href="{{ route('admin.passengers.show', $p->id) }}" class="text-xs text-emerald-400 hover:underline">
                                    Details &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">
                                No candidate records found. Use the registration form to create candidates.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
