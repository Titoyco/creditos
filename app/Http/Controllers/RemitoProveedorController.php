<?php

namespace App\Http\Controllers;

use App\Models\RemitoProveedor;
use App\Models\RemitoProveedorItem;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\ProductoStock;
use App\Models\ProductoMovimiento;
use App\Models\ProductoCategoria;
use App\Models\ProductoSubcategoria;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class RemitoProveedorController extends Controller
{
    /**
     * Listado general de remitos recibidos.
     */
    public function index(Request $request)
    {
        $query = RemitoProveedor::with(['proveedor', 'sucursal', 'usuario', 'items']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero_remito', 'like', "%{$search}%")
                  ->orWhereHas('proveedor', function ($pq) use ($search) {
                      $pq->where('nombre', 'like', "%{$search}%")
                         ->orWhere('cuit', 'like', "%{$search}%")
                         ->orWhere('legajo', 'like', "%{$search}%");
                  });
            });
        }

        if ($proveedorId = $request->input('proveedor_id')) {
            $query->where('proveedor_id', $proveedorId);
        }

        if ($sucursalId = $request->input('sucursal_id')) {
            $query->where('sucursal_id', $sucursalId);
        }

        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }

        if ($fechaDesde = $request->input('fecha_desde')) {
            $query->whereDate('fecha_recepcion', '>=', $fechaDesde);
        }

        if ($fechaHasta = $request->input('fecha_hasta')) {
            $query->whereDate('fecha_recepcion', '<=', $fechaHasta);
        }

        $remitos = $query->latest('id')->paginate(20);
        $proveedores = Proveedor::orderBy('nombre')->get();
        $sucursales = Sucursal::orderBy('nombre')->get();

        return view('proveedores.remitos.index', compact('remitos', 'proveedores', 'sucursales'));
    }

    /**
     * Formulario interactivo de carga de remito de mercadería.
     */
    public function create(Request $request)
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get();
        $categorias = ProductoCategoria::orderBy('nombre')->get();
        $subcategorias = ProductoSubcategoria::orderBy('nombre')->get();

        $sucursalActivaId = session('sucursal_activa_id') 
            ?? config('app.sucursal_activa')->id 
            ?? Auth::user()->id_sucursal 
            ?? 1;

        $proveedorSeleccionadoId = $request->input('proveedor_id');

        return view('proveedores.remitos.create', compact(
            'sucursales',
            'proveedores',
            'categorias',
            'subcategorias',
            'sucursalActivaId',
            'proveedorSeleccionadoId'
        ));
    }

    /**
     * Procesar y guardar el remito de mercadería (completo o parcial).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_remito' => 'required|string|max:50',
            'proveedor_id' => 'required|exists:proveedores,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'fecha_recepcion' => 'required|date',
            'estado' => 'required|in:completado,parcial',
            'observaciones' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio_compra' => 'required|numeric|min:0',
            'items.*.precio_venta' => 'required|numeric|min:0',
        ], [
            'items.required' => 'Debes ingresar al menos un artículo en el remito.',
            'items.min' => 'Debes ingresar al menos un artículo en el remito.',
        ]);

        try {
            DB::beginTransaction();

            $sucursal = Sucursal::findOrFail($validated['sucursal_id']);
            $proveedor = Proveedor::findOrFail($validated['proveedor_id']);

            $remito = RemitoProveedor::create([
                'numero_remito' => strtoupper(trim($validated['numero_remito'])),
                'proveedor_id' => $validated['proveedor_id'],
                'sucursal_id' => $validated['sucursal_id'],
                'fecha_recepcion' => $validated['fecha_recepcion'],
                'usuario_id' => Auth::id(),
                'estado' => $validated['estado'],
                'total_costo' => 0,
                'total_venta_estimado' => 0,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            $totalCosto = 0;
            $totalVenta = 0;

            foreach ($validated['items'] as $itemData) {
                $producto = Producto::findOrFail($itemData['producto_id']);
                $cantidad = (float) $itemData['cantidad'];
                $precioCompra = (float) $itemData['precio_compra'];
                $precioVenta = (float) $itemData['precio_venta'];

                $precioVentaAnterior = (float) ($producto->precio_venta ?? 0);

                // 1. Obtener o crear registro de stock en la sucursal del remito
                $stockRow = ProductoStock::firstOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'sucursal_id' => $sucursal->id,
                    ],
                    [
                        'stock' => 0,
                        'stock_minimo' => 0,
                        'stock_maximo' => 100,
                    ]
                );

                $stockAnterior = (float) $stockRow->stock;
                $stockNuevo = $stockAnterior + $cantidad;

                // 2. Incrementar stock físico de la sucursal
                $stockRow->update(['stock' => $stockNuevo]);

                // 3. Actualizar precios de costo y venta del producto
                $producto->update([
                    'precio_costo' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'proveedor_id' => $producto->proveedor_id ?? $proveedor->id,
                ]);

                // 4. Crear renglón del remito
                RemitoProveedorItem::create([
                    'remito_id' => $remito->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_compra' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'precio_venta_anterior' => $precioVentaAnterior,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockNuevo,
                ]);

                // 5. Registrar movimiento de inventario auditable
                ProductoMovimiento::create([
                    'producto_id' => $producto->id,
                    'sucursal_id' => $sucursal->id,
                    'tipo' => 'ingreso_remito',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'precio_costo' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'usuario_id' => Auth::id(),
                    'comprobante' => "REM-" . $remito->numero_remito,
                    'motivo' => "Recepción de mercadería - Remito N° {$remito->numero_remito} ({$proveedor->nombre})",
                    'observaciones' => $remito->estado === 'parcial' ? 'Ingreso por entrega parcial' : 'Ingreso por recepción completa',
                    'referencia_type' => RemitoProveedor::class,
                    'referencia_id' => $remito->id,
                ]);

                $totalCosto += ($cantidad * $precioCompra);
                $totalVenta += ($cantidad * $precioVenta);
            }

            $remito->update([
                'total_costo' => $totalCosto,
                'total_venta_estimado' => $totalVenta,
            ]);

            DB::commit();

            $msg = $remito->estado === 'parcial' 
                ? "Remito #{$remito->numero_remito} guardado como ENTREGA PARCIAL. Stock incrementado exitosamente ({$remito->items()->count()} artículos)."
                : "Remito #{$remito->numero_remito} RECEPCIONADO Y COMPLETADO. Stock físico actualizado en {$sucursal->nombre}.";

            return redirect()->route('remitos.show', $remito->id)->with('success', $msg);

        } catch (Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error al procesar el remito: ' . $e->getMessage());
        }
    }

    /**
     * Ver comprobante y detalle de recepción del remito.
     */
    public function show(RemitoProveedor $remito)
    {
        $remito->load(['proveedor', 'sucursal', 'usuario', 'items.producto.categoria']);

        return view('proveedores.remitos.show', compact('remito'));
    }

    /**
     * Agregar más artículos a un remito guardado previamente como parcial.
     */
    public function agregarItems(Request $request, RemitoProveedor $remito)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio_compra' => 'required|numeric|min:0',
            'items.*.precio_venta' => 'required|numeric|min:0',
            'completar_remito' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $sucursal = $remito->sucursal;
            $proveedor = $remito->proveedor;
            $totalCostoAdicional = 0;
            $totalVentaAdicional = 0;

            foreach ($validated['items'] as $itemData) {
                $producto = Producto::findOrFail($itemData['producto_id']);
                $cantidad = (float) $itemData['cantidad'];
                $precioCompra = (float) $itemData['precio_compra'];
                $precioVenta = (float) $itemData['precio_venta'];
                $precioVentaAnterior = (float) ($producto->precio_venta ?? 0);

                $stockRow = ProductoStock::firstOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'sucursal_id' => $sucursal->id,
                    ],
                    [
                        'stock' => 0,
                        'stock_minimo' => 0,
                        'stock_maximo' => 100,
                    ]
                );

                $stockAnterior = (float) $stockRow->stock;
                $stockNuevo = $stockAnterior + $cantidad;
                $stockRow->update(['stock' => $stockNuevo]);

                $producto->update([
                    'precio_costo' => $precioCompra,
                    'precio_venta' => $precioVenta,
                ]);

                RemitoProveedorItem::create([
                    'remito_id' => $remito->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_compra' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'precio_venta_anterior' => $precioVentaAnterior,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockNuevo,
                ]);

                ProductoMovimiento::create([
                    'producto_id' => $producto->id,
                    'sucursal_id' => $sucursal->id,
                    'tipo' => 'ingreso_remito',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'precio_costo' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'usuario_id' => Auth::id(),
                    'comprobante' => "REM-" . $remito->numero_remito,
                    'motivo' => "Recepción de mercadería adicional - Remito N° {$remito->numero_remito} ({$proveedor->nombre})",
                    'observaciones' => 'Entrega complementaria en remito parcial',
                    'referencia_type' => RemitoProveedor::class,
                    'referencia_id' => $remito->id,
                ]);

                $totalCostoAdicional += ($cantidad * $precioCompra);
                $totalVentaAdicional += ($cantidad * $precioVenta);
            }

            $nuevoEstado = !empty($validated['completar_remito']) ? 'completado' : $remito->estado;

            $remito->update([
                'total_costo' => $remito->total_costo + $totalCostoAdicional,
                'total_venta_estimado' => $remito->total_venta_estimado + $totalVentaAdicional,
                'estado' => $nuevoEstado,
            ]);

            DB::commit();

            return redirect()->route('remitos.show', $remito->id)
                             ->with('success', "Se agregaron nuevos artículos al Remito #{$remito->numero_remito} y se actualizó el stock.");

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al agregar artículos: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint API para buscar productos dinámicamente en el formulario de remito.
     */
    public function buscarProductos(Request $request)
    {
        $term = trim($request->input('q', ''));
        if (strlen($term) < 1) {
            return response()->json([]);
        }

        $sucursalId = (int) ($request->input('sucursal_id') 
            ?? session('sucursal_activa_id') 
            ?? Auth::user()->id_sucursal 
            ?? 1);

        $productos = Producto::with(['stocks' => function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            }, 'proveedor', 'categoria'])
            ->where(function ($q) use ($term) {
                $q->where('codigo', 'like', "%{$term}%")
                  ->orWhere('codigo_barra', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%");
            })
            ->where('activo', true)
            ->take(15)
            ->get()
            ->map(function ($prod) {
                $stockSede = (float) ($prod->stocks->first()?->stock ?? 0);
                $costo = (float) ($prod->precio_costo ?? 0);
                $venta = (float) ($prod->precio_venta ?? ($costo * 2));

                return [
                    'id' => $prod->id,
                    'codigo' => $prod->codigo,
                    'codigo_barra' => $prod->codigo_barra,
                    'descripcion' => $prod->descripcion,
                    'categoria' => $prod->categoria?->nombre ?? 'Sin Categoría',
                    'proveedor' => $prod->proveedor?->nombre ?? 'N/A',
                    'stock_actual' => $stockSede,
                    'precio_costo' => $costo,
                    'precio_venta' => $venta,
                    'precio_venta_sugerido' => round($costo * 2, 2),
                ];
            });

        return response()->json($productos);
    }

    /**
     * Crear producto nuevo en el catálogo directamente desde la pantalla de remito.
     */
    public function crearProductoRapido(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => 'required|string|max:200',
            'codigo' => 'nullable|string|max:50',
            'codigo_barra' => 'nullable|string|max:50',
            'proveedor_id' => 'required|exists:proveedores,id',
            'categoria_id' => 'nullable|exists:productos_categorias,id',
            'subcategoria_id' => 'nullable|exists:productos_subcategorias,id',
            'precio_costo' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
        ]);

        $proveedor = Proveedor::findOrFail($validated['proveedor_id']);

        // Si no viene código de barra ni código, generar código interno
        $codigoBarra = !empty($validated['codigo_barra']) ? trim($validated['codigo_barra']) : null;
        $codigo = !empty($validated['codigo']) ? trim($validated['codigo']) : ($codigoBarra ?? 'ART-' . strtoupper(uniqid()));

        // Verificar unicidad de código
        if (Producto::where('codigo', $codigo)->exists()) {
            $codigo = $codigo . '-' . rand(10, 99);
        }

        $producto = Producto::create([
            'codigo' => $codigo,
            'codigo_barra' => $codigoBarra,
            'descripcion' => strtoupper(trim($validated['descripcion'])),
            'proveedor_id' => $proveedor->id,
            'categoria_id' => $validated['categoria_id'] ?? null,
            'subcategoria_id' => $validated['subcategoria_id'] ?? null,
            'precio_costo' => $validated['precio_costo'],
            'precio_venta' => $validated['precio_venta'],
            'controla_stock' => true,
            'activo' => true,
        ]);

        return response()->json([
            'success' => true,
            'producto' => [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'codigo_barra' => $producto->codigo_barra,
                'descripcion' => $producto->descripcion,
                'categoria' => $producto->categoria?->nombre ?? 'General',
                'proveedor' => $proveedor->nombre,
                'stock_actual' => 0,
                'precio_costo' => (float) $producto->precio_costo,
                'precio_venta' => (float) $producto->precio_venta,
                'precio_venta_sugerido' => round($producto->precio_costo * 2, 2),
            ],
            'message' => "Producto '{$producto->descripcion}' dado de alta en catálogo.",
        ]);
    }
}
