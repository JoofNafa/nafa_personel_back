<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    // public function handle(Request $request, Closure $next, string $role)
    // {
    //     if (!Auth::check() || Auth::user()->role !== $role) {
    //         return redirect()->route('login')->withErrors(['access' => 'Accès non autorisé.']);
    //     }

    //     return $next($request);
    // }

    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth()->user();

        if (! $user || ! in_array($user->role, $roles)) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        return $next($request);
    }

    /**
     * Perform any operations after the response has been sent to the browser.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        // No operation needed here
    }
}