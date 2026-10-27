<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\Localidad;
use App\Models\Provincia;
use App\Models\Credito;
use Database\Seeders\UsuariosSucursalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ClientesConsolidadosTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Localidad $localidadUshuaia;
    protected Localidad $localidadTrelew;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UsuariosSucursalesSeeder::class);

        // Provincias y Localidades para testing
        $tdf = Provincia::firstOrCreate(['id' => 2], ['nombre' => 'Tierra Del Fuego']);
        $chubut = Provincia::firstOrCreate(['id' => 1], ['nombre' => 'Chubut']);

        $this->localidadUshuaia = Localidad::firstOrCreate(
            ['nombre' => 'USHUAIA', 'id_provincia' => $tdf->id],
            ['codigo_postal' => '9410']
        );

        $this->localidadTrelew = Localidad::firstOrCreate(
            ['nombre' => 'TRELEW', 'id_provincia' => $chubut->id],
            ['codigo_postal' => '9100']
        );

        $this->admin = User::where('username', 'Admin')->first();
    }

    public function test_can_render_clientes_index_and_create_screens(): void
    {
        $responseIndex = $this->actingAs($this->admin)->get('/clientes');
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Gestión de Clientes');
        $responseIndex->assertSee('Documento / Cuenta');

        $responseCreate = $this->actingAs($this->admin)->get('/clientes/nuevo');
        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('Alta de Nuevo Cliente');
        $responseCreate->assertSee('CUIT / CUIL');
        $responseCreate->assertSee('Nro. de Cuenta / Legajo');
    }

    public function test_can_create_and_view_cliente_with_tierra_del_fuego_data(): void
    {
        $payload = [
            'dni' => 32456789,
            'cuit' => '20-32456789-4',
            'nro_cuenta' => 'USM-501',
            'nombre' => 'GONZALEZ FEDERICO TDF',
            'id_sucursal' => 1, // Ushuaia San Martin
            'medio_pago_preferido' => 0, // Sucursal / Cta Cte
            'cbu' => null,
            'tipo_haber' => 1,
            'limite' => 1200000,
            'max_cuotas' => 12,
            'direccion' => 'Av. San Martín 450',
            'id_localidad' => $this->localidadUshuaia->id,
            'tel1' => '2901-445566',
            'tel2' => '2901-778899',
            'email' => 'fgonzalez@ejemplo.com',
            'trabajo' => 'Gobierno TDF',
            'direccion_trabajo' => 'San Martin 450',
            'id_localidad_trabajo' => $this->localidadUshuaia->id,
            'tel_trabajo' => '2901-441122',
            'obs' => 'Cliente histórico de Ushuaia con cuenta corriente activa.',
        ];

        $response = $this->actingAs($this->admin)->post('/clientes', $payload);
        $response->assertRedirect('/clientes');
        $response->assertSessionHas('success');

        $cliente = Cliente::where('dni', 32456789)->first();
        $this->assertNotNull($cliente);
        $this->assertEquals('20-32456789-4', $cliente->cuit);
        $this->assertEquals('USM-501', $cliente->nro_cuenta);
        $this->assertEquals(12, $cliente->max_cuotas);

        // Ver detalle del legajo
        $responseShow = $this->actingAs($this->admin)->get("/clientes/{$cliente->id}");
        $responseShow->assertStatus(200);
        $responseShow->assertSee('USM-501');
        $responseShow->assertSee('20-32456789-4');
        $responseShow->assertSee('USHUAIA');
        $responseShow->assertSee('12 Cuotas');
    }

    public function test_can_search_by_dni_cuit_and_nro_cuenta(): void
    {
        $cliente = Cliente::create([
            'dni' => 40111222,
            'cuit' => '27-40111222-3',
            'nro_cuenta' => 'UAN-9988',
            'nombre' => 'MARTINEZ SILVIA ANALIA',
            'id_sucursal' => 2,
            'medio_pago_preferido' => 0,
            'direccion' => 'Kuanip 820',
            'id_localidad' => $this->localidadUshuaia->id,
            'tel1' => '2901-667788',
            'limite' => 500000,
            'max_cuotas' => 6,
        ]);

        // 1. Buscar por DNI
        $resDni = $this->actingAs($this->admin)->get('/clientes?search=40111222');
        $resDni->assertSee('MARTINEZ SILVIA ANALIA');

        // 2. Buscar por CUIT
        $resCuit = $this->actingAs($this->admin)->get('/clientes?search=27-40111222-3');
        $resCuit->assertSee('MARTINEZ SILVIA ANALIA');

        // 3. Buscar por Nro. Cuenta
        $resCta = $this->actingAs($this->admin)->get('/clientes?search=UAN-9988');
        $resCta->assertSee('MARTINEZ SILVIA ANALIA');
    }

    public function test_cliente_with_history_cannot_be_deleted_but_clean_cliente_can(): void
    {
        $cliente = Cliente::create([
            'dni' => 15222333,
            'nombre' => 'LOPEZ RAMIRO SIN HISTORIAL',
            'id_sucursal' => 3,
            'medio_pago_preferido' => 0,
            'direccion' => 'Belgrano 120',
            'id_localidad' => $this->localidadTrelew->id,
            'tel1' => '280-4556677',
            'limite' => 300000,
            'saldo' => 0,
        ]);

        // 1. Simular saldo deudor para probar bloqueo de eliminación
        $cliente->saldo = 25000;
        $cliente->save();

        // Intento de eliminación con saldo -> Debe bloquearse
        $responseBlocked = $this->actingAs($this->admin)->delete("/clientes/{$cliente->id}");
        $responseBlocked->assertRedirect('/clientes');
        $responseBlocked->assertSessionHas('error');
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id]);

        // 2. Ahora saldamos la deuda (saldo = 0)
        $cliente->saldo = 0;
        $cliente->save();

        // Intento de eliminación en limpio -> Debe eliminarse con éxito
        $responseSuccess = $this->actingAs($this->admin)->delete("/clientes/{$cliente->id}");
        $responseSuccess->assertRedirect('/clientes');
        $responseSuccess->assertSessionHas('success');
        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    public function test_can_add_novedad_to_cliente_legajo(): void
    {
        $cliente = Cliente::create([
            'dni' => 22333444,
            'nombre' => 'SANCHEZ CARLOS NOVEDAD',
            'id_sucursal' => 1,
            'medio_pago_preferido' => 0,
            'direccion' => 'Maipu 100',
            'id_localidad' => $this->localidadUshuaia->id,
            'tel1' => '2901-112233',
            'limite' => 400000,
            'saldo' => 0,
        ]);

        $responseNovedad = $this->actingAs($this->admin)->post("/clientes/{$cliente->id}/novedades", [
            'mensaje' => 'Se comunicó telefónicamente para consultar límite disponible para compra de electrodoméstico.',
        ]);

        $responseNovedad->assertRedirect("/clientes/{$cliente->id}");
        $responseNovedad->assertSessionHas('success');

        $this->assertDatabaseHas('cliente_novedades', [
            'id_cliente' => $cliente->id,
            'mensaje' => 'Se comunicó telefónicamente para consultar límite disponible para compra de electrodoméstico.',
        ]);
    }
}
