<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Crear tabla medios_pago si no existe
        if (!Schema::hasTable('medios_pago')) {
            Schema::create('medios_pago', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 50)->unique();
                $table->string('nombre', 100);
                $table->string('tipo_base', 30)->default('otro'); // 'efectivo', 'tarjeta', 'credito', 'banco', 'otro'
                $table->decimal('recargo_porcentaje', 5, 2)->default(0.00);
                $table->decimal('descuento_porcentaje', 5, 2)->default(0.00);
                $table->boolean('requiere_referencia')->default(false);
                $table->boolean('permite_vuelto')->default(false);
                $table->string('icono', 50)->default('💳');
                $table->string('color', 50)->default('indigo');
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // 2. Poblar los medios de pago predeterminados para asegurar retrocompatibilidad inmediata
        $mediosIniciales = [
            [
                'codigo'               => 'efectivo',
                'nombre'               => 'Efectivo',
                'tipo_base'            => 'efectivo',
                'recargo_porcentaje'   => 0.00,
                'descuento_porcentaje' => 0.00,
                'requiere_referencia'  => false,
                'permite_vuelto'       => true,
                'icono'                => '💵',
                'color'                => 'emerald',
                'orden'                => 1,
                'activo'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'codigo'               => 'tarjeta_debito',
                'nombre'               => 'Tarjeta Débito',
                'tipo_base'            => 'tarjeta',
                'recargo_porcentaje'   => 0.00,
                'descuento_porcentaje' => 0.00,
                'requiere_referencia'  => true,
                'permite_vuelto'       => false,
                'icono'                => '💳',
                'color'                => 'cyan',
                'orden'                => 2,
                'activo'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'codigo'               => 'tarjeta_credito',
                'nombre'               => 'Tarjeta Crédito',
                'tipo_base'            => 'tarjeta',
                'recargo_porcentaje'   => 0.00,
                'descuento_porcentaje' => 0.00,
                'requiere_referencia'  => true,
                'permite_vuelto'       => false,
                'icono'                => '💳',
                'color'                => 'blue',
                'orden'                => 3,
                'activo'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'codigo'               => 'credito_personal',
                'nombre'               => 'Crédito Personal / Cta Cte',
                'tipo_base'            => 'credito',
                'recargo_porcentaje'   => 0.00,
                'descuento_porcentaje' => 0.00,
                'requiere_referencia'  => false,
                'permite_vuelto'       => false,
                'icono'                => '📑',
                'color'                => 'indigo',
                'orden'                => 4,
                'activo'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'codigo'               => 'transferencia',
                'nombre'               => 'Transferencia Bancaria / QR',
                'tipo_base'            => 'banco',
                'recargo_porcentaje'   => 0.00,
                'descuento_porcentaje' => 0.00,
                'requiere_referencia'  => true,
                'permite_vuelto'       => false,
                'icono'                => '📱',
                'color'                => 'teal',
                'orden'                => 5,
                'activo'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'codigo'               => 'cheque',
                'nombre'               => 'Cheque',
                'tipo_base'            => 'otro',
                'recargo_porcentaje'   => 0.00,
                'descuento_porcentaje' => 0.00,
                'requiere_referencia'  => true,
                'permite_vuelto'       => false,
                'icono'                => '🏦',
                'color'                => 'amber',
                'orden'                => 6,
                'activo'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'codigo'               => 'otros',
                'nombre'               => 'Otros Medios',
                'tipo_base'            => 'otro',
                'recargo_porcentaje'   => 0.00,
                'descuento_porcentaje' => 0.00,
                'requiere_referencia'  => false,
                'permite_vuelto'       => false,
                'icono'                => '📦',
                'color'                => 'slate',
                'orden'                => 7,
                'activo'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
        ];

        foreach ($mediosIniciales as $m) {
            DB::table('medios_pago')->updateOrInsert(['codigo' => $m['codigo']], $m);
        }

        // 3. Agregar id_medio_pago a la tabla ventas si no existe
        if (Schema::hasTable('ventas') && !Schema::hasColumn('ventas', 'id_medio_pago')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->foreignId('id_medio_pago')->nullable()->after('medio_pago')->constrained('medios_pago')->nullOnDelete();
            });

            // Vincular automáticamente ventas preexistentes con su id_medio_pago correspondiente (MySQL)
            if (DB::getDriverName() === 'mysql') {
                DB::update("
                    UPDATE ventas v
                    INNER JOIN medios_pago m ON LOWER(TRIM(v.medio_pago)) = LOWER(TRIM(m.codigo))
                    SET v.id_medio_pago = m.id
                ");

                // Mapeos adicionales comunes
                DB::update("
                    UPDATE ventas v
                    INNER JOIN medios_pago m ON m.codigo = 'efectivo'
                    SET v.id_medio_pago = m.id
                    WHERE v.id_medio_pago IS NULL AND (LOWER(v.medio_pago) LIKE '%efectivo%')
                ");
                DB::update("
                    UPDATE ventas v
                    INNER JOIN medios_pago m ON m.codigo = 'tarjeta_debito'
                    SET v.id_medio_pago = m.id
                    WHERE v.id_medio_pago IS NULL AND (LOWER(v.medio_pago) LIKE '%debito%')
                ");
                DB::update("
                    UPDATE ventas v
                    INNER JOIN medios_pago m ON m.codigo = 'tarjeta_credito'
                    SET v.id_medio_pago = m.id
                    WHERE v.id_medio_pago IS NULL AND (LOWER(v.medio_pago) LIKE '%credito%' AND LOWER(v.medio_pago) NOT LIKE '%personal%')
                ");
                DB::update("
                    UPDATE ventas v
                    INNER JOIN medios_pago m ON m.codigo = 'credito_personal'
                    SET v.id_medio_pago = m.id
                    WHERE v.id_medio_pago IS NULL AND (LOWER(v.medio_pago) LIKE '%personal%' OR LOWER(v.medio_pago) LIKE '%cta%')
                ");
                DB::update("
                    UPDATE ventas v
                    INNER JOIN medios_pago m ON m.codigo = 'transferencia'
                    SET v.id_medio_pago = m.id
                    WHERE v.id_medio_pago IS NULL AND (LOWER(v.medio_pago) LIKE '%transferencia%' OR LOWER(v.medio_pago) LIKE '%mercado%' OR LOWER(v.medio_pago) LIKE '%qr%')
                ");
                // Para las que queden huérfanas, asignar 'otros'
                DB::update("
                    UPDATE ventas v
                    INNER JOIN medios_pago m ON m.codigo = 'otros'
                    SET v.id_medio_pago = m.id
                    WHERE v.id_medio_pago IS NULL
                ");
            }
        }

        // 4. Agregar id_medio_pago a la tabla cuotas si no existe
        if (Schema::hasTable('cuotas') && !Schema::hasColumn('cuotas', 'id_medio_pago')) {
            Schema::table('cuotas', function (Blueprint $table) {
                $table->foreignId('id_medio_pago')->nullable()->after('medio_pago')->constrained('medios_pago')->nullOnDelete();
            });

            if (DB::getDriverName() === 'mysql') {
                DB::update("
                    UPDATE cuotas c
                    INNER JOIN medios_pago m ON LOWER(TRIM(c.medio_pago)) = LOWER(TRIM(m.codigo)) OR LOWER(TRIM(c.medio_pago)) = LOWER(TRIM(m.nombre))
                    SET c.id_medio_pago = m.id
                    WHERE c.medio_pago IS NOT NULL
                ");
            }
        }

        // 5. Agregar id_medio_pago a la tabla recibos si no existe
        if (Schema::hasTable('recibos') && !Schema::hasColumn('recibos', 'id_medio_pago')) {
            Schema::table('recibos', function (Blueprint $table) {
                $table->foreignId('id_medio_pago')->nullable()->after('medio_pago')->constrained('medios_pago')->nullOnDelete();
            });

            if (DB::getDriverName() === 'mysql') {
                DB::update("
                    UPDATE recibos r
                    INNER JOIN medios_pago m ON LOWER(TRIM(r.medio_pago)) = LOWER(TRIM(m.codigo)) OR LOWER(TRIM(r.medio_pago)) = LOWER(TRIM(m.nombre))
                    SET r.id_medio_pago = m.id
                    WHERE r.medio_pago IS NOT NULL
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('recibos') && Schema::hasColumn('recibos', 'id_medio_pago')) {
            Schema::table('recibos', function (Blueprint $table) {
                $table->dropForeign(['id_medio_pago']);
                $table->dropColumn('id_medio_pago');
            });
        }

        if (Schema::hasTable('cuotas') && Schema::hasColumn('cuotas', 'id_medio_pago')) {
            Schema::table('cuotas', function (Blueprint $table) {
                $table->dropForeign(['id_medio_pago']);
                $table->dropColumn('id_medio_pago');
            });
        }

        if (Schema::hasTable('ventas') && Schema::hasColumn('ventas', 'id_medio_pago')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->dropForeign(['id_medio_pago']);
                $table->dropColumn('id_medio_pago');
            });
        }

        Schema::dropIfExists('medios_pago');
    }
};
