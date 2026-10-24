<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirigirSiDebeActualizarPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if (
            $usuario?->debe_actualizar_password
            && $request->isMethod('get')
            && ! $request->routeIs('password.actualizar-inicial')
            && ! $request->routeIs('logout')
        ) {
            return redirect()->route('password.actualizar-inicial');
        }

        return $next($request);
    }
}
