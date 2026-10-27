<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dateTime('fecha_alta')->nullable()->index()->after('controla_stock');
            $table->dateTime('fecha_ultima_venta')->nullable()->index()->after('fecha_alta');
            $table->dateTime('fecha_ultima_compra')->nullable()->index()->after('fecha_ultima_venta');
            $table->dateTime('fecha_modificacion')->nullable()->index()->after('fecha_ultima_compra');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['fecha_alta']);
            $table->dropIndex(['fecha_ultima_venta']);
            $table->dropIndex(['fecha_ultima_compra']);
            $table->dropIndex(['fecha_modificacion']);
            $table->dropColumn([
                'fecha_alta',
                'fecha_ultima_venta',
                'fecha_ultima_compra',
                'fecha_modificacion',
            ]);
        });
    }
};
