<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\UserWeeklyDayOff;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Carbon\Carbon;



class UserStatsController extends Controller
{

    public function getAllUsersMonthlyStats(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m')); // "2025-02"

        [$year, $monthNumber] = explode('-', $month);
        $startDate = Carbon::create($year, $monthNumber, 1)->startOfMonth();
        $endDate   = Carbon::create($year, $monthNumber, 1)->endOfMonth();

        // Charger tous les utilisateurs + département
        $users = User::with('department')->get();

        // Charger toutes les présences du mois
        $attendances = Attendance::whereBetween('date', [$startDate, $endDate])->get();

        $result = [];

        foreach ($users as $user) {

            $userAttendances = $attendances->where('user_id', $user->id);

            // Nombre de jours présents
            $presentCount = $userAttendances->where('status', 'present')->count();

            // Nombre de jours en retard (minutes_late != 0)
            $lateCount = $userAttendances->where('minutes_late', '>', 0)->count();

            // Nombre de jours absents
            $absentCount = $userAttendances->where('status', 'absent')->count();

            // Nombre de jours avec permission
            $permissionCount = $userAttendances->where('status', 'permission')->count();

            $result[] = [
                'user_id'          => $user->id,
                'name'             => $user->first_name . ' ' . $user->last_name,
                'role'             => $user->role,
                'department'       => $user->department?->name,
                'present_days'     => $presentCount,
                'late_days'        => $lateCount,
                'absent_days'      => $absentCount,
                'permission_days'  => $permissionCount,
            ];
        }

        return response()->json([
            'month' => $month,
            'data'  => $result
        ]);
    }



public function getUserMonthlyStats(Request $request)
{
    // On récupère l'utilisateur authentifié
    $user = auth()->user();

    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    $month = $request->input('month', now()->format('Y-m'));
    // Format attendu : "2025-02"

    [$year, $monthNumber] = explode('-', $month);

    $startDate = Carbon::create($year, $monthNumber, 1)->startOfMonth();
    $endDate   = Carbon::create($year, $monthNumber, 1)->endOfMonth();

    // Présences du mois
    $attendances = Attendance::where('user_id', $user->id)
        ->whereBetween('date', [$startDate, $endDate])
        ->get();

    // 1️⃣ Total heures de présence
    $totalPresenceMinutes = 0;

    foreach ($attendances as $attendance) {
        if ($attendance->check_in && $attendance->check_out) {
            $totalPresenceMinutes +=
                Carbon::parse($attendance->check_in)
                    ->diffInMinutes(Carbon::parse($attendance->check_out));
        }
    }

    $presenceHours = round($totalPresenceMinutes / 60, 2);

    // 2️⃣ Total heures de retard
    $totalLateHours = round($attendances->sum('minutes_late') / 60, 2);

    // 3️⃣ Absences
    $absences = $attendances->whereNull('check_in')->count();

    // 4️⃣ Permissions du mois
    $permissions = Permission::where('user_id', $user->id)
        ->where(function($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()]);
        })
        ->count();

    return response()->json([
        'user' => [
            'id'         => $user->id,
            'name'       => $user->first_name . ' ' . $user->last_name,
            'department' => $user->department ? $user->department->name : null,
        ],
        'stats' => [
            'month'             => $month,
            'presence_hours'    => $presenceHours,
            'late_hours'        => $totalLateHours,
            'absences'          => $absences,
            'permissions_count' => $permissions,
        ]
    ]);
}


public function getMonthlyAttendanceSummary(Request $request)
{
    $month = $request->input('month', now()->format('Y-m')); // "2025-02"

    [$year, $monthNumber] = explode('-', $month);
    $startDate = Carbon::create($year, $monthNumber, 1)->startOfMonth();
    $endDate   = Carbon::create($year, $monthNumber, 1)->endOfMonth();

    // 1️⃣ Charger tous les utilisateurs + département + shift
    $users = User::with(['department', 'shift.workSchedules'])->get();

    // 2️⃣ Charger toutes les présences du mois
    $attendances = Attendance::whereBetween('date', [$startDate, $endDate])->get();

    $result = [];

    foreach ($users as $user) {
        $userAttendances = $attendances->where('user_id', $user->id);

        $presentMinutes = 0;
        $absentMinutes  = 0;
        $lateMinutes    = 0;

        // Parcours de tous les jours du mois
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $day) {
            $dateStr = $day->toDateString();
            $attendance = $userAttendances->firstWhere('date', $dateStr);

            // Récupérer le schedule du jour
            $dayName = strtolower($day->format('l')); // monday, tuesday...
            $schedule = $user->shift ? $user->shift->workSchedules->firstWhere('day', $dayName) : null;

            if (!$schedule) {
                // Pas de shift défini pour ce jour → considérer comme non travaillé
                continue;
            }

            $shiftStart = Carbon::parse($schedule->start_time);
            $shiftEnd   = Carbon::parse($schedule->end_time);
            $shiftMinutes = $shiftStart->diffInMinutes($shiftEnd);

            if ($attendance && $attendance->check_in && $attendance->check_out) {
                // Heures réellement travaillées
                $workedMinutes = Carbon::parse($attendance->check_in)
                    ->diffInMinutes(Carbon::parse($attendance->check_out));
                $presentMinutes += $workedMinutes;

                // Retard
                $lateMinutes += $attendance->minutes_late;

                // Absence partielle
                $absentMinutes += max(0, $shiftMinutes - $workedMinutes);
            } else {
                // Pas de check_in → journée complète d’absence
                $absentMinutes += $shiftMinutes;
            }
        }

        $result[] = [
            'user_id'       => $user->id,
            'name'          => $user->first_name . ' ' . $user->last_name,
            'role'          => $user->role,
            'department'    => $user->department ? $user->department->name : null,
            'present_hours' => round($presentMinutes / 60, 2),
            'absent_hours'  => round($absentMinutes / 60, 2),
            'late_hours'    => round($lateMinutes / 60, 2),
        ];
    }

    return response()->json([
        'month' => $month,
        'data'  => $result
    ]);
}


}
