<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sitting_minutes',
        'standing_minutes',
        'cycle_number',
        'health_score',
        'points_earned',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
