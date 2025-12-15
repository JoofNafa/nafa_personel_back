<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    /**
     * Gère les requêtes CORS pour toutes les routes API.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Autoriser toutes les origines (utile en développement)
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');

        // Pour les requêtes OPTIONS (prévol CORS)
        if ($request->getMethod() === "OPTIONS") {
            $response->setStatusCode(200);
        }

        return $response;
    }
}
