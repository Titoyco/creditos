<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Sucursal;
use Database\Seeders\UsuariosSucursalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginAndSucursalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Asegurar que las sucursales y usuarios existan
        $this->seed(UsuariosSucursalesSeeder::class);
    }

    public function test_login_screen_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('LA MARINA');
        $response->assertDontSee('vendedorush1');
        $response->assertDontSee('admin123');
        $response->assertDontSee('Accesos Rápidos');
    }

    public function test_admin_can_login_and_switch_branches(): void
    {
        $admin = User::where('username', 'Admin')->first();
        $this->assertNotNull($admin);

        $loginResponse = $this->post('/login', [
            'username' => 'Admin',
            'password' => 'admin123',
        ]);

        $loginResponse->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);

        // Dashboard renders for Admin
        $dashboardResponse = $this->actingAs($admin)->get('/');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Modo Administrador');
        $dashboardResponse->assertSee('Sucursal Activa (Admin)');
        $dashboardResponse->assertDontSee('Abrir Caja');
        $dashboardResponse->assertDontSee('Ver Catálogo');
        $dashboardResponse->assertSee(route('ventas.create'));
        $dashboardResponse->assertSee(route('productos.index'));
        $dashboardResponse->assertSee(route('clientes.index'));
        $dashboardResponse->assertSee(route('creditos.index'));
        $dashboardResponse->assertSee(route('sistema.index'));

        // Admin switches branch to Sucursal 2 (Ushuaia Antártida)
        $sucursal2 = Sucursal::find(2);
        $switchResponse = $this->actingAs($admin)->post('/cambiar-sucursal', [
            'id_sucursal' => 2,
        ]);

        $switchResponse->assertRedirect();
        $this->assertEquals(2, session('sucursal_activa_id'));
    }

    public function test_vendedor_ush1_is_locked_to_ushuaia_san_martin(): void
    {
        $vendedor = User::where('username', 'vendedorush1')->first();
        $this->assertNotNull($vendedor);
        $this->assertEquals(1, $vendedor->id_sucursal);
        $this->assertFalse((bool)$vendedor->is_admin);

        // Login as vendedorush1
        $loginResponse = $this->post('/login', [
            'username' => 'vendedorush1',
            'password' => 'ush123',
        ]);

        $loginResponse->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($vendedor);

        // Dashboard renders and shows assigned branch
        $dashboardResponse = $this->actingAs($vendedor)->get('/');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Modo Operador de Sucursal');
        $dashboardResponse->assertSee('Sucursal Asignada');
        $dashboardResponse->assertSee('Ushuaia - San Martín');

        // Vendedor attempts to switch branch -> MUST BE BLOCKED (403 Forbidden)
        $switchResponse = $this->actingAs($vendedor)->post('/cambiar-sucursal', [
            'id_sucursal' => 2,
        ]);

        $switchResponse->assertStatus(403);
    }

    public function test_all_vendedores_are_assigned_to_their_respective_branches(): void
    {
        $vendedores = [
            'vendedorush1' => 1, // Ushuaia - San Martín
            'vendedorush2' => 2, // Ushuaia - Antártida
            'vendedortw'   => 3, // Trelew - Centro
            'vendedorint'  => 4, // Trelew - Interior / Camión
        ];

        foreach ($vendedores as $username => $expectedSucursalId) {
            $user = User::where('username', $username)->first();
            $this->assertNotNull($user, "El usuario $username debe existir");
            $this->assertEquals($expectedSucursalId, $user->id_sucursal, "La sucursal de $username debe ser $expectedSucursalId");

            // Validar que el middleware asigne siempre su sucursal fija
            $response = $this->actingAs($user)->get('/');
            $response->assertStatus(200);
            $this->assertEquals($expectedSucursalId, config('app.sucursal_activa')->id);
        }
    }
}
