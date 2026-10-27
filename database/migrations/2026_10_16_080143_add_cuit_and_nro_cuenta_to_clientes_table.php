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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cuit', 20)->nullable()->after('dni')->index();
            $table->string('nro_cuenta', 50)->nullable()->after('id_sucursal')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['cuit']);
            $table->dropIndex(['nro_cuenta']);
            $table->dropColumn(['cuit', 'nro_cuenta']);
        });
    }
};
