<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\SucursalIndice;
use Illuminate\Support\Facades\Hash;

class UsuariosSucursalesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Configuración de las 4 Sucursales Oficiales de La Marina
        $sucursalesData = [
            [
                'id' => 1,
                'nombre' => 'Ushuaia - San Martín',
                'codigo_sucursal' => 'US',
                'direccion' => 'San Martín 43, Ushuaia, Tierra del Fuego',
            ],
            [
                'id' => 2,
                'nombre' => 'Ushuaia - Antártida',
                'codigo_sucursal' => 'UA',
                'direccion' => 'Antártida Argentina, Ushuaia, Tierra del Fuego',
            ],
            [
                'id' => 3,
                'nombre' => 'Trelew - Centro',
                'codigo_sucursal' => 'TW',
                'direccion' => 'Centro Comercial, Trelew, Chubut',
            ],
            [
                'id' => 4,
                'nombre' => 'Trelew - Interior / Camión',
                'codigo_sucursal' => 'IN',
                'direccion' => 'Rutas del Interior / Unidad Móvil Chubut',
            ],
        ];

        foreach ($sucursalesData as $data) {
            $sucursal = Sucursal::updateOrCreate(
                ['id' => $data['id']],
                [
                    'nombre' => $data['nombre'],
                    'codigo_sucursal' => $data['codigo_sucursal'],
                    'direccion' => $data['direccion'],
                ]
            );

            // Inicializar índices de crédito para la sucursal (C: Crédito, D: Débito, R: Refinanciación)
            foreach (['C', 'D', 'R'] as $tipo) {
                SucursalIndice::firstOrCreate(
                    [
                        'id_sucursal' => $sucursal->id,
                        'tipo_credito' => $tipo,
                    ],
                    [
                        'ultimo_indice' => 0,
                    ]
                );
            }
        }

        // 1.1 Asegurar Provincias y Localidades Base
        $chubut = \App\Models\Provincia::firstOrCreate(['id' => 1], ['nombre' => 'Chubut']);
        $tdf = \App\Models\Provincia::firstOrCreate(['id' => 2], ['nombre' => 'Tierra Del Fuego']);

        \App\Models\Localidad::firstOrCreate(
            ['nombre' => 'USHUAIA', 'id_provincia' => $tdf->id],
            ['codigo_postal' => '9410']
        );
        \App\Models\Localidad::firstOrCreate(
            ['nombre' => 'TOLHUIN', 'id_provincia' => $tdf->id],
            ['codigo_postal' => '9420']
        );
        \App\Models\Localidad::firstOrCreate(
            ['nombre' => 'RIO GRANDE', 'id_provincia' => $tdf->id],
            ['codigo_postal' => '9420']
        );
        \App\Models\Localidad::firstOrCreate(
            ['nombre' => 'TRELEW', 'id_provincia' => $chubut->id],
            ['codigo_postal' => '9100']
        );
        \App\Models\Localidad::firstOrCreate(
            ['nombre' => 'RAWSON', 'id_provincia' => $chubut->id],
            ['codigo_postal' => '9103']
        );
        \App\Models\Localidad::firstOrCreate(
            ['nombre' => 'PUERTO MADRYN', 'id_provincia' => $chubut->id],
            ['codigo_postal' => '9120']
        );

        // 2. Usuarios del Sistema (1 Administrador + 1 Vendedor por Sucursal)
        $usuarios = [
            [
                'username' => 'Admin',
                'name' => 'Administrador General',
                'password' => Hash::make('admin123'),
                'id_sucursal' => 1,
                'is_admin' => true,
            ],
            [
                'username' => 'vendedorush1',
                'name' => 'Vendedor Ushuaia San Martín',
                'password' => Hash::make('ush123'),
                'id_sucursal' => 1, // Ushuaia - San Martín
                'is_admin' => false,
            ],
            [
                'username' => 'vendedorush2',
                'name' => 'Vendedor Ushuaia Antártida',
                'password' => Hash::make('ush123'),
                'id_sucursal' => 2, // Ushuaia - Antártida
                'is_admin' => false,
            ],
            [
                'username' => 'vendedortw',
                'name' => 'Vendedor Trelew Centro',
                'password' => Hash::make('tw123'),
                'id_sucursal' => 3, // Trelew - Centro
                'is_admin' => false,
            ],
            [
                'username' => 'vendedorint',
                'name' => 'Vendedor Interior / Camión',
                'password' => Hash::make('int123'),
                'id_sucursal' => 4, // Trelew - Interior / Camión
                'is_admin' => false,
            ],
        ];

        foreach ($usuarios as $userData) {
            User::updateOrCreate(
                ['username' => $userData['username']],
                $userData
            );
        }
    }
}
