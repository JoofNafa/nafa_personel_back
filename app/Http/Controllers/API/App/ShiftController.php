<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * Lister tous les shifts
     */
    public function index()
    {
        $shifts = Shift::orderBy('start_time')->get();

        return response()->json([
            'success' => true,
            'shifts' => $shifts
        ]);
    }

    /**
     * Créer un shift
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        // Vérifie qu’il n’existe pas déjà un shift avec le même nom
        if (Shift::where('name', $request->name)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Un shift avec ce nom existe déjà.'
            ], 409);
        }

        $shift = Shift::create($request->only('name', 'start_time', 'end_time'));

        return response()->json([
            'success' => true,
            'message' => 'Shift créé avec succès.',
            'shift' => $shift
        ], 201);
    }

    /**
     * Afficher un shift spécifique
     */
    public function show($id)
    {
        $shift = Shift::find($id);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift non trouvé.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'shift' => $shift
        ]);
    }

    /**
     * Modifier un shift
     */
    public function update(Request $request, $id)
    {
        $shift = Shift::find($id);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift non trouvé.'
            ], 404);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
        ]);

        // Empêche les doublons de nom
        if ($request->name && Shift::where('name', $request->name)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Un autre shift avec ce nom existe déjà.'
            ], 409);
        }

        $shift->update($request->only('name', 'start_time', 'end_time'));

        return response()->json([
            'success' => true,
            'message' => 'Shift mis à jour avec succès.',
            'shift' => $shift
        ]);
    }

    /**
     * Supprimer un shift
     */
    public function destroy($id)
    {
        $shift = Shift::find($id);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift non trouvé.'
            ], 404);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift supprimé avec succès.'
        ]);
    }
}
