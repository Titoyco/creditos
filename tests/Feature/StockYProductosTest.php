<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\ProductoSubcategoria;
use App\Models\Proveedor;
use App\Models\ProductoStock;
use App\Models\ProductoMovimiento;

class StockYProductosTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Sucursal $sucUshuaia;
    protected Sucursal $sucTrelew;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);
        $this->seed(\Database\Seeders\UbicacionSeeder::class);
        $this->seed(\Database\Seeders\PresentacionSeeder::class);
        $this->seed(\Database\Seeders\CatalogoStockSeeder::class);

        $this->admin = User::where('username', 'admin')->first();
        $this->sucUshuaia = Sucursal::where('nombre', 'like', '%Ushuaia%')->first() ?? Sucursal::first();
        $this->sucTrelew = Sucursal::where('nombre', 'like', '%Trelew%')->first() ?? Sucursal::latest('id')->first();
        if ($this->admin) {
            $this->admin->id_sucursal = $this->sucUshuaia->id;
            $this->admin->save();
        }
        session(['sucursal_activa_id' => $this->sucUshuaia->id]);
    }

    public function test_can_render_productos_index_and_create_screens(): void
    {
        $response = $this->actingAs($this->admin)->get(route('productos.index'));
        $response->assertStatus(200);
        $response->assertSee('Catálogo y Stock de Productos');

        $responseCreate = $this->actingAs($this->admin)->get(route('productos.create'));
        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('Nuevo Producto / Artículo');
    }

    public function test_can_create_producto_with_multi_branch_stock(): void
    {
        $cat = ProductoCategoria::first();
        $prov = Proveedor::first();

        $data = [
            'codigo' => 'TEST-SKU-99',
            'codigo_barra' => '7799999999999',
            'descripcion' => 'ZAPATILLA RUNNING ADIDAS SUR',
            'categoria_id' => $cat->id,
            'proveedor_id' => $prov->id,
            'precio_costo' => 20000,
            'precio_venta' => 38000,
            'stock_minimo' => 3,
            'unidad_medida' => 'PAR',
            'stock_inicial' => [
                $this->sucUshuaia->id => 10,
                $this->sucTrelew->id => 5,
            ]
        ];

        $response = $this->actingAs($this->admin)->post(route('productos.store'), $data);
        
        $producto = Producto::where('codigo', 'TEST-SKU-99')->first();
        $this->assertNotNull($producto);
        $response->assertRedirect(route('productos.show', $producto->id));

        // Verificar que el stock se inicializó correctamente
        $this->assertEquals(10, $producto->getStockSucursal($this->sucUshuaia->id));
        $this->assertEquals(5, $producto->getStockSucursal($this->sucTrelew->id));
        $this->assertEquals(15, $producto->getStockTotal());

        // Verificar que se crearon los movimientos de auditoría
        $this->assertDatabaseHas('productos_movimientos', [
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'tipo' => 'alta',
            'cantidad' => 10,
        ]);
    }

    public function test_can_adjust_stock_manually(): void
    {
        $producto = Producto::first();
        $stockOriginal = $producto->getStockSucursal($this->sucUshuaia->id);

        // 1. Ajuste de alta (+5)
        $responseAlta = $this->actingAs($this->admin)->post(route('productos.ajuste', $producto->id), [
            'sucursal_id' => $this->sucUshuaia->id,
            'tipo' => 'alta',
            'cantidad' => 5,
            'motivo' => 'Compra rápida de mostrador',
            'comprobante' => 'REM-TEST-01',
        ]);
        $responseAlta->assertSessionHas('success');
        $this->assertEquals($stockOriginal + 5, $producto->fresh()->getStockSucursal($this->sucUshuaia->id));

        // 2. Ajuste de baja (-2)
        $responseBaja = $this->actingAs($this->admin)->post(route('productos.ajuste', $producto->id), [
            'sucursal_id' => $this->sucUshuaia->id,
            'tipo' => 'baja',
            'cantidad' => 2,
            'motivo' => 'Rotura de exhibición',
            'comprobante' => 'MERMA-01',
        ]);
        $responseBaja->assertSessionHas('success');
        $this->assertEquals($stockOriginal + 3, $producto->fresh()->getStockSucursal($this->sucUshuaia->id));
    }

    public function test_can_transfer_stock_between_branches(): void
    {
        $producto = Producto::first();
        // Asegurar stock en Ushuaia
        $producto->ajustarStock($this->sucUshuaia->id, 'alta', 20, 'Fondo de prueba');
        
        $stockOrigenAntes = $producto->getStockSucursal($this->sucUshuaia->id);
        $stockDestinoAntes = $producto->getStockSucursal($this->sucTrelew->id);

        // Trasladar 7 unidades de Ushuaia a Trelew
        $response = $this->actingAs($this->admin)->post(route('productos.trasladar', $producto->id), [
            'sucursal_origen_id' => $this->sucUshuaia->id,
            'sucursal_destino_id' => $this->sucTrelew->id,
            'cantidad' => 7,
            'comprobante' => 'GUIA-AEREA-9988',
            'motivo' => 'Refuerzo de stock por ventas',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals($stockOrigenAntes - 7, $producto->fresh()->getStockSucursal($this->sucUshuaia->id));
        $this->assertEquals($stockDestinoAntes + 7, $producto->fresh()->getStockSucursal($this->sucTrelew->id));

        // Verificar movimientos registrados
        $this->assertDatabaseHas('productos_movimientos', [
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'tipo' => 'traslado_salida',
            'cantidad' => -7,
        ]);

        $this->assertDatabaseHas('productos_movimientos', [
            'producto_id' => $producto->id,
            'sucursal_id' => $this->sucTrelew->id,
            'tipo' => 'traslado_entrada',
            'cantidad' => 7,
        ]);
    }

    public function test_cannot_transfer_more_stock_than_available(): void
    {
        $producto = Producto::first();
        $stockOrigen = $producto->getStockSucursal($this->sucUshuaia->id);

        $response = $this->actingAs($this->admin)->post(route('productos.trasladar', $producto->id), [
            'sucursal_origen_id' => $this->sucUshuaia->id,
            'sucursal_destino_id' => $this->sucTrelew->id,
            'cantidad' => $stockOrigen + 9999, // Supera stock
            'motivo' => 'Exceso imposible',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_proveedores_crud_and_protection(): void
    {
        // 1. Crear proveedor
        $response = $this->actingAs($this->admin)->post(route('proveedores.store'), [
            'nombre' => 'FABRICA TEXTIL PATAGONICA',
            'cuit' => '30999888771',
            'contacto' => 'Roberto Carlos',
            'tel1' => '2901-554433',
        ]);

        $prov = Proveedor::where('nombre', 'FABRICA TEXTIL PATAGONICA')->first();
        $this->assertNotNull($prov);
        $response->assertRedirect(route('proveedores.show', $prov->id));

        // 2. Sin operaciones se puede eliminar
        $responseDelete = $this->actingAs($this->admin)->delete(route('proveedores.destroy', $prov->id));
        $responseDelete->assertSessionHas('success');
        $this->assertDatabaseMissing('proveedores', ['id' => $prov->id]);
    }

    public function test_categorias_and_subcategorias_api(): void
    {
        $catPantalon = ProductoCategoria::create(['nombre' => 'PANTALONES']);
        $catRemera = ProductoCategoria::create(['nombre' => 'REMERAS']);
        
        // Crear subcategoría global
        $responseSub = $this->actingAs($this->admin)->post(route('subcategorias.store'), [
            'nombre' => 'CABALLERO',
            'descripcion' => 'Segmento masculino adulto',
        ]);
        $responseSub->assertSessionHas('success');

        $subCaballero = ProductoSubcategoria::where('nombre', 'CABALLERO')->first();
        $this->assertNotNull($subCaballero);

        // Ambas categorías diferentes se pueden combinar con la misma subcategoría
        $p1 = Producto::create([
            'codigo' => 'PANT-CAB-01',
            'descripcion' => 'PANTALON VESTIR CABALLERO',
            'categoria_id' => $catPantalon->id,
            'subcategoria_id' => $subCaballero->id,
            'precio_costo' => 5000,
            'precio_venta' => 12000,
            'controla_stock' => true,
        ]);

        $p2 = Producto::create([
            'codigo' => 'REM-CAB-01',
            'descripcion' => 'REMERA MANGA CORTA CABALLERO',
            'categoria_id' => $catRemera->id,
            'subcategoria_id' => $subCaballero->id,
            'precio_costo' => 2000,
            'precio_venta' => 5000,
            'controla_stock' => true,
        ]);

        $p1->ajustarStock($this->sucUshuaia->id, 'alta', 10, 'Stock test');
        $p2->ajustarStock($this->sucUshuaia->id, 'alta', 5, 'Stock test');

        $this->assertEquals($subCaballero->id, $p1->subcategoria_id);
        $this->assertEquals($subCaballero->id, $p2->subcategoria_id);

        // Endpoints JSON global y retrocompatible
        $responseJsonGlobal = $this->actingAs($this->admin)->get(route('subcategorias.json'));
        $responseJsonGlobal->assertStatus(200);
        $responseJsonGlobal->assertJsonFragment(['nombre' => 'CABALLERO']);

        $responseJsonLegacy = $this->actingAs($this->admin)->get(route('categorias.subcategorias.json', $catPantalon->id));
        $responseJsonLegacy->assertStatus(200);
        $responseJsonLegacy->assertJsonFragment(['nombre' => 'CABALLERO']);

        // Filtrar productos por subcategoría en el catálogo
        $responseFilterSub = $this->actingAs($this->admin)->get(route('productos.index', ['subcategoria_id' => $subCaballero->id]));
        $responseFilterSub->assertStatus(200);
        $responseFilterSub->assertSee('PANTALON VESTIR CABALLERO');
        $responseFilterSub->assertSee('REMERA MANGA CORTA CABALLERO');
    }

    public function test_generic_product_varios_does_not_distort_stock_or_valuation(): void
    {
        $varios = Producto::where('codigo', 'ART-009999')->first();
        $this->assertNotNull($varios);
        $this->assertFalse((bool)$varios->controla_stock, "El producto VARIOS no debe controlar stock fisico.");
        $this->assertEquals(0, (float)$varios->precio_costo);
        $this->assertEquals(0, (float)$varios->precio_venta);
        $this->assertEquals(0, $varios->getStockTotal(), "El producto VARIOS debe tener stock 0.");

        // Verificar que en el index la métrica de existencias físicas no suma 999 ni altera valuación
        $response = $this->actingAs($this->admin)->get(route('productos.index'));
        $response->assertStatus(200);
        $response->assertSee('Existencias Físicas');
        $response->assertSee('Libre');

        // Intentar trasladar producto genérico debe ser bloqueado
        $responseTraslado = $this->actingAs($this->admin)->post(route('productos.trasladar', $varios->id), [
            'sucursal_origen_id' => $this->sucUshuaia->id,
            'sucursal_destino_id' => $this->sucTrelew->id,
            'cantidad' => 10,
        ]);
        $responseTraslado->assertSessionHas('error');
    }

    public function test_search_filters_update_stock_and_sales_valuation_dynamically(): void
    {
        // 1. Consulta sin filtros
        $responseAll = $this->actingAs($this->admin)->get(route('productos.index'));
        $responseAll->assertStatus(200);
        $metricasAll = $responseAll->viewData('metricas');
        $this->assertFalse($metricasAll['is_filtered']);
        $this->assertGreaterThan(0, $metricasAll['valor_stock_venta']);

        // 2. Consulta filtrando por 'PANTALON'
        $responseSearch = $this->actingAs($this->admin)->get(route('productos.index', ['search' => 'PANTALON']));
        $responseSearch->assertStatus(200);
        $metricasSearch = $responseSearch->viewData('metricas');
        
        $this->assertTrue($metricasSearch['is_filtered']);
        $this->assertEquals(1, $metricasSearch['total_articulos']);
        $this->assertEquals(15, $metricasSearch['total_stock_unidades']);
        // 15 unidades * $28.500 precio de venta = $427.500,00
        $this->assertEquals(427500, $metricasSearch['valor_stock_venta']);
        $responseSearch->assertSee('Búsqueda');
        $responseSearch->assertSee('Capital posible de venta filtrado');
        $responseSearch->assertSee('427.500,00');
    }

    public function test_rotation_filter_and_sorting_by_dates(): void
    {
        // Crear un producto con última venta hace 3 años (estancado)
        $pEstancado = Producto::create([
            'codigo' => 'PROD-ESTANCADO',
            'descripcion' => 'CAMPERA ANTIGUA SIN SALIDA',
            'precio_costo' => 1000,
            'precio_venta' => 2500,
            'controla_stock' => true,
            'fecha_alta' => now()->subYears(4),
            'fecha_ultima_venta' => now()->subYears(3),
        ]);
        ProductoStock::create([
            'producto_id' => $pEstancado->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'stock' => 8,
        ]);

        // Crear un producto con venta reciente
        $pReciente = Producto::create([
            'codigo' => 'PROD-ACTIVO',
            'descripcion' => 'REMERON MODERNO VENTA DIARIA',
            'precio_costo' => 500,
            'precio_venta' => 1200,
            'controla_stock' => true,
            'fecha_alta' => now()->subMonths(2),
            'fecha_ultima_venta' => now()->subDays(5),
        ]);
        ProductoStock::create([
            'producto_id' => $pReciente->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'stock' => 12,
        ]);

        // 1. Filtrar por productos sin venta hace más de 2 años
        $responseStagnant = $this->actingAs($this->admin)->get(route('productos.index', ['rotacion' => 'sin_venta_2_anos']));
        $responseStagnant->assertStatus(200);
        $responseStagnant->assertSee('CAMPERA ANTIGUA SIN SALIDA');
        $responseStagnant->assertDontSee('REMERON MODERNO VENTA DIARIA');

        // 2. Filtrar por productos con venta reciente (< 90 días)
        $responseActive = $this->actingAs($this->admin)->get(route('productos.index', ['rotacion' => 'venta_reciente']));
        $responseActive->assertStatus(200);
        $responseActive->assertSee('REMERON MODERNO VENTA DIARIA');
        $responseActive->assertDontSee('CAMPERA ANTIGUA SIN SALIDA');

        // 3. Ordenar por rotacion_cero (estancados primero)
        $responseOrder = $this->actingAs($this->admin)->get(route('productos.index', ['orden' => 'rotacion_cero']));
        $responseOrder->assertStatus(200);
        $productosEnVista = $responseOrder->viewData('productos');
        $this->assertNotEmpty($productosEnVista);
        
        // 4. Verificar que se renderizan las columnas de Alta / Modif y Última Venta
        $responseStagnant->assertSee('Alta / Modif.');
        $responseStagnant->assertSee('Última Venta');
    }

    public function test_sorting_by_stock_and_sales_volume_with_filters(): void
    {
        $cat = ProductoCategoria::first();

        // 1. Verificar ordenamiento por stock_asc con filtro de categoría (regresión del bug 1054 total_stock_sum)
        $responseStockAsc = $this->actingAs($this->admin)->get(route('productos.index', [
            'categoria_id' => $cat->id,
            'orden' => 'stock_asc',
        ]));
        $responseStockAsc->assertStatus(200);

        // 2. Verificar ordenamiento por stock_desc con filtro de categoría
        $responseStockDesc = $this->actingAs($this->admin)->get(route('productos.index', [
            'categoria_id' => $cat->id,
            'orden' => 'stock_desc',
        ]));
        $responseStockDesc->assertStatus(200);

        // 3. Crear productos con diferentes volúmenes de venta
        $pTop = Producto::create([
            'codigo' => 'PROD-TOP-VENTAS',
            'descripcion' => 'ARTICULO SUPER ESTRELLA MAS VENDIDO',
            'precio_costo' => 100,
            'precio_venta' => 250,
            'controla_stock' => true,
            'total_ventas_unidades' => 150,
            'total_ventas_operaciones' => 80,
            'fecha_ultima_venta' => now(),
        ]);
        ProductoStock::create([
            'producto_id' => $pTop->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'stock' => 50,
        ]);

        $pBajo = Producto::create([
            'codigo' => 'PROD-BAJA-VENTA',
            'descripcion' => 'ARTICULO CON APENAS UNA VENTA',
            'precio_costo' => 100,
            'precio_venta' => 250,
            'controla_stock' => true,
            'total_ventas_unidades' => 1,
            'total_ventas_operaciones' => 1,
            'fecha_ultima_venta' => now()->subYear(),
        ]);
        ProductoStock::create([
            'producto_id' => $pBajo->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'stock' => 5,
        ]);

        // 4. Filtrar por lo más vendido
        $responseMasVendidos = $this->actingAs($this->admin)->get(route('productos.index', ['rotacion' => 'mas_vendidos']));
        $responseMasVendidos->assertStatus(200);
        $responseMasVendidos->assertSee('ARTICULO SUPER ESTRELLA MAS VENDIDO');
        $responseMasVendidos->assertDontSee('ARTICULO CON APENAS UNA VENTA');

        // 5. Filtrar por lo menos vendido
        $responseMenosVendidos = $this->actingAs($this->admin)->get(route('productos.index', ['rotacion' => 'menos_vendidos']));
        $responseMenosVendidos->assertStatus(200);
        $responseMenosVendidos->assertSee('ARTICULO CON APENAS UNA VENTA');
        $responseMenosVendidos->assertDontSee('ARTICULO SUPER ESTRELLA MAS VENDIDO');

        // 6. Ordenar por más vendidos (ventas_desc)
        $responseVentasDesc = $this->actingAs($this->admin)->get(route('productos.index', ['orden' => 'ventas_desc']));
        $responseVentasDesc->assertStatus(200);
        $prods = $responseVentasDesc->viewData('productos');
        $this->assertEquals('PROD-TOP-VENTAS', $prods->first()->codigo);

        // 7. Filtrar por nunca vendido (no debe traer productos con ventas registradas)
        $pNunca = Producto::create([
            'codigo' => 'PROD-NUNCA-VENDIDO',
            'descripcion' => 'ARTICULO TOTALMENTE SIN SALIDA NI VENTAS',
            'precio_costo' => 100,
            'precio_venta' => 250,
            'controla_stock' => true,
            'total_ventas_unidades' => 0,
            'total_ventas_operaciones' => 0,
            'fecha_ultima_venta' => null,
        ]);
        ProductoStock::create([
            'producto_id' => $pNunca->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'stock' => 10,
        ]);

        $responseNunca = $this->actingAs($this->admin)->get(route('productos.index', ['rotacion' => 'nunca_vendido']));
        $responseNunca->assertStatus(200);
        $responseNunca->assertSee('ARTICULO TOTALMENTE SIN SALIDA NI VENTAS');
        $responseNunca->assertDontSee('ARTICULO SUPER ESTRELLA MAS VENDIDO');
        $responseNunca->assertDontSee('ARTICULO CON APENAS UNA VENTA');
    }

    public function test_categorias_y_subcategorias_abm_con_restriccion_de_productos(): void
    {
        // 1. Renderizar vista index de categorias
        $responseView = $this->actingAs($this->admin)->get(route('categorias.index'));
        $responseView->assertStatus(200);
        $responseView->assertSee('Clasificación del Catálogo: Categorías y Subcategorías');

        // 2. Crear nueva categoría
        $responseCreateCat = $this->actingAs($this->admin)->post(route('categorias.store'), [
            'nombre' => 'ACCESORIOS DE MODA',
            'descripcion' => 'Cinturones, carteras y gorros',
        ]);
        $responseCreateCat->assertSessionHas('success');
        $cat = ProductoCategoria::where('nombre', 'ACCESORIOS DE MODA')->first();
        $this->assertNotNull($cat);
        $this->assertEquals('Cinturones, carteras y gorros', $cat->descripcion);

        // 3. Editar categoría
        $responseUpdateCat = $this->actingAs($this->admin)->put(route('categorias.update', $cat->id), [
            'nombre' => 'ACCESORIOS Y COMPLEMENTOS',
            'descripcion' => 'Descripción actualizada',
        ]);
        $responseUpdateCat->assertSessionHas('success');
        $this->assertEquals('ACCESORIOS Y COMPLEMENTOS', $cat->fresh()->nombre);

        // 4. Crear subcategoría global
        $responseCreateSub = $this->actingAs($this->admin)->post(route('subcategorias.store'), [
            'nombre' => 'UNISEX / JUVENIL',
            'descripcion' => 'Apto para todo público joven',
        ]);
        $responseCreateSub->assertSessionHas('success');
        $sub = ProductoSubcategoria::where('nombre', 'UNISEX / JUVENIL')->first();
        $this->assertNotNull($sub);

        // 5. Editar subcategoría global
        $responseUpdateSub = $this->actingAs($this->admin)->put(route('subcategorias.update', $sub->id), [
            'nombre' => 'JUVENIL Y ADOLESCENTE',
            'descripcion' => 'Segmento de 12 a 18 años',
        ]);
        $responseUpdateSub->assertSessionHas('success');
        $this->assertEquals('JUVENIL Y ADOLESCENTE', $sub->fresh()->nombre);

        // 6. Asociar un producto a la categoría y subcategoría
        $producto = Producto::create([
            'codigo' => 'ACC-001',
            'descripcion' => 'GORRO DE LANA INVIERNO',
            'categoria_id' => $cat->id,
            'subcategoria_id' => $sub->id,
            'precio_costo' => 1500,
            'precio_venta' => 3500,
            'controla_stock' => true,
        ]);

        // 7. Intentar eliminar categoría con productos asociados -> BLOQUEADO
        $responseDeleteCatBlocked = $this->actingAs($this->admin)->delete(route('categorias.destroy', $cat->id));
        $responseDeleteCatBlocked->assertSessionHas('error');
        $this->assertDatabaseHas('productos_categorias', ['id' => $cat->id]);

        // 8. Intentar eliminar subcategoría con productos asociados -> BLOQUEADO
        $responseDeleteSubBlocked = $this->actingAs($this->admin)->delete(route('subcategorias.destroy', $sub->id));
        $responseDeleteSubBlocked->assertSessionHas('error');
        $this->assertDatabaseHas('productos_subcategorias', ['id' => $sub->id]);

        // 9. Desvincular/eliminar producto y verificar que ahora sí permite eliminar categoría y subcategoría
        $producto->forceDelete();

        $responseDeleteCatOk = $this->actingAs($this->admin)->delete(route('categorias.destroy', $cat->id));
        $responseDeleteCatOk->assertSessionHas('success');
        $this->assertDatabaseMissing('productos_categorias', ['id' => $cat->id]);

        $responseDeleteSubOk = $this->actingAs($this->admin)->delete(route('subcategorias.destroy', $sub->id));
        $responseDeleteSubOk->assertSessionHas('success');
        $this->assertDatabaseMissing('productos_subcategorias', ['id' => $sub->id]);
    }

    public function test_lote_stock_transfer_with_barcode_search(): void
    {
        // 1. Preparar 2 productos con stock en sucursal origen Ushuaia
        $p1 = Producto::create([
            'codigo' => 'BAR-001',
            'codigo_barra' => '7790001112223',
            'descripcion' => 'REMERA MANGA CORTA NEGRA',
            'controla_stock' => true,
            'precio_costo' => 2000,
            'precio_venta' => 4500,
        ]);
        $p1->ajustarStock($this->sucUshuaia->id, 'alta', 15, 'Stock Inicial');

        $p2 = Producto::create([
            'codigo' => 'BAR-002',
            'codigo_barra' => '7790003334445',
            'descripcion' => 'PANTALON CARGO VERDE',
            'controla_stock' => true,
            'precio_costo' => 5000,
            'precio_venta' => 11000,
        ]);
        $p2->ajustarStock($this->sucUshuaia->id, 'alta', 20, 'Stock Inicial');

        // 2. Probar API de búsqueda por código de barras (buscarParaTraslado)
        $resSearchBarcode = $this->actingAs($this->admin)->getJson(route('productos.buscar.traslado', [
            'q' => '7790001112223',
            'sucursal_id' => $this->sucUshuaia->id,
        ]));
        $resSearchBarcode->assertStatus(200);
        $resSearchBarcode->assertJsonFragment([
            'codigo' => 'BAR-001',
            'exact_match' => true,
            'stock_origen' => 15,
        ]);

        // 3. Probar API de verificación de stock para lote al cambiar sucursal
        $resVerifyStock = $this->actingAs($this->admin)->postJson(route('productos.verificar.stock.traslado'), [
            'sucursal_id' => $this->sucUshuaia->id,
            'producto_ids' => [$p1->id, $p2->id],
        ]);
        $resVerifyStock->assertStatus(200);
        $resVerifyStock->assertJson([
            $p1->id => 15,
            $p2->id => 20,
        ]);

        // 4. Renderizar pantalla de traslado por lote
        $responseView = $this->actingAs($this->admin)->get(route('productos.traslado'));
        $responseView->assertStatus(200);
        $responseView->assertSee('Carga y Traslado de Mercadería por Lote');
        $responseView->assertSee('Punto de Escaneo de Artículos');

        // 5. Enviar lote completo con ambos productos hacia Trelew
        $remitoNum = 'REM-TEST-LOTE-99';
        $responseTransfer = $this->actingAs($this->admin)->post(route('productos.traslado.store'), [
            'sucursal_origen_id' => $this->sucUshuaia->id,
            'sucursal_destino_id' => $this->sucTrelew->id,
            'comprobante' => $remitoNum,
            'motivo' => 'Envío de caja N° 1 para sucursal Trelew',
            'items' => [
                ['producto_id' => $p1->id, 'cantidad' => 6],
                ['producto_id' => $p2->id, 'cantidad' => 8],
            ]
        ]);

        $responseTransfer->assertRedirect(route('stock.movimientos'));
        $responseTransfer->assertSessionHas('success');

        // 6. Verificar que los stocks en origen disminuyeron
        $this->assertEquals(9, $p1->fresh()->getStockSucursal($this->sucUshuaia->id));  // 15 - 6 = 9
        $this->assertEquals(12, $p2->fresh()->getStockSucursal($this->sucUshuaia->id)); // 20 - 8 = 12

        // 7. Verificar que los stocks en destino aumentaron
        $this->assertEquals(6, $p1->fresh()->getStockSucursal($this->sucTrelew->id));
        $this->assertEquals(8, $p2->fresh()->getStockSucursal($this->sucTrelew->id));

        // 8. Verificar que se auditaron los 4 movimientos (2 salidas + 2 entradas)
        $this->assertDatabaseHas('productos_movimientos', [
            'producto_id' => $p1->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'tipo' => 'traslado_salida',
            'cantidad' => -6,
            'comprobante' => $remitoNum,
        ]);
        $this->assertDatabaseHas('productos_movimientos', [
            'producto_id' => $p1->id,
            'sucursal_id' => $this->sucTrelew->id,
            'tipo' => 'traslado_entrada',
            'cantidad' => 6,
            'comprobante' => $remitoNum,
        ]);
        $this->assertDatabaseHas('productos_movimientos', [
            'producto_id' => $p2->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'tipo' => 'traslado_salida',
            'cantidad' => -8,
            'comprobante' => $remitoNum,
        ]);
        $this->assertDatabaseHas('productos_movimientos', [
            'producto_id' => $p2->id,
            'sucursal_id' => $this->sucTrelew->id,
            'tipo' => 'traslado_entrada',
            'cantidad' => 8,
            'comprobante' => $remitoNum,
        ]);
    }

    public function test_active_branch_is_filtered_by_default_and_other_branches_toggle(): void
    {
        // 1. Asignar stock en Trelew a un solo producto
        $prodTrelew = Producto::where('codigo', 'ART-001001')->first();
        $prodTrelew->ajustarStock($this->sucTrelew->id, 'alta', 7, 'Stock para Trelew');

        // Configurar la sucursal activa en Trelew
        session(['sucursal_activa_id' => $this->sucTrelew->id]);
        $this->admin->id_sucursal = $this->sucTrelew->id;
        $this->admin->save();

        // 2. Consulta de entrada (sin parámetros) -> debe filtrar por Trelew automáticamente
        $response = $this->actingAs($this->admin)->get(route('productos.index'));
        $response->assertStatus(200);

        $metricas = $response->viewData('metricas');
        $this->assertEquals($this->sucTrelew->id, $metricas['sucursal_id_filtrada']);
        $this->assertFalse($metricas['ver_otras']);
        // Solo debe contabilizar las 7 unidades de Trelew
        $this->assertEquals(7, $metricas['total_stock_unidades']);
        $this->assertEquals(7 * 28500, $metricas['valor_stock_venta']);
        $response->assertSee('Stock en ' . $this->sucTrelew->nombre);
        $response->assertSee('Mostrar stock demás sucursales');

        // 3. Activar ver_otras = 1 -> mantiene el filtro de Trelew pero muestra otras sedes
        $responseVerOtras = $this->actingAs($this->admin)->get(route('productos.index', ['ver_otras' => '1']));
        $responseVerOtras->assertStatus(200);
        $metricasOtras = $responseVerOtras->viewData('metricas');
        $this->assertEquals($this->sucTrelew->id, $metricasOtras['sucursal_id_filtrada']);
        $this->assertTrue($metricasOtras['ver_otras']);
        $this->assertEquals(7, $metricasOtras['total_stock_unidades']);
        $responseVerOtras->assertSee('Stock Demás Sucursales:');

        // 4. Seleccionar "Todas las sucursales" (sucursal_id = todas) -> consolidado
        $responseTodas = $this->actingAs($this->admin)->get(route('productos.index', ['sucursal_id' => 'todas']));
        $responseTodas->assertStatus(200);
        $metricasTodas = $responseTodas->viewData('metricas');
        $this->assertNull($metricasTodas['sucursal_id_filtrada']);
        $this->assertTrue($metricasTodas['ver_otras']);
        // En consolidado suma Trelew (7) + Ushuaia (15 + 8) = 30 unidades
        $this->assertEquals(30, $metricasTodas['total_stock_unidades']);
        $responseTodas->assertSee('Consolidado Todas las Sedes');
    }

    public function test_click_to_sort_table_headers(): void
    {
        // 1. Cargar vista index por defecto
        $response = $this->actingAs($this->admin)->get(route('productos.index'));
        $response->assertStatus(200);

        // Verificar que los encabezados son enlaces click-to-sort
        $response->assertSee('sort=codigo', false);
        $response->assertSee('sort=descripcion', false);
        $response->assertSee('sort=precio', false);
        $response->assertSee('sort=fecha_alta', false);
        $response->assertSee('sort=ultima_venta', false);
        $response->assertSee('sort=stock', false);

        // Por defecto: orden descripcion asc (indicador ▲ activo en descripcion)
        $this->assertEquals('descripcion', $response->viewData('sort'));
        $this->assertEquals('asc', $response->viewData('direction'));
        $response->assertSee('▲', false);

        // 2. Probar ordenamiento por precio desc
        $responsePrecioDesc = $this->actingAs($this->admin)->get(route('productos.index', [
            'sort' => 'precio',
            'direction' => 'desc',
        ]));
        $responsePrecioDesc->assertStatus(200);
        $this->assertEquals('precio', $responsePrecioDesc->viewData('sort'));
        $this->assertEquals('desc', $responsePrecioDesc->viewData('direction'));
        // El siguiente enlace para precio debe alternar a asc
        $responsePrecioDesc->assertSee('sort=precio&amp;direction=asc', false);
        // Indicador ▼ activo en precios
        $responsePrecioDesc->assertSee('▼', false);
        // Chip informativo de ordenamiento activo
        $responsePrecioDesc->assertSee('Orden: Precio Descendente (▼)', false);

        // 3. Probar ordenamiento por stock asc
        $responseStockAsc = $this->actingAs($this->admin)->get(route('productos.index', [
            'sort' => 'stock',
            'direction' => 'asc',
        ]));
        $responseStockAsc->assertStatus(200);
        $this->assertEquals('stock', $responseStockAsc->viewData('sort'));
        $this->assertEquals('asc', $responseStockAsc->viewData('direction'));
        // El siguiente enlace para stock debe alternar a desc
        $responseStockAsc->assertSee('sort=stock&amp;direction=desc', false);
        $responseStockAsc->assertSee('Orden: Stock Ascendente (▲)', false);

        // 4. Preservación de filtros vigentes al generar enlaces de ordenamiento
        $responseConFiltros = $this->actingAs($this->admin)->get(route('productos.index', [
            'search' => 'CAMPERA',
            'sort' => 'codigo',
            'direction' => 'asc',
        ]));
        $responseConFiltros->assertStatus(200);
        $this->assertEquals('codigo', $responseConFiltros->viewData('sort'));
        $this->assertEquals('asc', $responseConFiltros->viewData('direction'));
        // Los enlaces de orden deben preservar search=CAMPERA y resetear page=1
        $responseConFiltros->assertSee('search=CAMPERA', false);
        $responseConFiltros->assertSee('sort=codigo&amp;direction=desc&amp;page=1', false);
    }
}


