<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportCajasDataCommand extends Command
{
    protected $signature = 'app:import-cajas-sql {--path= : Ruta del archivo SQL a importar (por defecto database/data/cajas_historicas.sql)}';
    protected $description = 'Importa las cajas históricas y movimientos en producción desde el archivo SQL sin requerir drivers de Access';

    public function handle(): int
    {
        $this->info("=== IMPORTADOR DE CAJAS HISTÓRICAS (PRODUCCIÓN) ===");

        $sqlPath = $this->option('path') ?: database_path('data/cajas_historicas.sql');

        if (!File::exists($sqlPath)) {
            $this->error("No se encontró el archivo SQL en: {$sqlPath}");
            $this->warn("Asegúrese de correr 'php artisan app:export-cajas-sql' y subir el archivo con 'git push'.");
            return 1;
        }

        $this->info("Leyendo archivo SQL: {$sqlPath} (" . round(filesize($sqlPath) / 1024 / 1024, 2) . " MB)...");

        $sql = File::get($sqlPath);

        $this->info("Ejecutando sentencias SQL en la base de datos...");
        $startTime = microtime(true);

        DB::disableQueryLog();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        
        // Ejecutar las sentencias de cajas_sesiones y cajas_movimientos
        DB::unprepared($sql);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        $this->info("Vinculando ventas a sus respectivas cajas por fecha e índice...");
        $cajas = DB::table('cajas_sesiones')->select('id', 'id_sucursal', 'fecha_apertura')->get();
        $stmt = DB::getPdo()->prepare("UPDATE ventas SET id_caja_sesion = ? WHERE id_sucursal = ? AND fecha >= ? AND fecha <= ?");
        
        DB::beginTransaction();
        foreach ($cajas as $c) {
            $ymd = substr($c->fecha_apertura, 0, 10);
            $stmt->execute([$c->id, $c->id_sucursal, "$ymd 00:00:00", "$ymd 23:59:59"]);
        }
        DB::commit();

        $elapsed = round(microtime(true) - $startTime, 2);
        $sesionesCount = DB::table('cajas_sesiones')->count();
        $movsCount = DB::table('cajas_movimientos')->count();
        $ventasVinculadas = DB::table('ventas')->whereNotNull('id_caja_sesion')->count();

        $this->info("==================================================================");
        $this->info("✓ IMPORTACIÓN COMPLETADA EXITOSAMENTE EN {$elapsed} SEGUNDOS");
        $this->info("  - Cajas de Sesión registradas : {$sesionesCount}");
        $this->info("  - Movimientos de Efectivo     : {$movsCount}");
        $this->info("  - Ventas vinculadas a cajas   : {$ventasVinculadas}");
        $this->info("==================================================================");

        return 0;
    }
}
