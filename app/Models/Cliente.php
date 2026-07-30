<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'dni', 'nombre', 'medio_pago_preferido', 'cbu', 'id_sucursal', 'tipo_haber',
        'saldo', 'limite', 'max_cuotas', 'suspendido', 'obs', 'estado_banco', 'fecha_estado_banco',
        'direccion', 'id_localidad', 'email', 'tel1', 'tel2',
        'familiar', 'tipo_familiar', 'direccion_familiar', 'id_localidad_familiar', 'email_familiar', 'tel_familiar',
        'trabajo', 'direccion_trabajo', 'id_localidad_trabajo', 'tel_trabajo'
    ];

    // Relación con la Sucursal de alta
    public function sucursal(): BelongsTo {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    // Relación con la Localidad de residencia
    public function localidad(): BelongsTo {
        return $this->belongsTo(Localidad::class, 'id_localidad');
    }

    // Relación con la Localidad del Familiar
    public function localidadFamiliar(): BelongsTo {
        return $this->belongsTo(Localidad::class, 'id_localidad_familiar');
    }

    // Relación con la Localidad del Trabajo
    public function localidadTrabajo(): BelongsTo {
        return $this->belongsTo(Localidad::class, 'id_localidad_trabajo');
    }

    /**
     * Los clientes que este Titular ha autorizado.
     */
    public function autorizados(): BelongsToMany
    {
        return $this->belongsToMany(
            Cliente::class,                // Modelo relacionado (sí mismo)
            'cliente_autorizados',         // Tabla pivot
            'id_titular',                  // Clave foránea local en la pivot
            'id_autorizado'                // Clave foránea externa en la pivot
        )->withPivot(['relacion_aut', 'fecha_autorizado', 'limite_aut']) // Traer columnas extra
        ->withTimestamps();
    }

    /**
     * El o los titulares que han autorizado a este cliente como adicional.
     */
    public function titulares(): BelongsToMany
    {
        return $this->belongsToMany(
            Cliente::class,
            'cliente_autorizados',
            'id_autorizado',
            'id_titular'
        )->withPivot(['relacion_aut', 'fecha_autorizado', 'limite_aut'])
        ->withTimestamps();
    }

    /**
     * Método de conveniencia para obtener el límite real del autorizado.
     * Si tiene un límite específico asignado, usa ese; de lo contrario, 
     * hereda el límite del titular.
     */
    public function getLimiteEfectivoAttribute()
    {
        // Si este cliente actúa como autorizado en una relación
        $relacion = $this->titulares()->first();
        
        if ($relacion && $relacion->pivot->limite_aut !== null) {
            return $relacion->pivot->limite_aut;
        }
        
        // Si no tiene límite asignado o es titular, devuelve su propio límite
        return $this->limite;
    }

    public function creditos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Credito::class, 'id_cliente');
    } 

    // Relación con las novedades del cliente
    public function novedades()
    {
        return $this->hasMany(ClienteNovedad::class, 'id_cliente')->latest();
    }

}