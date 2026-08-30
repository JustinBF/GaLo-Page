<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ocultar botones en el frontend no protege nada: toda ruta de escritura
 * pasa obligatoriamente por aquí.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Necesitas permisos de administrador.',
            ], 403);
        }

        return $next($request);
    }
}
