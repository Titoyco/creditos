<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cuota extends Model
{
    protected $table = 'cuotas';

    protected $fillable = [
        'id_credito',
        'num_cuota',
        'valor_base',       // Valor original (Capital + Int. Financiación)
        'intereses',        // Mora acumulada calculada
        'entrego',          // Total pagado por el cliente
        'saldo_actual',     // (valor_base + intereses) - entrego
        'fecha_cobro',
        'medio_pago',
        'referencia_pago',
        'banco_envio',
        'fecha_ultimo_envio',
        'estado_bancario',   // ACE, R10, R08, etc.
        'obs_devolucion'
    ];

    public function credito(): BelongsTo {
        return $this->belongsTo(Credito::class, 'id_credito');
    }

    /**
     * Cálculo dinámico del número de lote (Presentación de crédito + nro de cuota)
     */
    public function getLotePresentacionAttribute() {
        return $this->credito->presentacion + $this->num_cuota;
    }

    public function getLoteCalculadoAttribute()
    {
        // Accedemos al campo 'numero' de la tabla presentaciones a través del crédito
        return $this->credito->presentacion->numero + $this->num_cuota;
    }

    /**
     * Genera el código completo de la cuota: Ej "1234TC1"
     */
    public function getCodigoGestionAttribute()
    {
        // Llamamos al identificador del crédito y le pegamos el número de cuota
        return $this->credito->full_identificador . $this->num_cuota;
    }

}