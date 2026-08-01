<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Models\Cuota;
use Illuminate\Http\Request;
use App\Models\Sucursal;
use App\Models\Presentacion;
use App\Models\SucursalIndice;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class CreditoController extends Controller
{
    public function index(Request $request)
    {
        $query = Credito::select('creditos.*')
            ->join('clientes', 'clientes.id', '=', 'creditos.id_cliente')
            ->with(['cliente', 'sucursal', 'autorizado.cliente'])
            ->withSum('cuotas as total_valor_base', 'valor_base')
            ->withSum('cuotas as total_entrego', 'entrego')
            ->withSum('cuotas as total_intereses', 'intereses')
            ->withSum('cuotas as total_saldo_actual', 'saldo_actual');

        // 1. FILTRO ESTRICTO POR CLIENTE ID (Si se pasa por URL)
        if ($request->filled('cliente_id')) {
            $query->where('creditos.id_cliente', $request->cliente_id);
        }

        // 2. Filtro por Búsqueda General (agrupado para no romper el cliente_id)
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('creditos.credito', 'LIKE', "%{$search}%")
                ->orWhereHas('cliente', function ($qCliente) use ($search) {
                    $qCliente->where('nombre', 'LIKE', "%{$search}%")
                            ->orWhere('dni', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('autorizado.cliente', function ($qAut) use ($search) {
                    $qAut->where('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('dni', 'LIKE', "%{$search}%");
                });
            });
        }

        // 3. Filtro por Sucursal
        if ($request->filled('sucursal') && $request->sucursal !== 'all') {
            $query->where('creditos.id_sucursal', $request->sucursal);
        }

        // 4. Filtro por Tipo de Crédito (C, D, R)
        if ($request->filled('tipo') && $request->tipo !== 'all') {
            $query->where('creditos.tipo_credito', $request->tipo);
        }

        // 5. Filtro por Estado (Abiertos / Cancelados)
        if ($request->filled('estado') && $request->estado !== 'all') {
            if ($request->estado === 'abiertos') {
                $query->whereNull('creditos.fecha_cancelacion');
            } elseif ($request->estado === 'cancelados') {
                $query->whereNotNull('creditos.fecha_cancelacion');
            }
        }

        // ====================================================================
        // 6. FILTROS FINANCIEROS (Corregidos usando whereHas)
        // ====================================================================
        
        // Interés > 0
        if ($request->boolean('has_interes')) {
            $query->whereHas('cuotas', function($q) {
                $q->where('intereses', '>', 0);
            });
        }

        // Entregó > 0
        if ($request->boolean('has_entrego')) {
            $query->whereHas('cuotas', function($q) {
                $q->where('entrego', '>', 0);
            });
        }

        // A Pagar (Saldo) > 0
        if ($request->boolean('has_saldo')) {
            $query->whereHas('cuotas', function($q) {
                $q->where('saldo_actual', '>', 0);
            });
        }

        // ====================================================================
        
        $creditos = $query->orderBy('creditos.fecha_credito', 'desc')
                        ->paginate(15)
                        ->withQueryString();

        $sucursales = Sucursal::all();

        return view('creditos.index', compact('creditos', 'sucursales'));
    }

    public function show(Credito $credito)
    {
        // Cargar relaciones necesarias
        $credito->load([
            'cliente',
            'sucursal',
            'autorizado.cliente',
            'cuotas' => function ($q) {
                $q->orderBy('num_cuota', 'asc');
            }
        ]);

        // Totales calculados sobre las cuotas para las tarjetas de resumen
        $totalValorBase = $credito->cuotas->sum('valor_base');
        $totalIntereses = $credito->cuotas->sum('intereses');
        $totalEntrego    = $credito->cuotas->sum('entrego');
        $totalSaldo      = $credito->cuotas->sum('saldo_actual');

        return view('creditos.show', compact(
            'credito', 
            'totalValorBase', 
            'totalIntereses', 
            'totalEntrego', 
            'totalSaldo'
        ));
    }

    public function pagarCuota(Request $request, Cuota $cuota)
    {
        // Lógica para registrar un pago manual
        $cuota->update([
            'entrego' => $request->monto,
            'fecha_cobro' => now(),
            'medio_pago' => $request->medio,
            'saldo_actual' => ($cuota->valor_base + $cuota->intereses) - $request->monto
        ]);

        return back()->with('status', 'Pago registrado correctamente');
    }

    public function create(Request $request)
        {
            $clienteId = $request->get('id_cliente');
            $autorizadoId = $request->get('id_autorizado');

            // Cargamos el cliente con sus relaciones clave
            $cliente = Cliente::with([
                'sucursal', 
                'creditos.cuotas', 
                'novedades.user', 
                'autorizados'
            ])->findOrFail($clienteId);
            
            // Verificamos si la operación la realiza una firma autorizada
            $autorizado = null;
            if ($autorizadoId) {
                $autorizado = $cliente->autorizados->where('id', $autorizadoId)->first();
            }

            // 1. Obtención de la Presentación Activa (Sesión -> Máximo ID de DB -> Fallback a 102)
            $presentacionActual = session('presentacion_actual') 
                ?? DB::table('presentaciones')->max('id') 
                ?? 102;

            // 2. Obtener Créditos Activos y sus Cuotas Pendientes de Pago
            $creditosActivos = $cliente->creditos->whereNull('fecha_cancelacion');
            
            // Obtenemos únicamente las cuotas que aún no fueron saldadas por completo
            $cuotasActivas = $creditosActivos->flatMap->cuotas->filter(function ($cuota) {
                return !isset($cuota->pagada) || $cuota->pagada == false || ($cuota->saldo_actual ?? 1) > 0;
            });

            // 3. Estructura para agrupar los vencimientos de los próximos 4 meses / presentaciones
            $vencimientos = [
                1 => ['cuotas' => collect(), 'total' => 0, 'presentacion' => $presentacionActual],
                2 => ['cuotas' => collect(), 'total' => 0, 'presentacion' => $presentacionActual + 1],
                3 => ['cuotas' => collect(), 'total' => 0, 'presentacion' => $presentacionActual + 2],
                4 => ['cuotas' => collect(), 'total' => 0, 'presentacion' => $presentacionActual + 3],
            ];

            // 4. Iteración y distribución de cuotas por presentación
        foreach ($cuotasActivas as $cuota) {
            // 1. Obtener la presentación
            $idPresentacionCredito = (int) ($cuota->credito->id_presentacion ?? $cuota->id_presentacion ?? 100);
            
            // 2. Número de cuota (evalúa varias opciones de nombres de columna)
            $nroCuota = (int) ($cuota->nro_cuota ?? $cuota->num_cuota ?? $cuota->cuota ?? 1);
            $cuota->numero_display = $nroCuota; // Guardamos explícitamente para el Blade

            // 3. Presentación de Cobro (id_presentacion + num_cuota)
            $presentacionCuota = $idPresentacionCredito + $nroCuota;

            // 4. Mapeo del valor puro de la cuota (Pasa por todas las variantes posibles de nombre)
            $valorPuro = (float) (
                $cuota->valor_base 
                ?? $cuota->importe 
                ?? $cuota->monto 
                ?? $cuota->valor 
                ?? $cuota->credito->valor_cuota 
                ?? $cuota->saldo_actual 
                ?? 0
            );

            $entregado = (float) ($cuota->entrego ?? $cuota->a_favor ?? 0);
            $saldoPendiente = max(0, $valorPuro - $entregado);

            // Si la cuota ya no debe nada, no se incluye en la grilla
            if ($saldoPendiente <= 0) {
                continue;
            }

            // Inyectamos el valor listo para Blade
            $cuota->saldo_neto_base = $saldoPendiente;

            // 5. Agrupamiento por mes
            if ($presentacionCuota <= $presentacionActual) {
                // Presentación actual o vencidas
                $vencimientos[1]['cuotas']->push($cuota);
                $vencimientos[1]['total'] += $saldoPendiente;
            } elseif ($presentacionCuota == $presentacionActual + 1) {
                // 2° Mes
                $vencimientos[2]['cuotas']->push($cuota);
                $vencimientos[2]['total'] += $saldoPendiente;
            } elseif ($presentacionCuota == $presentacionActual + 2) {
                // 3° Mes
                $vencimientos[3]['cuotas']->push($cuota);
                $vencimientos[3]['total'] += $saldoPendiente;
            } elseif ($presentacionCuota == $presentacionActual + 3) {
                // 4° Mes
                $vencimientos[4]['cuotas']->push($cuota);
                $vencimientos[4]['total'] += $saldoPendiente;
            }
        }

            // 5. Cálculo de Límites
            $limiteTotal = (float) ($cliente->limite ?? 800000);
            $totalOtorgadoActivo = (float) $creditosActivos->sum('importe_original');
            $limiteDisponible = max(0, $limiteTotal - $totalOtorgadoActivo);

            // 6. Totales Financieros de la Cartera Activa del Cliente
            $totalCreditosActivos = (float) $cuotasActivas->sum('valor_base');
            $totalEntregado = (float) $cuotasActivas->sum('entrego');
            $totalIntereses = (float) $cuotasActivas->sum('intereses');
            $totalAPagar = (float) $cuotasActivas->sum('saldo_actual');

            $aFavor = (float) $cliente->saldo ?? 0;

            return view('creditos.create', compact(
                'cliente',
                'autorizado',
                'presentacionActual',
                'vencimientos',
                'limiteTotal',
                'limiteDisponible',
                'totalEntregado',
                'totalCreditosActivos',
                'totalIntereses',
                'totalAPagar',
                'aFavor'
            ));
        }

    public function store(Request $request)
    {
        $request->validate([
            'id_cliente' => 'required|exists:clientes,id',
            'id_sucursal' => 'required|exists:sucursales,id',
            'id_presentacion' => 'required|exists:presentaciones,id',
            'tipo_credito' => 'required|in:C,D,R',
            'importe_original' => 'required|numeric|min:1',
            'cantidad_cuotas' => 'required|integer|min:1',
        ]);

        // Ejecutamos una transacción de base de datos para asegurar consistencia absoluta
        DB::transaction(function () use ($request) {
            
            // 1. Obtener y actualizar el correlativo único de la sucursal
            $indice = SucursalIndice::firstOrCreate(
                ['id_sucursal' => $request->id_sucursal, 'tipo_credito' => $request->tipo_credito],
                ['ultimo_indice' => 0]
            );
            
            $indice->increment('ultimo_indice');
            $nuevoNumeroIndice = $indice->ultimo_indice;

            // 2. Formatear el código de crédito para el campo 'credito' (ej: "IC125")
            $sucursal = Sucursal::find($request->id_sucursal);
            $codigoCredito = $sucursal->codigo_sucursal . $request->tipo_credito . $nuevoNumeroIndice;

            // 3. Traer la presentación para aplicar tasas de interés
            $presentacion = Presentacion::findOrFail($request->id_presentacion);
            
            // Cálculo de cuota de referencia (Asumiendo Interés Directo Simple para este borrador)
            $interesPorcentaje = $presentacion->tasa_cuotas ?? 0; // % ej: 15.00
            $montoConInteres = $request->importe_original * (1 + ($interesPorcentaje / 100));
            $valorCuotaRef = round($montoConInteres / $request->cantidad_cuotas, 2);

            // 4. Crear la cabecera del Crédito
            $credito = Credito::create([
                'id_cliente' => $request->id_cliente,
                'id_sucursal' => $request->id_sucursal,
                'id_autorizado' => $request->id_cliente, // Por defecto, el cliente es el autorizado
                'id_presentacion' => $request->id_presentacion,
                'credito' => $codigoCredito,
                'tipo_credito' => $request->tipo_credito,
                'importe_original' => $request->importe_original,
                'cantidad_cuotas' => $request->cantidad_cuotas,
                'valor_cuota_ref' => $valorCuotaRef,
                'fecha_credito' => now()->toDateString(),
            ]);

            // 5. Crear la cascada de Cuotas asociadas
            for ($i = 1; $i <= $request->cantidad_cuotas; $i++) {
                Cuota::create([
                    'id_credito' => $credito->id,
                    'num_cuota' => $i,
                    'valor_base' => $valorCuotaRef,
                    'intereses' => 0,
                    'entrego' => 0,
                    'saldo_actual' => $valorCuotaRef,
                    // Las fechas de vencimiento y envío bancario las definiremos con tus respuestas
                    'fecha_ultimo_envio' => null,
                    'estado_bancario' => null,
                ]);
            }
        });

        return redirect()->route('creditos.index')
                        ->with('success', 'Crédito y cuotas generados exitosamente.');
    }

    public function imprimirPagare(Credito $credito)
    {
        $credito->load(['cliente', 'sucursal', 'autorizado.cliente', 'cuotas']);

        $totalMonto = $credito->cantidad_cuotas * $credito->valor_cuota_ref;
        
        // Concepto
        if ($credito->tipo_credito === 'R') {
            $concepto = "En concepto de REFINANCIACION de creditos.";
        } else {
            $concepto = "En concepto de COMPRAS de mercaderias VARIAS.";
        }

        // Convertidor de números a letras para el Pagaré
        $formatter = new \NumberFormatter("es", \NumberFormatter::SPELLOUT);
        $montoEnLetras = mb_strtoupper($formatter->format($totalMonto));

        return view('creditos.pagare', compact('credito', 'totalMonto', 'concepto', 'montoEnLetras'));
    }

}