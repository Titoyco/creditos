<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique()->index();
            $table->string('codigo_barra', 50)->nullable()->index();
            $table->string('descripcion', 255)->index();
            $table->foreignId('categoria_id')->nullable()->constrained('productos_categorias')->nullOnDelete();
            $table->foreignId('subcategoria_id')->nullable()->constrained('productos_subcategorias')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->decimal('precio_costo', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(5);
            $table->string('unidad_medida', 30)->default('UNIDAD');
            $table->string('imagen')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->boolean('controla_stock')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
