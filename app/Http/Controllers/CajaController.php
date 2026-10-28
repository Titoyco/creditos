<?php

namespace App\Http\Controllers;

use App\Models\CajaSesion;
use App\Models\CajaMovimiento;
use App\Models\Sucursal;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    /**
     * Listado e Historial de Sesiones de Caja y Cierres Z.
     */
    public function index(Request $request)
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        $sucursalActiva = config('app.sucursal_activa') ?? Sucursal::first();
        $sucursalFiltro = $request->get('sucursal', (string)($sucursalActiva?->id ?? 1));
        $sucursalId = $sucursalFiltro === 'all' ? (int)($sucursalActiva?->id ?? 1) : (int)$sucursalFiltro;

        $query = CajaSesion::with(['sucursal', 'usuarioApertura', 'usuarioCierre']);

        // Filtro por sucursal
        if ($sucursalFiltro !== 'all') {
            $query->where('id_sucursal', $sucursalId);
        }

        // Filtro por estado
        if ($request->filled('estado') && $request->estado !== 'all') {
            $query->where('estado', $request->estado);
        }

        // Filtro por fechas
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_apertura', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_apertura', '<=', $request->fecha_hasta);
        }

        // Click-to-Sort
        $sort = $request->get('sort', 'fecha_apertura');
        $direction = strtolower($request->get('direction', 'desc'));
        if (!in_array($sort, ['id', 'fecha_apertura', 'fecha_cierre', 'total_general', 'monto_inicial_efectivo', 'diferencia_efectivo', 'estado'])) {
            $sort = 'fecha_apertura';
        }
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }

        // Totales agregados de la consulta filtrada (por medio de pago, ventas e ingresos/egresos)
        $totalesFiltro = (clone $query)->selectRaw('
            COALESCE(SUM(total_general), 0) as total_facturado,
            COALESCE(SUM(total_efectivo), 0) as total_efectivo,
            COALESCE(SUM(total_tarjeta_debito), 0) as total_debito,
            COALESCE(SUM(total_tarjeta_credito), 0) as total_credito,
            COALESCE(SUM(total_credito_personal), 0) as total_credito_personal,
            COALESCE(SUM(total_transferencia), 0) as total_transferencia,
            COALESCE(SUM(total_otros), 0) as total_otros,
            COALESCE(SUM(total_ingresos_efectivo), 0) as total_ingresos,
            COALESCE(SUM(total_retiros_efectivo), 0) as total_retiros,
            COALESCE(SUM(cant_ventas_total), 0) as cant_ventas
        ')->first();

        $cajas = $query->paginate(20)->withQueryString();

        // Sesión de caja abierta actual de la sucursal activa
        $cajaActiva = CajaSesion::activaParaSucursal($sucursalId);
        $totalesActiva = $cajaActiva ? $cajaActiva->calcularTotales() : null;
        $totalesCajaActiva = $totalesActiva;
        $requiereCierreAnterior = $cajaActiva && !$cajaActiva->esDeHoy();

        return view('cajas.index', compact(
            'cajas',
            'sucursales',
            'sucursalActiva',
            'sucursalId',
            'sucursalFiltro',
            'cajaActiva',
            'totalesActiva',
            'totalesCajaActiva',
            'requiereCierreAnterior',
            'totalesFiltro',
            'sort',
            'direction'
        ));
    }

    /**
     * API JSON: Consulta de estado actual de la caja para la sucursal activa (usado por el POS).
     */
    public function estadoActual(Request $request)
    {
        $sucursalId = (int) ($request->get('sucursal_id') ?? config('app.sucursal_activa.id') ?? auth()->user()->id_sucursal ?? 1);
        $caja = CajaSesion::activaParaSucursal($sucursalId);

        if (!$caja) {
            return response()->json([
                'abierta'       => false,
                'caja'          => null,
                'es_de_hoy'     => false,
                'requiere_cierre_anterior' => false,
            ]);
        }

        $esDeHoy = $caja->esDeHoy();
        $totales = $caja->calcularTotales();

        return response()->json([
            'abierta'       => true,
            'caja'          => [
                'id'                     => $caja->id,
                'sucursal'               => $caja->sucursal?->nombre,
                'id_sucursal'            => $caja->id_sucursal,
                'fecha_apertura'         => $caja->fecha_apertura?->format('d/m/Y H:i'),
                'fecha_apertura_raw'     => $caja->fecha_apertura?->toIso8601String(),
                'monto_inicial_efectivo' => (float) $caja->monto_inicial_efectivo,
                'usuario_apertura'       => $caja->usuarioApertura?->name,
                'estado'                 => $caja->estado,
            ],
            'es_de_hoy'                 => $esDeHoy,
            'requiere_cierre_anterior'  => !$esDeHoy,
            'totales'                   => $totales,
        ]);
    }

    /**
     * Apertura de una nueva sesión de caja.
     */
    public function abrir(Request $request)
    {
        $validated = $request->validate([
            'id_sucursal'            => 'nullable|exists:sucursales,id',
            'monto_inicial_efectivo' => 'required|numeric|min:0',
            'observaciones'          => 'nullable|string|max:500',
        ]);

        $sucursalId = (int) ($validated['id_sucursal'] ?? config('app.sucursal_activa.id') ?? auth()->user()->id_sucursal ?? 1);
        $sucursal = Sucursal::findOrFail($sucursalId);

        // Verificar si ya existe una caja abierta para esta sucursal
        $existente = CajaSesion::activaParaSucursal($sucursalId);
        if ($existente) {
            $msg = "La sucursal {$sucursal->nombre} ya posee una caja abierta (Sesión #{$existente->id}) desde el {$existente->fecha_apertura?->format('d/m/Y H:i')}.";
            return $request->wantsJson() ? response()->json(['error' => $msg], 422) : back()->with('error', $msg);
        }

        $caja = CajaSesion::create([
            'id_sucursal'            => $sucursalId,
            'id_usuario_apertura'    => auth()->id() ?? 1,
            'fecha_apertura'         => now(),
            'monto_inicial_efectivo' => (float) $validated['monto_inicial_efectivo'],
            'estado'                 => 'abierta',
            'observaciones_apertura' => $validated['observaciones'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Caja abierta exitosamente con un fondo inicial de $ " . number_format($caja->monto_inicial_efectivo, 2, ',', '.'),
                'caja'    => $caja,
            ]);
        }

        return redirect()->route('cajas.index', ['sucursal' => $sucursalId])
            ->with('success', "Caja #{$caja->id} abierta exitosamente para {$sucursal->nombre}.");
    }

    /**
     * Cierre de caja de jornada anterior y apertura inmediata de la jornada de hoy (Cierre Z automático / transición).
     */
    public function cerrarAnteriorYAbrir(Request $request)
    {
        if ($request->has('efectivo_real_contado') && !$request->has('monto_final_efectivo_real')) {
            $request->merge(['monto_final_efectivo_real' => $request->get('efectivo_real_contado')]);
        }

        $validated = $request->validate([
            'id_caja_anterior'         => 'required|exists:cajas_sesiones,id',
            'monto_final_efectivo_real'=> 'required|numeric|min:0',
            'observaciones_cierre'     => 'nullable|string|max:500',
            'monto_inicial_efectivo'   => 'required|numeric|min:0',
            'observaciones_apertura'   => 'nullable|string|max:500',
        ]);

        $cajaAnterior = CajaSesion::findOrFail($validated['id_caja_anterior']);
        if ($cajaAnterior->estado === 'cerrada') {
            return back()->with('error', 'La caja anterior ya se encuentra cerrada.');
        }

        $user = auth()->user();
        $sucursalId = $cajaAnterior->id_sucursal;

        $resultado = DB::transaction(function () use ($cajaAnterior, $validated, $user, $sucursalId) {
            // 1. Cerrar caja anterior y computar arqueo
            $cajaAnterior->cerrar(
                (float) $validated['monto_final_efectivo_real'],
                $validated['observaciones_cierre'] ?? 'Cierre automático al inicio de la jornada siguiente',
                $user?->id
            );

            // 2. Abrir la nueva caja de hoy
            $nuevaCaja = CajaSesion::create([
                'id_sucursal'            => $sucursalId,
                'id_usuario_apertura'    => $user?->id ?? 1,
                'fecha_apertura'         => now(),
                'monto_inicial_efectivo' => (float) $validated['monto_inicial_efectivo'],
                'estado'                 => 'abierta',
                'observaciones_apertura' => $validated['observaciones_apertura'] ?? null,
            ]);

            return ['caja_anterior' => $cajaAnterior, 'nueva_caja' => $nuevaCaja];
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Caja anterior #{$cajaAnterior->id} cerrada exitosamente y nueva caja #{$resultado['nueva_caja']->id} abierta.",
                'caja_anterior_id' => $cajaAnterior->id,
                'nueva_caja'       => $resultado['nueva_caja'],
            ]);
        }

        return redirect()->route('cajas.comprobante', $cajaAnterior->id)
            ->with('success', "Cierre Z de la caja anterior #{$cajaAnterior->id} registrado y nueva caja abierta para hoy.");
    }

    /**
     * Formulario interactivo de Cierre Z y Arqueo de Caja.
     */
    public function cerrarForm(CajaSesion $caja)
    {
        if ($caja->estado === 'cerrada') {
            return redirect()->route('cajas.comprobante', $caja->id);
        }

        $caja->load(['sucursal', 'usuarioApertura', 'movimientos.usuario']);
        $totales = $caja->calcularTotales();

        return view('cajas.cerrar', compact('caja', 'totales'));
    }

    /**
     * Procesa y confirma el Cierre Z de una caja.
     */
    public function cerrar(Request $request, CajaSesion $caja)
    {
        if ($caja->estado === 'cerrada') {
            $msg = 'Esta caja ya se encuentra cerrada.';
            return $request->wantsJson() ? response()->json(['error' => $msg], 422) : redirect()->route('cajas.comprobante', $caja->id);
        }

        $validated = $request->validate([
            'monto_final_efectivo_real' => 'required|numeric|min:0',
            'observaciones'             => 'nullable|string|max:500',
        ]);

        $caja->cerrar(
            (float) $validated['monto_final_efectivo_real'],
            $validated['observaciones'] ?? null,
            auth()->id()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => "Cierre Z ejecutado correctamente.",
                'caja_id'      => $caja->id,
                'redirect_url' => route('cajas.comprobante', $caja->id),
            ]);
        }

        return redirect()->route('cajas.comprobante', ['caja' => $caja->id, 'formato' => 'ticket'])
            ->with('success', "Cierre Z de la sesión #{$caja->id} registrado correctamente.");
    }

    /**
     * Vista de detalle de una sesión de caja específica: totales por medio de pago,
     * desglose de efectivo (apertura, ingresos, egresos, cierre) y listado de movimientos y ventas.
     */
    public function show(CajaSesion $caja)
    {
        $caja->load(['sucursal.provincia', 'usuarioApertura', 'usuarioCierre', 'movimientos.usuario']);
        
        $ventas = $caja->ventas()
            ->with(['cliente', 'usuario'])
            ->orderBy('id', 'desc')
            ->paginate(50);

        $totales = $caja->calcularTotales();
        $movimientos = $caja->movimientos()->orderBy('id', 'desc')->get();

        return view('cajas.show', compact('caja', 'totales', 'ventas', 'movimientos'));
    }

    /**
     * Vista de impresión oficial de Cierre Z (Ticket térmico 80mm y A4).
     */
    public function comprobante(Request $request, CajaSesion $caja)
    {
        $caja->load(['sucursal.provincia', 'usuarioApertura', 'usuarioCierre', 'ventas', 'movimientos.usuario']);
        $totales = $caja->calcularTotales();

        $formato = $request->get('formato', 'ticket');

        // Primer y último comprobante emitido en la sesión
        $primerComprobante = $caja->ventas()->orderBy('id', 'asc')->value('comprobante');
        $ultimoComprobante = $caja->ventas()->orderBy('id', 'desc')->value('comprobante');

        return view('cajas.comprobante', compact('caja', 'totales', 'primerComprobante', 'ultimoComprobante', 'formato'));
    }

    /**
     * Registra un ingreso o retiro manual de efectivo en la caja activa con su motivo justificado.
     */
    public function storeMovimiento(Request $request, CajaSesion $caja)
    {
        if ($caja->estado !== 'abierta') {
            $msg = 'No se pueden registrar movimientos en una caja que no esté abierta.';
            return $request->wantsJson() ? response()->json(['error' => $msg], 422) : back()->with('error', $msg);
        }

        $validated = $request->validate([
            'tipo'        => 'required|in:ingreso,retiro',
            'monto'       => 'required|numeric|min:0.01',
            'motivo'      => 'required|string|max:255',
            'comprobante' => 'nullable|string|max:100',
        ]);

        $movimiento = $caja->movimientos()->create([
            'id_usuario'  => auth()->id() ?? 1,
            'tipo'        => $validated['tipo'],
            'monto'       => (float) $validated['monto'],
            'motivo'      => trim($validated['motivo']),
            'comprobante' => !empty($validated['comprobante']) ? trim($validated['comprobante']) : null,
        ]);

        $totales = $caja->calcularTotales();

        if ($request->wantsJson()) {
            return response()->json([
                'success'           => true,
                'message'           => ($movimiento->tipo === 'ingreso' ? 'Ingreso' : 'Retiro') . " de efectivo registrado correctamente ($ " . number_format($movimiento->monto, 2, ',', '.') . ").",
                'movimiento'        => $movimiento,
                'efectivo_esperado' => $totales['efectivo_esperado'],
                'totales'           => $totales,
            ]);
        }

        $tipoStr = $movimiento->tipo === 'ingreso' ? 'Ingreso' : 'Retiro';
        return back()->with('success', "{$tipoStr} de efectivo por $ " . number_format($movimiento->monto, 2, ',', '.') . " asentado correctamente ({$movimiento->motivo}).");
    }

    /**
     * Retorna la lista de movimientos de efectivo de una caja en formato JSON.
     */
    public function movimientosJson(CajaSesion $caja)
    {
        $movimientos = $caja->movimientos()
            ->with('usuario')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'movimientos' => $movimientos,
            'totales'     => $caja->calcularTotales(),
        ]);
    }

    /**
     * Reporte Histórico de Facturación por Medios de Pago.
     * Consulta directamente sobre la tabla ventas donde residen los 137.000+ registros históricos.
     */
    public function historicoMedios(Request $request)
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        $sucursalId = $request->get('sucursal', 'all');
        $periodo = $request->get('periodo', 'mes'); // 'dia', 'mes', 'anio'
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');

        $query = Venta::where('estado', '!=', 'anulada');

        if ($sucursalId !== 'all' && is_numeric($sucursalId)) {
            $query->where('id_sucursal', (int) $sucursalId);
        }

        if ($fechaDesde) {
            $query->whereDate('fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->whereDate('fecha', '<=', $fechaHasta);
        }

        // Resumen total por medio de pago
        $porMedio = (clone $query)
            ->selectRaw('medio_pago, count(*) as cant_ventas, sum(total) as total_monto')
            ->groupBy('medio_pago')
            ->orderByDesc('total_monto')
            ->get();

        $totalGeneral = (float) $porMedio->sum('total_monto');
        $cantidadGeneral = (int) $porMedio->sum('cant_ventas');

        // Desglose cruzado por sucursal y medio de pago
        $porSucursalMedio = (clone $query)
            ->join('sucursales', 'ventas.id_sucursal', '=', 'sucursales.id')
            ->selectRaw('ventas.id_sucursal, sucursales.nombre as sucursal_nombre, ventas.medio_pago, count(*) as cant_ventas, sum(ventas.total) as total_monto')
            ->groupBy('ventas.id_sucursal', 'sucursales.nombre', 'ventas.medio_pago')
            ->orderBy('sucursales.nombre')
            ->orderByDesc('total_monto')
            ->get();

        // Exportación CSV
        if ($request->get('export') === 'csv') {
            return response()->streamDownload(function () use ($porMedio, $totalGeneral, $cantidadGeneral) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Medio de Pago', 'Cantidad Ventas', 'Total Facturado ($ ARS)', 'Participacion (%)']);
                foreach ($porMedio as $fila) {
                    $pct = $totalGeneral > 0 ? round(($fila->total_monto / $totalGeneral) * 100, 2) : 0;
                    fputcsv($handle, [$fila->medio_pago, $fila->cant_ventas, $fila->total_monto, $pct]);
                }
                fputcsv($handle, ['TOTAL GENERAL', $cantidadGeneral, $totalGeneral, 100.0]);
                fclose($handle);
            }, 'reporte_historico_medios_' . date('Ymd_His') . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        // Evolución temporal agrupada (compatible con MySQL y SQLite de tests)
        $evolucionQuery = clone $query;
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $sqliteFmt = match($periodo) {
                'dia' => '%Y-%m-%d',
                'anio' => '%Y',
                default => '%Y-%m',
            };
            $dateExpr = "strftime('{$sqliteFmt}', fecha)";
        } else {
            $formatoFecha = match($periodo) {
                'dia' => '%Y-%m-%d',
                'anio' => '%Y',
                default => '%Y-%m',
            };
            $dateExpr = "DATE_FORMAT(fecha, '{$formatoFecha}')";
        }

        $evolucion = $evolucionQuery
            ->selectRaw("{$dateExpr} as periodo_label, medio_pago, sum(total) as total, count(*) as cantidad")
            ->groupBy('periodo_label', 'medio_pago')
            ->orderBy('periodo_label', 'desc')
            ->limit(100)
            ->get();

        $filtroSucursal = $sucursalId;

        return view('cajas.historico_medios', compact(
            'sucursales',
            'filtroSucursal',
            'sucursalId',
            'periodo',
            'fechaDesde',
            'fechaHasta',
            'porMedio',
            'porSucursalMedio',
            'totalGeneral',
            'cantidadGeneral',
            'evolucion'
        ));
    }
}
