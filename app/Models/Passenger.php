<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'no_last_name',
        'passport_number',
        'national_id',
        'gender',
        'date_of_birth',
        'passport_expiration_date',
        'country_id',
        'country_name',
        'nationality_id',
        'nationality_name',
        'passport_file_path',
        'personal_photo_path',
        'national_id_front_path',
        'national_id_back_path',
        'education_level',
        'experience_level',
        'institute_name',
        'custom_institute_name',
        'email',
        'password',
        'country_code',
        'phone_number',
        'preferable_contact',
        'temp_mail_id',
        'temp_mail_password',
        'temp_mail_token',
        'status',
        'otp_code',
        'taqamul_response',
        'error_message',
    ];

    protected $casts = [
        'no_last_name' => 'boolean',
        'date_of_birth' => 'date',
        'passport_expiration_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->no_last_name ? '' : $this->last_name));
    }

    /**
     * Get array of all emails currently used in the Slot Checker Pool
     */
    public static function getPoolEmails(): array
    {
        $json = Setting::get('slot_checker_pool_accounts', '[]');
        $accounts = json_decode($json, true) ?: [];
        $emails = [];
        foreach ($accounts as $acc) {
            if (!empty($acc['email'])) {
                $emails[] = strtolower(trim($acc['email']));
            }
        }
        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Scope query to exclude Slot Checker Pool accounts from real passengers
     */
    public function scopeExcludePool($query)
    {
        $poolEmails = static::getPoolEmails();
        $poolEmails = array_values(array_diff($poolEmails, ['zidanmahmud_bdd21955@renonx.tech']));
        if (!empty($poolEmails)) {
            $query->whereNotIn('email', $poolEmails);
        }
        return $query;
    }
}
