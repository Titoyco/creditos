<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportCajasDataCommand extends Command
{
    protected $signature = 'app:export-cajas-sql {--path= : Ruta de salida (por defecto database/data/cajas_historicas.sql)}';
    protected $description = 'Exporta las tablas cajas_sesiones y cajas_movimientos a un archivo SQL listo para producción';

    public function handle(): int
    {
        $this->info("=== EXPORTADOR DE TABLAS DE CAJA PARA PRODUCCIÓN ===");
        
        $outPath = $this->option('path') ?: database_path('data/cajas_historicas.sql');
        File::ensureDirectoryExists(dirname($outPath));

        $fp = fopen($outPath, 'w');
        if (!$fp) {
            $this->error("No se pudo abrir el archivo de salida: {$outPath}");
            return 1;
        }

        fwrite($fp, "-- ==================================================================\n");
        fwrite($fp, "-- DUMP DE CAJAS HISTÓRICAS Y MOVIMIENTOS PARA TIENDAS LA MARINA\n");
        fwrite($fp, "-- Generado automáticamente para actualización de producción\n");
        fwrite($fp, "-- Fecha: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- ==================================================================\n\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($fp, "SET NAMES utf8mb4;\n\n");

        // 1. Exportar cajas_sesiones
        $this->info("Exportando cajas_sesiones...");
        fwrite($fp, "-- Tabla: cajas_sesiones\n");
        fwrite($fp, "TRUNCATE TABLE `cajas_movimientos`;\n");
        fwrite($fp, "TRUNCATE TABLE `cajas_sesiones`;\n\n");

        $columnsSesiones = [
            'id', 'id_sucursal', 'id_usuario_apertura', 'id_usuario_cierre', 'fecha_apertura', 'fecha_cierre',
            'monto_inicial_efectivo', 'total_ingresos_efectivo', 'total_retiros_efectivo',
            'monto_final_efectivo_esperado', 'monto_final_efectivo_real', 'diferencia_efectivo',
            'total_efectivo', 'total_tarjeta_debito', 'total_tarjeta_credito', 'total_credito_personal',
            'total_transferencia', 'total_otros', 'total_general',
            'cant_ventas_efectivo', 'cant_ventas_tarjeta_debito', 'cant_ventas_tarjeta_credito',
            'cant_ventas_credito_personal', 'cant_ventas_transferencia', 'cant_ventas_otros',
            'cant_ventas_total', 'cant_ingresos_efectivo', 'cant_retiros_efectivo',
            'estado', 'observaciones_apertura', 'observaciones_cierre', 'created_at', 'updated_at'
        ];

        $colListSesiones = '`' . implode('`, `', $columnsSesiones) . '`';

        $totalSesiones = 0;
        DB::table('cajas_sesiones')->orderBy('id')->chunk(500, function ($rows) use ($fp, $colListSesiones, &$totalSesiones) {
            $valueRows = [];
            foreach ($rows as $r) {
                $vals = [];
                foreach ((array)$r as $k => $v) {
                    if (is_null($v)) {
                        $vals[] = 'NULL';
                    } elseif (is_numeric($v)) {
                        $vals[] = (string)$v;
                    } else {
                        $vals[] = "'" . addslashes((string)$v) . "'";
                    }
                }
                $valueRows[] = '(' . implode(', ', $vals) . ')';
                $totalSesiones++;
            }
            fwrite($fp, "INSERT INTO `cajas_sesiones` ({$colListSesiones}) VALUES\n" . implode(",\n", $valueRows) . ";\n\n");
        });
        $this->info("✓ {$totalSesiones} cajas_sesiones exportadas.");

        // 2. Exportar cajas_movimientos
        $this->info("Exportando cajas_movimientos...");
        fwrite($fp, "-- Tabla: cajas_movimientos\n");
        $columnsMovs = ['id', 'id_caja_sesion', 'id_usuario', 'tipo', 'monto', 'motivo', 'comprobante', 'created_at', 'updated_at'];
        $colListMovs = '`' . implode('`, `', $columnsMovs) . '`';

        $totalMovs = 0;
        DB::table('cajas_movimientos')->orderBy('id')->chunk(500, function ($rows) use ($fp, $colListMovs, &$totalMovs) {
            $valueRows = [];
            foreach ($rows as $r) {
                $vals = [];
                foreach ((array)$r as $k => $v) {
                    if (is_null($v)) {
                        $vals[] = 'NULL';
                    } elseif (is_numeric($v)) {
                        $vals[] = (string)$v;
                    } else {
                        $vals[] = "'" . addslashes((string)$v) . "'";
                    }
                }
                $valueRows[] = '(' . implode(', ', $vals) . ')';
                $totalMovs++;
            }
            fwrite($fp, "INSERT INTO `cajas_movimientos` ({$colListMovs}) VALUES\n" . implode(",\n", $valueRows) . ";\n\n");
        });
        $this->info("✓ {$totalMovs} cajas_movimientos exportadas.");

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);

        $bytes = filesize($outPath);
        $mb = round($bytes / 1024 / 1024, 2);
        $this->info("✓ Archivo SQL generado exitosamente: {$outPath} ({$mb} MB)");

        return 0;
    }
}
