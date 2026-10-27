<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('cuit', 20)->nullable()->index();
            $table->string('nombre', 200)->index();
            $table->string('contacto', 100)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->foreignId('id_localidad')->nullable()->constrained('localidades')->nullOnDelete();
            $table->foreignId('id_iva')->nullable()->constrained('condiciones_iva')->nullOnDelete();
            $table->string('tel1', 50)->nullable();
            $table->string('tel2', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('web', 200)->nullable();
            $table->decimal('saldo', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
