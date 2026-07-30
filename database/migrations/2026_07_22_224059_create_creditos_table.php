<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos', function (Blueprint $table) {
            $table->id();
            
            // Relaciones con claves foráneas
            $table->foreignId('id_cliente')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('id_sucursal')->constrained('sucursales');
            $table->foreignId('id_autorizado')->constrained('cliente_autorizados')->onDelete('cascade');
            $table->foreignId('id_presentacion')->constrained('presentaciones');
            
            // Datos Identificatorios
            $table->string('credito', 20)->unique(); // Código único
            $table->string('tipo_credito', 1);       // C, D, R
            
            // Condiciones Económicas
            $table->decimal('importe_original', 12, 2);
            $table->integer('cantidad_cuotas');
            $table->decimal('valor_cuota_ref', 12, 2); 
            
            // Control de Lote


            $table->date('fecha_credito');
            $table->date('fecha_cancelacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditos');
    }
};
