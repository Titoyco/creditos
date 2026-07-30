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
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_credito')->constrained('creditos')->onDelete('cascade');
            
            $table->integer('num_cuota');
            $table->decimal('valor_base', 12, 2);
            $table->decimal('intereses', 12, 2)->default(0); // Interés por mora/atraso
            $table->decimal('entrego', 12, 2)->default(0);   // Sumatoria de pagos parciales
            $table->decimal('saldo_actual', 12, 2);          // Resultado neto a cobrar

            // Gestión de Cobro
            $table->date('fecha_cobro')->nullable();
            $table->string('medio_pago', 50)->nullable();
            $table->string('referencia_pago', 191)->nullable(); 
            $table->string('banco_envio', 100)->nullable(); 

            // Trazabilidad Bancaria y Devoluciones
            $table->date('fecha_ultimo_envio')->nullable();
            $table->string('estado_bancario', 5)->nullable(); // Ej: ACE, R10, R02
            $table->text('obs_devolucion')->nullable();       // Detalle del error del banco

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
