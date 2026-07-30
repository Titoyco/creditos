<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_autorizados', function (Blueprint $table) {
            $table->id();
            
            // Claves foráneas que apuntan a la misma tabla 'clientes'
            $table->foreignId('id_titular')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('id_autorizado')->constrained('clientes')->onDelete('cascade');
            
            // Metadatos de la autorización
            $table->string('relacion_aut', 100)->nullable(); // Parentesco: "Hijo", "Cónyuge", etc.
            $table->date('fecha_autorizado')->nullable();    // Fecha de alta de la autorización
            $table->decimal('limite_aut', 12, 2)->nullable(); // Límite específico (si es null, usa el del titular)
            
            $table->timestamps();

            // Evitamos que se duplique la misma relación titular-autorizado
            $table->unique(['id_titular', 'id_autorizado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_autorizados');
    }
};