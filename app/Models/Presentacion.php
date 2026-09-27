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
        'interes_credito', // % de financiación base del lote (columna REAL en 'presentaciones')
        'interes_mora',    // % de mora aplicado al cerrar el lote (columna REAL en 'presentaciones')
        // ⚠️ 'tasa_cuotas' y 'tasa_mora' NO existen como columnas en 'presentaciones';
        // se mantienen solo por compatibilidad con código legado. Fuente oficial: interes_credito / interes_mora.
        'tasa_cuotas',
        'tasa_mora'
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