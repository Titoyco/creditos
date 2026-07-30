<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('clientes', function (Blueprint $table) {
        $table->id();
        
        // Datos Identificatorios y Sucursal
        $table->integer('dni')->unique();
        $table->string('nombre', 191);
        $table->foreignId('id_sucursal')->constrained('sucursales');
        
        // Datos Financieros y Estado
        $table->integer('medio_pago_preferido')->default(0); // 0: Efectivo, 1: Banco, etc.
        $table->string('cbu', 25)->nullable();
        $table->bigInteger('tipo_haber')->default(1);
        $table->decimal('saldo', 12, 2)->default(0);
        $table->decimal('limite', 12, 2)->default(0);
        $table->integer('max_cuotas')->default(3);
        $table->boolean('suspendido')->default(false);
        $table->string('estado_banco', 3)->nullable(); // Ej: ACT, MOR, BAJ
        $table->date('fecha_estado_banco')->nullable();
        
        // Ubicación y Contacto Particular
        $table->string('direccion', 191);
        $table->foreignId('id_localidad')->constrained('localidades');
        $table->string('email', 191)->nullable();
        $table->string('tel1', 50);
        $table->string('tel2', 50)->nullable();
        
        // Datos del Familiar / Referencia
        $table->string('familiar', 191)->nullable();
        $table->string('tipo_familiar', 50)->nullable(); // Ej: Padre, Hermano
        $table->string('direccion_familiar', 191)->nullable();
        $table->foreignId('id_localidad_familiar')->nullable()->constrained('localidades');
        $table->string('email_familiar', 191)->nullable();
        $table->string('tel_familiar', 50)->nullable();
        
        // Datos Laborales
        $table->string('trabajo', 191)->nullable();
        $table->string('direccion_trabajo', 191)->nullable();
        $table->foreignId('id_localidad_trabajo')->nullable()->constrained('localidades');
        $table->string('tel_trabajo', 50)->nullable();

        // Notas y Auditoría
        $table->text('obs')->nullable();
        $table->timestamps(); // created_at y updated_at
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
