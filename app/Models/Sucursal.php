<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';
    protected $fillable = ['nombre', 'codigo_sucursal'];

    public function indices() {
        return $this->hasMany(SucursalIndice::class, 'id_sucursal');
    }
}