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
        'next_candidate_email',
        'next_candidate_token',
        'prewarm_status',
        'prewarmed_at',
        'status',
        'renew_count',
        'seat_history',
        'last_failure_reason',
        'last_failure_at',
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
        'prewarmed_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'seat_history' => 'array',
    ];

    public function recordSeatHistory(?string $seatId, int $renewCount = 0, string $type = 'Renewed', array $extra = []): void
    {
        $history = $this->seat_history ?: [];
        
        $entry = array_merge([
            'renew_count' => $renewCount,
            'seat_id' => (string)($seatId ?: 'N/A'),
            'timestamp' => now()->format('Y-m-d h:i:s A'),
            'type' => $type,
            'email' => $this->held_with_email,
        ], $extra);

        $history[] = $entry;

        $this->update(['seat_history' => $history]);
    }

    public function recordFailure(string $reason, ?string $candidateEmail = null, ?int $httpCode = null): void
    {
        $email = $candidateEmail ?: $this->held_with_email;
        $formattedReason = ($httpCode ? "HTTP {$httpCode}: " : "") . $reason;

        $this->update([
            'last_failure_reason' => $formattedReason,
            'last_failure_at' => now(),
        ]);

        $this->recordSeatHistory($this->temp_seat_id, (int)$this->renew_count, 'Rebook Failed', [
            'email' => $email,
            'error' => $reason,
            'http_status' => $httpCode,
        ]);
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
