<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'check_in',
        'check_out',
        'minutes_late',
        'total_minutes_late',
        'status',
        'early_leave',
        'scan_method',
    ];

    /**
     * L'utilisateur lié à cette présence
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Vérifie si l'utilisateur est en retard
     */
    public function isLate(): bool
    {
        return $this->minutes_late > 0;
    }

    /**
     * Calcule le retard basé sur l'heure d'arrivée et le shift
     *
     * @param string $shiftStartTime 'H:i:s'
     * @return int minutes de retard
     */
    public function calculateMinutesLate(string $shiftStartTime): int
    {
        if (!$this->check_in) return 0;

        $diff = (strtotime($this->check_in) - strtotime($shiftStartTime)) / 60;
        $minutesLate = $diff > 0 ? intval($diff) : 0;

        $this->minutes_late = $minutesLate;
        return $minutesLate;
    }

    /**
     * Marque le statut de la présence selon day off ou congé
     *
     * @param bool $isDayOff
     * @param bool $isOnLeave
     * @return void
     */
    public function updateStatus(bool $isDayOff = false, bool $isOnLeave = false): void
    {
        if ($isDayOff) {
            $this->status = 'day_off';
        } elseif ($isOnLeave) {
            $this->status = 'on_leave';
        } else {
            $this->status = 'present';
        }
    }
}
