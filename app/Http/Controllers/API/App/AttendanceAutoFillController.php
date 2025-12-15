<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Leave;
use App\Models\UserWeeklyDayOff;
use Carbon\Carbon;

class AttendanceAutoFillController extends Controller
{
    /**
     * Remplit automatiquement les présences pour tous les utilisateurs du shift donné.
     */
    public function fillAllAttendances(Request $request)
    {
        $request->validate([
            'shift_type' => 'required|in:morning,evening',
            'date' => 'nullable|date',
        ]);

        $shiftType = $request->shift_type;
        $date = $request->date ?? ($shiftType === 'evening'
            ? now()->subDay()->toDateString()
            : now()->toDateString());

        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        // Récupérer uniquement les utilisateurs du bon shift
        $users = User::with(['shift', 'permissions', 'leaves'])->get()->filter(function ($user) use ($shiftType, $dayOfWeek, $date) {
            if (!$user->shift || $user->shift->type !== $shiftType) {
                return false;
            }

            // Week-end : exclure ceux qui ne travaillent pas le week-end
            if (in_array($dayOfWeek, ['saturday', 'sunday']) && !$user->works_weekend) {
                return false;
            }

            // Exclure ceux qui ont une permission "missing" couvrant toute la journée
            $hasMissingPermission = Permission::where('user_id', $user->id)
                ->where('status', 'approved')
                ->where('type', 'missing')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();

            if ($hasMissingPermission) {
                return false;
            }

            // Exclure ceux qui sont en congé approuvé
            $onLeave = Leave::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();

            if ($onLeave) {
                return false;
            }

            return true;
        });

        $createdCount = 0;

        foreach ($users as $user) {
            // Ne pas recréer si déjà existant
            if (Attendance::where('user_id', $user->id)->where('date', $date)->exists()) {
                continue;
            }

            $status = $this->determineAttendanceStatus($user, $date);
            $hasLatePermission = $this->hasLatePermission($user, $date);

            // Définir les horaires selon le jour (week-end ou semaine)
            if (in_array($dayOfWeek, ['saturday', 'sunday'])) {
                $shiftStart = $shiftType === 'morning' ? '09:00:00' : '16:00:00';
                $shiftEnd   = $shiftType === 'morning' ? '14:00:00' : '21:00:00';
            } else {
                $shiftStart = $user->shift->start_time ?? '08:00:00';
                $shiftEnd   = $user->shift->end_time ?? '17:00:00';
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
                'has_late_permission' => $hasLatePermission, // Utile pour l'affichage
            ]);

            $createdCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "$createdCount présences créées automatiquement pour le shift $shiftType du " . Carbon::parse($date)->format('d/m/Y'),
            'created_count' => $createdCount,
            'date' => $date,
        ]);
    }

    /**
     * Détermine le statut de présence (utilisé lors du remplissage auto)
     */
    private function determineAttendanceStatus($user, string $date): string
    {
        $carbonDate = Carbon::parse($date);

        // 1. Congé approuvé → on_leave
        $onLeave = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($onLeave) {
            return 'on_leave';
        }

        // 2. Jour de repos personnalisé → day_off
        $hasDayOff = UserWeeklyDayOff::where('user_id', $user->id)
            ->where('day_off_date', $date)
            ->exists();

        if ($hasDayOff) {
            return 'day_off';
        }

        // 3. Permission "missing" → on ne crée même pas la ligne (déjà filtrée avant)
        //    Mais on garde la vérification par sécurité
        $hasMissing = Permission::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('type', 'missing')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($hasMissing) {
            return 'permission'; // Ne devrait jamais arriver grâce au filtre
        }

        // 4. Permission "late" → on ne change RIEN au statut → reste absent tant qu'il n'a pas pointé
        //    (on ne retourne pas 'permission')

        // 5. Autres permissions (early_leave, medical, etc.) → selon besoin futur

        // Par défaut : personne n'a pointé → absent
        return 'absent';
    }

    /**
     * Vérifie s'il existe une permission "late" approuvée couvrant cette date
     */
    private function hasLatePermission($user, string $date): bool
    {
        return Permission::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('type', 'late')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}