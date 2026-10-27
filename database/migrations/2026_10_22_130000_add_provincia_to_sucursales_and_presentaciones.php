<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Asegurar provincias base si no existen (necesario en migraciones limpias y tests)
        if (Schema::hasTable('provincias')) {
            if (!DB::table('provincias')->where('id', 1)->exists()) {
                DB::table('provincias')->insert(['id' => 1, 'nombre' => 'Chubut', 'created_at' => now(), 'updated_at' => now()]);
            }
            if (!DB::table('provincias')->where('id', 2)->exists()) {
                DB::table('provincias')->insert(['id' => 2, 'nombre' => 'Tierra del Fuego', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // 1. Agregar id_provincia a sucursales
        if (!Schema::hasColumn('sucursales', 'id_provincia')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->foreignId('id_provincia')->nullable()->after('direccion')->constrained('provincias')->nullOnDelete();
            });
        }

        // Asignar provincias a sucursales existentes
        DB::table('sucursales')->whereIn('id', [1, 2])->update(['id_provincia' => 2]); // Tierra del Fuego
        DB::table('sucursales')->whereIn('id', [3, 4])->update(['id_provincia' => 1]); // Chubut

        // 2. Agregar id_provincia a presentaciones
        if (!Schema::hasColumn('presentaciones', 'id_provincia')) {
            Schema::table('presentaciones', function (Blueprint $table) {
                $table->foreignId('id_provincia')->default(2)->after('id')->constrained('provincias')->cascadeOnDelete();
            });
        }

        // Dropear índice unique en 'numero' si existe para permitir secuencias independientes por provincia
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $indexes = DB::select("SHOW INDEXES FROM presentaciones WHERE Key_name = 'presentaciones_numero_unique'");
            if (!empty($indexes)) {
                Schema::table('presentaciones', function (Blueprint $table) {
                    $table->dropUnique('presentaciones_numero_unique');
                });
            }
        }

        // Asignar todas las presentaciones históricas existentes a Tierra del Fuego (id 2)
        DB::table('presentaciones')->whereNull('id_provincia')->orWhere('id_provincia', 0)->update(['id_provincia' => 2]);

        // Crear la presentación activa inicial para Chubut (id_provincia = 1) si no existe
        $existeChubut = DB::table('presentaciones')->where('id_provincia', 1)->exists();
        if (!$existeChubut) {
            DB::table('presentaciones')->insert([
                'id_provincia' => 1,
                'numero' => 1,
                'fecha_inicio' => now()->subMonths(1)->toDateString(),
                'fecha_fin' => null,
                'interes_credito' => 0.06,
                'interes_mora' => 0.06,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('presentaciones', 'id_provincia')) {
            Schema::table('presentaciones', function (Blueprint $table) {
                $table->dropForeign(['id_provincia']);
                $table->dropColumn('id_provincia');
            });
        }

        if (Schema::hasColumn('sucursales', 'id_provincia')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->dropForeign(['id_provincia']);
                $table->dropColumn('id_provincia');
            });
        }
    }
};
