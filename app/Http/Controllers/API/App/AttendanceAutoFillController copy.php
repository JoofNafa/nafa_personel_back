<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Leave;
use App\Models\UserWeeklyDayOff;

class AttendanceAutoFillController extends Controller
{
    /**
     * Remplit automatiquement les présences pour tous les utilisateurs.
     */
    public function fillAllAttendances(Request $request)
{
    $request->validate([
        'shift_type' => 'required|in:morning,evening',
        'date' => 'nullable|date',
    ]);

    $shiftType = $request->shift_type;
    $date = $request->date ?? ($shiftType === 'evening' ? now()->subDay()->toDateString() : now()->toDateString());
    $dayOfWeek = strtolower(\Carbon\::parse($date)->format('l'));

    // Récupération des utilisateurs filtrés par shift et week-end
    $users = User::with('shift')->get()->filter(function ($user) use ($shiftType, $dayOfWeek, $date) {
        if (!$user->shift) return false;
        if ($user->shift->type !== $shiftType) return false;

        // Si c'est le week-end, ne garder que ceux qui travaillent les week-ends
        if (in_array($dayOfWeek, ['saturday','sunday']) && !$user->works_weekend) {
            return false;
        }

        // 🔹 Vérifie s'il est en permission de type "missing"
        $hasMissingPermission = Permission::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('type', 'missing')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
        if ($hasMissingPermission) return false;

        // 🔹 Vérifie s'il est en congé
        $onLeave = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
        if ($onLeave) return false;

        return true;
    });

    foreach ($users as $user) {
        // Skip si déjà enregistré
        if (Attendance::where('user_id', $user->id)->where('date', $date)->exists()) {
            continue;
        }

        // Vérifie permission/jour de repos/congé (déjà filtré ci-dessus)
        $status = $this->determineAttendanceStatus($user, $date);

        // Définition des heures : week-end = standard, jours ouvrables = selon shift
        if (in_array($dayOfWeek, ['saturday', 'sunday'])) {
            $shiftStart = $shiftType === 'morning' ? '09:00:00' : '16:00:00';
            $shiftEnd = $shiftType === 'morning' ? '14:00:00' : '21:00:00';
        } else {
            $shiftStart = $user->shift->start_time;
            $shiftEnd = $user->shift->end_time;
        }

        Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'check_in' => null,
            'check_out' => null,
            'minutes_late' => 0,
            'status' => $status,
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => "Présences remplies pour le shift $shiftType pour la date: $date"
    ]);
}





    /**
     * Fonction privée qui gère la création des présences pour un ensemble d'utilisateurs.
     */
    private function fillAttendancesForUsers($users, string $date)
    {
        foreach ($users as $user) {
            // Skip si déjà enregistré
            if (Attendance::where('user_id', $user->id)->where('date', $date)->exists()) {
                continue;
            }

            // Déterminer le statut automatiquement
            $status = $this->determineAttendanceStatus($user, $date);

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

    /**
     * Détermine le statut de présence pour un utilisateur à une date donnée.
     */
    private function determineAttendanceStatus($user, string $date, ?string $time = null): string
    {
        // Convertit la date et l'heure en  pour comparer facilement
        $current = $time ? \Carbon\Carbon::parse("$date $time") : \Carbon\Carbon::parse($date);

        // Vérifie si une permission approuvée est active
        $permissions = Permission::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        foreach ($permissions as $permission) {
            $start = $permission->start_time ? \Carbon\Carbon::parse($permission->start_date . ' ' . $permission->start_time) : null;
            $end = $permission->end_time ? \Carbon\Carbon::parse($permission->end_date . ' ' . $permission->end_time) : null;

            // Si pas d'heure précise, toute la journée est couverte
            if (!$start && !$end) {
                return 'permission';
            }

            // Cas où seulement start_time et end_time sont définis
            if ($start && $end && $current->between($start, $end)) {
                return 'permission';
            }

            // Cas où seule start_time est définie (permission à partir d'une heure)
            if ($start && !$end && $current->greaterThanOrEqualTo($start)) {
                return 'permission';
            }

            // Cas où seule end_time est définie (permission jusqu'à une heure)
            if (!$start && $end && $current->lessThanOrEqualTo($end)) {
                return 'permission';
            }
        }

        // Vérifie si un congé approuvé est actif
        $hasApprovedLeave = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($hasApprovedLeave) {
            return 'on_leave';
        }

        // Vérifie si c'est un jour de repos défini
        $hasDayOff = UserWeeklyDayOff::where('user_id', $user->id)
            ->where('day_off_date', $date)
            ->exists();

        if ($hasDayOff) {
            return 'day_off';
        }

        // Si aucune condition n'est remplie → absent
        return 'absent';
    }

}
