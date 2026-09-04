<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'mother_hash',
        'center_name',
        'city',
        'category_id',
        'category_name',
        'exam_date',
        'temp_seat_id',
        'held_with_email',
        'status',
        'renew_count',
        'target_duration_minutes',
        'expires_at',
        'auto_renew_until',
        'last_renewed_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'expires_at' => 'datetime',
        'auto_renew_until' => 'datetime',
        'last_renewed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeActiveOrPending($query)
    {
        return $query->whereIn('status', ['active', 'pending_locking']);
    }
}
