<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class CajaSesion extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cajas_sesiones';

    protected $fillable = [
        'id_sucursal',
        'id_usuario_apertura',
        'id_usuario_cierre',
        'fecha_apertura',
        'fecha_cierre',
        'monto_inicial_efectivo',
        'monto_final_efectivo_esperado',
        'monto_final_efectivo_real',
        'diferencia_efectivo',
        'total_efectivo',
        'total_tarjeta_debito',
        'total_tarjeta_credito',
        'total_credito_personal',
        'total_transferencia',
        'total_otros',
        'total_general',
        'cant_ventas_efectivo',
        'cant_ventas_tarjeta_debito',
        'cant_ventas_tarjeta_credito',
        'cant_ventas_credito_personal',
        'cant_ventas_transferencia',
        'cant_ventas_otros',
        'cant_ventas_total',
        'total_ingresos_efectivo',
        'total_retiros_efectivo',
        'cant_ingresos_efectivo',
        'cant_retiros_efectivo',
        'estado',
        'observaciones_apertura',
        'observaciones_cierre',
    ];

    protected $casts = [
        'fecha_apertura'                => 'datetime',
        'fecha_cierre'                  => 'datetime',
        'monto_inicial_efectivo'        => 'float',
        'monto_final_efectivo_esperado' => 'float',
        'monto_final_efectivo_real'     => 'float',
        'diferencia_efectivo'           => 'float',
        'total_efectivo'                => 'float',
        'total_tarjeta_debito'          => 'float',
        'total_tarjeta_credito'         => 'float',
        'total_credito_personal'        => 'float',
        'total_transferencia'           => 'float',
        'total_otros'                   => 'float',
        'total_general'                 => 'float',
        'cant_ventas_efectivo'          => 'integer',
        'cant_ventas_tarjeta_debito'    => 'integer',
        'cant_ventas_tarjeta_credito'   => 'integer',
        'cant_ventas_credito_personal'  => 'integer',
        'cant_ventas_transferencia'     => 'integer',
        'cant_ventas_otros'             => 'integer',
        'cant_ventas_total'             => 'integer',
        'total_ingresos_efectivo'       => 'float',
        'total_retiros_efectivo'        => 'float',
        'cant_ingresos_efectivo'        => 'integer',
        'cant_retiros_efectivo'         => 'integer',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_apertura');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_cierre');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'id_caja_sesion');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class, 'id_caja_sesion');
    }

    // Accessors y alias para compatibilidad y conveniencia
    public function getMontoFinalEfectivoSistemaAttribute(): float
    {
        return (float) ($this->monto_final_efectivo_esperado ?? 0);
    }

    public function getTotalVentasAttribute(): float
    {
        return (float) ($this->total_general ?? 0);
    }

    public function getCantidadVentasAttribute(): int
    {
        return (int) ($this->cant_ventas_total ?? 0);
    }

    public function getCantidadVentasEfectivoAttribute(): int
    {
        return (int) ($this->cant_ventas_efectivo ?? 0);
    }

    public function getCantidadVentasDebitoAttribute(): int
    {
        return (int) ($this->cant_ventas_tarjeta_debito ?? 0);
    }

    public function getCantidadVentasCreditoAttribute(): int
    {
        return (int) ($this->cant_ventas_tarjeta_credito ?? 0);
    }

    public function getCantidadVentasCreditoPersonalAttribute(): int
    {
        return (int) ($this->cant_ventas_credito_personal ?? 0);
    }

    public function getCantidadVentasTransferenciaAttribute(): int
    {
        return (int) ($this->cant_ventas_transferencia ?? 0);
    }

    public function getCantidadVentasOtrosAttribute(): int
    {
        return (int) ($this->cant_ventas_otros ?? 0);
    }

    public function getTotalDebitoAttribute(): float
    {
        return (float) ($this->total_tarjeta_debito ?? 0);
    }

    public function getTotalCreditoAttribute(): float
    {
        return (float) ($this->total_tarjeta_credito ?? 0);
    }

    /**
     * Retorna la sesión de caja abierta para la sucursal indicada.
     */
    public static function activaParaSucursal(int $sucursalId): ?self
    {
        return self::where('id_sucursal', $sucursalId)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first();
    }

    /**
     * Verifica si la fecha de apertura corresponde al día actual.
     */
    public function esDeHoy(): bool
    {
        if (!$this->fecha_apertura) {
            return false;
        }
        return $this->fecha_apertura->isToday();
    }

    /**
     * Calcula en tiempo real los totales y cantidades discriminados por medio de pago.
     */
    public function calcularTotales(): array
    {
        $ventasActivas = $this->ventas()->with('medioPago')->where('estado', '!=', 'anulada')->get();

        $totales = [
            'efectivo' => ['total' => 0.0, 'cant' => 0],
            'tarjeta_debito' => ['total' => 0.0, 'cant' => 0],
            'tarjeta_credito' => ['total' => 0.0, 'cant' => 0],
            'credito_personal' => ['total' => 0.0, 'cant' => 0],
            'transferencia' => ['total' => 0.0, 'cant' => 0],
            'otros' => ['total' => 0.0, 'cant' => 0],
            'general' => ['total' => 0.0, 'cant' => 0],
        ];

        // Desglose dinámico por Medios de Pago registrados en la BD consolidada
        $porMedio = [];
        try {
            $mediosTodos = MedioPago::orderBy('orden')->orderBy('id')->get();
            foreach ($mediosTodos as $mp) {
                $porMedio[$mp->codigo] = [
                    'id'        => $mp->id,
                    'codigo'    => $mp->codigo,
                    'nombre'    => $mp->nombre,
                    'icono'     => $mp->icono ?: '💳',
                    'color'     => $mp->color ?: 'slate',
                    'tipo_base' => $mp->tipo_base,
                    'total'     => 0.0,
                    'cant'      => 0,
                ];
            }
        } catch (\Throwable $e) {
            // Si la tabla no estuviera disponible temporalmente
        }

        if (empty($porMedio)) {
            $defaultMedios = [
                'efectivo'          => ['nombre' => 'Efectivo', 'icono' => '💵', 'color' => 'emerald', 'tipo_base' => 'efectivo'],
                'tarjeta_debito'    => ['nombre' => 'Tarjeta Débito', 'icono' => '💳', 'color' => 'sky', 'tipo_base' => 'debito'],
                'tarjeta_credito'   => ['nombre' => 'Tarjeta Crédito', 'icono' => '💳', 'color' => 'blue', 'tipo_base' => 'tarjeta_credito'],
                'credito_personal'  => ['nombre' => 'Crédito Personal', 'icono' => '📋', 'color' => 'amber', 'tipo_base' => 'credito'],
                'transferencia'     => ['nombre' => 'Transferencia', 'icono' => '🏦', 'color' => 'teal', 'tipo_base' => 'transferencia'],
                'otros'             => ['nombre' => 'Otros', 'icono' => '🪙', 'color' => 'slate', 'tipo_base' => 'otros'],
            ];
            foreach ($defaultMedios as $cod => $def) {
                $porMedio[$cod] = [
                    'id'        => null,
                    'codigo'    => $cod,
                    'nombre'    => $def['nombre'],
                    'icono'     => $def['icono'],
                    'color'     => $def['color'],
                    'tipo_base' => $def['tipo_base'],
                    'total'     => 0.0,
                    'cant'      => 0,
                ];
            }
        }

        if ($ventasActivas->isNotEmpty()) {
            foreach ($ventasActivas as $v) {
                $monto = (float) $v->total;
                $mp = $v->medio_pago;
                $codigoMedio = $v->medioPago?->codigo ?? $mp;
                $tipoBase = $v->medioPago?->tipo_base;

                if ($mp === 'efectivo' || $tipoBase === 'efectivo') {
                    $totales['efectivo']['total'] += $monto;
                    $totales['efectivo']['cant']++;
                } elseif ($mp === 'tarjeta_debito' || $tipoBase === 'debito') {
                    $totales['tarjeta_debito']['total'] += $monto;
                    $totales['tarjeta_debito']['cant']++;
                } elseif ($mp === 'tarjeta_credito' || $tipoBase === 'tarjeta_credito') {
                    $totales['tarjeta_credito']['total'] += $monto;
                    $totales['tarjeta_credito']['cant']++;
                } elseif ($mp === 'credito_personal' || $tipoBase === 'credito') {
                    $totales['credito_personal']['total'] += $monto;
                    $totales['credito_personal']['cant']++;
                } elseif ($mp === 'transferencia' || $tipoBase === 'transferencia') {
                    $totales['transferencia']['total'] += $monto;
                    $totales['transferencia']['cant']++;
                } else {
                    $totales['otros']['total'] += $monto;
                    $totales['otros']['cant']++;
                }

                // Desglose por medio dinámico
                if (!isset($porMedio[$codigoMedio])) {
                    $porMedio[$codigoMedio] = [
                        'id'        => $v->id_medio_pago,
                        'codigo'    => $codigoMedio,
                        'nombre'    => $v->medioPago?->nombre ?? ucwords(str_replace('_', ' ', $codigoMedio)),
                        'icono'     => $v->medioPago?->icono ?: '💳',
                        'color'     => $v->medioPago?->color ?: 'slate',
                        'tipo_base' => $tipoBase ?? 'otros',
                        'total'     => 0.0,
                        'cant'      => 0,
                    ];
                }
                $porMedio[$codigoMedio]['total'] += $monto;
                $porMedio[$codigoMedio]['cant']++;

                $totales['general']['total'] += $monto;
                $totales['general']['cant']++;
            }
        } elseif ((float) $this->total_general > 0 || (float) $this->total_efectivo > 0) {
            // Caso de cajas cerradas o históricas donde los totales ya fueron consolidados en los campos de la sesión
            $totales['efectivo']         = ['total' => (float)$this->total_efectivo, 'cant' => (int)$this->cant_ventas_efectivo];
            $totales['tarjeta_debito']    = ['total' => (float)$this->total_tarjeta_debito, 'cant' => (int)$this->cant_ventas_tarjeta_debito];
            $totales['tarjeta_credito']   = ['total' => (float)$this->total_tarjeta_credito, 'cant' => (int)$this->cant_ventas_tarjeta_credito];
            $totales['credito_personal']  = ['total' => (float)$this->total_credito_personal, 'cant' => (int)$this->cant_ventas_credito_personal];
            $totales['transferencia']     = ['total' => (float)$this->total_transferencia, 'cant' => (int)$this->cant_ventas_transferencia];
            $totales['otros']             = ['total' => (float)$this->total_otros, 'cant' => (int)$this->cant_ventas_otros];
            $totales['general']           = ['total' => (float)$this->total_general, 'cant' => (int)$this->cant_ventas_total];

            if (isset($porMedio['efectivo'])) {
                $porMedio['efectivo']['total'] = (float)$this->total_efectivo;
                $porMedio['efectivo']['cant'] = (int)$this->cant_ventas_efectivo;
            }
            if (isset($porMedio['tarjeta_debito'])) {
                $porMedio['tarjeta_debito']['total'] = (float)$this->total_tarjeta_debito;
                $porMedio['tarjeta_debito']['cant'] = (int)$this->cant_ventas_tarjeta_debito;
            }
            if (isset($porMedio['tarjeta_credito'])) {
                $porMedio['tarjeta_credito']['total'] = (float)$this->total_tarjeta_credito;
                $porMedio['tarjeta_credito']['cant'] = (int)$this->cant_ventas_tarjeta_credito;
            }
            if (isset($porMedio['credito_personal'])) {
                $porMedio['credito_personal']['total'] = (float)$this->total_credito_personal;
                $porMedio['credito_personal']['cant'] = (int)$this->cant_ventas_credito_personal;
            }
            if (isset($porMedio['transferencia'])) {
                $porMedio['transferencia']['total'] = (float)$this->total_transferencia;
                $porMedio['transferencia']['cant'] = (int)$this->cant_ventas_transferencia;
            }
            if (isset($porMedio['otros'])) {
                $porMedio['otros']['total'] = (float)$this->total_otros;
                $porMedio['otros']['cant'] = (int)$this->cant_ventas_otros;
            }
        }

        // Redondeo
        foreach ($totales as $k => $data) {
            $totales[$k]['total'] = round($data['total'], 2);
        }
        foreach ($porMedio as $k => $data) {
            $porMedio[$k]['total'] = round($data['total'], 2);
        }
        $totales['por_medio'] = $porMedio;

        // Movimientos de efectivo (ingresos y retiros justificados)
        $movs = $this->movimientos()->get();
        $totalIngresos = (float) $movs->where('tipo', 'ingreso')->sum('monto');
        $cantIngresos = (int) $movs->where('tipo', 'ingreso')->count();
        $totalRetiros = (float) $movs->where('tipo', 'retiro')->sum('monto');
        $cantRetiros = (int) $movs->where('tipo', 'retiro')->count();

        // Efectivo esperado = Fondo Inicial + Ventas Efectivo + Ingresos - Retiros
        $efectivoEsperado = round(
            (float)$this->monto_inicial_efectivo
            + $totales['efectivo']['total']
            + $totalIngresos
            - $totalRetiros,
            2
        );
        $totales['efectivo_esperado'] = $efectivoEsperado;
        $totales['total_ingresos'] = round($totalIngresos, 2);
        $totales['cant_ingresos'] = $cantIngresos;
        $totales['total_retiros'] = round($totalRetiros, 2);
        $totales['cant_retiros'] = $cantRetiros;

        // Atajos planos para conveniencia en vistas y reportes
        $totales['efectivo_total'] = $totales['efectivo']['total'];
        $totales['efectivo_count'] = $totales['efectivo']['cant'];
        $totales['debito_total'] = $totales['tarjeta_debito']['total'];
        $totales['debito_count'] = $totales['tarjeta_debito']['cant'];
        $totales['credito_total'] = $totales['tarjeta_credito']['total'];
        $totales['credito_count'] = $totales['tarjeta_credito']['cant'];
        $totales['credito_personal_total'] = $totales['credito_personal']['total'];
        $totales['credito_personal_count'] = $totales['credito_personal']['cant'];
        $totales['transferencia_total'] = $totales['transferencia']['total'];
        $totales['transferencia_count'] = $totales['transferencia']['cant'];
        $totales['otros_total'] = $totales['otros']['total'];
        $totales['otros_count'] = $totales['otros']['cant'];
        $totales['total_general'] = $totales['general']['total'];
        $totales['ventas_count'] = $totales['general']['cant'];

        return $totales;
    }

    /**
     * Ejecuta el Cierre Z de la caja guardando el arqueo y los totales definitivos.
     */
    public function cerrar(float $efectivoRealContado, ?string $observaciones = null, ?int $usuarioId = null): self
    {
        $totales = $this->calcularTotales();
        $esperado = $totales['efectivo_esperado'];
        $diferencia = round($efectivoRealContado - $esperado, 2);

        $this->update([
            'fecha_cierre'                  => now(),
            'id_usuario_cierre'             => $usuarioId ?? auth()->id() ?? 1,
            'monto_final_efectivo_esperado' => $esperado,
            'monto_final_efectivo_real'     => $efectivoRealContado,
            'diferencia_efectivo'           => $diferencia,
            'total_efectivo'                => $totales['efectivo']['total'],
            'total_tarjeta_debito'          => $totales['tarjeta_debito']['total'],
            'total_tarjeta_credito'         => $totales['tarjeta_credito']['total'],
            'total_credito_personal'        => $totales['credito_personal']['total'],
            'total_transferencia'           => $totales['transferencia']['total'],
            'total_otros'                   => $totales['otros']['total'],
            'total_general'                 => $totales['general']['total'],
            'cant_ventas_efectivo'          => $totales['efectivo']['cant'],
            'cant_ventas_tarjeta_debito'    => $totales['tarjeta_debito']['cant'],
            'cant_ventas_tarjeta_credito'   => $totales['tarjeta_credito']['cant'],
            'cant_ventas_credito_personal'  => $totales['credito_personal']['cant'],
            'cant_ventas_transferencia'     => $totales['transferencia']['cant'],
            'cant_ventas_otros'             => $totales['otros']['cant'],
            'cant_ventas_total'             => $totales['general']['cant'],
            'total_ingresos_efectivo'       => $totales['total_ingresos'],
            'total_retiros_efectivo'        => $totales['total_retiros'],
            'cant_ingresos_efectivo'        => $totales['cant_ingresos'],
            'cant_retiros_efectivo'         => $totales['cant_retiros'],
            'estado'                        => 'cerrada',
            'observaciones_cierre'          => $observaciones,
        ]);

        return $this;
    }
}
