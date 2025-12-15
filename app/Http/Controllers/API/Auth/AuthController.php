<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Connexion utilisateur et génération de token
     */
    public function login(Request $request)
{
    $request->validate([
        'login' => 'required|string', // accepte email ou phone
        'password' => 'required|string'
    ]);

    $login = $request->input('login');
    $password = $request->input('password');

    // Vérifier si c'est un email ou un numéro de téléphone
    $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

    $credentials = [$fieldType => $login, 'password' => $password];

    // Tentative d'authentification
    if (!$token = auth('api')->attempt($credentials)) {
        return response()->json([
            'success' => false,
            'message' => 'Identifiants incorrects',
            'code' => 401
        ], 401);
    }

    // Récupération de l'utilisateur connecté
    $user = auth('api')->user();

    return $this->respondWithToken($token, $user);
}

public function loginWithPin(Request $request)
{
    $request->validate([
        'phone' => 'required|string',
        'pin' => 'required|digits:4',
    ]);

    $phone = str_replace(' ', '', $request->phone);

    // Charger l'utilisateur avec son shift
    $user = \App\Models\User::with('shift')->whereRaw("REPLACE(phone, ' ', '') = ?", [$phone])->first();

    if (!$user || !Hash::check($request->pin, $user->pin)) {
        return response()->json([
            'success' => false,
            'message' => 'Identifiants incorrects',
            'code' => 401
        ], 401);
    }

    $token = auth('api')->login($user);

    // Préparer les données utilisateur
    $userData = $user->toArray();

    // Remplacer shift_id par start_time et end_time du shift
    if ($user->shift) {
        $userData['shift_start_time'] = $user->shift->start_time;
        $userData['shift_end_time'] = $user->shift->end_time;
    } else {
        $userData['shift_start_time'] = null;
        $userData['shift_end_time'] = null;
    }

    // On peut supprimer shift_id si tu veux
    unset($userData['shift_id']);

    return response()->json([
        'success' => true,
        'token' => $token,
        'token_type' => 'bearer',
        'expires_in' => auth('api')->factory()->getTTL() * 60,
        'user' => $userData,
    ]);
}


    /**
     * Infos utilisateur connecté
     */
    public function me()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * Rafraîchir le token JWT
     */
    public function refresh()
    {
        $token = auth('api')->refresh();
        $user = auth('api')->user();

        return $this->respondWithToken($token, $user);
    }

    /**
     * Déconnexion (invalider le token)
     */
    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * Mise à jour du mot de passe utilisateur
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
                'code' => 401
            ], 401);
        }

        $user->password = Hash::make($request->password);

        // Si tu veux gérer le champ must_change_password
        if (isset($user->must_change_password)) {
            $user->must_change_password = false;
        }

        $user->save();

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

     /**
     * Mise à jour du mot de passe utilisateur
     */
    public function updatePin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'string',],
        ]);

        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
                'code' => 401
            ], 401);
        }

        $user->pin = Hash::make($request->pin);

        // Si tu veux gérer le champ must_change_pin
        if (isset($user->must_change_pin)) {
            $user->must_change_pin = false;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Pin mis à jour avec succès.',
            'code' => 200
        ], 200);

    }

    /**
     * Formater la réponse avec token
     */
    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }
}
