<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $totalMyPassengers = Passenger::where('user_id', $user->id)->count();
        $successfulCount = Passenger::where('user_id', $user->id)->where('status', 'completed')->count();
        $recentPassengers = Passenger::where('user_id', $user->id)->latest()->take(10)->get();

        return view('user.dashboard', compact('totalMyPassengers', 'successfulCount', 'recentPassengers'));
    }
}
