<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'user_id';
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'username',
        'date_of_birth',
        'role',
        'password',
        'desk_id',
        'sitting_position',
        'standing_position',
        'total_points',
        'daily_points',
        'last_points_date',
        'last_daily_reset',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'last_points_date' => 'date',
            'last_daily_reset' => 'datetime',
        ];
    }

    // user-desk relationship
    public function desk()
    {
        return $this->belongsTo(Desk::class, 'desk_id', 'id');
    }

    /**
     * Relationship with HealthCycles
     */
    public function healthCycles()
    {
        return $this->hasMany(HealthCycle::class, 'user_id', 'user_id');
    }

    // Relationship for saved rewards
    public function favoriteRewards()
    {
        return $this->belongsToMany(Reward::class, 'user_favorite_rewards', 'user_id', 'card_id');
    }

    // Relationship for redeemed rewards
  public function redeemedRewards()
  {
      return $this->belongsToMany(Reward::class, 'user_rewards', 'user_id', 'card_id')
                  ->withPivot('redeemed_at', 'card_name_snapshot', 'points_amount_snapshot', 'card_description_snapshot')
                  ->withTimestamps();
  }

    /**
     * Reset daily points if it's a new day
     * @param string|null $userDate - Optional user's timezone date (Y-m-d format). If null, uses server date.
     */
    public function resetDailyPointsIfNeeded($userDate = null)
    {
        // Use provided user date or fallback to server date
        $today = $userDate ? \Carbon\Carbon::parse($userDate)->toDateString() : now()->toDateString();

        $lastResetDate = $this->last_points_date ?
            \Carbon\Carbon::parse($this->last_points_date)->toDateString() : null;

        if ($lastResetDate !== $today) {
            // New day detected - reset daily points to 0
            $this->daily_points = 0;
            $this->last_points_date = $today;
            $this->save();

            \Log::info("Daily points reset for user {$this->getKey()}: new day {$today}");
        }
    }

    /**
     * Check if user can earn more points today
     * @param string|null $userDate - Optional user's timezone date (Y-m-d format)
     */
    public function canEarnPoints($userDate = null)
    {
        $this->resetDailyPointsIfNeeded($userDate);
        return $this->daily_points < 160;
    }

    /**
     * Add points to user (respecting daily limit)
     * @param int $points - Points to add
     * @param string|null $userDate - Optional user's timezone date (Y-m-d format)
     * @return int - Actual points added (may be less if daily limit reached)
     */
    public function addPoints($points, $userDate = null)
    {
        $this->resetDailyPointsIfNeeded($userDate);

        if ($this->daily_points >= 160) {
            return 0; // Already at daily limit
        }

        $pointsToAdd = min($points, 160 - $this->daily_points);

        $this->daily_points += $pointsToAdd;
        $this->total_points += $pointsToAdd;
        $this->save();

        return $pointsToAdd;
    }
}
