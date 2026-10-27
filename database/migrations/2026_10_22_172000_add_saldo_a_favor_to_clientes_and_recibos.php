<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'saldo_a_favor')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->decimal('saldo_a_favor', 12, 2)->default(0)->after('saldo');
            });
        }

        if (!Schema::hasColumn('recibos', 'saldo_a_favor_generado')) {
            Schema::table('recibos', function (Blueprint $table) {
                $table->decimal('saldo_a_favor_generado', 12, 2)->default(0)->after('importe_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'saldo_a_favor')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('saldo_a_favor');
            });
        }

        if (Schema::hasColumn('recibos', 'saldo_a_favor_generado')) {
            Schema::table('recibos', function (Blueprint $table) {
                $table->dropColumn('saldo_a_favor_generado');
            });
        }
    }
};
