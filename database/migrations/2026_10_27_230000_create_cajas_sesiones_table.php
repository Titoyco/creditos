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
        if (!Schema::hasTable('cajas_sesiones')) {
            Schema::create('cajas_sesiones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_sucursal')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('id_usuario_apertura')->constrained('users')->cascadeOnDelete();
                $table->foreignId('id_usuario_cierre')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('fecha_apertura');
                $table->dateTime('fecha_cierre')->nullable();
                $table->decimal('monto_inicial_efectivo', 12, 2)->default(0);
                $table->decimal('monto_final_efectivo_esperado', 12, 2)->nullable();
                $table->decimal('monto_final_efectivo_real', 12, 2)->nullable();
                $table->decimal('diferencia_efectivo', 12, 2)->nullable(); // real - esperado

                // Totales discriminados por medio de pago
                $table->decimal('total_efectivo', 12, 2)->default(0);
                $table->decimal('total_tarjeta_debito', 12, 2)->default(0);
                $table->decimal('total_tarjeta_credito', 12, 2)->default(0);
                $table->decimal('total_credito_personal', 12, 2)->default(0);
                $table->decimal('total_transferencia', 12, 2)->default(0);
                $table->decimal('total_otros', 12, 2)->default(0);
                $table->decimal('total_general', 12, 2)->default(0);

                // Cantidades de transacciones
                $table->unsignedInteger('cant_ventas_efectivo')->default(0);
                $table->unsignedInteger('cant_ventas_tarjeta_debito')->default(0);
                $table->unsignedInteger('cant_ventas_tarjeta_credito')->default(0);
                $table->unsignedInteger('cant_ventas_credito_personal')->default(0);
                $table->unsignedInteger('cant_ventas_transferencia')->default(0);
                $table->unsignedInteger('cant_ventas_otros')->default(0);
                $table->unsignedInteger('cant_ventas_total')->default(0);

                $table->string('estado', 20)->default('abierta'); // 'abierta', 'cerrada'
                $table->text('observaciones_apertura')->nullable();
                $table->text('observaciones_cierre')->nullable();
                $table->timestamps();

                $table->index(['id_sucursal', 'estado']);
                $table->index('fecha_apertura');
            });
        }

        if (Schema::hasTable('ventas') && !Schema::hasColumn('ventas', 'id_caja_sesion')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->foreignId('id_caja_sesion')->nullable()->after('id_sucursal')->constrained('cajas_sesiones')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ventas') && Schema::hasColumn('ventas', 'id_caja_sesion')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->dropForeign(['id_caja_sesion']);
                $table->dropColumn('id_caja_sesion');
            });
        }

        Schema::dropIfExists('cajas_sesiones');
    }
};
