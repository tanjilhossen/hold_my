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
        'seat_history',
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
        'seat_history' => 'array',
    ];

    public function recordSeatHistory(?string $seatId, int $renewCount = 0, string $type = 'Renewed'): void
    {
        if (empty($seatId)) return;

        $history = $this->seat_history ?: [];
        
        foreach ($history as $h) {
            if (($h['seat_id'] ?? '') === (string)$seatId && ($h['renew_count'] ?? -1) === $renewCount) {
                return;
            }
        }

        $history[] = [
            'renew_count' => $renewCount,
            'seat_id' => (string)$seatId,
            'timestamp' => now()->format('Y-m-d h:i:s A'),
            'type' => $type,
        ];

        $this->update(['seat_history' => $history]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeActiveOrPending($query)
    {
        return $query->whereIn('status', ['active', 'pending_locking']);
    }
}
