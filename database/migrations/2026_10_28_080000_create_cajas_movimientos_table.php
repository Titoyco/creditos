<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cajas_movimientos')) {
            Schema::create('cajas_movimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_caja_sesion')->constrained('cajas_sesiones')->cascadeOnDelete();
                $table->foreignId('id_usuario')->constrained('users')->cascadeOnDelete();
                $table->string('tipo', 20); // 'ingreso', 'retiro'
                $table->decimal('monto', 12, 2);
                $table->string('motivo', 255);
                $table->string('comprobante', 100)->nullable();
                $table->timestamps();

                $table->index(['id_caja_sesion', 'tipo']);
                $table->index('created_at');
            });
        }

        if (Schema::hasTable('cajas_sesiones')) {
            Schema::table('cajas_sesiones', function (Blueprint $table) {
                if (!Schema::hasColumn('cajas_sesiones', 'total_ingresos_efectivo')) {
                    $table->decimal('total_ingresos_efectivo', 12, 2)->default(0)->after('monto_inicial_efectivo');
                }
                if (!Schema::hasColumn('cajas_sesiones', 'total_retiros_efectivo')) {
                    $table->decimal('total_retiros_efectivo', 12, 2)->default(0)->after('total_ingresos_efectivo');
                }
                if (!Schema::hasColumn('cajas_sesiones', 'cant_ingresos_efectivo')) {
                    $table->unsignedInteger('cant_ingresos_efectivo')->default(0)->after('cant_ventas_total');
                }
                if (!Schema::hasColumn('cajas_sesiones', 'cant_retiros_efectivo')) {
                    $table->unsignedInteger('cant_retiros_efectivo')->default(0)->after('cant_ingresos_efectivo');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas_movimientos');

        if (Schema::hasTable('cajas_sesiones')) {
            Schema::table('cajas_sesiones', function (Blueprint $table) {
                if (Schema::hasColumn('cajas_sesiones', 'total_ingresos_efectivo')) {
                    $table->dropColumn([
                        'total_ingresos_efectivo',
                        'total_retiros_efectivo',
                        'cant_ingresos_efectivo',
                        'cant_retiros_efectivo'
                    ]);
                }
            });
        }
    }
};
