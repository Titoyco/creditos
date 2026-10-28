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
}
