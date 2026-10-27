<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sucursal;
use Database\Seeders\UsuariosSucursalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSecurityAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Cargar sucursales y usuarios base del sistema
        $this->seed(UsuariosSucursalesSeeder::class);
    }

    /**
     * 1. Pantalla de Login: limpia, sin credenciales de prueba, sin usuarios ni claves sugeridas.
     */
    public function test_login_screen_has_no_test_credentials_or_quick_access_buttons(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Iniciar Sesión');
        $response->assertSee('INGRESAR AL SISTEMA');

        // Garantizar que no se exponga ningún usuario ni clave en el HTML
        $response->assertDontSee('Accesos Rápidos');
        $response->assertDontSee('admin123');
        $response->assertDontSee('ush123');
        $response->assertDontSee('tw123');
        $response->assertDontSee('int123');
        $response->assertDontSee('Pablobar1979');
        $response->assertDontSee('superuser');
        $response->assertDontSee('setCredenciales');
    }

    /**
     * 2. Seguridad de Rutas: ninguna vista ni dato del sistema es accesible sin autenticación.
     */
    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $protectedGetRoutes = [
            '/',
            '/clientes',
            '/clientes/buscar',
            '/clientes/nuevo',
            '/creditos',
            '/creditos/nuevo',
            '/ventas',
            '/ventas/crear',
            '/ventas/buscar-producto',
            '/productos',
            '/productos/nuevo',
            '/productos/traslado',
            '/stock/movimientos',
            '/categorias',
            '/proveedores',
            '/remitos',
            '/remitos/nuevo',
            '/recibos',
            '/recibos/nuevo',
            '/sistema',
            '/sistema/usuarios',
            '/sistema/sucursales',
            '/sistema/auditoria',
            '/sistema/ubicaciones',
            '/profile',
        ];

        foreach ($protectedGetRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }

        // Endpoint POST protegido
        $postResponse = $this->post('/cambiar-sucursal', ['id_sucursal' => 1]);
        $postResponse->assertRedirect('/login');
    }

    /**
     * 3. Superusuario Secreto: existe y puede iniciar sesión con la clave Pablobar1979.
     * La clave debe estar encriptada en base de datos con algoritmo Bcrypt.
     */
    public function test_secret_superuser_authenticates_with_pablobar1979_and_is_encrypted(): void
    {
        $superuser = User::where('username', 'superuser')->first();
        $this->assertNotNull($superuser, 'El superusuario secreto "superuser" debe existir en la base de datos.');

        // Verificar encriptación en base de datos
        $this->assertStringStartsWith('$2y$', $superuser->password, 'La clave del superusuario debe estar encriptada con Bcrypt ($2y$).');
        $this->assertTrue(Hash::check('Pablobar1979', $superuser->password), 'La clave encriptada debe corresponder a Pablobar1979.');

        // Login vía formulario
        $response = $this->post('/login', [
            'username' => 'superuser',
            'password' => 'Pablobar1979',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($superuser);
    }

    /**
     * 4. Privacidad Total del Superusuario: NO aparece en el panel de administración bajo ningún concepto.
     */
    public function test_superuser_is_never_shown_in_user_administration_list(): void
    {
        $admin = User::where('username', 'Admin')->first();
        $superuser = User::where('username', 'superuser')->first();

        // 1. Un administrador común no debe ver al superuser
        $response = $this->actingAs($admin)->get('/sistema/usuarios');
        $response->assertOk();
        $response->assertSee('Admin');
        $response->assertSee('vendedorush1');
        $response->assertDontSee('@superuser');
        $response->assertDontSee('superuser');

        // 2. Ni siquiera el propio superuser aparece en la lista de operadores del panel
        $superuserResponse = $this->actingAs($superuser)->get('/sistema/usuarios');
        $superuserResponse->assertOk();
        $superuserResponse->assertDontSee('@superuser');
        $superuserResponse->assertDontSee('superuser');
    }

    /**
     * 5. Inaccesibilidad del Superusuario vía ABM: cualquier intento de acceder a su ID devuelve 404 Not Found.
     */
    public function test_superuser_is_inaccessible_via_operator_abm(): void
    {
        $admin = User::where('username', 'Admin')->first();
        $superuser = User::where('username', 'superuser')->first();

        // Intento de ver pantalla de edición del superuser -> 404 Not Found
        $editResponse = $this->actingAs($admin)->get("/sistema/usuarios/{$superuser->id}/edit");
        $editResponse->assertStatus(404);

        // Intento de actualizar el superuser -> 404 Not Found
        $updateResponse = $this->actingAs($admin)->put("/sistema/usuarios/{$superuser->id}", [
            'name'        => 'Hacked Name',
            'username'    => 'superuser',
            'id_sucursal' => 1,
        ]);
        $updateResponse->assertStatus(404);

        // Intento de eliminar el superuser -> 404 Not Found
        $deleteResponse = $this->actingAs($admin)->delete("/sistema/usuarios/{$superuser->id}");
        $deleteResponse->assertStatus(404);

        // Confirmar que el superusuario sigue intacto
        $this->assertNotNull(User::where('username', 'superuser')->first());

        // Intento de crear un nuevo usuario con el username 'superuser' -> Bloqueado por validación
        $createResponse = $this->actingAs($admin)->post('/sistema/usuarios', [
            'name'        => 'Fake Superuser',
            'username'    => 'superuser',
            'password'    => '123456',
            'id_sucursal' => 1,
        ]);
        $createResponse->assertSessionHasErrors('username');
    }

    /**
     * 6. ABM de Usuarios: Crear, editar nombre, username y clave encriptada.
     */
    public function test_admin_can_create_and_update_operator_profile_and_password(): void
    {
        $admin = User::where('username', 'Admin')->first();

        // Crear nuevo operador
        $createResponse = $this->actingAs($admin)->post('/sistema/usuarios', [
            'name'        => 'Carlos Vendedor',
            'username'    => 'carlosv',
            'password'    => 'clave123',
            'id_sucursal' => 1,
            'is_admin'    => 0,
        ]);

        $createResponse->assertRedirect('/sistema/usuarios');
        $carlos = User::where('username', 'carlosv')->first();
        $this->assertNotNull($carlos);
        $this->assertTrue(Hash::check('clave123', $carlos->password));
        $this->assertStringStartsWith('$2y$', $carlos->password);

        // Editar nombre, username y actualizar contraseña
        $updateResponse = $this->actingAs($admin)->put("/sistema/usuarios/{$carlos->id}", [
            'name'        => 'Carlos Vendedor Editado',
            'username'    => 'carlos_nuevo',
            'password'    => 'nuevaclave456',
            'id_sucursal' => 2,
            'is_admin'    => 1,
        ]);

        $updateResponse->assertRedirect('/sistema/usuarios');
        $carlos->refresh();
        $this->assertEquals('Carlos Vendedor Editado', $carlos->name);
        $this->assertEquals('carlos_nuevo', $carlos->username);
        $this->assertEquals(2, $carlos->id_sucursal);
        $this->assertEquals(1, $carlos->is_admin);
        $this->assertTrue(Hash::check('nuevaclave456', $carlos->password));

        // Editar nuevamente pero dejando la clave en blanco (mantener la actual)
        $noPassUpdateResponse = $this->actingAs($admin)->put("/sistema/usuarios/{$carlos->id}", [
            'name'        => 'Carlos Final',
            'username'    => 'carlos_nuevo',
            'password'    => '', // En blanco
            'id_sucursal' => 2,
            'is_admin'    => 1,
        ]);

        $noPassUpdateResponse->assertRedirect('/sistema/usuarios');
        $carlos->refresh();
        $this->assertEquals('Carlos Final', $carlos->name);
        // La clave previa 'nuevaclave456' se preserva intacta
        $this->assertTrue(Hash::check('nuevaclave456', $carlos->password));
    }

    /**
     * 7. Perfil de Usuario Propio: un operador puede editar su propio nombre, username y clave.
     */
    public function test_user_can_edit_own_profile_and_change_password(): void
    {
        $vendedor = User::where('username', 'vendedorush1')->first();

        // Actualizar información del perfil
        $profileResponse = $this->actingAs($vendedor)->patch('/profile', [
            'name'     => 'Juan Ushuaia',
            'username' => 'juan_ush1',
        ]);

        $profileResponse->assertSessionHasNoErrors();
        $vendedor->refresh();
        $this->assertEquals('Juan Ushuaia', $vendedor->name);
        $this->assertEquals('juan_ush1', $vendedor->username);

        // Actualizar contraseña propia
        $passwordResponse = $this->actingAs($vendedor)->put('/password', [
            'current_password'      => 'ush123',
            'password'              => 'nuevaPass1234',
            'password_confirmation' => 'nuevaPass1234',
        ]);

        $passwordResponse->assertSessionHasNoErrors();
        $vendedor->refresh();
        $this->assertTrue(Hash::check('nuevaPass1234', $vendedor->password));
        $this->assertStringStartsWith('$2y$', $vendedor->password);
    }
}
