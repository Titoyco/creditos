<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PuntoVentaController extends Controller
{
    /**
     * Listado de puntos de venta con filtros y paginación.
     */
    public function index(Request $request)
    {
        $query = PuntoVenta::with('sucursal');

        if ($request->filled('id_sucursal')) {
            $query->where('id_sucursal', $request->id_sucursal);
        }

        if ($request->filled('tipo_emision')) {
            $query->where('tipo_emision', $request->tipo_emision);
        }

        $puntosVenta = $query
            ->orderBy('id_sucursal')
            ->orderBy('numero')
            ->paginate(15)
            ->withQueryString();

        $sucursales = Sucursal::orderBy('nombre')->get();

        return view('sistema.puntos-venta.index', compact('puntosVenta', 'sucursales'));
    }

    /**
     * Formulario para crear un nuevo punto de venta.
     */
    public function create()
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        return view('sistema.puntos-venta.create', compact('sucursales'));
    }

    /**
     * Almacena un nuevo punto de venta en la base de datos.
     */
    public function store(Request $request)
    {
        $rules = [
            'id_sucursal'  => 'required|exists:sucursales,id',
            'numero'       => [
                'required',
                'integer',
                'min:1',
                'max:99999',
                Rule::unique('puntos_venta')->where(fn ($query) => $query->where('id_sucursal', $request->id_sucursal)),
            ],
            'nombre'       => 'required|string|max:100',
            'tipo_emision' => 'required|in:fiscal_hasar,arca_wsfe',
            'activo'       => 'nullable|boolean',
        ];

        if ($request->tipo_emision === 'fiscal_hasar') {
            $rules['hasar_ip']     = 'required|ipv4';
            $rules['hasar_puerto'] = 'required|integer|min:1|max:65535';
            $rules['puerto_local'] = 'required|integer|min:1024|max:65535';
            $rules['modelo']       = 'nullable|string|max:50';
        } elseif ($request->tipo_emision === 'arca_wsfe') {
            $rules['ambiente']     = 'required|in:homologacion,produccion';
            $rules['concepto']     = 'nullable|integer|in:1,2,3';
        }

        $validated = $request->validate($rules);

        $configuracion = [];
        if ($request->tipo_emision === 'fiscal_hasar') {
            $configuracion = [
                'puerto_local' => (int) $request->puerto_local,
                'hasar_ip'     => $request->hasar_ip,
                'hasar_puerto' => (int) $request->hasar_puerto,
                'modelo'       => $request->input('modelo', 'SMH/PT-250F') ?: 'SMH/PT-250F',
            ];
        } elseif ($request->tipo_emision === 'arca_wsfe') {
            $configuracion = [
                'ambiente' => $request->ambiente,
                'concepto' => (int) ($request->input('concepto', 1) ?: 1),
            ];
        }

        PuntoVenta::create([
            'id_sucursal'   => $request->id_sucursal,
            'numero'        => (int) $request->numero,
            'nombre'        => $request->nombre,
            'tipo_emision'  => $request->tipo_emision,
            'configuracion' => $configuracion,
            'activo'        => $request->has('activo') ? $request->boolean('activo') : true,
        ]);

        return redirect()->route('sistema.puntos-venta.index')
            ->with('success', 'Punto de venta registrado exitosamente.');
    }

    /**
     * Formulario de edición para un punto de venta.
     */
    public function edit(PuntoVenta $puntoVenta)
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        return view('sistema.puntos-venta.edit', compact('puntoVenta', 'sucursales'));
    }

    /**
     * Actualiza el punto de venta en la base de datos.
     */
    public function update(Request $request, PuntoVenta $puntoVenta)
    {
        $rules = [
            'id_sucursal'  => 'required|exists:sucursales,id',
            'numero'       => [
                'required',
                'integer',
                'min:1',
                'max:99999',
                Rule::unique('puntos_venta')
                    ->where(fn ($query) => $query->where('id_sucursal', $request->id_sucursal))
                    ->ignore($puntoVenta->id),
            ],
            'nombre'       => 'required|string|max:100',
            'tipo_emision' => 'required|in:fiscal_hasar,arca_wsfe',
            'activo'       => 'nullable|boolean',
        ];

        if ($request->tipo_emision === 'fiscal_hasar') {
            $rules['hasar_ip']     = 'required|ipv4';
            $rules['hasar_puerto'] = 'required|integer|min:1|max:65535';
            $rules['puerto_local'] = 'required|integer|min:1024|max:65535';
            $rules['modelo']       = 'nullable|string|max:50';
        } elseif ($request->tipo_emision === 'arca_wsfe') {
            $rules['ambiente']     = 'required|in:homologacion,produccion';
            $rules['concepto']     = 'nullable|integer|in:1,2,3';
        }

        $validated = $request->validate($rules);

        $configuracion = [];
        if ($request->tipo_emision === 'fiscal_hasar') {
            $configuracion = [
                'puerto_local' => (int) $request->puerto_local,
                'hasar_ip'     => $request->hasar_ip,
                'hasar_puerto' => (int) $request->hasar_puerto,
                'modelo'       => $request->input('modelo', 'SMH/PT-250F') ?: 'SMH/PT-250F',
            ];
        } elseif ($request->tipo_emision === 'arca_wsfe') {
            $configuracion = [
                'ambiente' => $request->ambiente,
                'concepto' => (int) ($request->input('concepto', 1) ?: 1),
            ];
        }

        $puntoVenta->update([
            'id_sucursal'   => $request->id_sucursal,
            'numero'        => (int) $request->numero,
            'nombre'        => $request->nombre,
            'tipo_emision'  => $request->tipo_emision,
            'configuracion' => $configuracion,
            'activo'        => $request->has('activo') ? $request->boolean('activo') : false,
        ]);

        return redirect()->route('sistema.puntos-venta.index')
            ->with('success', 'Punto de venta actualizado correctamente.');
    }

    /**
     * Elimina el punto de venta especificado.
     */
    public function destroy(PuntoVenta $puntoVenta)
    {
        $puntoVenta->delete();

        return redirect()->route('sistema.puntos-venta.index')
            ->with('success', 'Punto de venta eliminado correctamente.');
    }

    /**
     * Descarga el archivo config.json listo para el puente local de Windows.
     */
    public function descargarConfig(PuntoVenta $puntoVenta)
    {
        if ($puntoVenta->tipo_emision !== 'fiscal_hasar') {
            return redirect()->back()
                ->with('error', 'El archivo config.json sólo está disponible para puntos de venta con Controlador Fiscal Hasar.');
        }

        $payload = [
            'punto_venta_id'     => $puntoVenta->id,
            'punto_venta_numero' => $puntoVenta->numero,
            'sucursal'           => $puntoVenta->sucursal ? $puntoVenta->sucursal->nombre : 'Sin Sucursal',
            'puerto_local'       => (int) ($puntoVenta->configuracion['puerto_local'] ?? 8585),
            'hasar_ip'           => $puntoVenta->configuracion['hasar_ip'] ?? '',
            'hasar_puerto'       => (int) ($puntoVenta->configuracion['hasar_puerto'] ?? 2000),
            'modelo'             => $puntoVenta->configuracion['modelo'] ?? 'SMH/PT-250F',
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type'        => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="config.json"',
        ]);
    }
}

