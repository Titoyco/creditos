<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Localidad;
use App\Models\Provincia;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoAbmTest extends TestCase
{
    use RefreshDatabase;

    private function r(string $name, mixed $params = []): string
    {
        // URL relativa: en este entorno `$this->get(route(...))` (URL absoluta) responde 404.
        return route($name, $params, false);
    }

    private function actingAsAdmin()
    {
        // ⚠ El esquema de `users` es propio del proyecto (usa `username`, no `email`),
        // por lo que el UserFactory de Breeze no aplica: se crea el usuario directamente.
        $this->actingAs(User::create([
            'name' => 'Admin QA',
            'username' => 'admin.qa',
            'password' => 'secreto',
            'is_admin' => true,
        ]));

        // ⚠ En este entorno la PRIMERA petición HTTP de cada test responde 404
        //   (artefacto del kernel de testing). Se "calienta" con el health endpoint.
        $this->get('/up');
    }

    private function crearSucursal(string $nombre = 'Sucursal Centro', string $codigo = 'T'): Sucursal
    {
        return Sucursal::create(['nombre' => $nombre, 'codigo_sucursal' => $codigo]);
    }

    private function crearCliente(int $dni, int $idSucursal, int $idLocalidad): Cliente
    {
        return Cliente::create([
            'dni' => $dni,
            'nombre' => 'Cliente Test',
            'id_sucursal' => $idSucursal,
            'medio_pago_preferido' => 0,
            'limite' => 1000,
            'max_cuotas' => 3,
            'direccion' => 'Calle 1',
            'id_localidad' => $idLocalidad,
            'tel1' => '11223344',
        ]);
    }

    public function test_sucursales_crud_completo(): void
    {
        $this->actingAsAdmin();

        $this->get($this->r('sistema.sucursales.index'))->assertOk()->assertSee('Catálogo de Sucursales');
        $this->get($this->r('sistema.sucursales.create'))->assertOk()->assertSee('Nueva Sucursal');

        $this->post($this->r('sistema.sucursales.store'), [
            'nombre' => 'Sucursal Centro',
            'codigo_sucursal' => 't',
            'direccion' => 'Av. Corrientes 1234',
        ])->assertRedirect($this->r('sistema.sucursales.index'))->assertSessionHas('success');

        $sucursal = Sucursal::firstWhere('codigo_sucursal', 'T');
        $this->assertNotNull($sucursal, 'El código debe normalizarse a mayúsculas.');
        $this->assertSame('Av. Corrientes 1234', $sucursal->direccion);

        $this->post($this->r('sistema.sucursales.store'), [
            'nombre' => 'Duplicada', 'codigo_sucursal' => 'T',
        ])->assertSessionHasErrors('codigo_sucursal');

        $this->put($this->r('sistema.sucursales.update', $sucursal->id), [
            'nombre' => 'Sucursal Centro (editada)',
            'codigo_sucursal' => 'T',
            'direccion' => 'Av. Santa Fe 999',
        ])->assertRedirect($this->r('sistema.sucursales.index'))->assertSessionHas('success');

        $this->assertSame('Sucursal Centro (editada)', $sucursal->fresh()->nombre);

        $this->get($this->r('sistema.sucursales.edit', $sucursal->id))
            ->assertOk()->assertSee('Sucursal Centro (editada)');

        $this->delete($this->r('sistema.sucursales.destroy', $sucursal->id))
            ->assertRedirect($this->r('sistema.sucursales.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('sucursales', ['id' => $sucursal->id]);
    }

    public function test_no_se_puede_eliminar_sucursal_con_creditos(): void
    {
        $this->actingAsAdmin();

        $sucursal = $this->crearSucursal('Sucursal Con Créditos', 'X');
        $localidad = Localidad::create(['id_provincia' => Provincia::create(['nombre' => 'Bs As'])->id, 'nombre' => 'CABA']);
        $cliente = $this->crearCliente(12345678, $sucursal->id, $localidad->id);

        // `creditos` exige (NOT NULL) `id_autorizado` e `id_presentacion` según el esquema actual.
        $autorizado = new \App\Models\ClienteAutorizado();
        $autorizado->id_titular = $cliente->id;
        $autorizado->id_autorizado = $cliente->id;
        $autorizado->save();
        $presentacion = \App\Models\Presentacion::create([
            'numero' => 500,
            'fecha_inicio' => now()->toDateString(),
            'interes_credito' => 10,
            'interes_mora' => 10,
        ]);

        Credito::create([
            'id_cliente' => $cliente->id,
            'id_sucursal' => $sucursal->id,
            'id_autorizado' => $autorizado->id,
            'id_presentacion' => $presentacion->id,
            'credito' => 'X001',
            'tipo_credito' => 'C',
            'importe_original' => 1000,
            'cantidad_cuotas' => 1,
            'valor_cuota_ref' => 1000,
            'fecha_credito' => now()->toDateString(),
        ]);

        $this->delete($this->r('sistema.sucursales.destroy', $sucursal->id))
            ->assertRedirect($this->r('sistema.sucursales.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sucursales', ['id' => $sucursal->id]);
    }
    public function test_ubicaciones_consolidada_y_abm(): void
    {
        $this->actingAsAdmin();

        $provincia = Provincia::create(['nombre' => 'Buenos Aires']);
        $localidad = Localidad::create([
            'id_provincia' => $provincia->id,
            'nombre' => 'La Plata',
            'codigo_postal' => '1900',
        ]);

        $this->get($this->r('sistema.ubicaciones.index'))
            ->assertOk()->assertSee('Buenos Aires')->assertSee('La Plata')->assertSee('1900');

        $this->get($this->r('sistema.ubicaciones.index', ['id_provincia' => $provincia->id]))
            ->assertOk()->assertSee('La Plata');

        $this->get($this->r('sistema.ubicaciones.index', ['search' => '1900']))
            ->assertOk()->assertSee('La Plata');

        $this->post($this->r('sistema.ubicaciones.provincias.store'), ['nombre' => 'Córdoba'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('provincias', ['nombre' => 'Córdoba']);

        // Alta duplicada: la validación debe frenar la operación y reportar el error.
        // ⚠ El proyecto no tiene traducciones de validación (`lang/`), por lo que el mensaje
        //   llega en inglés; el atributo sí usa la etiqueta en español definida en el controlador.
        $this->post($this->r('sistema.ubicaciones.provincias.store'), ['nombre' => 'Córdoba'])
            ->assertSessionHasErrors('nombre');
        $this->assertSame(1, Provincia::where('nombre', 'Córdoba')->count(), 'No debe duplicarse la provincia.');

        // El error de validación debe quedar visible en la vista consolidada.
        $this->followingRedirects()
            ->post($this->r('sistema.ubicaciones.provincias.store'), ['nombre' => 'Córdoba'])
            ->assertSee('nombre de la provincia')
            ->assertSee('Error:');

        $nueva = Provincia::where('nombre', 'Córdoba')->first();
        $this->put($this->r('sistema.ubicaciones.provincias.update', $nueva->id), ['nombre' => 'Córdoba (ed)'])
            ->assertSessionHas('success');
        $this->assertSame('Córdoba (ed)', $nueva->fresh()->nombre);

        $this->post($this->r('sistema.ubicaciones.localidades.store'), [
            'id_provincia' => $provincia->id,
            'nombre' => 'CABA',
            'codigo_postal' => '1000',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('localidades', ['nombre' => 'Caba', 'codigo_postal' => '1000']);

        $this->put($this->r('sistema.ubicaciones.localidades.update', $localidad->id), [
            'id_provincia' => $provincia->id,
            'nombre' => 'La Plata (editada)',
            'codigo_postal' => '1901',
        ])->assertSessionHas('success');
        $this->assertSame('1901', $localidad->fresh()->codigo_postal);

        $this->delete($this->r('sistema.ubicaciones.provincias.destroy', $provincia->id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('provincias', ['id' => $provincia->id]);

        $caba = Localidad::where('nombre', 'Caba')->first();
        $this->delete($this->r('sistema.ubicaciones.localidades.destroy', $caba->id))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('localidades', ['id' => $caba->id]);

        $localidad->delete();
        $this->delete($this->r('sistema.ubicaciones.provincias.destroy', $provincia->id))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('provincias', ['id' => $provincia->id]);
    }

    public function test_no_se_puede_eliminar_localidad_con_clientes(): void
    {
        $this->actingAsAdmin();

        $provincia = Provincia::create(['nombre' => 'Córdoba']);
        $localidad = Localidad::create(['id_provincia' => $provincia->id, 'nombre' => 'Córdoba Capital']);
        $sucursal = $this->crearSucursal('Suc', 'S');
        $this->crearCliente(87654321, $sucursal->id, $localidad->id);

        $this->delete($this->r('sistema.ubicaciones.localidades.destroy', $localidad->id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('localidades', ['id' => $localidad->id]);
    }

    public function test_rutas_protegidas_por_auth(): void
    {
        $this->get('/up'); // calentamiento (ver actingAsAdmin)

        $this->get($this->r('sistema.sucursales.index'))
            ->assertRedirect(route('login', [], false));

        $this->get($this->r('sistema.ubicaciones.index'))
            ->assertRedirect(route('login', [], false));
    }
}
