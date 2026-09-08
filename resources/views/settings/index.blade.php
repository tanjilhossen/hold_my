@extends('layouts.app')

@section('title', 'Settings - Taqamul Engine')
@section('page_title', 'System & Pool Accounts Settings')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <!-- Section 1: Pool Candidate Accounts Manager -->
    <div class="col-lg-7">
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-users-gear text-primary me-2"></i> Pool Candidates & Passwords</h5>
                    <small class="text-muted">Candidate accounts used to query and hold slots in round-robin pool.</small>
                </div>
                <span class="badge bg-primary fs-6">{{ count($poolAccounts) }} Accounts</span>
            </div>
            
            <form action="{{ route('settings.pool.add') }}" method="POST" class="row g-2 mb-4 bg-light p-3 rounded border">
                @csrf
                <div class="col-md-5">
                    <input type="email" name="email" class="form-control" placeholder="Candidate Email" required>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="password" name="password" id="add_candidate_pwd" class="form-control" placeholder="Password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('add_candidate_pwd', this)" title="Toggle Password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Add Account
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Candidate Email</th>
                            <th>Password</th>
                            <th>Token / Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($poolAccounts as $index => $account)
                        <tr>
                            <td class="fw-bold">{{ $index + 1 }}</td>
                            <td>
                                <div><strong class="text-dark">{{ $account['email'] }}</strong></div>
                                <small class="text-muted">{{ $account['name'] ?? 'Pool Account' }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1 bg-light px-2 py-1 border rounded" style="max-width: 170px;">
                                    <input type="password" value="{{ $account['password'] ?? '' }}" readonly class="form-control form-control-sm font-monospace border-0 bg-transparent p-0 text-dark fw-bold" id="pool_pwd_{{ $index }}">
                                    <button type="button" class="btn btn-sm p-0 border-0 text-secondary" onclick="togglePasswordVisibility('pool_pwd_{{ $index }}', this)" title="Show/Hide Password">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                @if(!empty($account['token']))
                                    <span class="badge bg-success"><i class="fa-solid fa-key me-1"></i> Token Active</span>
                                @else
                                    <span class="badge bg-secondary">{{ $account['status'] ?? 'No Token' }}</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('settings.pool.remove') }}" method="POST" onsubmit="return confirm('Remove this account from candidate pool?');">
                                    @csrf
                                    <input type="hidden" name="email" value="{{ $account['email'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="fa-solid fa-user-slash fa-2x mb-2 d-block text-secondary"></i>
                                No pool accounts added yet. Add candidate accounts above.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 2: Software Admin Login Credentials & Automation Settings -->
    <div class="col-lg-5">
        <div class="card card-custom p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-user-shield text-danger me-2"></i> Software Admin Login Credentials</h5>
            <form action="{{ route('settings.admin_credentials') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Admin Name</label>
                    <input type="text" name="name" class="form-control" value="{{ Auth::user()->name ?? 'Super Admin' }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Login Email / Username</label>
                    <input type="email" name="email" class="form-control font-monospace" value="{{ Auth::user()->email ?? 'admin@taqamul.com' }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-danger">Current Password (Required)</label>
                    <div class="input-group">
                        <input type="password" name="current_password" id="admin_curr_pwd" class="form-control" placeholder="Enter current password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('admin_curr_pwd', this)" title="Toggle Password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="admin_new_pwd" class="form-control" placeholder="Leave blank if not changing">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('admin_new_pwd', this)" title="Toggle Password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password_confirmation" id="admin_conf_pwd" class="form-control" placeholder="Confirm new password">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('admin_conf_pwd', this)" title="Toggle Password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-danger w-100 fw-bold">
                    <i class="fa-solid fa-key me-1"></i> Update Admin Credentials
                </button>
            </form>
        </div>

        <!-- Section 3: Telegram & Automation Default Configurations -->
        <div class="card card-custom p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="fa-paper-plane text-info me-2"></i> Telegram Notifications</h5>
            <form action="{{ route('settings.update') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Telegram Bot Token</label>
                    <input type="text" name="telegram_bot_token" class="form-control font-monospace" value="{{ $settings['telegram_bot_token'] ?? '' }}" placeholder="123456789:ABCdefGHI...">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Telegram Chat / Channel ID</label>
                    <input type="text" name="telegram_chat_id" class="form-control font-monospace" value="{{ $settings['telegram_chat_id'] ?? '' }}" placeholder="6363876244">
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Telegram Settings
                </button>
            </form>
        </div>

        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left text-warning me-2"></i> Auto-Renew Parameters</h5>
            <form action="{{ route('settings.update') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Hold Auto-Renew Interval (Seconds)</label>
                    <input type="number" name="auto_renew_interval" class="form-control" value="{{ $settings['auto_renew_interval'] ?? 30 }}">
                    <small class="text-muted">Interval to renew held slot locks before 20-minute expiration.</small>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Parameters
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function togglePasswordVisibility(inputId, btn) {
        const field = document.getElementById(inputId);
        if (!field) return;
        const icon = btn.querySelector('i');
        if (field.type === 'password') {
            field.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            field.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }
</script>
@endsection
