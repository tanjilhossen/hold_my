<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'card_holder_name',
        'card_number',
        'expiry_month',
        'expiry_year',
        'cvv',
        'card_type',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get masked card number e.g. •••• •••• •••• 1234
     */
    public function getMaskedNumberAttribute(): string
    {
        $clean = preg_replace('/\D/', '', $this->card_number);
        $last4 = substr($clean, -4);
        return '•••• •••• •••• ' . ($last4 ?: '0000');
    }

    /**
     * Format expiry as MM/YY
     */
    public function getFormattedExpiryAttribute(): string
    {
        $m = str_pad($this->expiry_month, 2, '0', STR_PAD_LEFT);
        $y = substr($this->expiry_year, -2);
        return "{$m}/{$y}";
    }

    /**
     * Scope for default card
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true)->where('is_active', true);
    }
}
