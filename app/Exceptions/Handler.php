<?php

use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


protected function unauthenticated($request, AuthenticationException $exception)
{
    // Si la requête est pour l'API, on renvoie toujours du JSON
    if ($request->is('api/*') || $request->expectsJson()) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    // Sinon, comportement normal (redirection vers /login)
    return redirect()->guest(route('login'));
}

public function render($request, Throwable $exception)
{
    if ($exception instanceof AccessDeniedHttpException) {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'Forbidden. You do not have the required role.'], 403);
        }
    }

    return parent::render($request, $exception);
}
