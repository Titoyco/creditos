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
        if (!Schema::hasColumn('sucursales', 'ultimo_comprobante')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->unsignedInteger('ultimo_comprobante')->default(0)->after('ultimo_recibo');
            });
        }

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->string('cliente_nombre', 191)->default('CONSUMIDOR FINAL');
            $table->string('cliente_documento', 50)->nullable();
            $table->unsignedInteger('punto_venta')->default(1);
            $table->unsignedInteger('numero_factura');
            $table->string('comprobante', 30)->index();
            $table->string('tipo_comprobante', 30)->default('FACTURA_B');
            $table->dateTime('fecha');
            $table->string('medio_pago', 40); // efectivo, tarjeta_debito, tarjeta_credito, credito_personal
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('recargo', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedBigInteger('id_credito')->nullable();
            $table->unsignedTinyInteger('cuotas_credito')->nullable();
            $table->decimal('valor_cuota_credito', 12, 2)->nullable();
            $table->string('estado', 20)->default('completada'); // completada, anulada
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('id_sucursal')->references('id')->on('sucursales');
            $table->foreign('id_usuario')->references('id')->on('users');
            $table->foreign('id_cliente')->references('id')->on('clientes')->nullOnDelete();
            $table->foreign('id_credito')->references('id')->on('creditos')->nullOnDelete();

            $table->index(['id_sucursal', 'fecha']);
            $table->index('id_cliente');
            $table->index('medio_pago');
        });

        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_venta');
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->string('codigo_producto', 50)->nullable();
            $table->string('descripcion', 255);
            $table->decimal('cantidad', 10, 2)->default(1.00);
            $table->decimal('precio_unitario', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->boolean('descuenta_stock')->default(false);
            $table->timestamps();

            $table->foreign('id_venta')->references('id')->on('ventas')->cascadeOnDelete();
            $table->foreign('id_producto')->references('id')->on('productos')->nullOnDelete();

            $table->index('id_producto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_items');
        Schema::dropIfExists('ventas');

        if (Schema::hasColumn('sucursales', 'ultimo_comprobante')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->dropColumn('ultimo_comprobante');
            });
        }
    }
};

