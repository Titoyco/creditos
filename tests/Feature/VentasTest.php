<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\ProductoStock;
use App\Models\ProductoMovimiento;
use App\Models\Presentacion;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\Credito;
use App\Models\Cuota;

class VentasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Sucursal $sucUshuaia;
    protected Sucursal $sucTrelew;
    protected Presentacion $presentacionTdf;
    protected Presentacion $presentacionChubut;
    protected Producto $productoTest;
    protected Producto $productoGenerico;
    protected Cliente $clienteNormal;
    protected Cliente $clienteSuspendido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\InitialSetupSeeder::class);
        $this->seed(\Database\Seeders\UbicacionSeeder::class);
        $this->seed(\Database\Seeders\PresentacionSeeder::class);
        $this->seed(\Database\Seeders\CatalogoStockSeeder::class);

        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->create(['is_admin' => 1]);
        $this->sucUshuaia = Sucursal::where('nombre', 'like', '%Ushuaia%')->orWhere('codigo_sucursal', 'US')->first() ?? Sucursal::find(2);
        $this->sucUshuaia->update([
            'nombre_fantasia' => 'La Marina',
            'razon_social'    => 'BARZINI GUILLERMO ENRIQUE',
            'cuit'            => '20-11474524-4',
            'condicion_iva'   => 'IVA Responsable Inscripto',
            'domicilio_fiscal'=> 'San Martín 43, Ushuaia, Tierra del Fuego',
            'direccion'       => 'San Martín 43',
            'pagina_web'      => 'www.tiendaslamarina.com.ar',
        ]);

        $this->sucTrelew = Sucursal::where('nombre', 'like', '%Trelew%')->orWhere('codigo_sucursal', 'TW')->first() ?? Sucursal::find(1);
        $this->sucTrelew->update([
            'nombre_fantasia' => 'La Marina',
            'razon_social'    => 'Tiendas la Marina SAS',
            'cuit'            => '30-71699602-2',
            'condicion_iva'   => 'IVA Responsable Inscripto',
            'domicilio_fiscal'=> 'Rivadavia 249 - Trelew - Chubut',
            'direccion'       => 'Rivadavia 249',
            'pagina_web'      => 'www.tiendaslamarina.com.ar',
        ]);

        $this->admin->id_sucursal = $this->sucUshuaia->id;
        $this->admin->save();

        session(['sucursal_activa_id' => $this->sucUshuaia->id]);

        // Presentaciones activas (Tierra del Fuego con tasa decimal 0.06 = 6.0%)
        Presentacion::where('id_provincia', 2)->whereNull('fecha_fin')->update(['interes_credito' => 0.06, 'interes_mora' => 0.06]);
        $this->presentacionTdf = Presentacion::activaParaSucursal($this->sucUshuaia->id) ?? Presentacion::create([
            'id_provincia'    => 2,
            'numero'          => 200,
            'fecha_inicio'    => now()->toDateString(),
            'fecha_fin'       => null,
            'interes_credito' => 0.06,
            'interes_mora'    => 0.06,
        ]);
        $this->presentacionTdf->update(['interes_credito' => 0.06, 'interes_mora' => 0.06]);

        $this->presentacionChubut = Presentacion::activaParaSucursal($this->sucTrelew->id) ?? Presentacion::create([
            'id_provincia'    => 1,
            'numero'          => 100,
            'fecha_inicio'    => now()->toDateString(),
            'fecha_fin'       => null,
            'interes_credito' => 6.00,
            'interes_mora'    => 6.00,
        ]);

        // Producto para pruebas con stock en Ushuaia
        $cat = ProductoCategoria::first();
        $this->productoTest = Producto::create([
            'codigo'         => 'PROD-TEST-01',
            'codigo_barra'   => '7798888888888',
            'descripcion'    => 'CAMPERA INVIERNO POLAR',
            'precio_costo'   => 10000,
            'precio_venta'   => 25000,
            'stock_minimo'   => 2,
            'unidad_medida'  => 'UNIDAD',
            'controla_stock' => true,
            'activo'         => true,
            'categoria_id'   => $cat->id,
        ]);

        ProductoStock::create([
            'producto_id'  => $this->productoTest->id,
            'sucursal_id'  => $this->sucUshuaia->id,
            'stock'        => 10,
            'precio_venta' => 25000,
        ]);

        // Producto Genérico VARIOS
        $this->productoGenerico = Producto::firstOrCreate(
            ['codigo' => 'VARIOS'],
            [
                'codigo_barra'   => '1',
                'descripcion'    => 'VARIOS - ARTICULO GENERICO',
                'precio_costo'   => 0,
                'precio_venta'   => 1,
                'controla_stock' => false,
                'activo'         => true,
                'categoria_id'   => $cat->id,
            ]
        );

        // Clientes de prueba
        $this->clienteNormal = Cliente::create([
            'dni'         => 35123456,
            'nombre'      => 'GARCIA JUAN CARLOS',
            'id_sucursal' => $this->sucUshuaia->id,
            'id_localidad' => 1,
            'direccion'   => 'San Martin 500',
            'tel1'        => '2901400000',
            'medio_pago_preferido' => 0,
            'saldo'       => 0,
            'limite'      => 300000,
            'max_cuotas'  => 4,
            'suspendido'  => false,
        ]);

        $this->clienteSuspendido = Cliente::create([
            'dni'         => 20999888,
            'nombre'      => 'LOPEZ ROBERTO (SUSPENDIDO)',
            'id_sucursal' => $this->sucUshuaia->id,
            'id_localidad' => 1,
            'direccion'   => 'Kuanip 200',
            'tel1'        => '2901400001',
            'medio_pago_preferido' => 0,
            'saldo'       => 50000,
            'limite'      => 100000,
            'max_cuotas'  => 3,
            'suspendido'  => true,
        ]);

        // Cajas activas para Ushuaia y Trelew para permitir ventas en los tests
        \App\Models\CajaSesion::create([
            'id_sucursal' => $this->sucUshuaia->id,
            'id_usuario_apertura' => $this->admin->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 10000,
            'estado' => 'abierta',
        ]);

        \App\Models\CajaSesion::create([
            'id_sucursal' => $this->sucTrelew->id,
            'id_usuario_apertura' => $this->admin->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 10000,
            'estado' => 'abierta',
        ]);
    }

    public function test_ventas_index_renderiza_correctamente_con_kpis_y_ordenamiento(): void
    {
        $response = $this->actingAs($this->admin)->get(route('ventas.index'));
        $response->assertStatus(200);
        $response->assertSee('Historial de Ventas', false);
        $response->assertSee('Comprobante');
        $response->assertSee('Total');

        // Ordenamiento interactivo click-to-sort
        $resSort = $this->actingAs($this->admin)->get(route('ventas.index', ['sort' => 'total', 'direction' => 'asc']));
        $resSort->assertStatus(200);
        $resSort->assertSee('Ordenando por:');
    }

    public function test_ventas_pos_pantalla_carga_con_sucursal_activa_y_generico(): void
    {
        $response = $this->actingAs($this->admin)->get(route('ventas.create'));
        $response->assertStatus(200);
        $response->assertSee('Punto de Venta (POS)', false);
        $response->assertSee('CONSUMIDOR FINAL');
        $response->assertSee('Carga Rápida de Artículo Genérico (VARIOS)');
    }

    public function test_buscar_producto_api_encuentra_por_codigo_de_barra_y_descripcion(): void
    {
        // 1. Coincidencia exacta por código de barra
        $resExact = $this->actingAs($this->admin)->get(route('ventas.buscar-producto', [
            'q'           => '7798888888888',
            'sucursal_id' => $this->sucUshuaia->id
        ]));
        $resExact->assertStatus(200);
        $resExact->assertJson([
            'exact' => true,
            'producto' => [
                'id'          => $this->productoTest->id,
                'codigo'      => 'PROD-TEST-01',
                'descripcion' => 'CAMPERA INVIERNO POLAR',
                'stock'       => 10,
            ]
        ]);

        // 2. Coincidencia parcial por texto
        $resFuzzy = $this->actingAs($this->admin)->get(route('ventas.buscar-producto', [
            'q'           => 'CAMPERA',
            'sucursal_id' => $this->sucUshuaia->id
        ]));
        $resFuzzy->assertStatus(200);
        $resFuzzy->assertJsonStructure(['exact', 'productos']);
    }

    public function test_venta_mostrador_consumidor_final_efectivo_en_ushuaia_rebaja_stock(): void
    {
        $stockInicial = ProductoStock::where('producto_id', $this->productoTest->id)
            ->where('sucursal_id', $this->sucUshuaia->id)
            ->value('stock');

        $payload = [
            'id_sucursal'    => $this->sucUshuaia->id,
            'id_cliente'     => null,
            'cliente_nombre' => 'CONSUMIDOR FINAL',
            'medio_pago'     => 'efectivo',
            'items'          => [
                [
                    'id_producto'     => $this->productoTest->id,
                    'codigo_producto' => $this->productoTest->codigo,
                    'descripcion'     => $this->productoTest->descripcion,
                    'cantidad'        => 2,
                    'precio_unitario' => 25000,
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)->postJson(route('ventas.store'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $ventaId = $response->json('venta_id');
        $venta = Venta::with('items')->find($ventaId);

        $this->assertNotNull($venta);
        $this->assertNull($venta->id_cliente);
        $this->assertEquals('CONSUMIDOR FINAL', $venta->cliente_nombre);
        $this->assertEquals(50000, $venta->total);
        $this->assertEquals('efectivo', $venta->medio_pago);
        $this->assertStringContainsString(str_pad($this->sucUshuaia->id, 4, '0', STR_PAD_LEFT) . '-', $venta->comprobante);

        // Verificación de stock rebajado en Ushuaia
        $stockFinal = ProductoStock::where('producto_id', $this->productoTest->id)
            ->where('sucursal_id', $this->sucUshuaia->id)
            ->value('stock');

        $this->assertEquals($stockInicial - 2, $stockFinal);

        // Verificación de movimiento de auditoría
        $movimiento = ProductoMovimiento::where('producto_id', $this->productoTest->id)
            ->where('comprobante', $venta->comprobante)
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertEquals('venta', $movimiento->tipo);
        $this->assertEquals(-2, $movimiento->cantidad);
    }

    public function test_venta_trelew_con_producto_generico_sin_bloqueo_de_stock(): void
    {
        $payload = [
            'id_sucursal'    => $this->sucTrelew->id,
            'id_cliente'     => null,
            'cliente_nombre' => 'CONSUMIDOR FINAL',
            'medio_pago'     => 'tarjeta_debito',
            'items'          => [
                [
                    'id_producto'     => $this->productoGenerico->id,
                    'codigo_producto' => 'VARIOS',
                    'descripcion'     => 'VARIOS - ARTICULO GENERAL',
                    'cantidad'        => 1,
                    'precio_unitario' => 15000,
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)->postJson(route('ventas.store'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $venta = Venta::find($response->json('venta_id'));
        $this->assertNotNull($venta);
        $this->assertEquals($this->sucTrelew->id, $venta->id_sucursal);
        $this->assertEquals(15000, $venta->total);
        $this->assertEquals('tarjeta_debito', $venta->medio_pago);
    }

    public function test_info_credito_cliente_detecta_mora_suspension_y_simula_cuotas(): void
    {
        // 1. Cliente Habilitado
        $resNormal = $this->actingAs($this->admin)->getJson(
            route('ventas.cliente-info-credito', ['cliente' => $this->clienteNormal->id, 'monto' => 30000])
        );
        $resNormal->assertStatus(200);
        $resNormal->assertJson([
            'estado_crediticio' => [
                'habilitado' => true,
                'suspendido' => false,
                'tiene_mora' => false,
            ]
        ]);
        $this->assertCount(4, $resNormal->json('simulacion_cuotas'));
        $this->assertEquals(6.0, $resNormal->json('tasa_interes'));
        // Simulación a 3 cuotas con interés escalonado (1 cuota 0%, 2: 6%, 3: 12% = $3.600 sobre $30.000 -> total $33.600)
        $cuota3 = $resNormal->json('simulacion_cuotas.3');
        $this->assertEquals(33600, (float)$cuota3['total_financiado']);
        $this->assertEquals(3600, (float)$cuota3['recargo']);
        $this->assertEquals(11200, (float)$cuota3['valor_cuota']);

        // 2. Cliente Suspendido
        $resSusp = $this->actingAs($this->admin)->getJson(
            route('ventas.cliente-info-credito', ['cliente' => $this->clienteSuspendido->id, 'monto' => 10000])
        );
        $resSusp->assertStatus(200);
        $resSusp->assertJson([
            'estado_crediticio' => [
                'habilitado' => false,
                'suspendido' => true,
            ]
        ]);
    }

    public function test_venta_credito_personal_genera_credito_y_cuotas(): void
    {
        $payload = [
            'id_sucursal'    => $this->sucUshuaia->id,
            'id_cliente'     => $this->clienteNormal->id,
            'medio_pago'     => 'credito_personal',
            'cuotas'         => 3,
            'items'          => [
                [
                    'id_producto'     => $this->productoTest->id,
                    'codigo_producto' => $this->productoTest->codigo,
                    'descripcion'     => $this->productoTest->descripcion,
                    'cantidad'        => 1,
                    'precio_unitario' => 25000,
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)->postJson(route('ventas.store'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $venta = Venta::with(['credito.cuotas', 'cliente'])->find($response->json('venta_id'));

        $this->assertNotNull($venta);
        $this->assertEquals($this->clienteNormal->id, $venta->id_cliente);
        $this->assertEquals('credito_personal', $venta->medio_pago);
        $this->assertNotNull($venta->id_credito);
        $this->assertEquals(3, $venta->cuotas_credito);
        $this->assertEquals(25000, (float)$venta->subtotal);
        $this->assertEquals(3000, (float)$venta->recargo_financiacion); // 12% de interés (3 cuotas con primera sin interés) sobre $25.000
        $this->assertEquals(28000, (float)$venta->total);

        // Crédito y Cuotas
        $credito = $venta->credito;
        $this->assertNotNull($credito);
        $this->assertEquals($this->clienteNormal->id, $credito->id_cliente);
        $this->assertEquals(25000, (float)$credito->importe_original);
        $this->assertEquals(9333.33, (float)$credito->valor_cuota_ref); // $28.000 / 3 = $9.333,33
        $this->assertCount(3, $credito->cuotas);
        $this->assertEquals(9333.33, (float)$credito->cuotas->first()->valor_base);

        // Novedad en legajo
        $novedad = $this->clienteNormal->novedades()->first();
        $this->assertNotNull($novedad);
        $this->assertStringContainsString('COMPRA A CRÉDITO', $novedad->mensaje);
    }

    public function test_venta_credito_personal_es_bloqueada_si_cliente_esta_suspendido(): void
    {
        $payload = [
            'id_sucursal'    => $this->sucUshuaia->id,
            'id_cliente'     => $this->clienteSuspendido->id,
            'medio_pago'     => 'credito_personal',
            'cuotas'         => 2,
            'items'          => [
                [
                    'id_producto'     => $this->productoTest->id,
                    'codigo_producto' => $this->productoTest->codigo,
                    'descripcion'     => $this->productoTest->descripcion,
                    'cantidad'        => 1,
                    'precio_unitario' => 25000,
                ]
            ]
        ];

        // Debe ser rechazada
        $response = $this->actingAs($this->admin)->postJson(route('ventas.store'), $payload);
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
        $this->assertStringContainsString('SUSPENDIDO', $response->json('error'));

        // Fallback: Si el cliente suspendido abona en efectivo, la venta sí se aprueba
        $payload['medio_pago'] = 'efectivo';
        $resFallback = $this->actingAs($this->admin)->postJson(route('ventas.store'), $payload);
        $resFallback->assertStatus(200);
        $resFallback->assertJson(['success' => true]);
    }

    public function test_anulacion_de_venta_restaura_stock_y_anula_credito(): void
    {
        // 1. Crear venta a crédito
        $payload = [
            'id_sucursal'    => $this->sucUshuaia->id,
            'id_cliente'     => $this->clienteNormal->id,
            'medio_pago'     => 'credito_personal',
            'cuotas'         => 2,
            'items'          => [
                [
                    'id_producto'     => $this->productoTest->id,
                    'codigo_producto' => $this->productoTest->codigo,
                    'descripcion'     => $this->productoTest->descripcion,
                    'cantidad'        => 2,
                    'precio_unitario' => 25000,
                ]
            ]
        ];

        $res = $this->actingAs($this->admin)->postJson(route('ventas.store'), $payload);
        $venta = Venta::find($res->json('venta_id'));
        $creditoId = $venta->id_credito;

        $stockDespuesDeVenta = ProductoStock::where('producto_id', $this->productoTest->id)
            ->where('sucursal_id', $this->sucUshuaia->id)
            ->value('stock');

        // 2. Anular venta
        $resAnular = $this->actingAs($this->admin)->post(route('ventas.anular', $venta->id), [
            'motivo' => 'Devolución de mercadería por talle erróneo'
        ]);

        $resAnular->assertSessionHas('success');

        // Stock restaurado
        $stockRestaurado = ProductoStock::where('producto_id', $this->productoTest->id)
            ->where('sucursal_id', $this->sucUshuaia->id)
            ->value('stock');

        $this->assertEquals($stockDespuesDeVenta + 2, $stockRestaurado);

        // Venta anulada
        $venta->refresh();
        $this->assertEquals('anulada', $venta->estado);

        // Crédito eliminado
        $this->assertNull(Credito::find($creditoId));
    }

    public function test_impresion_ticket_y_a4_renderizan_correctamente(): void
    {
        // 1. Venta en Ushuaia
        $ventaUshuaia = Venta::create([
            'id_sucursal'         => $this->sucUshuaia->id,
            'id_usuario'          => $this->admin->id,
            'id_cliente'          => null,
            'cliente_nombre'      => 'CONSUMIDOR FINAL',
            'punto_venta'         => $this->sucUshuaia->id,
            'numero_factura'      => 1,
            'comprobante'         => '0001-00000001',
            'tipo_comprobante'    => 'FACTURA_B',
            'fecha'               => now(),
            'medio_pago'          => 'efectivo',
            'subtotal'            => 10000,
            'total'               => 10000,
            'estado'              => 'completada',
        ]);

        VentaItem::create([
            'id_venta'        => $ventaUshuaia->id,
            'descripcion'     => 'ARTICULO TEST USHUAIA',
            'cantidad'        => 1,
            'precio_unitario' => 10000,
            'subtotal'        => 10000,
        ]);

        $resTicketUsh = $this->actingAs($this->admin)->get(route('ventas.imprimir', ['venta' => $ventaUshuaia->id, 'formato' => 'ticket']));
        $resTicketUsh->assertStatus(200);
        $resTicketUsh->assertSee('0001-00000001');
        $resTicketUsh->assertSee('La Marina');
        $resTicketUsh->assertSee('BARZINI GUILLERMO ENRIQUE');
        $resTicketUsh->assertSee('20-11474524-4');
        $resTicketUsh->assertSee('San Martín 43');
        $resTicketUsh->assertSee('www.tiendaslamarina.com.ar');

        $resA4Ush = $this->actingAs($this->admin)->get(route('ventas.imprimir', ['venta' => $ventaUshuaia->id, 'formato' => 'a4']));
        $resA4Ush->assertStatus(200);
        $resA4Ush->assertSee('ORIGINAL');
        $resA4Ush->assertSee('DUPLICADO');
        $resA4Ush->assertSee('BARZINI GUILLERMO ENRIQUE');
        $resA4Ush->assertSee('20-11474524-4');

        // 2. Venta en Trelew
        $ventaTrelew = Venta::create([
            'id_sucursal'         => $this->sucTrelew->id,
            'id_usuario'          => $this->admin->id,
            'id_cliente'          => null,
            'cliente_nombre'      => 'CONSUMIDOR FINAL',
            'punto_venta'         => $this->sucTrelew->id,
            'numero_factura'      => 2,
            'comprobante'         => '0003-00000002',
            'tipo_comprobante'    => 'FACTURA_B',
            'fecha'               => now(),
            'medio_pago'          => 'efectivo',
            'subtotal'            => 15000,
            'total'               => 15000,
            'estado'              => 'completada',
        ]);

        VentaItem::create([
            'id_venta'        => $ventaTrelew->id,
            'descripcion'     => 'ARTICULO TEST TRELEW',
            'cantidad'        => 1,
            'precio_unitario' => 15000,
            'subtotal'        => 15000,
        ]);

        $resTicketTw = $this->actingAs($this->admin)->get(route('ventas.imprimir', ['venta' => $ventaTrelew->id, 'formato' => 'ticket']));
        $resTicketTw->assertStatus(200);
        $resTicketTw->assertSee('Tiendas la Marina SAS');
        $resTicketTw->assertSee('30-71699602-2');
        $resTicketTw->assertSee('Rivadavia 249');
        $resTicketTw->assertSee('www.tiendaslamarina.com.ar');

        $resA4Tw = $this->actingAs($this->admin)->get(route('ventas.imprimir', ['venta' => $ventaTrelew->id, 'formato' => 'a4']));
        $resA4Tw->assertStatus(200);
        $resA4Tw->assertSee('Tiendas la Marina SAS');
        $resA4Tw->assertSee('30-71699602-2');
    }
}
