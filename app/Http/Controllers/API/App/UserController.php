<?php

namespace App\Http\Controllers\API\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // 🧭 Récupère la taille de la page depuis la requête (par défaut 6)
        $perPage = $request->get('per_page', 6);

        // 📄 Récupère les utilisateurs paginés avec leur département
        $users = User::with('department')->paginate($perPage);

        // 🔙 Retourne une réponse JSON bien structurée
        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 200);
    }


    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:departments,id',
            'role' => 'required|in:employee,manager,rh,admin',
            'shift' => 'nullable|exists:shifts,id',
            'works_weekend' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // 🔤 Génération automatique de l'email
        $email = $this->generateAdminEmail($validated['first_name'], $validated['last_name']);

        // 👤 Création de l’utilisateur
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $email,
            'phone'      => $validated['phone'] ?? null,
            'password'   => Hash::make("NAFA2025"),
            'pin'   => Hash::make("2025"),
            'department_id' => $validated['department_id'] ?? null,
            'role'       => $validated['role'],
            'shift_id'   => $validated['shift'] ?? null, // ✅ nouveau champ relationnel
            'leave_balance' => 0,
            'must_change_password' => true,
            'must_change_pin' => true,
            'works_weekend' => $validated['works_weekend'] ? 1 : 0

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $user->load(['department', 'shift']), // ✅ on charge la relation shift
        ], 201);
    }



    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = User::with('department')->find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'department_id' => 'nullable|exists:departments,id',
            'role' => 'required|in:employee,manager,rh,admin',
            'shift' => 'nullable|in:morning,evening',
            'leave_balance' => 'nullable|integer|min:0',
            'must_change_password' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
            'department_id' => $request->department_id,
            'role' => $request->role,
            'shift' => $request->shift ?? $user->shift,
            'leave_balance' => $request->leave_balance ?? $user->leave_balance,
            'must_change_password' => $request->must_change_password ?? $user->must_change_password,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->load('department'),
        ], 200);
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->delete();
        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ], 200);
    }

    private function generateAdminEmail($firstName, $lastName)
    {
        // Fonction de nettoyage
        $normalize = function ($string) {
            $unwanted_array = [
                'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
                'ç'=>'c','ć'=>'c','č'=>'c',
                'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ė'=>'e','ę'=>'e',
                'î'=>'i','ï'=>'i','í'=>'i','ī'=>'i','į'=>'i','ì'=>'i',
                'ô'=>'o','ö'=>'o','ò'=>'o','ó'=>'o','õ'=>'o','ø'=>'o','ō'=>'o',
                'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u','ū'=>'u',
                'ÿ'=>'y','ý'=>'y',
                'ñ'=>'n',
                'À'=>'a','Á'=>'a','Â'=>'a','Ã'=>'a','Ä'=>'a','Å'=>'a','Ā'=>'a','Ă'=>'a','Ą'=>'a',
                'Ç'=>'c','Ć'=>'c','Č'=>'c',
                'È'=>'e','É'=>'e','Ê'=>'e','Ë'=>'e','Ē'=>'e','Ė'=>'e','Ę'=>'e',
                'Î'=>'i','Ï'=>'i','Í'=>'i','Ī'=>'i','Į'=>'i','Ì'=>'i',
                'Ô'=>'o','Ö'=>'o','Ò'=>'o','Ó'=>'o','Õ'=>'o','Ø'=>'o','Ō'=>'o',
                'Ù'=>'u','Û'=>'u','Ü'=>'u','Ú'=>'u','Ū'=>'u',
                'Ÿ'=>'y','Ý'=>'y',
                'Ñ'=>'n',
            ];
            $string = strtr($string, $unwanted_array);
            $string = preg_replace('/[^a-zA-Z0-9]/', '', $string);
            return strtolower($string);
        };

        $first = $normalize($firstName);
        $last = $normalize($lastName);
        $emailBase = "{$first}.{$last}";
        $domain = "@nafa.com";
        $email = "{$emailBase}{$domain}";
        $count = 1;

        while (User::where('email', $email)->exists()) {
            $email = "{$emailBase}{$count}{$domain}";
            $count++;
        }

        return $email;
    }
}