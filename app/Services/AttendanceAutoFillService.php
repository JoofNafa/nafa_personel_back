<?php

namespace App\Services;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Leave;
use App\Models\UserWeeklyDayOff;

class AttendanceAutoFillService
{
    /**
     * Remplit automatiquement les présences pour tous les utilisateurs pour une date donnée
     * en tenant compte du shift
     *
     * @param string|null $date format Y-m-d, si null = aujourd'hui
     * @param string|null $shiftName nom du shift à traiter ('morning', 'evening', etc.)
     */
    public function fillAttendancesForDate(?string $date = null, ?string $shiftName = null)
    {
        $date = $date ?? now()->toDateString();

        // Récupère tous les utilisateurs du shift demandé
        $users = User::with('shift')
            ->when($shiftName, function($q) use ($shiftName) {
                $q->whereHas('shift', fn($q2) => $q2->where('name', $shiftName));
            })
            ->get();

        foreach ($users as $user) {

            // Si une attendance existe déjà pour cet utilisateur à cette date, on skip
            $exists = Attendance::where('user_id', $user->id)
                ->where('date', $date)
                ->exists();

            if ($exists) continue;

            // Par défaut, absent
            $status = 'absent';

            // Vérifie si le user a une permission active
            $permission = Permission::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->first();

            if ($permission) {
                $status = 'permission';
            }

            // Vérifie si le user est en congé
            $leave = Leave::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->first();

            if ($leave) {
                $status = 'on_leave';
            }

            // Vérifie le day off hebdomadaire
            $dayOff = UserWeeklyDayOff::where('user_id', $user->id)
                ->where('day_off_date', $date)
                ->first();

            if ($dayOff) {
                $status = 'day_off';
            }

            // Crée l'enregistrement Attendance
            Attendance::create([
                'user_id' => $user->id,
                'date' => $date,
                'check_in' => null,
                'check_out' => null,
                'minutes_late' => 0,
                'status' => $status,
            ]);
        }
    }
}
