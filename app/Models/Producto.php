<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Exception;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'codigo_barra',
        'descripcion',
        'categoria_id',
        'subcategoria_id',
        'proveedor_id',
        'precio_costo',
        'precio_venta',
        'stock_minimo',
        'unidad_medida',
        'imagen',
        'observaciones',
        'activo',
        'controla_stock',
        'total_ventas_unidades',
        'total_ventas_operaciones',
        'fecha_alta',
        'fecha_ultima_venta',
        'fecha_ultima_compra',
        'fecha_modificacion',
    ];

    protected $casts = [
        'precio_costo' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'activo' => 'boolean',
        'controla_stock' => 'boolean',
        'total_ventas_unidades' => 'decimal:2',
        'total_ventas_operaciones' => 'integer',
        'fecha_alta' => 'datetime',
        'fecha_ultima_venta' => 'datetime',
        'fecha_ultima_compra' => 'datetime',
        'fecha_modificacion' => 'datetime',
    ];

    public function scopeInventariables($query)
    {
        return $query->where('controla_stock', true);
    }

    public function scopeGenericos($query)
    {
        return $query->where('controla_stock', false);
    }

    /**
     * Scope para productos sin rotación (sin ventas desde hace $meses meses)
     */
    public function scopeSinRotacion($query, int $meses = 24)
    {
        return $query->where('controla_stock', true)
            ->where(function ($q) use ($meses) {
                $q->where('fecha_ultima_venta', '<', now()->subMonths($meses))
                  ->orWhereNull('fecha_ultima_venta');
            });
    }

    public function diasSinVenta(): ?int
    {
        if (!$this->fecha_ultima_venta) {
            return null;
        }
        return (int)now()->diffInDays($this->fecha_ultima_venta);
    }

    public function getRotacionStatus(): array
    {
        if (!$this->controla_stock) {
            return ['label' => 'Sin Control', 'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'];
        }

        if (!$this->fecha_ultima_venta) {
            return ['label' => 'Sin ventas', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800'];
        }

        $dias = $this->diasSinVenta();
        if ($dias >= 730) { // +2 años
            $anos = round($dias / 365, 1);
            return [
                'label' => "+{$anos}a sin venta", 
                'class' => 'bg-red-100 text-red-800 dark:bg-red-950/70 dark:text-red-300 border border-red-300 dark:border-red-800 font-bold'
            ];
        } elseif ($dias >= 365) { // +1 año
            return [
                'label' => '+1a sin venta', 
                'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800'
            ];
        } elseif ($dias >= 180) { // +6 meses
            return [
                'label' => '+6m sin venta', 
                'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950/60 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800'
            ];
        }

        return [
            'label' => 'Venta reciente', 
            'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
        ];
    }

    public function esInventariable(): bool
    {
        return (bool)$this->controla_stock;
    }

    public function categoria()
    {
        return $this->belongsTo(ProductoCategoria::class, 'categoria_id');
    }

    public function subcategoria()
    {
        return $this->belongsTo(ProductoSubcategoria::class, 'subcategoria_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function stocks()
    {
        return $this->hasMany(ProductoStock::class, 'producto_id');
    }

    public function movimientos()
    {
        return $this->hasMany(ProductoMovimiento::class, 'producto_id');
    }

    public function sucursales()
    {
        return $this->belongsToMany(Sucursal::class, 'productos_stock')
                    ->withPivot('stock', 'stock_minimo', 'punto_compra', 'precio_venta', 'ubicacion')
                    ->withTimestamps();
    }

    /**
     * Obtiene el stock disponible en una sucursal específica.
     */
    public function getStockSucursal(int $sucursalId): float
    {
        $stockRow = $this->stocks()->where('sucursal_id', $sucursalId)->first();
        return $stockRow ? (float)$stockRow->stock : 0.0;
    }

    /**
     * Obtiene la suma de stock en todas las sucursales.
     */
    public function getStockTotal(): float
    {
        return (float)$this->stocks()->sum('stock');
    }

    /**
     * Registra un movimiento de stock atómico (alta, baja, corrección, venta, etc.)
     */
    public function ajustarStock(
        int $sucursalId,
        string $tipo,
        float $cantidad,
        ?string $motivo = null,
        ?int $usuarioId = null,
        ?string $comprobante = null,
        ?string $observaciones = null,
        ?Model $referencia = null
    ): ProductoMovimiento {
        return DB::transaction(function () use (
            $sucursalId, $tipo, $cantidad, $motivo, $usuarioId, $comprobante, $observaciones, $referencia
        ) {
            $stockRegistro = ProductoStock::firstOrCreate(
                ['producto_id' => $this->id, 'sucursal_id' => $sucursalId],
                [
                    'stock' => 0,
                    'stock_minimo' => $this->stock_minimo ?? 0,
                    'punto_compra' => 0,
                    'precio_venta' => $this->precio_venta,
                ]
            );

            $stockAnterior = (float)$stockRegistro->stock;
            $stockNuevo = $stockAnterior + $cantidad;

            // Actualizar stock de la sucursal
            $stockRegistro->stock = $stockNuevo;
            $stockRegistro->save();

            // Crear el registro de auditoría de movimiento
            $movimiento = new ProductoMovimiento([
                'producto_id' => $this->id,
                'sucursal_id' => $sucursalId,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'precio_costo' => $this->precio_costo,
                'precio_venta' => $stockRegistro->precio_venta ?? $this->precio_venta,
                'usuario_id' => $usuarioId,
                'comprobante' => $comprobante,
                'motivo' => $motivo,
                'observaciones' => $observaciones,
            ]);

            if ($referencia) {
                $movimiento->referencia()->associate($referencia);
            }

            $movimiento->save();

            return $movimiento;
        });
    }

    /**
     * Traslada stock de una sucursal origen a una sucursal destino de manera atómica.
     */
    public function trasladar(
        int $sucursalOrigenId,
        int $sucursalDestinoId,
        float $cantidad,
        ?int $usuarioId = null,
        ?string $comprobante = null,
        ?string $motivo = null
    ): array {
        if (!$this->controla_stock) {
            throw new Exception("El artículo '{$this->descripcion}' es genérico / sin control de stock y no admite traslados.");
        }

        if ($sucursalOrigenId === $sucursalDestinoId) {
            throw new Exception("La sucursal de origen y destino no pueden ser la misma.");
        }

        if ($cantidad <= 0) {
            throw new Exception("La cantidad a trasladar debe ser mayor a cero.");
        }

        return DB::transaction(function () use (
            $sucursalOrigenId, $sucursalDestinoId, $cantidad, $usuarioId, $comprobante, $motivo
        ) {
            // 1. Obtener o crear stock en origen
            $stockOrigen = ProductoStock::firstOrCreate(
                ['producto_id' => $this->id, 'sucursal_id' => $sucursalOrigenId],
                ['stock' => 0, 'stock_minimo' => $this->stock_minimo ?? 0]
            );

            if ((float)$stockOrigen->stock < $cantidad) {
                throw new Exception("Stock insuficiente en la sucursal de origen (Disponible: {$stockOrigen->stock}).");
            }

            $stockOrigenAnterior = (float)$stockOrigen->stock;
            $stockOrigenNuevo = $stockOrigenAnterior - $cantidad;
            $stockOrigen->stock = $stockOrigenNuevo;
            $stockOrigen->save();

            // Movimiento de salida en origen
            $movSalida = ProductoMovimiento::create([
                'producto_id' => $this->id,
                'sucursal_id' => $sucursalOrigenId,
                'tipo' => 'traslado_salida',
                'cantidad' => -$cantidad,
                'stock_anterior' => $stockOrigenAnterior,
                'stock_nuevo' => $stockOrigenNuevo,
                'precio_costo' => $this->precio_costo,
                'precio_venta' => $this->precio_venta,
                'usuario_id' => $usuarioId,
                'sucursal_origen_id' => $sucursalOrigenId,
                'sucursal_destino_id' => $sucursalDestinoId,
                'comprobante' => $comprobante ?? 'TRASLADO-ENVIO',
                'motivo' => $motivo ?? 'Traslado inter-sucursal',
            ]);

            // 2. Obtener o crear stock en destino
            $stockDestino = ProductoStock::firstOrCreate(
                ['producto_id' => $this->id, 'sucursal_id' => $sucursalDestinoId],
                ['stock' => 0, 'stock_minimo' => $this->stock_minimo ?? 0]
            );

            $stockDestinoAnterior = (float)$stockDestino->stock;
            $stockDestinoNuevo = $stockDestinoAnterior + $cantidad;
            $stockDestino->stock = $stockDestinoNuevo;
            $stockDestino->save();

            // Movimiento de entrada en destino
            $movEntrada = ProductoMovimiento::create([
                'producto_id' => $this->id,
                'sucursal_id' => $sucursalDestinoId,
                'tipo' => 'traslado_entrada',
                'cantidad' => $cantidad,
                'stock_anterior' => $stockDestinoAnterior,
                'stock_nuevo' => $stockDestinoNuevo,
                'precio_costo' => $this->precio_costo,
                'precio_venta' => $this->precio_venta,
                'usuario_id' => $usuarioId,
                'sucursal_origen_id' => $sucursalOrigenId,
                'sucursal_destino_id' => $sucursalDestinoId,
                'comprobante' => $comprobante ?? 'TRASLADO-RECEPCION',
                'motivo' => $motivo ?? 'Recepción de traslado inter-sucursal',
            ]);

            return [
                'salida' => $movSalida,
                'entrada' => $movEntrada,
            ];
        });
    }
}
