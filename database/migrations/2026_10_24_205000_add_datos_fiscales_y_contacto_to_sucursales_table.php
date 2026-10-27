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
        Schema::table('sucursales', function (Blueprint $table) {
            if (!Schema::hasColumn('sucursales', 'nombre_fantasia')) {
                $table->string('nombre_fantasia', 100)->default('La Marina')->after('nombre');
            }
            if (!Schema::hasColumn('sucursales', 'razon_social')) {
                $table->string('razon_social', 150)->nullable()->after('nombre_fantasia');
            }
            if (!Schema::hasColumn('sucursales', 'cuit')) {
                $table->string('cuit', 25)->nullable()->after('razon_social');
            }
            if (!Schema::hasColumn('sucursales', 'condicion_iva')) {
                $table->string('condicion_iva', 100)->default('IVA Responsable Inscripto')->after('cuit');
            }
            if (!Schema::hasColumn('sucursales', 'ingresos_brutos')) {
                $table->string('ingresos_brutos', 100)->nullable()->after('condicion_iva');
            }
            if (!Schema::hasColumn('sucursales', 'domicilio_fiscal')) {
                $table->string('domicilio_fiscal', 191)->nullable()->after('ingresos_brutos');
            }
            if (!Schema::hasColumn('sucursales', 'telefono')) {
                $table->string('telefono', 50)->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('sucursales', 'email')) {
                $table->string('email', 100)->nullable()->after('telefono');
            }
            if (!Schema::hasColumn('sucursales', 'pagina_web')) {
                $table->string('pagina_web', 150)->default('www.tiendaslamarina.com.ar')->after('email');
            }
            if (!Schema::hasColumn('sucursales', 'inicio_actividades')) {
                $table->string('inicio_actividades', 30)->nullable()->after('pagina_web');
            }
        });

        // Actualizar datos fiscales y de contacto para sucursales de Chubut (ID 3 y 4 o provincia 1)
        DB::table('sucursales')
            ->where('id', 3)
            ->orWhere('id', 4)
            ->orWhere('id_provincia', 1)
            ->update([
                'nombre_fantasia' => 'La Marina',
                'razon_social'    => 'Tiendas la Marina SAS',
                'cuit'            => '30-71699602-2',
                'condicion_iva'   => 'IVA Responsable Inscripto',
                'ingresos_brutos' => '30-71699602-2',
                'domicilio_fiscal'=> 'Rivadavia 249 - Trelew - Chubut',
                'telefono'        => '0280 442-0000',
                'email'           => 'trelew@tiendaslamarina.com.ar',
                'pagina_web'      => 'www.tiendaslamarina.com.ar',
            ]);

        // Asegurar dirección física específica de Trelew Centro (sucursal 3)
        DB::table('sucursales')->where('id', 3)->update([
            'direccion' => 'Rivadavia 249, Trelew, Chubut',
        ]);

        // Actualizar datos fiscales y de contacto para sucursales de Ushuaia / Tierra del Fuego (ID 1 y 2 o provincia 2)
        DB::table('sucursales')
            ->where('id', 1)
            ->orWhere('id', 2)
            ->orWhere('id_provincia', 2)
            ->update([
                'nombre_fantasia' => 'La Marina',
                'razon_social'    => 'BARZINI GUILLERMO ENRIQUE',
                'cuit'            => '20-11474524-4',
                'condicion_iva'   => 'IVA Responsable Inscripto',
                'ingresos_brutos' => '20-11474524-4',
                'domicilio_fiscal'=> 'San Martín 43, Ushuaia, Tierra del Fuego',
                'telefono'        => '02901 42-0000',
                'email'           => 'ushuaia@tiendaslamarina.com.ar',
                'pagina_web'      => 'www.tiendaslamarina.com.ar',
            ]);

        // Asegurar direcciones físicas de Ushuaia
        DB::table('sucursales')->where('id', 1)->update([
            'direccion' => 'San Martín 43, Ushuaia, Tierra del Fuego',
        ]);
        DB::table('sucursales')->where('id', 2)->update([
            'direccion' => 'Antártida Argentina, Ushuaia, Tierra del Fuego',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $columns = [
                'nombre_fantasia',
                'razon_social',
                'cuit',
                'condicion_iva',
                'ingresos_brutos',
                'domicilio_fiscal',
                'telefono',
                'email',
                'pagina_web',
                'inicio_actividades',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('sucursales', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
