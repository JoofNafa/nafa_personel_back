<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\UserWeeklyDayOff;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;


class UserWeeklyDayOffController extends Controller
{


    /**
     * Liste de tous les day-offs
     */
    public function index()
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek   = Carbon::now()->endOfWeek();

        $dayOffs = UserWeeklyDayOff::with('user.department')
            ->whereBetween('day_off_date', [$startOfWeek, $endOfWeek])
            ->orderBy('day_off_date', 'asc')
            ->get();

        return response()->json($dayOffs);
    }

    public function userForDayOff()
{
    // 📄 Récupère tous les utilisateurs
    $users = User::all();

    // 🔙 Retourne une réponse JSON
    return response()->json([
        'success' => true,
        'message' => 'Users retrieved successfully',
        'data' => $users,
    ], 200);
}


    /**
     * Enregistrer un nouveau day off
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'day_off_date'  => 'required|date',
        ]);

        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'rh', 'manager'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $date = Carbon::parse($validated['day_off_date']);

        // Vérifier s'il existe déjà un day_off dans la même semaine
        $exists = UserWeeklyDayOff::where('user_id', $validated['user_id'])
            ->whereBetween('day_off_date', [
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek()
            ])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Cet employé a déjà un day off pour cette semaine."
            ], 409);
        }

        $dayOff = UserWeeklyDayOff::create([
            'user_id'       => $validated['user_id'],
            'day_off_date'  => $validated['day_off_date'],
            'created_by'    => auth()->id(),
        ]);

        return response()->json($dayOff, 201);
    }

    /**
     * Afficher un day off
     */
    public function show($id)
    {
        $dayOff = UserWeeklyDayOff::with('user', 'creator')->findOrFail($id);
        return response()->json($dayOff);
    }

    /**
     * Modifier un day off
     */
    public function update(Request $request, $id)
    {
        $dayOff = UserWeeklyDayOff::findOrFail($id);

        $validated = $request->validate([
            'day_off_date' => 'required|date',
        ]);

        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'rh', 'manager'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $date = Carbon::parse($validated['day_off_date']);

        // Vérifier si un autre day_off existe dans la même semaine
        $exists = UserWeeklyDayOff::where('user_id', $dayOff->user_id)
            ->whereBetween('day_off_date', [
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek()
            ])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Cet employé a déjà un day off pour cette semaine."
            ], 409);
        }

        $dayOff->update([
            'day_off_date' => $validated['day_off_date'],
        ]);

        return response()->json($dayOff);
    }

    /**
     * Supprimer un day off
     */
    public function destroy($id)
    {
        $dayOff = UserWeeklyDayOff::findOrFail($id);
        $dayOff->delete();

        return response()->json(['message' => 'Day off supprimé avec succès']);
    }
}
