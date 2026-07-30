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
            Schema::create('sucursales', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 191);
                $table->string('codigo_sucursal', 1)->unique(); // T, U, I
                $table->timestamps();
            });

            Schema::create('sucursal_indices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_sucursal')->constrained('sucursales')->onDelete('cascade');
                $table->string('tipo_credito', 1); // C, D, R
                $table->integer('ultimo_indice')->default(0);
                $table->timestamps();
                
                $table->unique(['id_sucursal', 'tipo_credito'], 'idx_sucursal_tipo');
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
