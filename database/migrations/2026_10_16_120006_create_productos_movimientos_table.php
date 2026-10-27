<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('tipo', 30)->index(); // 'alta', 'baja', 'traslado_salida', 'traslado_entrada', 'venta', 'ajuste', 'devolucion'
            $table->decimal('cantidad', 12, 2); // positivo o negativo
            $table->decimal('stock_anterior', 12, 2)->default(0);
            $table->decimal('stock_nuevo', 12, 2)->default(0);
            $table->decimal('precio_costo', 12, 2)->nullable();
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_origen_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('sucursal_destino_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('comprobante', 100)->nullable()->index();
            $table->string('motivo', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->nullableMorphs('referencia'); // Para asociar a Venta, Factura, Remito, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_movimientos');
    }
};
