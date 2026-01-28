<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{

    use HasFactory;

    protected $table = 'rewards_catalog';

    protected $fillable = [
        'card_name',
        'points_amount',
        'card_description',
        'card_image',
        'archived',
    ];

    protected $casts = [
        'archived' => 'boolean',
        'points_amount' => 'integer',
    ];

    // Users who favorited this reward (inverse of User->favoriteRewards)
    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'user_favorite_rewards', 'card_id', 'user_id');
    }
}