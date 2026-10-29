<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentaciones', function (Blueprint $table) {
            // Mayor precisión para guardar factores (ej: 0.0650 = 6,5%)
            $table->decimal('interes_credito', 8, 4)->default(0)->change();
            $table->decimal('interes_mora', 8, 4)->default(0)->change();

            // Si es true: 1 pago = 0%, 2 cuotas = 1x tasa, 3 cuotas = 2x tasa...
            // Si es false: 1 pago = 1x tasa, 2 cuotas = 2x tasa...
            if (!Schema::hasColumn('presentaciones', 'primera_cuota_sin_interes')) {
                $table->boolean('primera_cuota_sin_interes')->default(true)->after('interes_mora');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presentaciones', function (Blueprint $table) {
            if (Schema::hasColumn('presentaciones', 'primera_cuota_sin_interes')) {
                $table->dropColumn('primera_cuota_sin_interes');
            }
            $table->decimal('interes_credito', 5, 2)->default(0)->change();
            $table->decimal('interes_mora', 5, 2)->default(0)->change();
        });
    }
};
