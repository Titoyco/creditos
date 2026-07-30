<?php

namespace App\Http\Controllers;

use App\Models\Provincia;
use App\Models\Localidad;
use Illuminate\Http\Request;

class UbicacionController extends Controller
{
    public function provincias()
    {
        $provincias = Provincia::withCount('localidades')->get();
        return view('sistema.ubicaciones.provincias', compact('provincias'));
    }

    public function localidades()
    {
        $localidades = Localidad::with('provincia')->paginate(20);
        return view('sistema.ubicaciones.localidades', compact('localidades'));
    }
}