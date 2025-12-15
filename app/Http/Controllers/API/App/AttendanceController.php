<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Models\UserWeeklyDayOff;
use App\Models\Permission;
use App\Models\WorkSchedule;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * 📜 Liste des présences
     */
    public function index(Request $request)
    {
        $query = Attendance::with('user')->orderBy('date', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        return response()->json([
            'success' => true,
            'attendances' => $query->paginate(10),
        ]);
    }

    /**
     * 👤 Présences de l'utilisateur connecté
     */
    public function myAttendances()
{
    $user = auth()->user();

    $startOfMonth = now()->startOfMonth();
    $endOfMonth = now()->endOfMonth();

    $attendances = $user->attendances()
        ->with('user.shift') // Charger le shift via l'utilisateur
        ->whereBetween('date', [$startOfMonth, $endOfMonth])
        ->orderBy('date', 'desc')
        ->get()
        ->map(function ($att) {

            $shift = $att->user->shift; // Correction ici

            $isLate = false;
            $leftEarly = false;

            $hasLatePermission = Permission::where('user_id', $att->user_id)
                ->where('status', 'approved')
                ->where('type', 'late')
                ->whereDate('start_date', '<=', $att->date)
                ->whereDate('end_date', '>=', $att->date)
                ->exists();

            $hasEarlyLeavePermission = Permission::where('user_id', $att->user_id)
                ->where('status', 'approved')
                ->where('type', 'early_leave')
                ->whereDate('start_date', '<=', $att->date)
                ->whereDate('end_date', '>=', $att->date)
                ->exists();

            if ($shift && $att->check_in) {
                $isLate = strtotime($att->check_in) > strtotime($shift->start_time);
                if ($hasLatePermission) $isLate = false;
            }

            if ($shift && $att->check_out) {
                $leftEarly = strtotime($att->check_out) < strtotime($shift->end_time);
                if ($hasEarlyLeavePermission) $leftEarly = false;
            }

            return [
                'user_id' => $att->user_id,
                'first_name' => $att->user->first_name ?? null,
                'last_name' => $att->user->last_name ?? null,
                'date' => $att->date,
                'check_in' => $att->check_in,
                'check_out' => $att->check_out,
                'minutes_late' => $att->minutes_late,
                'status' => $att->status,
                'is_late' => $isLate,
                'left_early' => $leftEarly,
                'has_late_permission' => $hasLatePermission,
                'has_early_leave_permission' => $hasEarlyLeavePermission,
            ];
        });

    return response()->json([
        'success' => true,
        'attendances' => $attendances
    ]);
}




    public function attendanceSummary(Request $request)
{
    $user = auth()->user();

    $query = Attendance::with('user.shift')->orderBy('date', 'desc');

    if ($user->isEmployee()) {
        $query->where('user_id', $user->id);
    }

    $period = $request->input('period', 'day');
    $perPage = $request->input('per_page', 10);

    if ($request->filled('date')) {
        $query->where('date', $request->date);
        $period = 'day';
    }

    $attendances = $query->get()->map(function ($att) {

        $shift = $att->user->shift;

        $isLate = false;
        $leftEarly = false;

        $hasLatePermission = Permission::where('user_id', $att->user_id)
        ->where('status', 'approved')
        ->where('type', 'late')
        ->whereDate('start_date', '<=', $att->date)
        ->whereDate('end_date', '>=', $att->date)
        ->exists();

        // Vérifier si l'utilisateur est en retard
        if ($shift && $att->check_in) {
            $isLate = strtotime($att->check_in) > strtotime($shift->start_time);

            if ($hasLatePermission) {
                $isLate = false;
            }
        }

        // Vérifier si l'utilisateur est parti avant la fin du shift
        if ($shift && $att->check_out) {
            $leftEarly = strtotime($att->check_out) < strtotime($shift->end_time);
        }

        // Nouveau : Vérifie s'il y a une permission "early_leave" approuvée pour cette date
        $hasEarlyLeavePermission = Permission::where('user_id', $att->user_id)
            ->where('status', 'approved')
            ->where('type', 'early_leave')
            ->whereDate('start_date', '<=', $att->date)
            ->whereDate('end_date', '>=', $att->date)
            ->exists();

        // Utiliser le statut de la base de données
        $status = $att->status;

        return [
            'user_id' => $att->user_id,
            'first_name' => $att->user->first_name ?? null,
            'last_name' => $att->user->last_name ?? null,
            'date' => $att->date,
            'check_in' => $att->check_in,
            'check_out' => $att->check_out,
            'minutes_late' => $att->minutes_late,
            'status' => $status,
            'is_late' => $isLate,
            'left_early' => $leftEarly,
            'has_early_leave_permission' => $hasEarlyLeavePermission,
        ];
    });

    // Filtrage par semaine ou mois
    if ($period === 'week' && $request->filled('week') && $request->filled('year')) {
        $attendances = $attendances->filter(function ($item) use ($request) {
            $week = Carbon::parse($item['date'])->weekOfYear;
            $year = Carbon::parse($item['date'])->year;
            return $week == $request->week && $year == $request->year;
        });
    }

    if ($period === 'month' && $request->filled('month') && $request->filled('year')) {
        $attendances = $attendances->filter(function ($item) use ($request) {
            $month = Carbon::parse($item['date'])->month;
            $year = Carbon::parse($item['date'])->year;
            return $month == $request->month && $year == $request->year;
        });
    }

    // Pagination
    $page = $request->input('page', 1);
    $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
        $attendances->forPage($page, $perPage),
        $attendances->count(),
        $perPage,
        $page,
        ['path' => $request->url(), 'query' => $request->query()]
    );

    return response()->json([
        'success' => true,
        'period' => $period,
        'data' => $paginated
    ]);
}

public function myAttendanceSummary(Request $request)
{
    $user = auth()->user(); // Utilisateur connecté

    $query = Attendance::with('user.shift')
        ->where('user_id', $user->id) // Seulement pour l'utilisateur connecté
        ->orderBy('date', 'desc');

    $period = $request->input('period', 'day');
    $perPage = $request->input('per_page', 10);

    if ($request->filled('date')) {
        $query->where('date', $request->date);
        $period = 'day';
    }

    $attendances = $query->get()->map(function ($att) {

        $shift = $att->user->shift;

        $isLate = false;
        $leftEarly = false;

        // Permission late approuvée
        $hasLatePermission = Permission::where('user_id', $att->user_id)
            ->where('status', 'approved')
            ->where('type', 'late')
            ->whereDate('start_date', '<=', $att->date)
            ->whereDate('end_date', '>=', $att->date)
            ->exists();

        // Permission early_leave approuvée
        $hasEarlyLeavePermission = Permission::where('user_id', $att->user_id)
            ->where('status', 'approved')
            ->where('type', 'early_leave')
            ->whereDate('start_date', '<=', $att->date)
            ->whereDate('end_date', '>=', $att->date)
            ->exists();

        // Vérifier retard / départ anticipé
        if ($shift && $att->check_in) {
            $isLate = strtotime($att->check_in) > strtotime($shift->start_time);
            if ($hasLatePermission) $isLate = false;
        }

        if ($shift && $att->check_out) {
            $leftEarly = strtotime($att->check_out) < strtotime($shift->end_time);
            if ($hasEarlyLeavePermission) $leftEarly = false;
        }

        return [
            'user_id' => $att->user_id,
            'first_name' => $att->user->first_name ?? null,
            'last_name' => $att->user->last_name ?? null,
            'date' => $att->date,
            'check_in' => $att->check_in,
            'check_out' => $att->check_out,
            'minutes_late' => $att->minutes_late,
            'status' => $att->status,
            'is_late' => $isLate,
            'left_early' => $leftEarly,
            'has_early_leave_permission' => $hasEarlyLeavePermission,
            'has_late_permission' => $hasLatePermission,
        ];
    });

    // Filtrage par semaine ou mois si demandé
    if ($period === 'week' && $request->filled('week') && $request->filled('year')) {
        $attendances = $attendances->filter(function ($item) use ($request) {
            $week = Carbon::parse($item['date'])->weekOfYear;
            $year = Carbon::parse($item['date'])->year;
            return $week == $request->week && $year == $request->year;
        });
    }

    if ($period === 'month' && $request->filled('month') && $request->filled('year')) {
        $attendances = $attendances->filter(function ($item) use ($request) {
            $month = Carbon::parse($item['date'])->month;
            $year = Carbon::parse($item['date'])->year;
            return $month == $request->month && $year == $request->year;
        });
    }

    // Pagination
    $page = $request->input('page', 1);
    $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
        $attendances->forPage($page, $perPage),
        $attendances->count(),
        $perPage,
        $page,
        ['path' => $request->url(), 'query' => $request->query()]
    );

    return response()->json([
        'success' => true,
        'period' => $period,
        'data' => $paginated
    ]);
}



    /**
     * 🕒 Check-in (arrivée)
     */
    public function checkIn(Request $request)
    {
        $request->validate([
            'source' => 'required|string',
        ]);

        if ($request->input('source') !== 'NAFA') {
            return response()->json(['success' => false, 'message' => 'Valeur du champ source invalide.'], 400);
        }

        $user = auth()->user();
        $todayStr = now()->toDateString();

        // 🔹 Vérifie si déjà pointé
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->first();
        if ($attendance && $attendance->check_in) {
            return response()->json(['success' => false, 'message' => 'Vous avez déjà effectué votre check-in aujourd\'hui.'], 400);
        }

        // 🔹 Vérifie congé
        $onLeave = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        if ($onLeave) {
            return response()->json(['success' => false, 'message' => 'Vous êtes en congé pour cette journée.'], 400);
        }

        // 🔹 Vérifie jour de repos
        $hasDayOff = UserWeeklyDayOff::where('user_id', $user->id)
            ->whereDate('day_off_date', $todayStr)
            ->exists();
        if ($hasDayOff) {
            return response()->json(['success' => false, 'message' => 'Ce jour est défini comme un jour de repos.'], 400);
        }

        // 🔹 Récupère le shift
        $shift = $user->shift;
        if (!$shift) {
            return response()->json(['success' => false, 'message' => 'Aucun shift assigné.'], 400);
        }

        $dayOfWeek = strtolower(now()->format('l'));
        $now = Carbon::now();

        // 🔹 Définition des heures selon week-end ou jour ouvrable
        if (in_array($dayOfWeek, ['saturday', 'sunday'])) {
            if (!$user->works_weekend) {
                return response()->json(['success' => false, 'message' => 'Vous ne travaillez pas le week-end.'], 400);
            }

            $shiftStart = $shift->type === 'morning' ? Carbon::parse('09:00:00') : Carbon::parse('16:00:00');
            $shiftEnd   = $shift->type === 'morning' ? Carbon::parse('14:00:00') : Carbon::parse('21:00:00');
        } else {
            $shiftStart = Carbon::parse($shift->start_time);
            $shiftEnd   = Carbon::parse($shift->end_time);
        }

        // 🔹 Vérifie permission de type 'late'
        $hasLatePermission = Permission::where('user_id', $user->id)
            ->where('type', 'late')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        // 🔹 Calcul du retard
        $minutesLate = 0;
        if (!$hasLatePermission) {
            $minutesLate = $now->lessThanOrEqualTo($shiftStart->copy()->addMinutes(15))
                ? 0
                : $shiftStart->diffInMinutes($now) - 15;
        }

        // 🔹 Création ou mise à jour du pointage
        $attendance = Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => $todayStr],
            [
                'check_in' => $now->format('H:i:s'),
                'minutes_late' => $minutesLate,
                'status' => 'present',
                'scan_method' => $request->scan_method ?? 'scan',
            ]
        );

        // 🔹 Total retard du mois
        $attendance->total_minutes_late = Attendance::where('user_id', $user->id)
            ->whereMonth('date', now()->month)
            ->sum('minutes_late');
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'Check-in réussi.',
            'minutes_late' => $minutesLate,
            'shift_type' => $shift->type,
            'shift_start' => $shiftStart->format('H:i'),
            'shift_end' => $shiftEnd->format('H:i'),
        ]);
    }


    /**
     * 🕔 Check-out (départ)
     */
    public function checkOut(Request $request)
    {
        $request->validate([
            'source' => 'required|string',
        ]);

        if ($request->input('source') !== 'NAFA') {
            return response()->json(['success' => false, 'message' => 'Valeur du champ source invalide.'], 400);
        }

        $user = auth()->user();
        $todayStr = Carbon::today()->toDateString();
        $attendance = Attendance::where('user_id', $user->id)->where('date', $todayStr)->first();

        if (!$attendance || !$attendance->check_in) {
            return response()->json(['success' => false, 'message' => 'Aucun check-in trouvé pour aujourd\'hui.'], 400);
        }

        if ($attendance->check_out) {
            return response()->json(['success' => false, 'message' => 'Vous avez déjà effectué votre check-out.'], 400);
        }

        $shift = $user->shift;
        if (!$shift) {
            return response()->json(['success' => false, 'message' => 'Aucun shift assigné.'], 400);
        }

        $dayOfWeek = strtolower(now()->format('l'));

        // 🔹 Heures de fin
        if (in_array($dayOfWeek, ['saturday', 'sunday']) && $user->works_weekend) {
            $shiftEnd = $shift->type === 'morning' ? Carbon::parse('14:00:00') : Carbon::parse('21:00:00');
        } else {
            $shiftEnd = Carbon::parse($shift->end_time);
        }

        $checkInTime = Carbon::parse($attendance->check_in);
        $now = Carbon::now();
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
            'total_minutes_late' => $attendance->minutes_late,
            'early_leave' => $earlyLeave ? 1 : 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Check-out réussi.',
            'worked_minutes' => $workedMinutes,
            'early_leave' => $earlyLeave,
            'shift_type' => $shift->type,
            'shift_start' => $shift->type === 'morning' ? ($dayOfWeek==='saturday'||$dayOfWeek==='sunday'?($user->works_weekend?'09:00':''):$shift->start_time) : ($dayOfWeek==='saturday'||$dayOfWeek==='sunday'?($user->works_weekend?'16:00':''):$shift->start_time),
            'shift_end' => $shiftEnd->format('H:i'),
        ]);
    }



    /**
     * 🚫 Marquer un utilisateur absent
     */
    public function markAbsent($userId, Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());

        $attendance = Attendance::updateOrCreate(
            ['user_id' => $userId, 'date' => $date],
            ['status' => 'absent']
        );

        return response()->json([
            'success' => true,
            'message' => 'Employé marqué absent.',
            'attendance' => $attendance
        ]);
    }

    /**
     * 🔍 Détails d'une présence
     */
    public function show($id)
    {
        $attendance = Attendance::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'attendance' => $attendance
        ]);
    }

    /**
     * 🗑️ Supprimer une présence
     */
    public function destroy($id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Présence supprimée avec succès.'
        ]);
    }

    public function vigileAttendanceSummary(Request $request)
{
    $today = now()->toDateString();

    $attendances = Attendance::with('user.shift')
        ->where('date', $today)
        ->get()
        ->map(function ($att) {

            $shift = $att->user->shift;

            $isLate = false;
            $leftEarly = false;

            $hasLatePermission = Permission::where('user_id', $att->user_id)
                ->where('status', 'approved')
                ->where('type', 'late')
                ->whereDate('start_date', '<=', $att->date)
                ->whereDate('end_date', '>=', $att->date)
                ->exists();

            if ($shift && $att->check_in) {
                $isLate = strtotime($att->check_in) > strtotime($shift->start_time);
                if ($hasLatePermission) {
                    $isLate = false;
                }
            }

            $hasEarlyLeavePermission = Permission::where('user_id', $att->user_id)
                ->where('status', 'approved')
                ->where('type', 'early_leave')
                ->whereDate('start_date', '<=', $att->date)
                ->whereDate('end_date', '>=', $att->date)
                ->exists();

            if ($shift && $att->check_out) {
                $leftEarly = strtotime($att->check_out) < strtotime($shift->end_time);
            }

            return [
                'user_id' => $att->user_id,
                'first_name' => $att->user->first_name ?? null,
                'last_name' => $att->user->last_name ?? null,
                'date' => $att->date,
                'check_in' => $att->check_in,
                'check_out' => $att->check_out,
                'minutes_late' => $att->minutes_late,
                'status' => $att->status,
                'is_late' => $isLate,
                'left_early' => $leftEarly,
                'has_early_leave_permission' => $hasEarlyLeavePermission,
            ];
        });

    return response()->json([
        'success' => true,
        'date' => $today,
        'data' => $attendances
    ]);
}

public function todaySituation()
{
    $today = Carbon::today()->toDateString();

    // 🔹 5 dernières présences du jour
    $attendances = Attendance::with('user.department')
        ->where('date', $today)
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    // 🔹 Statistiques du jour
    $presentCount = Attendance::where('date', $today)
        ->where('status', 'present')
        ->count();

    $absentCount = Attendance::where('date', $today)
        ->where('status', 'absent')
        ->count();

    $lateCount = Attendance::where('date', $today)
        ->where('minutes_late', '>', 0)
        ->count();

    // 🔹 5 dernières permissions en attente
    $pendingPermissions = Permission::with('user.department')
        ->where('status', 'pending')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    // 🔹 5 derniers congés en attente
    $pendingLeaves = Leave::with('user')
        ->where('status', 'pending')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    // 🔹 Total combiné des demandes pending
    $totalPendingRequests = $pendingPermissions->count() + $pendingLeaves->count();

    return response()->json([
        'success' => true,
        'attendances_today' => $attendances,
        'pending_permissions' => $pendingPermissions,
        'pending_leaves' => $pendingLeaves,

        'statistics' => [
            'present' => $presentCount,
            'absent' => $absentCount,
            'late' => $lateCount,
            'total_pending_requests_today' => $totalPendingRequests,
        ]
    ]);
}


}
