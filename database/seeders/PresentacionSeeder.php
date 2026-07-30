<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Presentacion;
use Carbon\Carbon;

class PresentacionSeeder extends Seeder
{
    public function run(): void
    {
        $lotes = [
            [
                'numero' => 500,
                'fecha_inicio' => Carbon::now()->subMonths(2),
                'interes_credito' => 15.00,
                'interes_mora' => 2.50
            ],
            [
                'numero' => 510,
                'fecha_inicio' => Carbon::now()->subMonth(),
                'interes_credito' => 18.00,
                'interes_mora' => 3.00
            ],
            [
                'numero' => 520,
                'fecha_inicio' => Carbon::now(),
                'interes_credito' => 20.00,
                'interes_mora' => 3.50
            ],
            [
                'numero' => 530,
                'fecha_inicio' => Carbon::now()->addMonth(),
                'interes_credito' => 22.00,
                'interes_mora' => 4.00
            ],
        ];

        foreach ($lotes as $lote) {
            Presentacion::create($lote);
        }
    }
}