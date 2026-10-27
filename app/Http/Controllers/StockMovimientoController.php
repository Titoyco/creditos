<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductoMovimiento;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\ProductoStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class StockMovimientoController extends Controller
{
    /**
     * Auditoría general de movimientos de stock.
     */
    public function index(Request $request)
    {
        $query = ProductoMovimiento::with(['producto.categoria', 'sucursal', 'usuario', 'sucursalOrigen', 'sucursalDestino']);

        if ($tipo = $request->input('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($sucursalId = $request->input('sucursal_id')) {
            $query->where('sucursal_id', $sucursalId);
        }

        if ($productoId = $request->input('producto_id')) {
            $query->where('producto_id', $productoId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('comprobante', 'like', "%{$search}%")
                  ->orWhere('motivo', 'like', "%{$search}%")
                  ->orWhereHas('producto', function ($pq) use ($search) {
                      $pq->where('descripcion', 'like', "%{$search}%")
                         ->orWhere('codigo', 'like', "%{$search}%")
                         ->orWhere('codigo_barra', 'like', "%{$search}%");
                  });
            });
        }

        if ($fechaDesde = $request->input('fecha_desde')) {
            $query->whereDate('created_at', '>=', $fechaDesde);
        }

        if ($fechaHasta = $request->input('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $fechaHasta);
        }

        $movimientos = $query->latest('id')->paginate(25);
        $sucursales = Sucursal::orderBy('nombre')->get();

        return view('productos.movimientos', compact('movimientos', 'sucursales'));
    }

    /**
     * Formulario de traslado entre sucursales (tipo punto de venta / carga por escaneo de lote).
     */
    public function trasladoForm(Request $request)
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        $sucursalActivaId = config('app.sucursal_activa.id') ?? ($sucursales->first()?->id ?? 1);
        $comprobanteSugerido = 'REM-' . date('Ymd-His');

        $productoSeleccionado = null;
        if ($request->filled('producto_id')) {
            $p = Producto::with(['categoria', 'subcategoria', 'stocks' => function ($q) use ($sucursalActivaId) {
                $q->where('sucursal_id', $sucursalActivaId);
            }])->find($request->producto_id);

            if ($p && $p->controla_stock) {
                $productoSeleccionado = [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'codigo_barra' => $p->codigo_barra,
                    'descripcion' => $p->descripcion,
                    'categoria' => $p->categoria?->nombre ?? 'GENERAL',
                    'subcategoria' => $p->subcategoria?->nombre ?? null,
                    'unidad_medida' => $p->unidad_medida ?? 'UN',
                    'stock_origen' => (float)($p->stocks->first()?->stock ?? 0),
                    'cantidad' => 1,
                ];
            }
        }

        return view('productos.traslado', compact('sucursales', 'sucursalActivaId', 'comprobanteSugerido', 'productoSeleccionado'));
    }

    /**
     * Endpoint API para búsqueda por código de barras / SKU / descripción para carga en lote.
     */
    public function buscarParaTraslado(Request $request)
    {
        $term = trim($request->input('q', ''));
        $sucursalId = (int)$request->input('sucursal_id', config('app.sucursal_activa.id') ?? 1);

        if (empty($term)) {
            return response()->json([]);
        }

        $productos = Producto::where('activo', true)
            ->where('controla_stock', true)
            ->where(function ($q) use ($term) {
                $q->where('codigo_barra', $term)
                  ->orWhere('codigo', $term)
                  ->orWhere('codigo', 'like', "%{$term}%")
                  ->orWhere('codigo_barra', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%");
            })
            ->with(['categoria', 'subcategoria', 'stocks' => function ($sq) use ($sucursalId) {
                $sq->where('sucursal_id', $sucursalId);
            }])
            ->limit(20)
            ->get();

        $resultado = $productos->map(function ($p) use ($term) {
            $stockOrigen = (float)($p->stocks->first()?->stock ?? 0);
            $esExacto = (
                strcasecmp($p->codigo_barra ?? '', $term) === 0 || 
                strcasecmp($p->codigo, $term) === 0
            );

            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'codigo_barra' => $p->codigo_barra,
                'descripcion' => $p->descripcion,
                'categoria' => $p->categoria?->nombre ?? 'GENERAL',
                'subcategoria' => $p->subcategoria?->nombre ?? null,
                'unidad_medida' => $p->unidad_medida ?? 'UN',
                'precio_venta' => (float)$p->precio_venta,
                'stock_origen' => $stockOrigen,
                'exact_match' => $esExacto,
            ];
        });

        return response()->json($resultado);
    }

    /**
     * Endpoint API para actualizar stock en origen de los productos en lote al cambiar la sucursal emisora.
     */
    public function verificarStock(Request $request)
    {
        $sucursalId = (int)$request->input('sucursal_id');
        $productoIds = (array)$request->input('producto_ids', []);

        if (empty($productoIds) || !$sucursalId) {
            return response()->json([]);
        }

        $stocks = ProductoStock::where('sucursal_id', $sucursalId)
            ->whereIn('producto_id', $productoIds)
            ->pluck('stock', 'producto_id');

        return response()->json($stocks);
    }

    /**
     * Procesa el traslado inter-sucursal (tanto lote de artículos como artículo individual).
     */
    public function trasladoStore(Request $request)
    {
        $validated = $request->validate([
            'sucursal_origen_id' => 'required|exists:sucursales,id',
            'sucursal_destino_id' => 'required|exists:sucursales,id|different:sucursal_origen_id',
            'comprobante' => 'nullable|string|max:100',
            'motivo' => 'nullable|string|max:255',
            // Soporta lote multi-artículo
            'items' => 'nullable|array',
            'items.*.producto_id' => 'required_with:items|exists:productos,id',
            'items.*.cantidad' => 'required_with:items|numeric|min:0.01',
            // Soporta artículo individual (compatibilidad retroactiva)
            'producto_id' => 'nullable|exists:productos,id',
            'cantidad' => 'nullable|numeric|min:0.01',
        ], [
            'sucursal_destino_id.different' => 'La sucursal de destino no puede ser la misma que la sucursal de origen.',
        ]);

        $sucursalOrigenId = (int)$validated['sucursal_origen_id'];
        $sucursalDestinoId = (int)$validated['sucursal_destino_id'];
        $comprobante = trim($validated['comprobante'] ?? '') ?: ('REM-' . date('Ymd-His'));
        $motivo = trim($validated['motivo'] ?? '') ?: 'Traslado inter-sucursal de mercadería';
        $usuarioId = Auth::id();

        // Consolidar lista de items a trasladar
        $itemsATrasladar = [];
        if (!empty($validated['items'])) {
            foreach ($validated['items'] as $it) {
                $cant = (float)$it['cantidad'];
                if ($cant > 0) {
                    $itemsATrasladar[] = [
                        'producto_id' => (int)$it['producto_id'],
                        'cantidad' => $cant,
                    ];
                }
            }
        } elseif (!empty($validated['producto_id']) && !empty($validated['cantidad'])) {
            $itemsATrasladar[] = [
                'producto_id' => (int)$validated['producto_id'],
                'cantidad' => (float)$validated['cantidad'],
            ];
        }

        if (empty($itemsATrasladar)) {
            return back()->withInput()->with('error', "Debe ingresar al menos un artículo con cantidad válida en el lote a trasladar.");
        }

        $sucursalOrigen = Sucursal::findOrFail($sucursalOrigenId);
        $sucursalDestino = Sucursal::findOrFail($sucursalDestinoId);

        try {
            DB::transaction(function () use ($itemsATrasladar, $sucursalOrigenId, $sucursalDestinoId, $usuarioId, $comprobante, $motivo) {
                foreach ($itemsATrasladar as $it) {
                    $producto = Producto::findOrFail($it['producto_id']);
                    $producto->trasladar(
                        $sucursalOrigenId,
                        $sucursalDestinoId,
                        $it['cantidad'],
                        $usuarioId,
                        $comprobante,
                        $motivo
                    );
                }
            });

            $totalArticulos = count($itemsATrasladar);
            $totalUnidades = array_sum(array_column($itemsATrasladar, 'cantidad'));

            return redirect()->route('stock.movimientos')
                             ->with('success', "Lote de traslado completado exitosamente: {$totalArticulos} artículos ({$totalUnidades} unidades) trasladados de {$sucursalOrigen->nombre} a {$sucursalDestino->nombre} bajo comprobante {$comprobante}.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', "Error al procesar el traslado: " . $e->getMessage());
        }
    }
}
