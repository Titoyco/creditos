<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Presentacion extends Model
{
    protected $table = 'presentaciones';

    protected $fillable = [
        'numero',          // Número de lote o presentación (ej: 500, 510, etc.)
        'fecha_inicio',
        'fecha_fin',
        'tasa_cuotas', // % de financiación base
        'tasa_mora'     // % de recargo por atraso
    ];

    /**
     * Relación: Una presentación engloba muchos créditos 
     * (según el número base del crédito).
     */
    public function creditos(): HasMany
    {
        return $this->hasMany(Credito::class, 'id_presentacion');
    }
}