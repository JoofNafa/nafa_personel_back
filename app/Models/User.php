<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;



class User extends Authenticatable implements JWTSubject

{
    use HasFactory, Notifiable;

    // Champs autorisés pour la création/mise à jour
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'pin',
        'department_id',
        'role',
        'shift_id',
        'leave_balance',
        'must_change_password',
        'must_change_pin',
        'works_weekend',
    ];

    // Champs cachés lors de la sérialisation
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Types de champs
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Le département de l'utilisateur
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Les présences de l'utilisateur
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Les congés de l'utilisateur
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    /**
     * Les permissions de l'utilisateur
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Les jours off hebdomadaires définis pour l'utilisateur
     */
    public function weeklyDayOffs(): HasMany
    {
        return $this->hasMany(UserWeeklyDayOff::class);
    }

    /**
     * Vérifie si l'utilisateur est un manager
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Vérifie si l'utilisateur est un RH
     */
    public function isRH(): bool
    {
        return $this->role === 'rh';
    }

    /**
     * Vérifie si l'utilisateur est un admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifie si l'utilisateur est un employé normal
     */
    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function shift()
{
    return $this->belongsTo(Shift::class);
}

public function todaySchedule()
    {
        if (!$this->shift) {
            return null;
        }

        $day = strtolower(now()->format('l')); // 'monday', 'tuesday', ...

        // si c'est weekend mais l'utilisateur ne travaille pas les weekend -> null
        if (in_array($day, ['saturday','sunday']) && !$this->works_weekend) {
            return null;
        }

        return $this->shift->workSchedules()->where('day', $day)->first();
    }

}
