<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provincia;
use App\Models\Localidad;

class UbicacionSeeder extends Seeder
{
    public function run(): void
    {
        // Provincias
        $chubut = Provincia::create(['nombre' => 'Chubut']);
        $tdf = Provincia::create(['nombre' => 'Tierra del Fuego']);

        // Localidades Chubut
        $localidadesChubut = [
            ['nombre' => 'Trelew', 'codigo_postal' => '9100'],
            ['nombre' => 'Rawson', 'codigo_postal' => '9103'],
            ['nombre' => 'Puerto Madryn', 'codigo_postal' => '9120'],
            ['nombre' => 'Comodoro Rivadavia', 'codigo_postal' => '9000'],
        ];

        foreach ($localidadesChubut as $loc) {
            Localidad::create([
                'id_provincia' => $chubut->id,
                'nombre' => $loc['nombre'],
                'codigo_postal' => $loc['codigo_postal']
            ]);
        }

        // Localidades TDF
        $localidadesTDF = [
            ['nombre' => 'Ushuaia', 'codigo_postal' => '9410'],
            ['nombre' => 'Río Grande', 'codigo_postal' => '9420'],
        ];

        foreach ($localidadesTDF as $loc) {
            Localidad::create([
                'id_provincia' => $tdf->id,
                'nombre' => $loc['nombre'],
                'codigo_postal' => $loc['codigo_postal']
            ]);
        }
    }
}