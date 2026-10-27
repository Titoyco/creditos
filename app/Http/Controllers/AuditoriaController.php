<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

class AuditoriaController extends Controller
{
    /**
     * Listado paginado de eventos de auditoría con filtros.
     */
    public function index(Request $request)
    {
        $query = Audit::with(['user', 'auditable'])->orderBy('id', 'desc');

        // Filtro por Modelo / Entidad
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        // Filtro por Evento (created, updated, deleted, etc.)
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        // Buscador por ID del registro auditado (auditable_id) o ID de auditoría
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('auditable_id', $search)
                  ->orWhere('id', $search);
            });
        }

        // Filtro opcional por fecha
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $audits = $query->paginate(20)->withQueryString();

        // Tipos de entidades auditadas registradas en el sistema para el selector
        $entidades = [
            'App\Models\Cliente'      => 'Cliente',
            'App\Models\Credito'      => 'Crédito',
            'App\Models\Cuota'        => 'Cuota',
            'App\Models\Presentacion' => 'Presentación',
        ];

        return view('sistema.auditoria.index', compact('audits', 'entidades'));
    }

    /**
     * Rollback / Deshacer una actualización de auditoría.
     */
    public function rollback(Audit $audit)
    {
        if ($audit->event !== 'updated') {
            return back()->with('error', 'Solo se pueden revertir eventos de modificación (updated).');
        }

        $auditable = $audit->auditable;

        if (! $auditable) {
            return back()->with('error', 'El registro original ya no existe en la base de datos.');
        }

        if (! method_exists($auditable, 'transitionTo')) {
            return back()->with('error', 'El modelo destino no soporta la transición de estados.');
        }

        try {
            // transitionTo($audit, true) aplica los old_values en el modelo
            $auditable->transitionTo($audit, true);
            $auditable->save();

            return back()->with('success', "Valores anteriores restaurados correctamente para el registro #{$audit->auditable_id}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo restaurar el estado anterior: ' . $e->getMessage());
        }
    }
}
