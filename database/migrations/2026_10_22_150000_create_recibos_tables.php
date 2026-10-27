<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar ultimo_recibo a sucursales si no existe
        if (!Schema::hasColumn('sucursales', 'ultimo_recibo')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->integer('ultimo_recibo')->default(0)->after('codigo_sucursal');
            });

            // Trelew - Centro (id 3) inicia en 26416 según la base real de Trelew
            DB::table('sucursales')->where('id', 3)->update(['ultimo_recibo' => 26416]);
            // Trelew - Interior (id 4)
            DB::table('sucursales')->where('id', 4)->update(['ultimo_recibo' => 0]);
        }

        // 2. Tabla recibos
        if (!Schema::hasTable('recibos')) {
            Schema::create('recibos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_sucursal')->constrained('sucursales');
                $table->foreignId('id_cliente')->constrained('clientes')->onDelete('cascade');
                $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
                
                $table->integer('numero_recibo');
                $table->dateTime('fecha');
                $table->decimal('importe_total', 12, 2);
                $table->string('medio_pago', 50)->default('Efectivo'); // Efectivo, Débito, Crédito, Transferencia, etc.
                $table->text('observaciones')->nullable();

                // Control de anulación
                $table->boolean('anulado')->default(false);
                $table->dateTime('fecha_anulacion')->nullable();
                $table->string('motivo_anulacion', 255)->nullable();
                $table->foreignId('user_anulacion_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['id_sucursal', 'numero_recibo']);
                $table->index('fecha');
                $table->index('id_cliente');
            });
        }

        // 3. Tabla recibo_detalles
        if (!Schema::hasTable('recibo_detalles')) {
            Schema::create('recibo_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_recibo')->constrained('recibos')->cascadeOnDelete();
                $table->foreignId('id_credito')->nullable()->constrained('creditos')->nullOnDelete();
                $table->foreignId('id_cuota')->nullable()->constrained('cuotas')->nullOnDelete();
                
                $table->integer('num_cuota')->nullable();
                $table->string('concepto', 191)->nullable(); // Ej: "Cuota 2/3 - Crédito C00325623T"
                $table->decimal('importe', 12, 2);
                $table->decimal('saldo_anterior', 12, 2)->default(0);
                $table->decimal('saldo_nuevo', 12, 2)->default(0);

                $table->timestamps();

                $table->index('id_recibo');
                $table->index('id_credito');
                $table->index('id_cuota');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recibo_detalles');
        Schema::dropIfExists('recibos');

        if (Schema::hasColumn('sucursales', 'ultimo_recibo')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->dropColumn('ultimo_recibo');
            });
        }
    }
};
