<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotHash extends Model
{
    use HasFactory;

    protected $fillable = [
        'mother_hash',
        'category_id',
        'category_name',
        'category_name_ar',
        'city',
        'exam_date',
        'center_name',
        'center_id',
        'center_address',
        'phone',
        'email',
        'start_time',
        'available_seats',
        'location_link',
        'discovered_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'discovered_at' => 'datetime',
    ];
}
