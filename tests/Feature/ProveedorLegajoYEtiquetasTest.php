<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProveedorLegajoYEtiquetasTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Sucursal $sucursal;

    private function r(string $name, mixed $params = []): string
    {
        return route($name, $params, false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin Test',
            'username' => 'admin.test',
            'password' => bcrypt('password123'),
        ]);

        $this->sucursal = Sucursal::create([
            'nombre' => 'Sucursal Central',
            'codigo_sucursal' => 'CEN01',
            'activo' => true,
        ]);
    }

    public function test_proveedor_soporta_legajo_y_formateo_de_tres_digitos(): void
    {
        $proveedor = Proveedor::create([
            'legajo' => '43',
            'nombre' => 'CASA JONAS S.A.',
            'cuit' => '30112233445',
            'activo' => true,
        ]);

        $this->assertEquals('43', $proveedor->legajo);
        $this->assertEquals('043', $proveedor->legajo_formateado);

        // Sugerencia de siguiente legajo
        $siguiente = Proveedor::sugerirSiguienteLegajo();
        $this->assertNotEmpty($siguiente);
        $this->assertEquals('044', $siguiente);
    }

    public function test_proveedor_creacion_y_edicion_con_legajo_via_controlador(): void
    {
        $this->actingAs($this->user);

        // Alta de proveedor
        $response = $this->post($this->r('proveedores.store'), [
            'legajo' => '7',
            'nombre' => 'TEXTIL PATAGONIA',
            'cuit' => '30998877665',
        ]);

        $response->assertSessionHasNoErrors();
        $proveedor = Proveedor::where('nombre', 'TEXTIL PATAGONIA')->first();
        $this->assertNotNull($proveedor);
        $this->assertEquals('007', $proveedor->legajo);

        // Edición de proveedor
        $updateResponse = $this->put($this->r('proveedores.update', $proveedor->id), [
            'legajo' => '8',
            'nombre' => 'TEXTIL PATAGONIA S.A.',
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $proveedor->refresh();
        $this->assertEquals('008', $proveedor->legajo);
        $this->assertEquals('TEXTIL PATAGONIA S.A.', $proveedor->nombre);
    }

    public function test_imprimir_etiquetas_renderiza_correctamente_5_copias_y_svg(): void
    {
        $this->actingAs($this->user);

        $proveedor = Proveedor::create([
            'legajo' => '204',
            'nombre' => 'PROVEEDOR 204',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'codigo' => '204000000173',
            'codigo_barra' => '204000000173',
            'descripcion' => 'CONJUNTO PARIS TULL',
            'precio_costo' => 5000,
            'precio_venta' => 12500,
            'proveedor_id' => $proveedor->id,
            'controla_stock' => true,
            'activo' => true,
        ]);

        $response = $this->get($this->r('productos.etiqueta', [
            'producto' => $producto->id,
            'cantidad' => 5,
            'autoprint' => 1,
        ]));

        $response->assertStatus(200);
        $response->assertSee('204000000173');
        $response->assertSee('CONJUNTO PARIS TULL');
        $response->assertSee('12.500,00');
        $response->assertSee('Imprimir 5 Etiquetas');
        // Debe contener etiquetas en formato SVG
        $response->assertSee('<svg', false);
    }

    public function test_alta_de_stock_permite_redirigir_a_impresion_de_etiquetas(): void
    {
        $this->actingAs($this->user);

        $producto = Producto::create([
            'codigo' => 'ART-ETIQ-001',
            'descripcion' => 'REMERA TERMICA ALTA',
            'precio_costo' => 2000,
            'precio_venta' => 5000,
            'controla_stock' => true,
            'activo' => true,
        ]);

        $response = $this->post($this->r('productos.ajuste', $producto->id), [
            'sucursal_id' => $this->sucursal->id,
            'tipo' => 'alta',
            'cantidad' => 5,
            'motivo' => 'Ingreso de mercadería por factura',
            'comprobante' => 'FAC-0001-1234',
            'imprimir_etiquetas' => '1',
        ]);

        // Debe redirigir a la vista de impresión con cantidad=5, es_alta=1 y autoprint=1
        $response->assertRedirect($this->r('productos.etiqueta', [
            'producto' => $producto->id,
            'cantidad' => 5,
            'sucursal_id' => $this->sucursal->id,
            'es_alta' => 1,
            'autoprint' => 1,
        ]));

        // Verificar que el stock físico se incrementó
        $stock = $producto->stocks()->where('sucursal_id', $this->sucursal->id)->first();
        $this->assertEquals(5, (float)$stock->stock);
    }

    public function test_reimpresion_de_etiquetas_no_altera_stock_y_muestra_advertencias(): void
    {
        $this->actingAs($this->user);

        $producto = Producto::create([
            'codigo' => 'ART-REIMP-001',
            'descripcion' => 'BUZO ALGODON FRISA',
            'precio_costo' => 4000,
            'precio_venta' => 9000,
            'controla_stock' => true,
            'activo' => true,
        ]);

        // Asignar stock inicial de 10 unidades
        $producto->ajustarStock($this->sucursal->id, 'alta', 10, 'Stock inicial', $this->user->id);

        $stockAntes = (float)$producto->fresh()->getStockTotal();
        $this->assertEquals(10, $stockAntes);

        // Invocar reimpresión de 3 etiquetas
        $response = $this->get($this->r('productos.etiqueta', [
            'producto' => $producto->id,
            'cantidad' => 3,
            'motivo' => 'Etiqueta rota / dañada en salón',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Reimpresión (No modifica stock)');
        $response->assertSee('Etiqueta rota / dañada en salón');

        // Verificar que el stock NO fue alterado
        $stockDespues = (float)$producto->fresh()->getStockTotal();
        $this->assertEquals(10, $stockDespues);

        // Verificar que el modal en el catálogo contiene la advertencia explícita
        $indexResponse = $this->get($this->r('productos.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Esta acción NO modifica el stock ni registra movimientos');
    }

    public function test_busqueda_de_catalogo_por_legajo_de_proveedor(): void
    {
        $this->actingAs($this->user);

        $prov = Proveedor::create([
            'legajo' => '348',
            'nombre' => 'MAGIC TEXTIL SRL',
            'activo' => true,
        ]);

        $prod = Producto::create([
            'codigo' => '348000080776',
            'codigo_barra' => '348000080776',
            'descripcion' => 'JOGGING MARVEL AZUL',
            'precio_costo' => 3000,
            'precio_venta' => 8000,
            'proveedor_id' => $prov->id,
            'controla_stock' => true,
            'activo' => true,
        ]);

        // Buscar por los primeros 3 dígitos del legajo (348)
        $response = $this->get($this->r('productos.index', ['search' => '348']));
        $response->assertStatus(200);
        $response->assertSee('JOGGING MARVEL AZUL');
        $response->assertSee('348000080776');
    }

    public function test_creacion_de_producto_con_stock_inicial_e_impresion_de_etiquetas(): void
    {
        $this->actingAs($this->user);

        // Verificar que el formulario de creación contiene la advertencia de control de inventario
        $createForm = $this->get($this->r('productos.create'));
        $createForm->assertStatus(200);
        $createForm->assertSee('No imprima etiquetas de más');

        $response = $this->post($this->r('productos.store'), [
            'codigo' => 'ART-NUEVO-001',
            'descripcion' => 'REMERA MANGA CORTA JERSEY',
            'precio_venta' => 4500,
            'controla_stock' => '1',
            'stock_inicial' => [
                $this->sucursal->id => 6,
            ],
            'imprimir_etiquetas' => '1',
        ]);

        $producto = Producto::where('codigo', 'ART-NUEVO-001')->firstOrFail();

        // Debe redirigir a la vista de impresión con cantidad=6 y es_alta=1
        $response->assertRedirect($this->r('productos.etiqueta', [
            'producto' => $producto->id,
            'cantidad' => 6,
            'es_alta' => 1,
            'autoprint' => 1,
        ]));

        // Comprobar que en la vista de etiqueta se visualiza el banner de alerta de stock
        $labelView = $this->get($this->r('productos.etiqueta', [
            'producto' => $producto->id,
            'cantidad' => 6,
            'es_alta' => 1,
        ]));
        $labelView->assertStatus(200);
        $labelView->assertSee('Impresión por Ingreso de Mercadería (6 etiquetas)');
        $labelView->assertSee('No imprima etiquetas de más');
    }
}

