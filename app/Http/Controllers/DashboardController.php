<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Placeholder stats data to be populated with database models
        $stats = [
            'active_holds' => 0,
            'vault_items' => 0,
            'pool_accounts' => 0,
            'system_status' => 'Operational'
        ];

        return view('dashboard', compact('stats'));
    }
}
