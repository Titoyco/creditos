<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $usuarios = User::with('sucursal')->get();
        return view('sistema.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $sucursales = Sucursal::all();
        return view('sistema.usuarios.create', compact('sucursales'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:users',
            'password' => 'required|min:4',
            'id_sucursal' => 'required'
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->name . '@sistema.com', // Email interno genérico
            'password' => Hash::make($request->password),
            'id_sucursal' => $request->id_sucursal,
        ]);

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado');
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