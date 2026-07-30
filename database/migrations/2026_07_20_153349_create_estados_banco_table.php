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
        Schema::create('estados_banco', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique(); // Ej: R02, R08, ACE
            $table->string('descripcion');          // Ej: Fondos Insuficientes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estados_banco');
    }
};
