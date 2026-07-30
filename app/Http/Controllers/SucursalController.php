<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SucursalController extends Controller
{
// app/Http/Controllers/SucursalController.php

    public function updateGlobal(Request $request)
    {
        // Validamos que llegue el ID
        $request->validate([
            'id_sucursal' => 'required|exists:sucursales,id'
        ]);

        // Guardamos en sesión de forma persistente
        session(['sucursal_activa_id' => $request->id_sucursal]);
        
        // Forzamos el guardado de la sesión antes de redireccionar (opcional pero seguro)
        $request->session()->save();

        return back();
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
