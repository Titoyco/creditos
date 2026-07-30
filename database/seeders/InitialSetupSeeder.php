<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\SucursalIndice;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear el Usuario Administrador Maestro
        // Toma los valores del .env, si no existen usa 'admin' / 'admin123'
        User::create([
            'name' => 'Admin',
            'username' => env('ADMIN_USERNAME', 'admin'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'admin123')),
            'id_sucursal' => 1, // El administrador maestro se asigna la sucursal 1 por defecto
            'is_admin' => true,
        ]);

        // 2. Definir las Sucursales
        $sucursales = [
            ['nombre' => 'Trelew', 'codigo' => 'T'],
            ['nombre' => 'Ushuaia', 'codigo' => 'U'],
            ['nombre' => 'Interior', 'codigo' => 'I'],
        ];

        foreach ($sucursales as $s) {
            $nuevaSucursal = Sucursal::create([
                'nombre' => $s['nombre'],
                'codigo_sucursal' => $s['codigo'],
            ]);

            // 3. Inicializar los índices para cada tipo de crédito en esta sucursal
            // C = Crédito, D = Débito, R = Refinanciación
            $tipos = ['C', 'D', 'R'];

            foreach ($tipos as $tipo) {
                SucursalIndice::create([
                    'id_sucursal' => $nuevaSucursal->id,
                    'tipo_credito' => $tipo,
                    'ultimo_indice' => 0, // Comienza en 0 para que el primero sea 1
                ]);
            }
        }
    }
}