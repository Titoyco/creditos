<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Venta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ventas';

    protected $fillable = [
        'id_operacion',
        'id_sucursal',
        'id_caja_sesion',
        'id_usuario',
        'id_cliente',
        'cliente_nombre',
        'cliente_documento',
        'punto_venta',
        'numero_factura',
        'comprobante',
        'tipo_comprobante',
        'fecha',
        'medio_pago',
        'id_medio_pago',
        'subtotal',
        'recargo',
        'descuento',
        'total',
        'id_credito',
        'cuotas_credito',
        'valor_cuota_credito',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'decimal:2',
        'recargo' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'valor_cuota_credito' => 'decimal:2',
        'cuotas_credito' => 'integer',
        'punto_venta' => 'integer',
        'numero_factura' => 'integer',
    ];
    public function getRecargoFinanciacionAttribute(): float
    {
        return (float) ($this->recargo ?? 0);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'id_caja_sesion');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class, 'id_credito');
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class, 'id_medio_pago');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class, 'id_venta');
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('id_sucursal', $sucursalId);
    }

    public function scopePorProvincia($query, $provinciaId)
    {
        return $query->whereHas('sucursal', function ($q) use ($provinciaId) {
            $q->where('id_provincia', $provinciaId);
        });
    }

    public function getMedioPagoFormateadoAttribute(): string
    {
        return match ($this->medio_pago) {
            'credito_personal' => 'Crédito Personal',
            'tarjeta_debito'   => 'Tarjeta Débito',
            'tarjeta_credito'  => 'Tarjeta Crédito',
            'transferencia'    => 'Transferencia',
            default            => 'Efectivo',
        };
    }

    public function getEsCreditoPersonalAttribute(): bool
    {
        return $this->medio_pago === 'credito_personal';
    }

    public function getEsConsumidorFinalAttribute(): bool
    {
        return is_null($this->id_cliente);
    }
}
