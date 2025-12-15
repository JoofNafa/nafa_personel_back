<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    /**
     * 📜 Liste des congés
     */
    public function requestedLeave(Request $request)
    {
        // Charger la relation department de l'utilisateur et l'approbateur
        $query = Leave::with(['user.department', 'approver'])->orderBy('start_date', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtrer pour le mois en cours
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $query->whereBetween('start_date', [$startOfMonth, $endOfMonth]);

        $leaves = $query->get();

        // Ajouter le nom du département pour chaque congé
        $leavesTransformed = $leaves->map(function ($leave) {
            $leaveArray = $leave->toArray();
            $leaveArray['department_name'] = $leave->user->department->name ?? null;
            return $leaveArray;
        });

        // Pagination personnalisée
        $perPage = $request->input('per_page', 5);
        $page = $request->input('page', 1);
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $leavesTransformed->forPage($page, $perPage),
            $leavesTransformed->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'leaves' => $paginated
        ]);
    }

    /**
     * 👤 Liste des congés de l'utilisateur connecté
     */
    public function myLeaves()
    {
        $user = auth()->user();

        $leaves = Leave::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'leaves' => $leaves,
        ]);
    }

    /**
     * 📝 Créer une demande de congé
     */
    public function newLeaveRequest(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            // 'reason' => ['required', 'string', 'max:255'],
        ], [
            'start_date.required' => 'La date de début est obligatoire.',
            'start_date.date' => 'La date de début doit être une date valide.',
            'start_date.after_or_equal' => 'La date de début doit être aujourd\'hui ou ultérieure.',

            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.date' => 'La date de fin doit être une date valide.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',

            'reason.required' => 'La raison du congé est obligatoire.',
            'reason.string' => 'La raison doit être une chaîne de caractères.',
            'reason.max' => 'La raison ne peut pas dépasser 255 caractères.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des champs.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        $days = (Carbon::parse($request->end_date)->diffInDays(Carbon::parse($request->start_date))) + 1;

        // Vérifier le solde de congés
        if ($user->leave_balance < $days) {
            return response()->json([
                'success' => false,
                'message' => 'Solde de congés insuffisant pour cette demande.'
            ], 400);
        }

        /**
         * 🔍 VERIFIER S'IL EXISTE UNE DEMANDE EN PENDING SUR LA MEME PERIODE
         * (même logique que pour Permission)
         */
        $hasPending = Leave::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function ($query) use ($request) {
                $start = $request->start_date;
                $end = $request->end_date;

                $query->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($q) use ($start, $end) {
                          $q->where('start_date', '<=', $end)
                            ->where('end_date', '>=', $start);
                      });
            })
            ->exists();

        if ($hasPending) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà une demande de congé en attente pour cette période.'
            ], 409);
        }

        /**
         * 🔍 Vérifier chevauchement avec un autre congé existant (approved ou pending)
         * (tu l’avais déjà – je laisse tel quel)
         */
        $overlap = Leave::where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('start_date', '<=', $request->start_date)
                         ->where('end_date', '>=', $request->end_date);
                  });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Un congé existe déjà sur cette période.'
            ], 400);
        }

        // Création de la demande
        $leave = Leave::create([
            'user_id' => $user->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => '',
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de congé soumise avec succès.',
            'leave' => $leave,
        ]);
    }

    /**
     * 🖊️ Mettre à jour une demande de congé (avant validation)
     */
    public function update(Request $request, $id)
    {
        $leave = Leave::findOrFail($id);
        $user = auth()->user();

        if ($leave->user_id !== $user->id && !$user->isRH() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à modifier cette demande.'
            ], 403);
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier un congé déjà traité.'
            ], 400);
        }

        $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $leave->update([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de congé mise à jour.',
            'leave' => $leave,
        ]);
    }

    /**
     * ✅ Approuver un congé
     */
    public function approve($id)
    {
        $user = auth()->user();

        if (!$user->isRH() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un manager ou un RH peut approuver un congé.'
            ], 403);
        }

        $leave = Leave::findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande a déjà été traitée.'
            ], 400);
        }

        $leave->approve($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Congé approuvé avec succès.',
            'leave' => $leave,
        ]);
    }

    /**
     * ❌ Rejeter un congé
     */
    public function reject($id)
    {
        $user = auth()->user();

        if (!$user->isRH() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un manager ou un RH peut rejeter un congé.'
            ], 403);
        }

        $leave = Leave::findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande a déjà été traitée.'
            ], 400);
        }

        $leave->reject($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Congé rejeté avec succès.',
            'leave' => $leave,
        ]);
    }

    /**
     * 🔍 Détails d'une demande
     */
    public function show($id)
    {
        $leave = Leave::with(['user', 'approver'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'leave' => $leave,
        ]);
    }

    /**
     * 🗑️ Supprimer une demande de congé
     */
    public function destroy($id)
    {
        $leave = Leave::findOrFail($id);
        $user = auth()->user();

        if ($leave->user_id !== $user->id && !$user->isRH()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à supprimer ce congé.'
            ], 403);
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un congé déjà validé.'
            ], 400);
        }

        $leave->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande de congé supprimée avec succès.'
        ]);
    }
}
