<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWeeklyDayOff extends Model
{
    use HasFactory;

    protected $table = 'user_weekly_day_offs';

    protected $fillable = [
        'user_id',
        'day_off_date',
        'created_by',
    ];

    /**
     * L'utilisateur concerné par le day off
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Le RH qui a défini le day off
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Vérifie si le day off est pour une date passée ou future
     */
    public function isFuture(): bool
    {
        return $this->day_off_date >= now()->toDateString();
    }

    /**
     * Vérifie si le day off correspond à une date donnée
     */
    public function isForDate(string $date): bool
    {
        return $this->day_off_date === $date;
    }
}
