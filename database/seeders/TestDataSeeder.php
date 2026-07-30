<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Sucursal;
use App\Models\Localidad;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Presentacion;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $sucursal = Sucursal::first();
        $localidad = Localidad::first();
        $admin = User::first();

        // 1. Crear 10 Clientes
        for ($i = 1; $i <= 10; $i++) {
            $cliente = Cliente::create([
                'dni' => rand(50000000, 99999999),
                'nombre' => "Cliente Prueba $i",
                'id_sucursal' => $sucursal->id,
                'id_localidad' => $localidad->id,
                'direccion' => "Calle Falsa $i",
                'tel1' => "280400000$i",
                'limite' => 500000,
                'estado' => 'ACT'
            ]);

            // 2. Para los primeros 5 clientes, crear 1 crédito a cada uno
            if ($i <= 5) {
                $cantCuotas = rand(3, 4);
                $importeOriginal = 100000;
                $valorCuota = 35000; // Ejemplo: capital + interés fijo

                // Buscamos la presentación que tenga el número de lote deseado
                $lote = Presentacion::where('numero', 510)->first();

                // Si no existe el lote 510, usamos el primero que encuentre para que no falle
                if (!$lote) {
                    $lote = Presentacion::first();
                }
                $credito = Credito::create([
                    'id_cliente' => $cliente->id,
                    'id_sucursal' => $sucursal->id,
                    'id_autorizado' => $admin->id,
                    'id_presentacion' => $lote->id, // Asignamos la presentación encontrada
                    'credito' => (1000 + $i),
                    'tipo_credito' => 'C',
                    'importe_original' => $importeOriginal,
                    'cantidad_cuotas' => $cantCuotas,
                    'valor_cuota_ref' => $valorCuota,
                    'fecha_credito' => Carbon::now(),
                ]);

                // 3. Generar las N cuotas para este crédito
                for ($j = 1; $j <= $cantCuotas; $j++) {
                    Cuota::create([
                        'id_credito' => $credito->id,
                        'num_cuota' => $j,
                        'valor_base' => $valorCuota,
                        'intereses' => 0,
                        'entrego' => 0,
                        'saldo_actual' => $valorCuota, // Al ser nueva, debe el total
                        'estado_bancario' => null
                    ]);
                }
            }
        }
    }
}