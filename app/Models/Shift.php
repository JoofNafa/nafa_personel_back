<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'type',
        'start_time',
        'end_time',
        'is_active'
    ];

    /**
     * Les utilisateurs qui sont affectés à ce shift
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }



    /**
     * Vérifie si une heure donnée est en retard par rapport au début du shift
     *
     * @param string $time 'H:i:s'
     * @return int minutes de retard
     */
    public function calculateLateMinutes(string $time): int
    {
        $shiftStart = strtotime($this->start_time);
        $checkTime  = strtotime($time);
        $diff = ($checkTime - $shiftStart) / 60;

        return $diff > 0 ? intval($diff) : 0;
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }
}
