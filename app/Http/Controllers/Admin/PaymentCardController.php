<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentCardController extends Controller
{
    /**
     * Display a listing of payment cards
     */
    public function index()
    {
        $cards = PaymentCard::latest()->get();
        $defaultCard = PaymentCard::default()->first() ?? $cards->first();

        return view('admin.cards.index', compact('cards', 'defaultCard'));
    }

    /**
     * Store a newly created card in storage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'card_holder_name' => 'required|string|max:150',
            'card_number' => 'required|string|max:30',
            'expiry_month' => 'required|string|size:2',
            'expiry_year' => 'required|string|min:2|max:4',
            'cvv' => 'required|string|min:3|max:4',
            'card_type' => 'nullable|string|max:30',
            'is_default' => 'nullable|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        // Clean up card number (remove spaces)
        $cleanNumber = preg_replace('/\D/', '', $validated['card_number']);
        $validated['card_number'] = $cleanNumber;

        // Clean up year (ensure 4 digits e.g. 28 -> 2028)
        if (strlen($validated['expiry_year']) === 2) {
            $validated['expiry_year'] = '20' . $validated['expiry_year'];
        }

        // Auto-detect card type if not selected
        if (empty($validated['card_type'])) {
            $firstDigit = substr($cleanNumber, 0, 1);
            if ($firstDigit === '4') {
                $validated['card_type'] = 'Visa';
            } elseif ($firstDigit === '5' || $firstDigit === '2') {
                $validated['card_type'] = 'Mastercard';
            } else {
                $validated['card_type'] = 'Visa';
            }
        }

        $isDefault = $request->boolean('is_default') || PaymentCard::count() === 0;
        $validated['is_default'] = $isDefault;
        $validated['user_id'] = Auth::id();

        if ($isDefault) {
            PaymentCard::query()->update(['is_default' => false]);
        }

        PaymentCard::create($validated);

        return redirect()->route('admin.cards.index')->with('success', 'Payment card added successfully!');
    }

    /**
     * Update the specified card
     */
    public function update(Request $request, $id)
    {
        $card = PaymentCard::findOrFail($id);

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'card_holder_name' => 'required|string|max:150',
            'card_number' => 'nullable|string|max:30',
            'expiry_month' => 'required|string|size:2',
            'expiry_year' => 'required|string|min:2|max:4',
            'cvv' => 'nullable|string|min:3|max:4',
            'card_type' => 'nullable|string|max:30',
            'is_default' => 'nullable|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        if (!empty($validated['card_number'])) {
            $validated['card_number'] = preg_replace('/\D/', '', $validated['card_number']);
        } else {
            unset($validated['card_number']);
        }

        if (empty($validated['cvv'])) {
            unset($validated['cvv']);
        }

        if (isset($validated['expiry_year']) && strlen($validated['expiry_year']) === 2) {
            $validated['expiry_year'] = '20' . $validated['expiry_year'];
        }

        $isDefault = $request->boolean('is_default');
        if ($isDefault) {
            PaymentCard::where('id', '!=', $card->id)->update(['is_default' => false]);
            $validated['is_default'] = true;
        }

        $card->update($validated);

        return redirect()->route('admin.cards.index')->with('success', 'Card details updated successfully!');
    }

    /**
     * Set a card as primary default
     */
    public function setDefault($id)
    {
        PaymentCard::query()->update(['is_default' => false]);
        $card = PaymentCard::findOrFail($id);
        $card->update(['is_default' => true, 'is_active' => true]);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$card->bank_name} ({$card->masked_number}) is now set as default payment card!",
            ]);
        }

        return redirect()->route('admin.cards.index')->with('success', "{$card->bank_name} is now your default card!");
    }

    /**
     * Remove the specified card
     */
    public function destroy($id)
    {
        $card = PaymentCard::findOrFail($id);
        $card->delete();

        // If default was deleted, assign to first available
        if ($card->is_default) {
            $next = PaymentCard::first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return redirect()->route('admin.cards.index')->with('success', 'Card removed successfully.');
    }
}
