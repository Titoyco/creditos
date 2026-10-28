<?php

namespace Tests\Feature;

use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoSucursal;
use App\Models\ProductoStock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Database\Seeders\UsuariosSucursalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaControlTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $sucursal;
    protected $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UsuariosSucursalesSeeder::class);

        $this->user = User::where('is_admin', true)->first() ?? User::factory()->create(['is_admin' => true]);

        $this->sucursal = Sucursal::where('nombre', 'LIKE', '%Trelew%')->first()
            ?: Sucursal::create([
                'nombre' => 'Trelew - Centro',
                'direccion' => 'Rivadavia 249',
                'id_provincia' => 1,
            ]);

        $this->producto = Producto::create([
            'codigo' => 'ART-CAJA-' . uniqid(),
            'codigo_barra' => '779' . rand(1000000000, 9999999999),
            'descripcion' => 'Articulo Test Control Caja',
            'precio_costo' => 1000,
            'precio_venta' => 2500,
            'controla_stock' => false,
            'activo' => true,
        ]);
    }

    public function test_no_se_puede_facturar_si_la_caja_esta_cerrada()
    {
        // Asegurar que no hay caja abierta
        CajaSesion::where('id_sucursal', $this->sucursal->id)->delete();

        $payload = [
            'id_sucursal' => $this->sucursal->id,
            'medio_pago' => 'efectivo',
            'items' => [
                [
                    'id_producto' => $this->producto->id,
                    'descripcion' => $this->producto->descripcion,
                    'cantidad' => 1,
                    'precio_unitario' => 2500,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'requiere_apertura' => true,
        ]);
    }

    public function test_cajas_index_pantalla_renderiza_correctamente()
    {
        $response = $this->actingAs($this->user)
            ->get(route('cajas.index'));

        $response->assertStatus(200);
        $response->assertSee('Cierres Z');
    }

    public function test_apertura_de_caja_exitosa()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('cajas.abrir'), [
                'id_sucursal' => $this->sucursal->id,
                'monto_inicial_efectivo' => 15000.50,
                'observaciones' => 'Cambio inicial apertura',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('cajas_sesiones', [
            'id_sucursal' => $this->sucursal->id,
            'monto_inicial_efectivo' => 15000.50,
            'estado' => 'abierta',
        ]);
    }

    public function test_facturacion_asocia_id_caja_sesion_correctamente()
    {
        // 1. Abrir caja de hoy con fondo de $10.000
        $caja = CajaSesion::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 10000.00,
            'estado' => 'abierta',
        ]);

        // 2. Realizar venta en efectivo
        $payload = [
            'id_sucursal' => $this->sucursal->id,
            'medio_pago' => 'efectivo',
            'items' => [
                [
                    'id_producto' => $this->producto->id,
                    'descripcion' => $this->producto->descripcion,
                    'cantidad' => 2,
                    'precio_unitario' => 2500,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $payload);

        $response->assertStatus(200);

        // 3. Verificar que la venta guardó id_caja_sesion
        $this->assertDatabaseHas('ventas', [
            'id_sucursal' => $this->sucursal->id,
            'id_caja_sesion' => $caja->id,
            'medio_pago' => 'efectivo',
            'total' => 5000.00,
        ]);

        // 4. Verificar totales en tiempo real calculados por la caja
        $totales = $caja->calcularTotales();
        $this->assertEquals(1, $totales['ventas_count']);
        $this->assertEquals(5000.00, $totales['efectivo_total']);
        $this->assertEquals(15000.00, $totales['efectivo_esperado']); // 10000 fondo + 5000 venta
    }

    public function test_caja_de_jornada_anterior_bloquea_facturacion_y_exige_cierre()
    {
        // Simular caja abierta ayer que no se cerró
        $cajaAyer = CajaSesion::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => Carbon::yesterday()->setTime(9, 0, 0),
            'monto_inicial_efectivo' => 8000.00,
            'estado' => 'abierta',
        ]);

        $payload = [
            'id_sucursal' => $this->sucursal->id,
            'medio_pago' => 'efectivo',
            'items' => [
                [
                    'id_producto' => $this->producto->id,
                    'descripcion' => $this->producto->descripcion,
                    'cantidad' => 1,
                    'precio_unitario' => 2500,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('ventas.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'requiere_cierre_anterior' => true,
            'caja_anterior_id' => $cajaAyer->id,
        ]);
    }

    public function test_cierre_anterior_y_abrir_asienta_fallo_y_abre_nueva_caja()
    {
        // 1. Caja abierta hace 2 días con ventas
        $cajaVieja = CajaSesion::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => Carbon::now()->subDays(2)->setTime(9, 0, 0),
            'monto_inicial_efectivo' => 5000.00,
            'estado' => 'abierta',
        ]);

        // Simular una venta de $10.000 en efectivo asociada
        Venta::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario' => $this->user->id,
            'id_caja_sesion' => $cajaVieja->id,
            'punto_venta' => 1,
            'numero_factura' => 9991,
            'comprobante' => 'B-0001-00009991',
            'tipo_comprobante' => 'FACTURA_B',
            'fecha' => Carbon::now()->subDays(2),
            'medio_pago' => 'efectivo',
            'subtotal' => 10000.00,
            'total' => 10000.00,
            'estado' => 'completada',
        ]);

        // Efectivo esperado = 5000 (fondo) + 10000 (venta) = $15000.
        // Simulamos que el cajero cuenta $14800 (Faltante / Fallo de caja de -$200)
        // Y deja $5000 como fondo para la caja de HOY
        $response = $this->actingAs($this->user)
            ->postJson(route('cajas.cerrar-anterior-y-abrir'), [
                'id_sucursal' => $this->sucursal->id,
                'id_caja_anterior' => $cajaVieja->id,
                'efectivo_real_contado' => 14800.00,
                'monto_inicial_efectivo' => 5000.00,
                'observaciones' => 'Ajuste de jornada anterior por apertura siguiente',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 2. Verificar que la caja anterior quedó cerrada con la diferencia registrada
        $cajaVieja->refresh();
        $this->assertEquals('cerrada', $cajaVieja->estado);
        $this->assertEquals(15000.00, (float)$cajaVieja->monto_final_efectivo_sistema);
        $this->assertEquals(14800.00, (float)$cajaVieja->monto_final_efectivo_real);
        $this->assertEquals(-200.00, (float)$cajaVieja->diferencia_efectivo);

        // 3. Verificar que se abrió una nueva caja para hoy
        $cajaHoy = CajaSesion::activaParaSucursal($this->sucursal->id);
        $this->assertNotNull($cajaHoy);
        $this->assertTrue($cajaHoy->esDeHoy());
        $this->assertEquals(5000.00, (float)$cajaHoy->monto_inicial_efectivo);
    }

    public function test_cierre_z_calcula_totales_por_medio_y_diferencia_de_efectivo()
    {
        // 1. Abrir caja de hoy
        $caja = CajaSesion::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 10000.00,
            'estado' => 'abierta',
        ]);

        // Venta en efectivo $3000
        Venta::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario' => $this->user->id,
            'id_caja_sesion' => $caja->id,
            'punto_venta' => 1,
            'numero_factura' => 9992,
            'comprobante' => 'B-0001-00009992',
            'tipo_comprobante' => 'FACTURA_B',
            'fecha' => now(),
            'medio_pago' => 'efectivo',
            'subtotal' => 3000.00,
            'total' => 3000.00,
            'estado' => 'completada',
        ]);

        // Venta con tarjeta de débito $7000
        Venta::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario' => $this->user->id,
            'id_caja_sesion' => $caja->id,
            'punto_venta' => 1,
            'numero_factura' => 9993,
            'comprobante' => 'B-0001-00009993',
            'tipo_comprobante' => 'FACTURA_B',
            'fecha' => now(),
            'medio_pago' => 'tarjeta_debito',
            'subtotal' => 7000.00,
            'total' => 7000.00,
            'estado' => 'completada',
        ]);

        // 2. Realizar Cierre Z ingresando $13.050 en efectivo (+50 de sobrante)
        $response = $this->actingAs($this->user)
            ->post(route('cajas.cerrar.store', $caja->id), [
                'monto_final_efectivo_real' => 13050.00,
                'observaciones' => 'Cierre Z final del día',
            ]);

        $response->assertRedirect(route('cajas.comprobante', ['caja' => $caja->id, 'formato' => 'ticket']));

        $caja->refresh();
        $this->assertEquals('cerrada', $caja->estado);
        $this->assertEquals(2, $caja->cantidad_ventas);
        $this->assertEquals(10000.00, (float)$caja->total_ventas);
        $this->assertEquals(3000.00, (float)$caja->total_efectivo);
        $this->assertEquals(7000.00, (float)$caja->total_debito);
        $this->assertEquals(13000.00, (float)$caja->monto_final_efectivo_sistema); // 10000 + 3000
        $this->assertEquals(13050.00, (float)$caja->monto_final_efectivo_real);
        $this->assertEquals(50.00, (float)$caja->diferencia_efectivo);
    }

    public function test_reporte_historico_medios_de_pago()
    {
        $response = $this->actingAs($this->user)
            ->get(route('cajas.historico-medios'));

        $response->assertStatus(200);
        $response->assertSee('Histórico de Facturación por Medio de Pago');
    }

    public function test_movimiento_manual_requiere_motivo_y_monto_positivo()
    {
        $caja = CajaSesion::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 10000.00,
            'estado' => 'abierta',
        ]);

        // Sin motivo
        $response = $this->actingAs($this->user)
            ->postJson(route('cajas.movimientos.store', $caja->id), [
                'tipo' => 'retiro',
                'monto' => 500,
                'motivo' => '',
            ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['motivo']);

        // Monto <= 0
        $response2 = $this->actingAs($this->user)
            ->postJson(route('cajas.movimientos.store', $caja->id), [
                'tipo' => 'ingreso',
                'monto' => 0,
                'motivo' => 'Cobro pendiente',
            ]);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['monto']);
    }

    public function test_ingreso_y_retiro_manual_afectan_efectivo_esperado_y_cierre_z()
    {
        // 1. Abrir caja con $10.000
        $caja = CajaSesion::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 10000.00,
            'estado' => 'abierta',
        ]);

        // 2. Venta en efectivo de $5.000
        Venta::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario' => $this->user->id,
            'id_caja_sesion' => $caja->id,
            'punto_venta' => 1,
            'numero_factura' => 9994,
            'comprobante' => 'B-0001-00009994',
            'tipo_comprobante' => 'FACTURA_B',
            'fecha' => now(),
            'medio_pago' => 'efectivo',
            'subtotal' => 5000.00,
            'total' => 5000.00,
            'estado' => 'completada',
        ]);

        // 3. Registrar Ingreso manual de $2.000 (Cobro pendiente)
        $respIngreso = $this->actingAs($this->user)
            ->postJson(route('cajas.movimientos.store', $caja->id), [
                'tipo' => 'ingreso',
                'monto' => 2000.00,
                'motivo' => 'Cobro pendiente de cliente',
                'comprobante' => 'REC-001',
            ]);
        $respIngreso->assertStatus(200);
        $respIngreso->assertJson(['success' => true]);

        // 4. Registrar Retiro manual de $1.500 (Pago flete compra)
        $respRetiro = $this->actingAs($this->user)
            ->postJson(route('cajas.movimientos.store', $caja->id), [
                'tipo' => 'retiro',
                'monto' => 1500.00,
                'motivo' => 'Pago flete compra insumos',
                'comprobante' => 'REM-456',
            ]);
        $respRetiro->assertStatus(200);
        $respRetiro->assertJson(['success' => true]);

        // 5. Verificar base de datos
        $this->assertDatabaseHas('cajas_movimientos', [
            'id_caja_sesion' => $caja->id,
            'tipo' => 'ingreso',
            'monto' => 2000.00,
            'motivo' => 'Cobro pendiente de cliente',
        ]);
        $this->assertDatabaseHas('cajas_movimientos', [
            'id_caja_sesion' => $caja->id,
            'tipo' => 'retiro',
            'monto' => 1500.00,
            'motivo' => 'Pago flete compra insumos',
        ]);

        // 6. Verificar cálculo de totales
        // Esperado: 10000 (inicial) + 5000 (venta ef) + 2000 (ingreso) - 1500 (retiro) = 15500
        $totales = $caja->calcularTotales();
        $this->assertEquals(2000.00, (float)$totales['total_ingresos']);
        $this->assertEquals(1500.00, (float)$totales['total_retiros']);
        $this->assertEquals(15500.00, (float)$totales['efectivo_esperado']);

        // 7. Realizar Cierre Z con $15.500 exactos
        $responseCierre = $this->actingAs($this->user)
            ->post(route('cajas.cerrar.store', $caja->id), [
                'monto_final_efectivo_real' => 15500.00,
                'observaciones' => 'Cierre con movimientos justificados',
            ]);

        $responseCierre->assertRedirect(route('cajas.comprobante', ['caja' => $caja->id, 'formato' => 'ticket']));

        $caja->refresh();
        $this->assertEquals('cerrada', $caja->estado);
        $this->assertEquals(2000.00, (float)$caja->total_ingresos_efectivo);
        $this->assertEquals(1500.00, (float)$caja->total_retiros_efectivo);
        $this->assertEquals(1, $caja->cant_ingresos_efectivo);
        $this->assertEquals(1, $caja->cant_retiros_efectivo);
        $this->assertEquals(15500.00, (float)$caja->monto_final_efectivo_sistema);
        $this->assertEquals(15500.00, (float)$caja->monto_final_efectivo_real);
        $this->assertEquals(0.00, (float)$caja->diferencia_efectivo);

        // 8. Verificar que el comprobante de Cierre Z incluye los motivos y montos
        $responseTicket = $this->actingAs($this->user)
            ->get(route('cajas.comprobante', ['caja' => $caja->id, 'formato' => 'ticket']));
        $responseTicket->assertStatus(200);
        $responseTicket->assertSee('Cobro pendiente de cliente');
        $responseTicket->assertSee('Pago flete compra insumos');
        $responseTicket->assertSee('2.000,00');
        $responseTicket->assertSee('1.500,00');
    }

    public function test_caja_show_visualiza_totales_por_medio_y_movimientos_del_dia()
    {
        $caja = CajaSesion::create([
            'id_sucursal' => $this->sucursal->id,
            'id_usuario_apertura' => $this->user->id,
            'fecha_apertura' => now(),
            'monto_inicial_efectivo' => 2000.00,
            'total_efectivo' => 1500.00,
            'total_tarjeta_debito' => 3000.00,
            'total_general' => 4500.00,
            'cant_ventas_efectivo' => 1,
            'cant_ventas_tarjeta_debito' => 1,
            'cant_ventas_total' => 2,
            'estado' => 'cerrada',
            'monto_final_efectivo_esperado' => 3500.00,
            'monto_final_efectivo_real' => 3500.00,
            'diferencia_efectivo' => 0.00,
        ]);

        $caja->movimientos()->create([
            'id_usuario' => $this->user->id,
            'tipo' => 'retiro',
            'monto' => 500.00,
            'motivo' => 'Bolsas de compras',
        ]);

        $response = $this->actingAs($this->user)->get(route('cajas.show', $caja->id));

        $response->assertStatus(200);
        $response->assertSee('Detalle de Caja #' . $caja->id);
        $response->assertSee('Bolsas de compras');
        $response->assertSee('1.500,00'); // Efectivo
        $response->assertSee('3.000,00'); // Débito
        $response->assertSee('4.500,00'); // Total general
    }
}

