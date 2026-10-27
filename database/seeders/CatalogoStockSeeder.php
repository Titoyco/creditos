<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CondicionIva;
use App\Models\Proveedor;
use App\Models\ProductoCategoria;
use App\Models\ProductoSubcategoria;
use App\Models\Producto;
use App\Models\Sucursal;

class CatalogoStockSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Condiciones IVA
        $condiciones = [
            ['nombre' => 'RESPONSABLE INSCRIPTO', 'codigo_afip' => '01'],
            ['nombre' => 'MONOTRIBUTO', 'codigo_afip' => '06'],
            ['nombre' => 'EXENTO', 'codigo_afip' => '04'],
            ['nombre' => 'CONSUMIDOR FINAL', 'codigo_afip' => '05'],
        ];

        foreach ($condiciones as $cond) {
            CondicionIva::firstOrCreate(['nombre' => $cond['nombre']], $cond);
        }

        $ivaRi = CondicionIva::where('nombre', 'RESPONSABLE INSCRIPTO')->first();

        // 2. Proveedores iniciales
        $prov1 = Proveedor::firstOrCreate(['cuit' => '30711223344'], [
            'cuit' => '30711223344',
            'nombre' => 'DISTRIBUIDORA PATAGONIA TEXTIL S.A.',
            'contacto' => 'Carlos Morales',
            'direccion' => 'Av. San Martín 1540',
            'tel1' => '2901-445566',
            'email' => 'ventas@patagoniatextil.com',
            'id_iva' => $ivaRi?->id,
            'saldo' => 0,
            'activo' => true,
        ]);

        $prov2 = Proveedor::firstOrCreate(['cuit' => '30698877665'], [
            'cuit' => '30698877665',
            'nombre' => 'CALZADOS DEL SUR S.R.L.',
            'contacto' => 'Mariana Gomez',
            'direccion' => 'Ruta 3 Km 5',
            'tel1' => '280-4556677',
            'email' => 'contacto@calzadosdelsur.com.ar',
            'id_iva' => $ivaRi?->id,
            'saldo' => 0,
            'activo' => true,
        ]);

        // 3. Categorías y Subcategorías
        $catIndumentaria = ProductoCategoria::firstOrCreate(['nombre' => 'INDUMENTARIA']);
        $catCalzado = ProductoCategoria::firstOrCreate(['nombre' => 'CALZADO']);
        $catHogar = ProductoCategoria::firstOrCreate(['nombre' => 'HOGAR Y BLANQUERIA']);
        $catVarios = ProductoCategoria::firstOrCreate(['nombre' => 'VARIOS']);

        $subPantalones = ProductoSubcategoria::firstOrCreate(
            ['nombre' => 'PANTALONES Y JEANS']
        );
        $subCamperas = ProductoSubcategoria::firstOrCreate(
            ['nombre' => 'CAMPERAS Y ABRIGOS']
        );
        $subZapatillas = ProductoSubcategoria::firstOrCreate(
            ['nombre' => 'ZAPATILLAS URBANAS']
        );
        $subGeneral = ProductoSubcategoria::firstOrCreate(
            ['nombre' => 'ARTICULOS GENERALES']
        );

        // 4. Productos de muestra con códigos de barra y stock multi-sucursal
        $sucursales = Sucursal::all();

        $prod1 = Producto::firstOrCreate(['codigo' => 'ART-001001'], [
            'codigo' => 'ART-001001',
            'codigo_barra' => '7791234567890',
            'descripcion' => 'PANTALON CORDEROY CLASICO TULY',
            'categoria_id' => $catIndumentaria->id,
            'subcategoria_id' => $subPantalones->id,
            'proveedor_id' => $prov1->id,
            'precio_costo' => 15000.00,
            'precio_venta' => 28500.00,
            'stock_minimo' => 4,
            'unidad_medida' => 'UNIDAD',
            'activo' => true,
        ]);

        $prod2 = Producto::firstOrCreate(['codigo' => 'ART-002005'], [
            'codigo' => 'ART-002005',
            'codigo_barra' => '7799876543210',
            'descripcion' => 'CAMPERA INFLABLE POLAR SUR',
            'categoria_id' => $catIndumentaria->id,
            'subcategoria_id' => $subCamperas->id,
            'proveedor_id' => $prov1->id,
            'precio_costo' => 38000.00,
            'precio_venta' => 69000.00,
            'stock_minimo' => 2,
            'unidad_medida' => 'UNIDAD',
            'activo' => true,
        ]);

        $prod3 = Producto::firstOrCreate(['codigo' => 'ART-009999'], [
            'codigo' => 'ART-009999',
            'codigo_barra' => 'VARIOS',
            'descripcion' => 'ARTICULO GENERAL / VENTA VARIOS',
            'categoria_id' => $catVarios->id,
            'subcategoria_id' => $subGeneral->id,
            'proveedor_id' => $prov1->id,
            'precio_costo' => 0.00,
            'precio_venta' => 0.00,
            'stock_minimo' => 0,
            'unidad_medida' => 'UNIDAD',
            'activo' => true,
            'controla_stock' => false,
        ]);

        // Cargar stock inicial en sucursales
        foreach ($sucursales as $suc) {
            // Asignar stock en Ushuaia principalmente para productos inventariables
            $stockQty1 = str_contains(strtoupper($suc->nombre), 'USHUAIA') ? 15 : 0;
            $stockQty2 = str_contains(strtoupper($suc->nombre), 'USHUAIA') ? 8 : 0;

            if ($stockQty1 > 0) {
                $prod1->ajustarStock($suc->id, 'alta', $stockQty1, 'Stock inicial de apertura');
            }
            if ($stockQty2 > 0) {
                $prod2->ajustarStock($suc->id, 'alta', $stockQty2, 'Stock inicial de apertura');
            }

            // Para el artículo genérico VARIOS, se inicializa el registro con stock 0 (no contabiliza unidades ni valor)
            \App\Models\ProductoStock::firstOrCreate(
                ['producto_id' => $prod3->id, 'sucursal_id' => $suc->id],
                ['stock' => 0, 'stock_minimo' => 0, 'precio_venta' => 0]
            );
        }
    }
}
