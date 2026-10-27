<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Presentacion;
use App\Models\ClienteAutorizado;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreditosProvinciaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $vendedorUsh;
    protected User $vendedorTw;
    protected Sucursal $sucUshuaia;
    protected Sucursal $sucTrelew;
    protected Cliente $clienteUsh;
    protected Cliente $clienteTw;
    protected Credito $creditoUsh;
    protected Credito $creditoTw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\UsuariosSucursalesSeeder::class);
        $this->seed(\Database\Seeders\PresentacionSeeder::class);

        $this->sucUshuaia = Sucursal::where('nombre', 'like', '%Ushuaia%')->first();
        $this->sucTrelew = Sucursal::where('nombre', 'like', '%Trelew%')->first();

        $this->admin = User::where('is_admin', true)->first();
        $this->vendedorUsh = User::where('username', 'vendedorush1')->first();
        $this->vendedorTw = User::where('username', 'vendedortw')->first();

        // Asegurar presentaciones
        $presTdf = Presentacion::firstOrCreate(
            ['id_provincia' => 2, 'numero' => 100],
            ['fecha_inicio' => now()->subMonth(), 'interes_credito' => 0.05, 'interes_mora' => 0.05]
        );

        $presChubut = Presentacion::firstOrCreate(
            ['id_provincia' => 1, 'numero' => 1],
            ['fecha_inicio' => now()->subMonth(), 'interes_credito' => 0.05, 'interes_mora' => 0.05]
        );

        // Cliente Ushuaia (TDF)
        $this->clienteUsh = Cliente::create([
            'id_sucursal' => $this->sucUshuaia->id,
            'id_localidad' => 1,
            'nombre' => 'PEREZ JUAN CARLOS (USHUAIA)',
            'dni' => '30111222',
            'direccion' => 'San Martin 500',
            'tel1' => '2901400000',
            'limite' => 200000,
        ]);

        $autUsh = ClienteAutorizado::create([
            'id_titular' => $this->clienteUsh->id,
            'id_autorizado' => $this->clienteUsh->id,
            'relacion_aut' => 'Titular',
        ]);

        $this->creditoUsh = Credito::create([
            'id_cliente' => $this->clienteUsh->id,
            'id_sucursal' => $this->sucUshuaia->id,
            'id_autorizado' => $autUsh->id,
            'id_presentacion' => $presTdf->id,
            'credito' => '5001',
            'tipo_credito' => 'C',
            'importe_original' => 25000,
            'cantidad_cuotas' => 2,
            'valor_cuota_ref' => 12500,
            'fecha_credito' => now()->subDays(5),
        ]);

        // Cliente Trelew (Chubut)
        $this->clienteTw = Cliente::create([
            'id_sucursal' => $this->sucTrelew->id,
            'id_localidad' => 4,
            'nombre' => 'GOMEZ MARIO (TRELEW)',
            'dni' => '30333444',
            'direccion' => 'Fontana 800',
            'tel1' => '2804500000',
            'limite' => 200000,
        ]);

        $autTw = ClienteAutorizado::create([
            'id_titular' => $this->clienteTw->id,
            'id_autorizado' => $this->clienteTw->id,
            'relacion_aut' => 'Titular',
        ]);

        $this->creditoTw = Credito::create([
            'id_cliente' => $this->clienteTw->id,
            'id_sucursal' => $this->sucTrelew->id,
            'id_autorizado' => $autTw->id,
            'id_presentacion' => $presChubut->id,
            'credito' => '5002',
            'tipo_credito' => 'C',
            'importe_original' => 30000,
            'cantidad_cuotas' => 3,
            'valor_cuota_ref' => 10000,
            'fecha_credito' => now()->subDays(3),
        ]);
    }

    /**
     * TEST 1: Al ingresar un vendedor de Ushuaia, por defecto solo ve créditos de TDF.
     */
    public function test_vendedor_ushuaia_ve_por_defecto_creditos_de_tierra_del_fuego(): void
    {
        $response = $this->actingAs($this->vendedorUsh)->get(route('creditos.index'));

        $response->assertStatus(200);
        $response->assertSee('PEREZ JUAN CARLOS (USHUAIA)');
        $response->assertDontSee('GOMEZ MARIO (TRELEW)');
    }

    /**
     * TEST 2: Al ingresar un vendedor de Trelew, por defecto solo ve créditos de Chubut.
     */
    public function test_vendedor_trelew_ve_por_defecto_creditos_de_chubut(): void
    {
        $response = $this->actingAs($this->vendedorTw)->get(route('creditos.index'));

        $response->assertStatus(200);
        $response->assertSee('GOMEZ MARIO (TRELEW)');
        $response->assertDontSee('PEREZ JUAN CARLOS (USHUAIA)');
    }

    /**
     * TEST 3: Al seleccionar "all" (Todas las sucursales), se quita el filtro y se ven ambos.
     */
    public function test_al_seleccionar_todas_las_sucursales_se_quita_filtro_de_provincia(): void
    {
        $response = $this->actingAs($this->vendedorUsh)->get(route('creditos.index', ['sucursal' => 'all']));

        $response->assertStatus(200);
        $response->assertSee('PEREZ JUAN CARLOS (USHUAIA)');
        $response->assertSee('GOMEZ MARIO (TRELEW)');
    }

    /**
     * TEST 4: Se puede seguir filtrando por sucursal específica.
     */
    public function test_se_puede_filtrar_por_sucursal_especifica(): void
    {
        // Filtrar explícitamente por Trelew
        $responseTw = $this->actingAs($this->admin)->get(route('creditos.index', ['sucursal' => $this->sucTrelew->id]));
        $responseTw->assertStatus(200);
        $responseTw->assertSee('GOMEZ MARIO (TRELEW)');
        $responseTw->assertDontSee('PEREZ JUAN CARLOS (USHUAIA)');

        // Filtrar explícitamente por Ushuaia
        $responseUsh = $this->actingAs($this->admin)->get(route('creditos.index', ['sucursal' => $this->sucUshuaia->id]));
        $responseUsh->assertStatus(200);
        $responseUsh->assertSee('PEREZ JUAN CARLOS (USHUAIA)');
        $responseUsh->assertDontSee('GOMEZ MARIO (TRELEW)');
    }
}
