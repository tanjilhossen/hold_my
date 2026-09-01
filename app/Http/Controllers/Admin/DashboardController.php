<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalPassengers = Passenger::count();
        $successfulRegistrations = Passenger::where('status', 'completed')->count();
        $pendingRegistrations = Passenger::where('status', 'otp_sent')->count();
        $todayRegistrations = Passenger::whereDate('created_at', Carbon::today())->count();

        $recentPassengers = Passenger::with('user')->latest()->take(8)->get();
        $totalUsers = User::count();

        return view('admin.dashboard', compact(
            'totalPassengers',
            'successfulRegistrations',
            'pendingRegistrations',
            'todayRegistrations',
            'recentPassengers',
            'totalUsers'
        ));
    }
}
