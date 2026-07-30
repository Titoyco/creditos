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
        Schema::create('cliente_novedades', function (Blueprint $table) {
            $table->id();
            
            // Relación con Clientes
            $table->foreignId('id_cliente')->constrained('clientes')->onDelete('cascade');
            
            // Relación con Usuario de la app (Null si fue importado o eliminado)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('usuario_nombre')->nullable();
            
            // Contenido y Metadatos
            $table->text('mensaje');
            $table->string('origen_accion')->default('MIGRACION')->nullable(); // Ej: "MIGRACION", "Llamada", "Caja"
            $table->tinyInteger('tipo')->default(1); // 1 = Positiva (Verde), 0 = Negativa (Rojo)
            $table->string('metodo', 50)->nullable(); // "telefonico", "presencial", "mensaje", "sistema"
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_novedades');
    }
};