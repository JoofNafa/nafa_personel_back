<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'user_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'reason',
        'status',
        'approved_by',
    ];

    /**
     * L'employé qui a demandé la permission
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * L'utilisateur (manager ou RH) qui a approuvé ou rejeté
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Vérifie si la permission est pour une journée complète
     */
    public function isFullDay(): bool
    {
        return is_null($this->start_time) && is_null($this->end_time);
    }

    /**
     * Vérifie si la permission est en cours à une date donnée
     */
    public function isActiveOnDate(string $date): bool
    {
        return $date >= $this->start_date && $date <= $this->end_date;
    }

    /**
     * Approuve la permission
     */
    public function approve(int $approverId): void
    {
        $this->status = 'approved';
        $this->approved_by = $approverId;
        $this->save();
    }

    /**
     * Rejette la permission
     */
    public function reject(int $approverId): void
    {
        $this->status = 'rejected';
        $this->approved_by = $approverId;
        $this->save();
    }
}
