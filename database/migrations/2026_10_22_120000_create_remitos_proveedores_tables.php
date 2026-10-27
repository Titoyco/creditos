<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remitos_proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('numero_remito', 50)->index();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->date('fecha_recepcion');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('completado'); // 'parcial', 'completado'
            $table->decimal('total_costo', 14, 2)->default(0);
            $table->decimal('total_venta_estimado', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('remitos_proveedores_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remito_id')->constrained('remitos_proveedores')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_compra', 12, 2);
            $table->decimal('precio_venta', 12, 2);
            $table->decimal('precio_venta_anterior', 12, 2)->nullable();
            $table->decimal('stock_anterior', 12, 2)->default(0);
            $table->decimal('stock_posterior', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remitos_proveedores_items');
        Schema::dropIfExists('remitos_proveedores');
    }
};
