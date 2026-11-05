<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_proveedor', 100)->nullable()->after('codigo_barra')->index();
            $table->string('talle', 20)->nullable()->after('codigo_proveedor')->index();
            $table->string('color', 50)->nullable()->after('talle')->index();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['codigo_proveedor']);
            $table->dropIndex(['talle']);
            $table->dropIndex(['color']);
            $table->dropColumn(['codigo_proveedor', 'talle', 'color']);
        });
    }
};

