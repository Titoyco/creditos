<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ClienteAutorizado;
use App\Models\Presentacion;
use App\Models\Sucursal;



class Credito extends Model
{
    protected $table = 'creditos';

    protected $fillable = [
        'id_cliente', 
        'id_sucursal', 
        'id_autorizado',
        'id_presentacion',   // Relación con la tabla presentaciones
        'credito',          // Ej: IC124
        'tipo_credito',     // C, D, R
        'importe_original', // Capital inicial 
        'cantidad_cuotas', 
        'valor_cuota_ref',  // Monto fijo pactado por cuota
        'fecha_credito', 
        'fecha_cancelacion'
    ];

    public function cliente(): BelongsTo {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }


    public function cuotas(): HasMany {
        return $this->hasMany(Cuota::class, 'id_credito');
    }

    /**
     * Presentación (lote) ORIGEN del crédito — FUENTE DE VERDAD del lote inicial.
     *
     * ⚠️ El lote donde nació el crédito se resuelve SIEMPRE por la FK relacional
     * `id_presentacion` (esta relación). NO existe columna física `presentacion_origen`
     * en la tabla `creditos`; cualquier consulta que necesite el lote de origen debe
     * hacer INNER JOIN con `presentaciones` vía `creditos.id_presentacion`.
     * El lote exigible actual de una cuota se calcula con `presentaciones.numero + cuotas.num_cuota`
     * (ver Cuota::getLoteCalculadoAttribute / Cuota::lote_calculado).
     */
    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class, 'id_presentacion');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    /**
     * Genera el identificador único del crédito: Ej "1234TC"
     * (Número de Crédito/Venta + Código Sucursal + Tipo Crédito)
     */
    public function getFullIdentificadorAttribute(): string
    {
        // 1. Número de venta/crédito relleno con ceros a 8 dígitos (ej: 00001234)
        $numeroCredito = str_pad($this->credito, 8, '0', STR_PAD_LEFT);

        // 2. Código de la sucursal (ej: "T" o "I")
        $sucursalPart = $this->sucursal?->codigo_sucursal ?? 'T';

        // 3. Tipo de crédito (ej: "C", "D" o "R")
        $tipoPart = strtoupper($this->tipo_credito ?? 'C');

        // Ejemplo resultado: R00001234T
        return "{$tipoPart}{$numeroCredito}{$sucursalPart}";
    }
    /**
     * Firma autorizada que respalda el crédito.
     * ⚠️ `creditos.id_autorizado` referencia `cliente_autorizados.id` (NO `clientes.id`).
     * NULL = crédito tomado directamente por el titular.
     */
    public function autorizado(): BelongsTo
    {
        return $this->belongsTo(ClienteAutorizado::class, 'id_autorizado');
    }

    
}