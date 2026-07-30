<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Sucursal;

class SetSucursalGlobal
{
    public function handle(Request $request, Closure $next)
    {
        // Solo actuamos si el usuario está logueado
        if (Auth::check()) {
            // Buscamos ID en sesión o en el usuario
            $id = session('sucursal_activa_id') ?? Auth::user()->id_sucursal;
            
            $sucursal = Sucursal::find($id);

            if ($sucursal) {
                // IMPORTANTE: Guardamos como OBJETO para acceder como ->id
                config(['app.sucursal_activa' => $sucursal]);
            }
        }

        return $next($request);
    }
}