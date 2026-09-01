@extends('layouts.app')

@section('title', 'Users Management')

@section('content')
<div class="space-y-8">

    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div>
            <div class="flex items-center gap-2 text-emerald-400 text-xs font-mono font-semibold uppercase tracking-wider mb-1">
                <i class="fa-solid fa-user-shield"></i> Access & Roles Management
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Users Management</h1>
            <p class="text-xs text-slate-400 mt-1">Create and manage internal staff/admin accounts and control system access.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" onclick="openCreateUserModal()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-bold shadow-lg shadow-emerald-600/20 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-user-plus"></i>
                <span>Create New User</span>
            </button>
        </div>
    </div>

    <!-- Stats Summary Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Users</p>
                <div class="w-8 h-8 rounded-lg bg-slate-800 text-slate-300 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-white mt-2 font-mono">{{ $users->total() }}</p>
            <p class="text-[11px] text-slate-500 mt-1">All accounts</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-400">Administrators</p>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-emerald-400 mt-2 font-mono">
                {{ \App\Models\User::where('role', 'admin')->count() }}
            </p>
            <p class="text-[11px] text-emerald-400/70 mt-1">Full access admins</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-teal-400">Staff / Users</p>
                <div class="w-8 h-8 rounded-lg bg-teal-500/20 text-teal-400 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-teal-400 mt-2 font-mono">
                {{ \App\Models\User::where('role', 'user')->count() }}
            </p>
            <p class="text-[11px] text-teal-400/70 mt-1">Data entry staff</p>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-amber-400">Active Accounts</p>
                <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-amber-400 mt-2 font-mono">
                {{ \App\Models\User::where('is_active', true)->count() }}
            </p>
            <p class="text-[11px] text-amber-400/70 mt-1">Active accounts</p>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
        <form action="{{ route('admin.users.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500 text-sm">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Name or Email address..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-white text-xs outline-none">
            </div>

            <div>
                <select name="role" class="w-full px-3 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none">
                    <option value="">All Roles</option>
                    <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="user" {{ request('role') == 'user' ? 'selected' : '' }}>Staff User</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'role']))
                    <a href="{{ route('admin.users.index') }}" class="px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs flex items-center justify-center">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/80 uppercase text-[11px] font-bold text-slate-400 tracking-wider border-b border-slate-800">
                    <tr>
                        <th class="py-4 px-4">User Info</th>
                        <th class="py-4 px-4">Login Email</th>
                        <th class="py-4 px-4 text-center">Role</th>
                        <th class="py-4 px-4 text-center">Created Candidates</th>
                        <th class="py-4 px-4 text-center">Status</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse($users as $u)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <!-- User Name -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700/60 flex items-center justify-center font-bold {{ $u->role === 'admin' ? 'text-emerald-400 bg-emerald-500/10' : 'text-teal-400 bg-teal-500/10' }}">
                                        {{ substr($u->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-sm">{{ $u->name }}</p>
                                        <p class="text-[11px] text-slate-500 font-mono">Created: {{ $u->created_at->format('d M, Y') }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Email -->
                            <td class="py-4 px-4 font-mono text-slate-200">
                                {{ $u->email }}
                            </td>

                            <!-- Role Badge -->
                            <td class="py-4 px-4 text-center">
                                @if($u->role === 'admin')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[11px] font-bold">
                                        <i class="fa-solid fa-shield-halved text-[10px]"></i> Administrator
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 text-[11px] font-bold">
                                        <i class="fa-solid fa-user text-[10px]"></i> Staff User
                                    </span>
                                @endif
                            </td>

                            <!-- Created Candidates Count -->
                            <td class="py-4 px-4 text-center font-mono font-bold text-slate-200">
                                <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700">
                                    {{ $u->passengers_count }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="py-4 px-4 text-center">
                                @if($u->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-[11px] font-bold">
                                        <i class="fa-solid fa-circle-check text-[9px]"></i> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-500/10 text-rose-400 text-[11px] font-bold">
                                        <i class="fa-solid fa-circle-xmark text-[9px]"></i> Inactive
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" onclick="openEditUserModal({{ json_encode($u) }})" title="Edit User" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                                        <i class="fa-regular fa-pen-to-square"></i>
                                    </button>

                                    @if(Auth::id() != $u->id)
                                        <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete User" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 flex items-center justify-center transition-colors">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

<!-- CREATE USER MODAL -->
<div id="createUserModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-400"></i>
                <span>Create New User</span>
            </h3>
            <button type="button" onclick="closeCreateUserModal()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">User Name <span class="text-rose-400">*</span></label>
                <input type="text" name="name" required placeholder="Staff Member Name"
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address <span class="text-rose-400">*</span></label>
                <input type="email" name="email" required placeholder="staff@taqamul.com"
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password <span class="text-rose-400">*</span></label>
                <input type="text" name="password" required value="user123" placeholder="Minimum 6 characters"
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Role <span class="text-rose-400">*</span></label>
                <select name="role" required class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none">
                    <option value="user" selected>Staff User (Registration form & own history)</option>
                    <option value="admin">Administrator (Full administrative access)</option>
                </select>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeCreateUserModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-400 text-xs font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/20">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div id="editUserModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-teal-400"></i>
                <span>Edit User Details</span>
            </h3>
            <button type="button" onclick="closeEditUserModal()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="editUserForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">User Name <span class="text-rose-400">*</span></label>
                <input type="text" name="name" id="editName" required
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address <span class="text-rose-400">*</span></label>
                <input type="email" name="email" id="editEmail" required
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">New Password (Optional)</label>
                <input type="text" name="password" placeholder="Leave blank to keep unchanged"
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Role <span class="text-rose-400">*</span></label>
                <select name="role" id="editRole" required class="w-full px-4 py-2.5 rounded-xl bg-slate-800/90 border border-slate-700 focus:border-emerald-500 text-white text-xs outline-none">
                    <option value="user">Staff User</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" name="is_active" id="editIsActive" value="1" class="rounded bg-slate-800 border-slate-700 text-emerald-600 focus:ring-emerald-500">
                <label for="editIsActive" class="text-xs text-slate-300 cursor-pointer">Account is Active</label>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-400 text-xs font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold shadow-lg shadow-teal-600/20">
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openCreateUserModal() {
        document.getElementById('createUserModal').classList.remove('hidden');
    }
    function closeCreateUserModal() {
        document.getElementById('createUserModal').classList.add('hidden');
    }

    function openEditUserModal(user) {
        document.getElementById('editUserForm').action = `/admin/users/${user.id}`;
        document.getElementById('editName').value = user.name;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editRole').value = user.role;
        document.getElementById('editIsActive').checked = Boolean(user.is_active);
        document.getElementById('editUserModal').classList.remove('hidden');
    }
    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }
</script>
@endpush
