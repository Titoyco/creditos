<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('productos_subcategorias', 'categoria_id')) {
            Schema::table('productos_subcategorias', function (Blueprint $table) {
                $table->unsignedBigInteger('categoria_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos_subcategorias', 'categoria_id')) {
            Schema::table('productos_subcategorias', function (Blueprint $table) {
                $table->unsignedBigInteger('categoria_id')->nullable(false)->change();
            });
        }
    }
};
