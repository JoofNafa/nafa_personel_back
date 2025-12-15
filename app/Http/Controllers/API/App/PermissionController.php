<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PermissionController extends Controller
{
    /**
     * Créer une nouvelle permission
     */
    public function store(Request $request)
{
    $user = auth()->user();

    $request->validate([
        'type' => ['required', 'in:messing,late,early_leave'],
        'start_date' => ['required', 'date'],
        'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        'start_time' => ['nullable', 'date_format:H:i'],
        'end_time' => ['nullable', 'date_format:H:i'],
        'reason' => ['required', 'string'],
    ]);

    // Vérifier si une demande en pending existe pour la période
    $hasPending = Permission::where('user_id', $user->id)
        ->where('status', 'pending')
        ->where(function ($query) use ($request) {
            $startDate = $request->start_date;
            $endDate = $request->end_date ?? $request->start_date;

            $query->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                  });
        })
        ->exists();

    if ($hasPending) {
        return response()->json([
            'success' => false,
            'message' => 'Vous avez déjà une demande en attente pour cette période.'
        ], 409);
    }

    // Enregistrer la permission
    $permission = Permission::create([
        'user_id' => $user->id,
        'type' => $request->type,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date ?? $request->start_date,
        'start_time' => $request->start_time,
        'end_time' => $request->end_time,
        'reason' => $request->reason,
        'status' => 'pending',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Permission créée avec succès',
        'permission' => $permission
    ]);
}


    /**
     * Lister toutes les permissions ou filtrer par utilisateur
     */

        public function index(Request $request)
        {
            $query = Permission::query()->with('user.department', 'approver');

            // Filtrer par user_id si fourni
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filtrer par status si fourni
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filtrer pour la semaine en cours
            $startOfWeek = Carbon::now()->startOfWeek(); // lundi de la semaine
            $endOfWeek = Carbon::now()->endOfWeek();     // dimanche de la semaine

            $query->whereBetween('start_date', [$startOfWeek, $endOfWeek]);

            $permissions = $query->orderBy('start_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'permissions' => $permissions
            ]);
        }

    /**
     * Modifier une permission
     */
    public function update(Request $request, $id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission non trouvée'
            ], 404);
        }

        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier une permission déjà traitée'
            ], 400);
        }

        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string'],
        ]);

        $permission->update($request->only(['start_date', 'end_date', 'start_time', 'end_time', 'reason']));

        return response()->json([
            'success' => true,
            'message' => 'Permission mise à jour avec succès',
            'permission' => $permission
        ]);
    }

    /**
     * Supprimer une permission
     */
    public function destroy($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission non trouvée'
            ], 404);
        }

        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une permission déjà traitée'
            ], 400);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission supprimée avec succès'
        ]);
    }

    /**
     * Approuver une permission
     */
    public function approve($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission non trouvée'
            ], 404);
        }

        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permission déjà traitée'
            ], 400);
        }

        $permission->approve(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Permission approuvée',
            'permission' => $permission
        ]);
    }

    /**
     * Rejeter une permission
     */
    public function reject($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission non trouvée'
            ], 404);
        }

        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permission déjà traitée'
            ], 400);
        }

        $permission->reject(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Permission rejetée',
            'permission' => $permission
        ]);
    }

    /**
 * Retourner toutes les permissions de l'utilisateur connecté pour un mois donné
 * Si aucun mois n'est fourni, on prend le mois en cours
 */
public function myMonthlyPermissions(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    // Récupérer le mois depuis la requête, format "YYYY-MM", par défaut le mois en cours
    $month = $request->input('month', now()->format('Y-m'));
    [$year, $monthNumber] = explode('-', $month);

    $startDate = Carbon::create($year, $monthNumber, 1)->startOfMonth();
    $endDate   = Carbon::create($year, $monthNumber, 1)->endOfMonth();

    // Permissions dont la date de début ou de fin tombe dans le mois
    $permissions = Permission::with('approver')
        ->where('user_id', $user->id)
        ->where(function($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()]);
        })
        ->orderBy('start_date', 'desc')
        ->get();

    return response()->json([
        'success' => true,
        'month' => $month,
        'permissions' => $permissions
    ]);
}


}
