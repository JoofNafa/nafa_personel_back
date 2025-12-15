<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Shift;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\UserWeeklyDayOff;
use App\Models\Leave;

class VigileController extends Controller
{

    public function usersPendingAttendance(Request $request)
    {
        $type = $request->input('type', 'check_in');
        $today = now()->toDateString();
        $currentHour = now()->format('H');

        if (!in_array($type, ['check_in', 'check_out'])) {
            return response()->json([
                'success' => false,
                'message' => 'Type invalide. Utiliser "check_in" ou "check_out".'
            ], 400);
        }

        // -------------------------------
        // 1. Déterminer le shift ciblé
        // -------------------------------
        if ($currentHour >= 7 && $currentHour <= 14) {
            // Période du matin
            $targetShiftType = ($type === 'check_in') ? 'morning' : 'evening';
        } elseif ($currentHour >= 14 && $currentHour <= 20) {
            // Période de l'après-midi
            $targetShiftType = ($type === 'check_out') ? 'morning' : 'evening';
        } else {
            // En dehors des plages définies → retour vide
            return response()->json([
                'success' => true,
                'type' => $type,
                'data' => []
            ]);
        }

        // -------------------------------
        // 2. Charger seulement les users avec le shift nécessaire
        // -------------------------------
        $users = User::where('role', 'employee')
             ->with(['department', 'shift'])
            ->whereHas('shift', function ($q) use ($targetShiftType) {
                $q->where('type', $targetShiftType);
            })
            ->get();

        // -------------------------------
        // 3. Filtrer en fonction de la présence
        // -------------------------------
        $pendingUsers = $users->filter(function ($user) use ($type, $today) {

            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->first();

            // Permissions messing ou leave approuvé → exclure
            $hasMessingPermission = Permission::where('user_id', $user->id)
                ->where('type', 'messing')
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            $hasApprovedLeave = Leave::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            if ($hasMessingPermission || $hasApprovedLeave) {
                return false;
            }

            // Logique check_in / check_out
            if ($type === 'check_in') {
                return !$attendance || !$attendance->check_in;
            } else {
                return !$attendance || !$attendance->check_out;
            }
        });

        return response()->json([
            'success' => true,
            'type' => $type,
            'shift_filtered' => $targetShiftType,
            'data' => $pendingUsers->values()
        ]);
    }


    public function bulkCheckIn(Request $request)
{
    $request->validate([
        'user_ids' => 'required|array|min:1',
        'user_ids.*' => 'exists:users,id',

    ]);


    $todayStr = now()->toDateString();
    $results = [];

    foreach ($request->user_ids as $userId) {
        $user = User::find($userId);

        // Vérifie déjà pointé
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->first();
        if ($attendance && $attendance->check_in) {
            $results[$user->id] = [
                'success' => false,
                'message' => 'Déjà check-in aujourd\'hui.'
            ];
            continue;
        }

        // Vérifie congé
        $onLeave = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();
        if ($onLeave) {
            $results[$user->id] = [
                'success' => false,
                'message' => 'En congé aujourd\'hui.'
            ];
            continue;
        }

        // Vérifie jour de repos
        $hasDayOff = UserWeeklyDayOff::where('user_id', $user->id)
            ->whereDate('day_off_date', $todayStr)
            ->exists();
        if ($hasDayOff) {
            $results[$user->id] = [
                'success' => false,
                'message' => 'Jour de repos.'
            ];
            continue;
        }

        // Récupère shift
        $shift = $user->shift;
        if (!$shift) {
            $results[$user->id] = [
                'success' => false,
                'message' => 'Aucun shift assigné.'
            ];
            continue;
        }

        $dayOfWeek = strtolower(now()->format('l'));
        $now = Carbon::now();

        // Définition heures selon week-end ou jour ouvrable
        if (in_array($dayOfWeek, ['saturday', 'sunday'])) {
            if (!$user->works_weekend) {
                $results[$user->id] = [
                    'success' => false,
                    'message' => 'Ne travaille pas le week-end.'
                ];
                continue;
            }
            $shiftStart = $shift->type === 'morning' ? Carbon::parse('09:00:00') : Carbon::parse('16:00:00');
            $shiftEnd   = $shift->type === 'morning' ? Carbon::parse('14:00:00') : Carbon::parse('21:00:00');
        } else {
            $shiftStart = Carbon::parse($shift->start_time);
            $shiftEnd   = Carbon::parse($shift->end_time);
        }

        // Vérifie permission 'late'
        $hasLatePermission = Permission::where('user_id', $user->id)
            ->where('type', 'late')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        // Calcul retard
        $minutesLate = 0;
        if (!$hasLatePermission) {
            $minutesLate = $now->lessThanOrEqualTo($shiftStart->copy()->addMinutes(15))
                ? 0
                : $shiftStart->diffInMinutes($now) - 15;
        }

        // Création ou mise à jour
        $attendance = Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => $todayStr],
            [
                'check_in' => $now->format('H:i:s'),
                'minutes_late' => $minutesLate,
                'status' => 'present',
                'scan_method' => $request->scan_method ?? 'manual',
            ]
        );

        // Total retard du mois
        $attendance->total_minutes_late = Attendance::where('user_id', $user->id)
            ->whereMonth('date', now()->month)
            ->sum('minutes_late');
        $attendance->save();

        $results[$user->id] = [
            'success' => true,
            'message' => 'Check-in effectué.',
            'minutes_late' => $minutesLate,
            'shift_type' => $shift->type,
            'shift_start' => $shiftStart->format('H:i'),
            'shift_end' => $shiftEnd->format('H:i'),
        ];
    }

    return response()->json([
        'success' => true,
        'date' => $todayStr,
        'results' => $results,
    ]);
}

public function bulkCheckOut(Request $request)
{
    $request->validate([
        'user_ids' => 'required|array|min:1',
        'user_ids.*' => 'exists:users,id',

    ]);



    $todayStr = now()->toDateString();
    $results = [];

    foreach ($request->user_ids as $userId) {
        $user = User::find($userId);

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->first();

        // Vérifie check-in existant
        if (!$attendance || !$attendance->check_in) {
            $results[$user->id] = [
                'success' => false,
                'message' => 'Aucun check-in trouvé pour aujourd\'hui.'
            ];
            continue;
        }

        // Vérifie check-out déjà fait
        if ($attendance->check_out) {
            $results[$user->id] = [
                'success' => false,
                'message' => 'Déjà check-out effectué.'
            ];
            continue;
        }

        // Vérifie shift
        $shift = $user->shift;
        if (!$shift) {
            $results[$user->id] = [
                'success' => false,
                'message' => 'Aucun shift assigné.'
            ];
            continue;
        }

        $dayOfWeek = strtolower(now()->format('l'));
        $now = Carbon::now();

        // Définition heures de fin selon week-end ou jour ouvrable
        if (in_array($dayOfWeek, ['saturday', 'sunday']) && $user->works_weekend) {
            $shiftEnd = $shift->type === 'morning' ? Carbon::parse('14:00:00') : Carbon::parse('21:00:00');
        } else {
            $shiftEnd = Carbon::parse($shift->end_time);
        }

        $checkInTime = Carbon::parse($attendance->check_in);
        $workedMinutes = $checkInTime->diffInMinutes($now);

        $earlyLeave = $now->lessThan($shiftEnd);

        // Vérifie permission early_leave
        $earlyLeavePermission = Permission::where('user_id', $user->id)
            ->where('type', 'early_leave')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        if ($earlyLeavePermission) $earlyLeave = false;

        $attendance->update([
            'check_out' => $now->format('H:i:s'),
            'early_leave' => $earlyLeave ? 1 : 0,
            // total_minutes_late reste inchangé pour check-out
        ]);

        $results[$user->id] = [
            'success' => true,
            'message' => 'Check-out effectué.',
            'worked_minutes' => $workedMinutes,
            'early_leave' => $earlyLeave,
            'shift_type' => $shift->type,
            'shift_start' => $shift->start_time,
            'shift_end' => $shiftEnd->format('H:i'),
        ];
    }

    return response()->json([
        'success' => true,
        'date' => $todayStr,
        'results' => $results,
    ]);
}



}
