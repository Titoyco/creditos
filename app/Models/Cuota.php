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
     * Cálculo dinámico del número de lote.
     *
     * @deprecated Nomenclatura obsoleta. El accesor oficial es `lote_calculado`
     *             (getLoteCalculadoAttribute). Se conserva delegando en él para no
     *             romper vistas antiguas; la implementación anterior sumaba un Modelo
     *             a un entero (`$this->credito->presentacion + $this->num_cuota`), lo
     *             que provocaba un error fatal.
     */
    public function getLotePresentacionAttribute() {
        return $this->lote_calculado;
    }

    /**
     * ACCESOR OFICIAL DE LOTE: número base de la presentación del crédito (origen)
     * + número de cuota. Ej: presentación #500, cuota 3 => lote 503.
     */
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