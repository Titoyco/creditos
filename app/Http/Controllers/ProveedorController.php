<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Proveedor;
use App\Models\Localidad;
use App\Models\CondicionIva;
use App\Models\Provincia;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::with(['localidad.provincia', 'condicionIva']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('cuit', 'like', "%{$search}%")
                  ->orWhere('legajo', 'like', "%{$search}%")
                  ->orWhere('contacto', 'like', "%{$search}%");
            });
        }

        if ($locId = $request->input('id_localidad')) {
            $query->where('id_localidad', $locId);
        }

        $proveedores = $query->orderBy('nombre', 'asc')->paginate(20);
        $localidades = Localidad::orderBy('nombre')->get();
        $provincias = Provincia::orderBy('nombre')->get();

        return view('proveedores.index', compact('proveedores', 'localidades', 'provincias'));
    }

    public function create()
    {
        $localidades = Localidad::with('provincia')->orderBy('nombre')->get();
        $condicionesIva = CondicionIva::all();
        $siguienteLegajo = Proveedor::sugerirSiguienteLegajo();

        return view('proveedores.form', compact('localidades', 'condicionesIva', 'siguienteLegajo'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'legajo' => 'nullable|string|max:10|unique:proveedores,legajo',
            'nombre' => 'required|string|max:200',
            'cuit' => 'nullable|string|max:20',
            'contacto' => 'nullable|string|max:100',
            'direccion' => 'nullable|string|max:255',
            'id_localidad' => 'nullable|exists:localidades,id',
            'id_iva' => 'nullable|exists:condiciones_iva,id',
            'tel1' => 'nullable|string|max:50',
            'tel2' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'web' => 'nullable|string|max:200',
            'observaciones' => 'nullable|string',
        ]);

        $legajo = !empty($validated['legajo']) 
            ? (is_numeric($validated['legajo']) ? str_pad(trim($validated['legajo']), 3, '0', STR_PAD_LEFT) : strtoupper(trim($validated['legajo'])))
            : Proveedor::sugerirSiguienteLegajo();

        $proveedor = Proveedor::create([
            'legajo' => $legajo,
            'nombre' => strtoupper(trim($validated['nombre'])),
            'cuit' => !empty($validated['cuit']) ? preg_replace('/[^0-9]/', '', $validated['cuit']) : null,
            'contacto' => !empty($validated['contacto']) ? trim($validated['contacto']) : null,
            'direccion' => !empty($validated['direccion']) ? trim($validated['direccion']) : null,
            'id_localidad' => $validated['id_localidad'] ?? null,
            'id_iva' => $validated['id_iva'] ?? null,
            'tel1' => $validated['tel1'] ?? null,
            'tel2' => $validated['tel2'] ?? null,
            'email' => $validated['email'] ?? null,
            'web' => $validated['web'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'saldo' => 0,
            'activo' => true,
        ]);

        return redirect()->route('proveedores.show', $proveedor->id)
                         ->with('success', "Proveedor '{$proveedor->nombre}' registrado correctamente con Legajo [{$proveedor->legajo}].");
    }

    public function show(Proveedor $proveedor)
    {
        $proveedor->load(['localidad.provincia', 'condicionIva', 'productos' => function ($q) {
            $q->take(50);
        }]);

        return view('proveedores.show', compact('proveedor'));
    }

    public function edit(Proveedor $proveedor)
    {
        $localidades = Localidad::with('provincia')->orderBy('nombre')->get();
        $condicionesIva = CondicionIva::all();
        $siguienteLegajo = $proveedor->legajo ?? Proveedor::sugerirSiguienteLegajo();

        return view('proveedores.form', compact('proveedor', 'localidades', 'condicionesIva', 'siguienteLegajo'));
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $validated = $request->validate([
            'legajo' => 'nullable|string|max:10|unique:proveedores,legajo,' . $proveedor->id,
            'nombre' => 'required|string|max:200',
            'cuit' => 'nullable|string|max:20',
            'contacto' => 'nullable|string|max:100',
            'direccion' => 'nullable|string|max:255',
            'id_localidad' => 'nullable|exists:localidades,id',
            'id_iva' => 'nullable|exists:condiciones_iva,id',
            'tel1' => 'nullable|string|max:50',
            'tel2' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'web' => 'nullable|string|max:200',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $legajo = !empty($validated['legajo']) 
            ? (is_numeric($validated['legajo']) ? str_pad(trim($validated['legajo']), 3, '0', STR_PAD_LEFT) : strtoupper(trim($validated['legajo'])))
            : $proveedor->legajo;

        $proveedor->update([
            'legajo' => $legajo,
            'nombre' => strtoupper(trim($validated['nombre'])),
            'cuit' => !empty($validated['cuit']) ? preg_replace('/[^0-9]/', '', $validated['cuit']) : null,
            'contacto' => !empty($validated['contacto']) ? trim($validated['contacto']) : null,
            'direccion' => !empty($validated['direccion']) ? trim($validated['direccion']) : null,
            'id_localidad' => $validated['id_localidad'] ?? null,
            'id_iva' => $validated['id_iva'] ?? null,
            'tel1' => $validated['tel1'] ?? null,
            'tel2' => $validated['tel2'] ?? null,
            'email' => $validated['email'] ?? null,
            'web' => $validated['web'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'activo' => $request->boolean('activo', true),
        ]);

        return redirect()->route('proveedores.show', $proveedor->id)
                         ->with('success', "Proveedor '{$proveedor->nombre}' actualizado correctamente con Legajo [{$proveedor->legajo}].");
    }

    public function destroy(Proveedor $proveedor)
    {
        if ($proveedor->tieneOperaciones()) {
            return back()->with('error', "No se puede eliminar el proveedor porque posee productos asociados o saldo pendiente.");
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')
                         ->with('success', "Proveedor eliminado correctamente.");
    }
}
