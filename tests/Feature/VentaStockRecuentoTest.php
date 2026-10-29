<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoStock;
use App\Models\ProductoMovimiento;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\UsuariosSucursalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaStockRecuentoTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $sucursalTrelew;
    protected $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UsuariosSucursalesSeeder::class);

        $this->user = User::where('is_admin', true)->first() ?? User::factory()->create(['is_admin' => true]);

        // Sucursal Trelew (Chubut)
        $this->sucursalTrelew = Sucursal::where('nombre', 'LIKE', '%Trelew%')->first()
            ?: Sucursal::create([
                'nombre' => 'Trelew - Centro',
                'direccion' => 'Rivadavia 249',
                'id_provincia' => 1,
            ]);

        // Producto que controla stock con stock inicial 0 en Trelew
        $this->producto = Producto::create([
            'codigo' => 'TEST-TRELEW-' . uniqid(),
            'codigo_barra' => '779' . rand(1000000000, 9999999999),
            'descripcion' => 'Remera Test Control Stock Trelew',
            'precio_costo' => 5000,
            'precio_venta' => 10000,
            'controla_stock' => true,
            'activo' => true,
        ]);

        ProductoStock::updateOrCreate(
            ['producto_id' => $this->producto->id, 'sucursal_id' => $this->sucursalTrelew->id],
            ['stock' => 0, 'precio_venta' => 10000]
        );

        \App\Models\CajaSesion::create([
            'id_sucursal' => $this->sucursalTrelew->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 5000,
            'estado' => 'abierta',
        ]);
    }

    public function test_buscar_producto_retorna_stock_cero_en_trelew()
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('ventas.buscar-producto', [
                'q' => $this->producto->codigo,
                'sucursal_id' => $this->sucursalTrelew->id,
            ]));

        $response->assertStatus(200);
        $data = $response->json();
        
        $prodData = $data['exact'] ? $data['producto'] : $data['productos'][0];
        $this->assertEquals(0, $prodData['stock']);
        $this->assertTrue($prodData['controla_stock']);
    }

    public function test_venta_falla_si_intenta_vender_articulo_con_stock_cero()
    {
        $payload = [
            'id_sucursal' => $this->sucursalTrelew->id,
            'cliente_nombre' => 'CONSUMIDOR FINAL',
            'medio_pago' => 'efectivo',
            'items' => [
                [
                    'id_producto' => $this->producto->id,
                    'codigo_producto' => $this->producto->codigo,
                    'descripcion' => $this->producto->descripcion,
                    'cantidad' => 1,
                    'precio_unitario' => 10000,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
        $this->assertStringContainsString('Stock insuficiente', $response->json('error'));

        // El stock debe permanecer intacto en 0 (sin generar negativos)
        $stock = ProductoStock::where('producto_id', $this->producto->id)
            ->where('sucursal_id', $this->sucursalTrelew->id)
            ->value('stock');
        $this->assertEquals(0, (float)$stock);
    }

    public function test_ajuste_recuento_rapido_habilita_venta_y_queda_en_cero_al_facturar()
    {
        // 1. Operador realiza recuento rápido de 1 unidad que encontró físicamente
        $recuentoResponse = $this->actingAs($this->user)
            ->postJson(route('ventas.ajuste-recuento-rapido'), [
                'id_producto' => $this->producto->id,
                'id_sucursal' => $this->sucursalTrelew->id,
                'cantidad' => 1,
                'motivo' => 'Recuento físico previo a facturación en mostrador Trelew',
            ]);

        $recuentoResponse->assertStatus(200);
        $recuentoResponse->assertJson([
            'success' => true,
            'nuevo_stock' => 1,
        ]);

        // Verificar que el stock en la sucursal ahora es 1
        $stockActual = ProductoStock::where('producto_id', $this->producto->id)
            ->where('sucursal_id', $this->sucursalTrelew->id)
            ->value('stock');
        $this->assertEquals(1, (float)$stockActual);

        // Verificar que se registró el movimiento de corrección
        $movRecuento = ProductoMovimiento::where('producto_id', $this->producto->id)
            ->where('sucursal_id', $this->sucursalTrelew->id)
            ->where('tipo', 'correccion')
            ->first();
        $this->assertNotNull($movRecuento);
        $this->assertEquals(1, (float)$movRecuento->cantidad);

        // 2. Ahora emite la factura por 1 unidad
        $payload = [
            'id_sucursal' => $this->sucursalTrelew->id,
            'cliente_nombre' => 'CONSUMIDOR FINAL',
            'medio_pago' => 'efectivo',
            'items' => [
                [
                    'id_producto' => $this->producto->id,
                    'codigo_producto' => $this->producto->codigo,
                    'descripcion' => $this->producto->descripcion,
                    'cantidad' => 1,
                    'precio_unitario' => 10000,
                ]
            ]
        ];

        $ventaResponse = $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $payload);

        $ventaResponse->assertStatus(200);
        $ventaResponse->assertJson(['success' => true]);

        // 3. El stock debe haber vuelto a CERO (1 - 1 = 0), jamás negativo
        $stockFinal = ProductoStock::where('producto_id', $this->producto->id)
            ->where('sucursal_id', $this->sucursalTrelew->id)
            ->value('stock');
        $this->assertEquals(0, (float)$stockFinal);

        // Verificar que se registró el movimiento de venta
        $movVenta = ProductoMovimiento::where('producto_id', $this->producto->id)
            ->where('sucursal_id', $this->sucursalTrelew->id)
            ->where('tipo', 'venta')
            ->first();
        $this->assertNotNull($movVenta);
        $this->assertEquals(-1, (float)$movVenta->cantidad);
    }

    public function test_venta_con_cliente_dni_numerico_y_articulo_generico_credito_personal()
    {
        // 1. Presentación abierta para la sucursal (o usar la existente)
        $presentacion = \App\Models\Presentacion::activaParaSucursal($this->sucursalTrelew->id)
            ?: \App\Models\Presentacion::create([
                'id_sucursal' => $this->sucursalTrelew->id,
                'numero' => 999,
                'interes_credito' => 6.0,
                'fecha_inicio' => now(),
                'fecha_fin' => null,
            ]);

        // 2. Cliente con DNI numérico y habilitado
        $cliente = Cliente::create([
            'nombre' => 'JUAN TEST DNI ENTERO',
            'dni' => 20123456,
            'direccion' => 'San Martin 500',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804000001',
            'email' => 'juan@test.com',
            'limite' => 100000,
            'max_cuotas' => 4,
            'suspendido' => false,
        ]);

        // 3. Payload con DNI entero (como viene de JSON) y artículo genérico sin stock
        $payload = [
            'id_sucursal' => $this->sucursalTrelew->id,
            'id_cliente' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_documento' => 20123456, // int directo desde JSON
            'medio_pago' => 'credito_personal',
            'cuotas' => 2,
            'items' => [
                [
                    'id_producto' => null,
                    'codigo_producto' => 1, // int directo
                    'descripcion' => 'VARIOS - ARTICULO GENERAL',
                    'cantidad' => 1,
                    'precio_unitario' => 5000,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $data = $response->json();
        $this->assertNotEmpty($data['id_credito']);
        $this->assertNotEmpty($data['pagare_url']);

        // Verificar que la URL del pagaré renderiza correctamente
        $pagareResponse = $this->actingAs($this->user)->get($data['pagare_url']);
        $pagareResponse->assertStatus(200);
        $pagareResponse->assertSee('Pagaré');
        $pagareResponse->assertSee($cliente->nombre);
        $pagareResponse->assertSee((string)$cliente->dni);

        $this->assertDatabaseHas('ventas', [
            'id_cliente' => $cliente->id,
            'cliente_documento' => '20123456',
            'medio_pago' => 'credito_personal',
            'subtotal' => 5000,
        ]);

        $this->assertDatabaseHas('creditos', [
            'id_cliente' => $cliente->id,
            'id_presentacion' => $presentacion->id,
            'cantidad_cuotas' => 2,
        ]);
    }

    public function test_venta_con_cliente_dni_numerico_y_medio_efectivo()
    {
        $cliente = Cliente::create([
            'nombre' => 'MARIA TEST EFECTIVO',
            'dni' => 30987654,
            'direccion' => 'Rivadavia 100',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804000002',
            'email' => 'maria@test.com',
            'limite' => 50000,
            'max_cuotas' => 3,
            'suspendido' => false,
        ]);

        $payload = [
            'id_sucursal' => $this->sucursalTrelew->id,
            'id_cliente' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_documento' => 30987654,
            'medio_pago' => 'efectivo',
            'cuotas' => null,
            'items' => [
                [
                    'id_producto' => null,
                    'codigo_producto' => 'VARIOS',
                    'descripcion' => 'VARIOS - CARGA RAPIDA',
                    'cantidad' => 1,
                    'precio_unitario' => 1200,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('ventas', [
            'id_cliente' => $cliente->id,
            'cliente_documento' => '30987654',
            'medio_pago' => 'efectivo',
            'total' => 1200,
        ]);
    }

    public function test_interes_escalonado_en_simulacion_y_alta_de_venta()
    {
        $presentacion = \App\Models\Presentacion::activaParaSucursal($this->sucursalTrelew->id)
            ?: \App\Models\Presentacion::create([
                'id_sucursal' => $this->sucursalTrelew->id,
                'numero' => 998,
                'interes_credito' => 0.06,
                'primera_cuota_sin_interes' => true,
                'fecha_inicio' => now(),
                'fecha_fin' => null,
            ]);

        $cliente = Cliente::create([
            'nombre' => 'TEST ESCALONAMIENTO',
            'dni' => 45678912,
            'direccion' => 'Belgrano 250',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804111222',
            'email' => 'escalonamiento@test.com',
            'limite' => 200000,
            'max_cuotas' => 4,
            'suspendido' => false,
        ]);

        // 1. Validar endpoint de simulación de crédito
        $simulacionResp = $this->actingAs($this->user)->getJson(route('ventas.cliente-info-credito', [
            'cliente' => $cliente->id,
            'monto' => 10000,
            'sucursal_id' => $this->sucursalTrelew->id,
        ]));

        $simulacionResp->assertStatus(200);
        $simData = $simulacionResp->json('simulacion_cuotas');

        // 1 pago: 0% recargo
        $this->assertEquals(0, $simData[1]['porcentaje_interes']);
        $this->assertEquals(0, $simData[1]['recargo']);
        $this->assertEquals(10000, $simData[1]['total_financiado']);
        $this->assertEquals(10000, $simData[1]['valor_cuota']);

        // 2 cuotas: 6% recargo
        $this->assertEquals(6, $simData[2]['porcentaje_interes']);
        $this->assertEquals(600, $simData[2]['recargo']);
        $this->assertEquals(10600, $simData[2]['total_financiado']);
        $this->assertEquals(5300, $simData[2]['valor_cuota']);

        // 3 cuotas: 12% recargo
        $this->assertEquals(12, $simData[3]['porcentaje_interes']);
        $this->assertEquals(1200, $simData[3]['recargo']);
        $this->assertEquals(11200, $simData[3]['total_financiado']);
        $this->assertEquals(round(11200 / 3, 2), $simData[3]['valor_cuota']);

        // 4 cuotas: 18% recargo
        $this->assertEquals(18, $simData[4]['porcentaje_interes']);
        $this->assertEquals(1800, $simData[4]['recargo']);
        $this->assertEquals(11800, $simData[4]['total_financiado']);
        $this->assertEquals(2950, $simData[4]['valor_cuota']);

        // 2. Venta en 1 pago: total = subtotal sin interés
        $venta1PagoResp = $this->actingAs($this->user)->postJson(route('ventas.store'), [
            'id_sucursal' => $this->sucursalTrelew->id,
            'id_cliente' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_documento' => $cliente->dni,
            'medio_pago' => 'credito_personal',
            'cuotas' => 1,
            'items' => [
                [
                    'id_producto' => null,
                    'codigo_producto' => 'GEN1',
                    'descripcion' => 'GENERICO 1 PAGO',
                    'cantidad' => 1,
                    'precio_unitario' => 10000,
                ]
            ]
        ]);

        $venta1PagoResp->assertStatus(200);
        $this->assertDatabaseHas('ventas', [
            'id_cliente' => $cliente->id,
            'subtotal' => 10000,
            'recargo' => 0,
            'total' => 10000,
            'cuotas_credito' => 1,
            'valor_cuota_credito' => 10000,
        ]);

        // 3. Venta en 3 cuotas: total = 10000 * 1.12 = 11200, cuota = 3733.33
        $venta3CuotasResp = $this->actingAs($this->user)->postJson(route('ventas.store'), [
            'id_sucursal' => $this->sucursalTrelew->id,
            'id_cliente' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_documento' => $cliente->dni,
            'medio_pago' => 'credito_personal',
            'cuotas' => 3,
            'items' => [
                [
                    'id_producto' => null,
                    'codigo_producto' => 'GEN3',
                    'descripcion' => 'GENERICO 3 PAGOS',
                    'cantidad' => 1,
                    'precio_unitario' => 10000,
                ]
            ]
        ]);

        $venta3CuotasResp->assertStatus(200);
        $this->assertDatabaseHas('ventas', [
            'id_cliente' => $cliente->id,
            'subtotal' => 10000,
            'recargo' => 1200,
            'total' => 11200,
            'cuotas_credito' => 3,
            'valor_cuota_credito' => 3733.33,
        ]);
    }

    public function test_pantalla_pos_crear_renderiza_completa_con_panel_cliente_y_modal_ficha_cliente()
    {
        $response = $this->actingAs($this->user)->get(route('ventas.create'));
        $response->assertStatus(200);
        $response->assertSee('Punto de Venta (POS) & Facturación', false);
        $response->assertSee('Datos del Cliente');
        $response->assertSee('Escanee código de barra');
        $response->assertSee('TOTAL FACTURADO');
        $response->assertSee('modalFichaCliente');
        $response->assertSee('abrirFichaClienteModal');
        $response->assertDontSee('abrirEstadoCuentaModal');
        $response->assertSee('AUTORIZAR CRÉDITO');
        $response->assertSee('DETALLE DE COMPRAS:');
        $response->assertSee('PROXIMOS VENCIMIENTOS:');
        $response->assertSee('COMENTARIOS / NOVEDADES:');
        $response->assertSee('creditoAutorizado ? \'Facturar\' : \'Verificar y Facturar Crédito\'', false);
    }

    public function test_edicion_cliente_con_parametro_embed_redirecciona_a_show_embebido()
    {
        $cliente = Cliente::create([
            'nombre' => 'JUAN PEREZ MODAL TEST',
            'dni' => '28999111',
            'direccion' => 'Mitre 500',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804111222',
            'limite' => 60000,
            'max_cuotas' => 4,
            'medio_pago_preferido' => 0,
            'suspendido' => false,
        ]);

        $response = $this->actingAs($this->user)->put(route('clientes.update', $cliente->id), [
            'embed' => 1,
            'dni' => '28999111',
            'nombre' => 'JUAN PEREZ MODAL TEST EDITADO',
            'direccion' => 'Mitre 550',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804111222',
            'limite' => 70000,
            'max_cuotas' => 6,
            'medio_pago_preferido' => 0,
            'suspendido' => 0,
        ]);

        $response->assertRedirect(route('clientes.show', ['cliente' => $cliente->id, 'embed' => 1]));

        $cliente->refresh();
        $this->assertEquals('JUAN PEREZ MODAL TEST EDITADO', $cliente->nombre);
        $this->assertEquals(70000, (float) $cliente->limite);

        // Al acceder a la ficha embebida no debe renderizar la barra de navegación superior completa
        $showResp = $this->actingAs($this->user)->get(route('clientes.show', ['cliente' => $cliente->id, 'embed' => 1]));
        $showResp->assertStatus(200);
        $showResp->assertSee('JUAN PEREZ MODAL TEST EDITADO');
        $showResp->assertDontSee('Cerrar Sesión');
    }

    public function test_info_credito_cliente_retorna_detalle_compras_vencimientos_cuatro_meses_y_novedades()
    {
        $cliente = Cliente::create([
            'nombre' => 'ANA TEST CREDITO DETALLE',
            'dni' => '30111222',
            'direccion' => 'Belgrano 100',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804000002',
            'limite' => 50000,
            'limite_efectivo' => 50000,
            'max_cuotas' => 4,
            'suspendido' => false,
        ]);

        $cliente->novedades()->create([
            'user_id' => $this->user->id,
            'usuario_nombre' => $this->user->name,
            'mensaje' => 'Avisó que pasa por el Banco a pagar',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('ventas.cliente-info-credito', ['cliente' => $cliente->id, 'monto' => 10000, 'sucursal_id' => $this->sucursalTrelew->id]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('detalle_compras', $data);
        $this->assertArrayHasKey('total_adeudado', $data['detalle_compras']);
        $this->assertArrayHasKey('pendiente_cobro', $data['detalle_compras']);
        $this->assertArrayHasKey('mora_vencido', $data['detalle_compras']);
        $this->assertArrayHasKey('limite_disponible', $data['detalle_compras']);

        $this->assertArrayHasKey('proximos_vencimientos', $data);
        $this->assertCount(4, $data['proximos_vencimientos']);
        $this->assertEquals(1, $data['proximos_vencimientos'][0]['numero_mes']);
        $this->assertEquals(2, $data['proximos_vencimientos'][1]['numero_mes']);
        $this->assertEquals(3, $data['proximos_vencimientos'][2]['numero_mes']);
        $this->assertEquals(4, $data['proximos_vencimientos'][3]['numero_mes']);

        $this->assertArrayHasKey('novedades', $data);
        $this->assertNotEmpty($data['novedades']);
        $this->assertStringContainsString('Avisó que pasa por el Banco', $data['novedades'][0]['mensaje']);
    }

    public function test_actualizar_limite_cliente_desde_modal_facturador()
    {
        $cliente = Cliente::create([
            'nombre' => 'MARCOS TEST LIMITE',
            'dni' => '32123456',
            'direccion' => 'Mitre 200',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804000003',
            'limite' => 40000,
            'limite_efectivo' => 40000,
            'max_cuotas' => 4,
            'suspendido' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('ventas.cliente-actualizar-limite', $cliente->id), [
                'limite' => 75000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'nuevo_limite' => 75000]);

        $cliente->refresh();
        $this->assertEquals(75000, (float) $cliente->limite);
        $this->assertEquals(75000, (float) $cliente->limite_efectivo);

        $novedad = $cliente->novedades()->first();
        $this->assertNotNull($novedad);
        $this->assertStringContainsString('Límite modificado', $novedad->mensaje);
    }

    public function test_autorizacion_excepcional_vendedor_habilita_venta_con_mora_o_exceso_limite()
    {
        // Cliente con límite bajo ($500) para forzar superación del límite
        $cliente = Cliente::create([
            'nombre' => 'ROBERTO TEST EXCEPCION',
            'dni' => '25999888',
            'direccion' => 'Rivadavia 300',
            'id_localidad' => 1,
            'id_sucursal' => $this->sucursalTrelew->id,
            'tel1' => '2804000004',
            'limite' => 500,
            'limite_efectivo' => 500,
            'max_cuotas' => 4,
            'suspendido' => false,
        ]);

        $payload = [
            'id_sucursal' => $this->sucursalTrelew->id,
            'id_cliente' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_documento' => $cliente->dni,
            'medio_pago' => 'credito_personal',
            'cuotas' => 2,
            'autorizado_vendedor' => false, // No autorizado aún
            'items' => [
                [
                    'id_producto' => null,
                    'codigo_producto' => 'GEN01',
                    'descripcion' => 'ARTICULO PRUEBA',
                    'cantidad' => 1,
                    'precio_unitario' => 5000, // Supera el límite de $500
                ]
            ]
        ];

        // 1. Sin autorización del vendedor debe fallar con 422
        $respFalla = $this->actingAs($this->user)->postJson(route('ventas.store'), $payload);
        $respFalla->assertStatus(422);
        $this->assertStringContainsString('supera el límite disponible', $respFalla->json('error'));

        // 2. Con autorización del vendedor (override) debe procesar exitosamente la venta
        $payload['autorizado_vendedor'] = true;
        $respExito = $this->actingAs($this->user)->postJson(route('ventas.store'), $payload);
        $respExito->assertStatus(200);
        $respExito->assertJson(['success' => true]);

        // Verificar que quedó registrada la novedad de autorización excepcional en el cliente
        $novedad = $cliente->novedades()->latest()->first();
        $this->assertNotNull($novedad);
        $this->assertStringContainsString('AUTORIZACIÓN EXCEPCIONAL POR VENDEDOR', $novedad->mensaje);
    }
}


