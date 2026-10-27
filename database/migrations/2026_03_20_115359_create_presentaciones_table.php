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
        Schema::create('presentaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('numero'); // El ID del lote (Ej: 500, 510, 600)
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            
            // Tasas que regirán para este lote
            $table->decimal('interes_credito', 5, 2)->default(0);
            $table->decimal('interes_mora', 5, 2)->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentaciones');
    }
};
