<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\ProductoStock;
use App\Models\ProductoMovimiento;
use App\Models\RemitoProveedor;
use App\Models\Presentacion;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class RemitosYPresentacionesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $vendedorUsh;
    protected User $vendedorTw;
    protected Sucursal $sucUshuaia;
    protected Sucursal $sucTrelew;
    protected Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\UbicacionSeeder::class);
        $this->seed(\Database\Seeders\UsuariosSucursalesSeeder::class);
        $this->seed(\Database\Seeders\PresentacionSeeder::class);
        $this->seed(\Database\Seeders\CatalogoStockSeeder::class);

        $this->sucUshuaia = Sucursal::where('nombre', 'like', '%Ushuaia%')->first();
        $this->sucTrelew = Sucursal::where('nombre', 'like', '%Trelew%')->first();

        $this->admin = User::where('is_admin', true)->first();
        $this->vendedorUsh = User::where('username', 'vendedorush1')->first();
        $this->vendedorTw = User::where('username', 'vendedortw')->first();

        // Asegurar id_provincia en sucursales para el entorno sqlite in-memory
        $this->sucUshuaia->update(['id_provincia' => 2]);
        $this->sucTrelew->update(['id_provincia' => 1]);

        // Asegurar que las presentaciones del seeder tengan id_provincia = 2 (TDF)
        Presentacion::query()->update(['id_provincia' => 2]);

        // Asegurar presentación abierta para Chubut (id_provincia = 1)
        Presentacion::firstOrCreate(
            ['id_provincia' => 1, 'numero' => 1],
            [
                'fecha_inicio' => now()->subMonths(1)->toDateString(),
                'fecha_fin' => null,
                'interes_credito' => 0.06,
                'interes_mora' => 0.06,
            ]
        );

        $this->proveedor = Proveedor::firstOrCreate(
            ['legajo' => '991'],
            [
                'nombre' => 'DISTRIBUIDORA PATAGONICA TEST',
                'cuit' => '30999999991',
                'activo' => true,
            ]
        );
    }

    /**
     * TEST 1: Carga completa de remito de mercadería, incremento de stock y auditoría.
     */
    public function test_carga_completa_de_remito_con_incremento_de_stock_y_movimientos(): void
    {
        $this->actingAs($this->admin);

        // Crear producto de prueba
        $prod = Producto::create([
            'codigo' => 'TEST-REM-01',
            'codigo_barra' => '779999900001',
            'descripcion' => 'BUZO POLAR TERMICO',
            'proveedor_id' => $this->proveedor->id,
            'precio_costo' => 5000,
            'precio_venta' => 10000,
            'controla_stock' => true,
            'activo' => true,
        ]);

        $stockInicialRow = ProductoStock::firstOrCreate(
            ['producto_id' => $prod->id, 'sucursal_id' => $this->sucUshuaia->id],
            ['stock' => 10]
        );
        $stockInicialRow->update(['stock' => 10]);

        $payload = [
            'numero_remito' => 'REM-2026-001',
            'proveedor_id' => $this->proveedor->id,
            'sucursal_id' => $this->sucUshuaia->id,
            'fecha_recepcion' => date('Y-m-d'),
            'estado' => 'completado',
            'observaciones' => 'Recepción de prueba completa',
            'items' => [
                [
                    'producto_id' => $prod->id,
                    'cantidad' => 15,
                    'precio_compra' => 6000, // Costo nuevo
                    'precio_venta' => 12000, // Venta x2 modificado
                ],
            ],
        ];

        $response = $this->post(route('remitos.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 1. Verificar Remito creado
        $remito = RemitoProveedor::where('numero_remito', 'REM-2026-001')->first();
        $this->assertNotNull($remito);
        $this->assertEquals('completado', $remito->estado);
        $this->assertEquals(90000, (float)$remito->total_costo); // 15 * 6000
        $this->assertEquals(180000, (float)$remito->total_venta_estimado); // 15 * 12000

        // 2. Verificar Stock incrementado en la sucursal de recepción (10 + 15 = 25)
        $stockFinal = ProductoStock::where('producto_id', $prod->id)
            ->where('sucursal_id', $this->sucUshuaia->id)
            ->value('stock');
        $this->assertEquals(25, (float)$stockFinal);

        // 3. Verificar Precios actualizados en el producto
        $prod->refresh();
        $this->assertEquals(6000, (float)$prod->precio_costo);
        $this->assertEquals(12000, (float)$prod->precio_venta);

        // 4. Verificar ProductoMovimiento generado
        $mov = ProductoMovimiento::where('referencia_type', RemitoProveedor::class)
            ->where('referencia_id', $remito->id)
            ->first();
        $this->assertNotNull($mov);
        $this->assertEquals('ingreso_remito', $mov->tipo);
        $this->assertEquals(15, (float)$mov->cantidad);
        $this->assertEquals(10, (float)$mov->stock_anterior);
        $this->assertEquals(25, (float)$mov->stock_nuevo);
        $this->assertEquals('REM-REM-2026-001', $mov->comprobante);
    }

    /**
     * TEST 2: Carga parcial de remito y posterior entrega complementaria.
     */
    public function test_carga_parcial_de_remito_y_entrega_complementaria(): void
    {
        $this->actingAs($this->admin);

        $prodA = Producto::create([
            'codigo' => 'PARCIAL-A',
            'descripcion' => 'ZAPATILLAS RUNNING T42',
            'proveedor_id' => $this->proveedor->id,
            'precio_costo' => 15000,
            'precio_venta' => 30000,
            'controla_stock' => true,
            'activo' => true,
        ]);

        $prodB = Producto::create([
            'codigo' => 'PARCIAL-B',
            'descripcion' => 'ZAPATILLAS RUNNING T43',
            'proveedor_id' => $this->proveedor->id,
            'precio_costo' => 15000,
            'precio_venta' => 30000,
            'controla_stock' => true,
            'activo' => true,
        ]);

        // Primera entrega (Parcial): solo llega Prod A (5 unidades)
        $response = $this->post(route('remitos.store'), [
            'numero_remito' => 'REM-PARCIAL-99',
            'proveedor_id' => $this->proveedor->id,
            'sucursal_id' => $this->sucTrelew->id,
            'fecha_recepcion' => date('Y-m-d'),
            'estado' => 'parcial',
            'items' => [
                [
                    'producto_id' => $prodA->id,
                    'cantidad' => 5,
                    'precio_compra' => 15000,
                    'precio_venta' => 30000,
                ],
            ],
        ]);
        $response->assertSessionHasNoErrors();

        $remito = RemitoProveedor::where('numero_remito', 'REM-PARCIAL-99')->first();
        $this->assertEquals('parcial', $remito->estado);
        $this->assertEquals(1, $remito->items()->count());

        // Segunda entrega: llega Prod B (8 unidades) y se marca como completado
        $response2 = $this->post(route('remitos.agregar-items', $remito->id), [
            'completar_remito' => 1,
            'items' => [
                [
                    'producto_id' => $prodB->id,
                    'cantidad' => 8,
                    'precio_compra' => 16000,
                    'precio_venta' => 32000,
                ],
            ],
        ]);
        $response2->assertSessionHasNoErrors();

        $remito->refresh();
        $this->assertEquals('completado', $remito->estado);
        $this->assertEquals(2, $remito->items()->count());

        // Verificar stock de ambos productos en Trelew
        $stockA = ProductoStock::where('producto_id', $prodA->id)->where('sucursal_id', $this->sucTrelew->id)->value('stock');
        $stockB = ProductoStock::where('producto_id', $prodB->id)->where('sucursal_id', $this->sucTrelew->id)->value('stock');
        $this->assertEquals(5, (float)$stockA);
        $this->assertEquals(8, (float)$stockB);
    }

    /**
     * TEST 3: Alta rápida de producto directamente desde la pantalla de remito (API).
     */
    public function test_alta_rapida_de_producto_en_catalogo_para_remito(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('remitos.crear-producto-rapido'), [
            'descripcion' => 'CAMPERA INFLABLE NEGRA TALLE L',
            'codigo_barra' => '779888877771',
            'proveedor_id' => $this->proveedor->id,
            'precio_costo' => 20000,
            'precio_venta' => 40000,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'producto' => [
                'descripcion' => 'CAMPERA INFLABLE NEGRA TALLE L',
                'codigo_barra' => '779888877771',
                'precio_costo' => 20000,
                'precio_venta' => 40000,
                'precio_venta_sugerido' => 40000,
            ],
        ]);

        $this->assertDatabaseHas('productos', [
            'codigo_barra' => '779888877771',
            'descripcion' => 'CAMPERA INFLABLE NEGRA TALLE L',
            'precio_costo' => 20000,
            'precio_venta' => 40000,
        ]);
    }

    /**
     * TEST 4: Presentaciones independientes y control de permisos entre TDF y Chubut.
     */
    public function test_presentaciones_independientes_y_permisos_por_sucursal(): void
    {
        // 1. Vendedor de Ushuaia NO puede cerrar la presentación de Chubut (provincia 1)
        $respBloqueadoUsh = $this->actingAs($this->vendedorUsh)->post(route('sistema.presentaciones.cambiar'), [
            'id_provincia' => 1, // Chubut
            'interes_credito' => 0.06,
            'interes_mora' => 0.06,
        ]);
        $respBloqueadoUsh->assertSessionHas('error');

        // 2. Vendedor de Trelew NO puede cerrar la presentación de TDF (provincia 2)
        $respBloqueadoTw = $this->actingAs($this->vendedorTw)->post(route('sistema.presentaciones.cambiar'), [
            'id_provincia' => 2, // TDF
            'interes_credito' => 0.06,
            'interes_mora' => 0.06,
        ]);
        $respBloqueadoTw->assertSessionHas('error');

        // 3. Crear créditos en ambas provincias con cuotas impagas
        $clienteUsh = Cliente::create([
            'id_sucursal' => $this->sucUshuaia->id,
            'id_localidad' => 1,
            'nombre' => 'CLIENTE USHUAIA TEST',
            'dni' => '35999001',
            'direccion' => 'San Martin 100',
            'tel1' => '2901-111111',
            'limite' => 500000,
        ]);

        $clienteTw = Cliente::create([
            'id_sucursal' => $this->sucTrelew->id,
            'id_localidad' => 4,
            'nombre' => 'CLIENTE TRELEW TEST',
            'dni' => '35999002',
            'direccion' => 'Belgrano 200',
            'tel1' => '280-222222',
            'limite' => 500000,
        ]);

        $presTdfActual = Presentacion::activaParaProvincia(2);
        $presChubutActual = Presentacion::activaParaProvincia(1);

        $this->assertNotNull($presTdfActual, 'Debe existir presentacion activa para TDF');
        $this->assertNotNull($presChubutActual, 'Debe existir presentacion activa para Chubut');

        $autUsh = \App\Models\ClienteAutorizado::create([
            'id_titular' => $clienteUsh->id,
            'id_autorizado' => $clienteUsh->id,
            'relacion_aut' => 'Titular',
        ]);

        $autTw = \App\Models\ClienteAutorizado::create([
            'id_titular' => $clienteTw->id,
            'id_autorizado' => $clienteTw->id,
            'relacion_aut' => 'Titular',
        ]);

        // Crédito TDF
        $creditoTdf = Credito::create([
            'id_cliente' => $clienteUsh->id,
            'id_sucursal' => $this->sucUshuaia->id,
            'id_autorizado' => $autUsh->id,
            'id_presentacion' => $presTdfActual->id,
            'credito' => '9901',
            'tipo_credito' => 'C',
            'importe_original' => 10000,
            'cantidad_cuotas' => 1,
            'valor_cuota_ref' => 10000,
            'fecha_credito' => now()->subDays(15),
        ]);
        $cuotaTdf = Cuota::create([
            'id_credito' => $creditoTdf->id,
            'num_cuota' => 1,
            'valor_base' => 10000,
            'saldo_actual' => 10000, // Impaga
            'intereses' => 0,
        ]);

        // Crédito Chubut
        $creditoChubut = Credito::create([
            'id_cliente' => $clienteTw->id,
            'id_sucursal' => $this->sucTrelew->id,
            'id_autorizado' => $autTw->id,
            'id_presentacion' => $presChubutActual->id,
            'credito' => '9902',
            'tipo_credito' => 'C',
            'importe_original' => 10000,
            'cantidad_cuotas' => 1,
            'valor_cuota_ref' => 10000,
            'fecha_credito' => now()->subDays(15),
        ]);
        $cuotaChubut = Cuota::create([
            'id_credito' => $creditoChubut->id,
            'num_cuota' => 1,
            'valor_base' => 10000,
            'saldo_actual' => 10000, // Impaga
            'intereses' => 0,
        ]);

        // Ajustar fecha_inicio de la presentación de Chubut para cumplir la regla de 7 días
        $presChubutActual->update(['fecha_inicio' => now()->subDays(10)]);

        // 4. El vendedor de Trelew cierra el ciclo de CHUBUT
        $responseCierreChubut = $this->actingAs($this->vendedorTw)->post(route('sistema.presentaciones.cambiar'), [
            'id_provincia' => 1, // Chubut
            'interes_credito' => 0.08,
            'interes_mora' => 0.10, // 10% mora
        ]);
        $responseCierreChubut->assertSessionHas('success');

        // 5. Verificar que SOLO la cuota de CHUBUT recibió interés de mora (10000 + 6% = 10600)
        $cuotaChubut->refresh();
        $this->assertEquals(10600, (float)$cuotaChubut->saldo_actual);
        $this->assertEquals(600, (float)$cuotaChubut->intereses);

        // 6. Verificar que la cuota de TDF PERMANECE INTACTA en 10000 (sin mora aplicada)
        $cuotaTdf->refresh();
        $this->assertEquals(10000, (float)$cuotaTdf->saldo_actual);
        $this->assertEquals(0, (float)$cuotaTdf->intereses);

        // 7. Verificar que la nueva presentación activa de Chubut es # (numero anterior + 1)
        $nuevaPresChubut = Presentacion::activaParaProvincia(1);
        $this->assertEquals($presChubutActual->numero + 1, $nuevaPresChubut->numero);

        // 8. Verificar que la presentación activa de TDF no cambió de número
        $presTdfDespues = Presentacion::activaParaProvincia(2);
        $this->assertEquals($presTdfActual->numero, $presTdfDespues->numero);
    }
}
