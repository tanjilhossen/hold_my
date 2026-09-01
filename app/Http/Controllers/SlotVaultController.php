<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SlotVaultController extends Controller
{
    public function index()
    {
        $vaultItems = [];

        return view('vault.index', compact('vaultItems'));
    }
}
