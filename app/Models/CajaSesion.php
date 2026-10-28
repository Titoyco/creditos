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
        $ventasActivas = $this->ventas()->where('estado', '!=', 'anulada')->get();

        $totales = [
            'efectivo' => ['total' => 0.0, 'cant' => 0],
            'tarjeta_debito' => ['total' => 0.0, 'cant' => 0],
            'tarjeta_credito' => ['total' => 0.0, 'cant' => 0],
            'credito_personal' => ['total' => 0.0, 'cant' => 0],
            'transferencia' => ['total' => 0.0, 'cant' => 0],
            'otros' => ['total' => 0.0, 'cant' => 0],
            'general' => ['total' => 0.0, 'cant' => 0],
        ];

        foreach ($ventasActivas as $v) {
            $monto = (float) $v->total;
            $mp = $v->medio_pago;

            if ($mp === 'efectivo') {
                $totales['efectivo']['total'] += $monto;
                $totales['efectivo']['cant']++;
            } elseif ($mp === 'tarjeta_debito') {
                $totales['tarjeta_debito']['total'] += $monto;
                $totales['tarjeta_debito']['cant']++;
            } elseif ($mp === 'tarjeta_credito') {
                $totales['tarjeta_credito']['total'] += $monto;
                $totales['tarjeta_credito']['cant']++;
            } elseif ($mp === 'credito_personal') {
                $totales['credito_personal']['total'] += $monto;
                $totales['credito_personal']['cant']++;
            } elseif ($mp === 'transferencia') {
                $totales['transferencia']['total'] += $monto;
                $totales['transferencia']['cant']++;
            } else {
                $totales['otros']['total'] += $monto;
                $totales['otros']['cant']++;
            }

            $totales['general']['total'] += $monto;
            $totales['general']['cant']++;
        }

        // Redondeo
        foreach ($totales as $k => $data) {
            $totales[$k]['total'] = round($data['total'], 2);
        }

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
