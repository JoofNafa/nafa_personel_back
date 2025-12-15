<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approved_by',
    ];

    /**
     * L'employé qui a fait la demande de congé
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Le manager ou RH qui a validé ou rejeté le congé
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Vérifie si le congé est en cours à une date donnée
     */
    public function isActiveOnDate(string $date): bool
    {
        return $date >= $this->start_date && $date <= $this->end_date;
    }

    /**
     * Calcule le nombre de jours de congé
     */
    public function totalDays(): int
    {
        return (strtotime($this->end_date) - strtotime($this->start_date)) / 86400 + 1;
    }

    /**
     * Approuve le congé et peut mettre à jour le solde de congés de l'utilisateur
     */
    public function approve(int $approverId): void
    {
        $this->status = 'approved';
        $this->approved_by = $approverId;
        $this->save();

        // Déduire du solde de congés de l'utilisateur
        $this->user->decrement('leave_balance', $this->totalDays());
    }

    /**
     * Rejette le congé
     */
    public function reject(int $approverId): void
    {
        $this->status = 'rejected';
        $this->approved_by = $approverId;
        $this->save();
    }
}
