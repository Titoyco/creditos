<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDatosTransaccionalesCommand extends Command
{
    protected $signature = 'app:reset-datos-transaccionales {--force : Forzar la eliminación sin confirmación interactiva}';

    protected $description = 'Elimina clientes, autorizados, novedades, créditos, cuotas, productos, movimientos y stock para pruebas de migración limpias.';

    public function handle()
    {
        $this->warn('==================================================================');
        $this->warn('   REINICIO DE DATOS: Clientes, Créditos, Cuotas, Productos y Stock');
        $this->warn('==================================================================');

        if (!$this->option('force') && !$this->confirm('¿Está seguro de que desea truncar todos los clientes, créditos, cuotas, productos y stock en la base activa?')) {
            $this->info('Operación cancelada por el usuario.');
            return 0;
        }

        $this->info('Desactivando restricciones de claves foráneas...');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        $tables = [
            'recibo_detalles',
            'recibos',
            'cuotas',
            'creditos',
            'cliente_autorizados',
            'cliente_novedades',
            'devolucion_bancarias',
            'clientes',
            'productos_movimientos',
            'remitos_proveedores_items',
            'remitos_proveedores',
            'productos_stock',
            'productos',
        ];

        foreach ($tables as $table) {
            $this->line("Truncando tabla <comment>{$table}</comment>...");
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        $this->info('Claves foráneas reactivadas.');

        $this->info('✓ Todas las tablas especificadas han sido vaciadas exitosamente.');

        return 0;
    }
}
