<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('total_ventas_unidades', 12, 2)->default(0)->after('controla_stock')->index();
            $table->unsignedInteger('total_ventas_operaciones')->default(0)->after('total_ventas_unidades')->index();
        });

        // Poblamos inicialmente los totales desde los movimientos históricos existentes si la tabla ya tiene datos (solo MySQL)
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('productos_movimientos')) {
            DB::statement("
                UPDATE productos p
                JOIN (
                    SELECT producto_id, ABS(SUM(cantidad)) as vendidas, COUNT(*) as ops
                    FROM productos_movimientos
                    WHERE tipo = 'venta'
                    GROUP BY producto_id
                ) m ON p.id = m.producto_id
                SET p.total_ventas_unidades = m.vendidas,
                    p.total_ventas_operaciones = m.ops
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['total_ventas_unidades']);
            $table->dropIndex(['total_ventas_operaciones']);
            $table->dropColumn(['total_ventas_unidades', 'total_ventas_operaciones']);
        });
    }
};
