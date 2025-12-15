<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Models\UserWeeklyDayOff;
use App\Models\Permission;
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

        $attendances = $user->attendances()->orderBy('date', 'desc')->paginate(20);

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

            // Vérifier si l'utilisateur est en retard
            if ($shift && $att->check_in) {
                $isLate = strtotime($att->check_in) > strtotime($shift->start_time);
            }

            // Vérifier si l'utilisateur est parti avant la fin du shift
            if ($shift && $att->check_out) {
                $leftEarly = strtotime($att->check_out) < strtotime($shift->end_time);
            }

            // Déterminer le statut général
            if ($att->check_in === null && $att->check_out === null) {
                $status = 'absent';
            } elseif ($isLate) {
                $status = 'late';
            } else {
                $status = 'present';
            }

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


    /**
     * 🕒 Check-in (arrivée)
     */
    public function checkIn(Request $request)
    {
        $user = auth()->user();
        $today = Carbon::today();
        $todayStr = $today->toDateString();

        // 🔹 Vérifie si un enregistrement existe déjà
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->first();

        if ($attendance && $attendance->check_in) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà effectué votre entrée pour aujourd\'hui.'
            ], 400);
        }

        // 🔹 Vérifie si le jour est un day off
        $hasDayOff = UserWeeklyDayOff::where('user_id', $user->id)
            ->whereDate('day_off_date', $todayStr)
            ->exists();

        if ($hasDayOff) {
            return response()->json([
                'success' => false,
                'message' => 'Ce jour est défini comme un jour de repos.'
            ], 400);
        }

        // 🔹 Vérifie si le user est en permission ou congé validé
        $onPermission = Permission::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        $onLeave = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        if ($onPermission || $onLeave) {
            return response()->json([
                'success' => false,
                'message' => 'Vous êtes en permission ou en congé pour cette journée.'
            ], 400);
        }

        // 🔹 Récupération de l'heure de début du shift via shift_id
        $shift = $user->shift ?? null;

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun shift n\'est assigné à cet utilisateur.'
            ], 400);
        }

        $shiftStart = Carbon::parse($shift->start_time);
        $now = Carbon::now();

        // 🔹 Calcul du retard (minutes de retard si check-in après le début du shift)
        $minutesLate = $now->greaterThan($shiftStart)
            ? $shiftStart->diffInMinutes($now)
            : 0;

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

        return response()->json([
            'success' => true,
            'message' => 'Check-in réussi.',

        ]);
    }


    /**
     * 🕔 Check-out (départ)
     */
    public function checkOut()
    {
        $user = auth()->user();
        $todayStr = Carbon::today()->toDateString();

        // 🔹 Récupération du pointage du jour
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->first();

        if (!$attendance || !$attendance->check_in) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune entrée trouvé pour aujourd\'hui.'
            ], 400);
        }

        if ($attendance->check_out) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà effectué votre sortie.'
            ], 400);
        }

        $now = Carbon::now();

        // 🔹 Récupération du shift
        $shift = $user->shift ?? null;
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun shift assigné à cet utilisateur.'
            ], 400);
        }

        // 🔹 Calcul du retard total si nécessaire (optionnel)
        $shiftStart = Carbon::parse($shift->start_time);
        $minutesLateToday = $attendance->minutes_late;

        $attendance->update([
            'check_out' => $now->format('H:i:s'),
            // cumul des minutes de retard sur ce jour
            'total_minutes_late' => $minutesLateToday,

        ]);
        return response()->json([
            'success' => true,
            'message' => 'Sortie réussi.',

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

    public function todaySituation()
    {
        // $today = Carbon::today()->toDateString();

        // 🔹 5 dernières présences du jour
        $attendances = Attendance::with('user')
            // ->where('date', $today)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // 🔹 5 dernières permissions en attente
        $pendingPermissions = Permission::with('user')
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

        return response()->json([
            'success' => true,
            'attendances_today' => $attendances,
            'pending_permissions' => $pendingPermissions,
            'pending_leaves' => $pendingLeaves,
        ]);
    }

}
