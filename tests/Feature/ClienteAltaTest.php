<?php

namespace Tests\Feature;

use App\Models\Localidad;
use App\Models\Provincia;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteAltaTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin()
    {
        $this->actingAs(User::create([
            'name' => 'Admin QA',
            'username' => 'admin.legajo',
            'password' => 'secreto',
            'is_admin' => true,
        ]));
        $this->get('/up'); // calentamiento (artefacto del kernel de testing)
    }

    private function alta(array $overrides = [])
    {
        // Código de sucursal único por invocación (codigo_sucursal es UNIQUE).
        $codigo = 'A' . substr(md5(uniqid('', true)), 0, 8);

        $sucursal = Sucursal::create(['nombre' => 'Suc QA', 'codigo_sucursal' => $codigo]);
        $localidad = Localidad::create([
            'id_provincia' => Provincia::create(['nombre' => 'Bs As ' . $codigo])->id,
            'nombre' => 'CABA ' . $codigo,
        ]);

        return $this->post(route('clientes.store', [], false), array_merge([
            'dni' => 28682461,
            'nombre' => 'Roberto',
            'id_sucursal' => $sucursal->id,
            'id_localidad' => $localidad->id,
            'direccion' => 'Pasaje Santa Cruz 455',
            'tel1' => '2804000005',
            'email' => 'titoyco@gmail.com',
            'medio_pago_preferido' => 0,
            'limite' => 800000,
            'max_cuotas' => 4,
        ], $overrides));
    }

    /**
     * Reproduce el error reportado: alta SIN completar `tipo_haber`
     * (columna NOT NULL DEFAULT 1 en el esquema `clientes`).
     */
    public function test_alta_sin_tipo_haber_no_rompe_por_integridad(): void
    {
        $this->actingAsAdmin();

        $this->alta()                       // tipo_haber ausente del POST
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'dni' => 28682461,
            'tipo_haber' => 1,               // se aplicó el default del esquema
            'saldo' => 0,
            'suspendido' => 0,
        ]);
    }

    public function test_alta_con_tipo_haber_vacio_usa_default(): void
    {
        $this->actingAsAdmin();

        $this->alta(['tipo_haber' => ''])
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', ['dni' => 28682461, 'tipo_haber' => 1]);
    }

    public function test_alta_con_tipo_haber_informado_se_respeta(): void
    {
        $this->actingAsAdmin();

        $this->alta(['tipo_haber' => 7])
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', ['dni' => 28682461, 'tipo_haber' => 7]);
    }

    public function test_tipo_haber_invalido_es_rechazado_con_mensaje(): void
    {
        $this->actingAsAdmin();

        $this->alta(['tipo_haber' => 'abc'])->assertSessionHasErrors('tipo_haber');
        $this->alta(['tipo_haber' => 0])->assertSessionHasErrors('tipo_haber');
    }

    /**
     * Los opcionales vacíos deben guardarse como NULL, nunca como cadena vacía.
     */
    public function test_campos_opcionales_vacios_se_guardan_como_null(): void
    {
        $this->actingAsAdmin();

        $this->alta([
            'tel2' => '', 'email_familiar' => '', 'familiar' => '',
            'trabajo' => '', 'obs' => '',
        ])->assertRedirect(route('clientes.index'));

        $cliente = \App\Models\Cliente::where('dni', 28682461)->first();

        $this->assertNull($cliente->tel2);
        $this->assertNull($cliente->email_familiar);
        $this->assertNull($cliente->familiar);
        $this->assertNull($cliente->trabajo);
        $this->assertNull($cliente->obs);
        $this->assertNull($cliente->cbu);
    }

    /**
     * `saldo` / `estado_banco` no forman parte del formulario: no deben ser mass-assignable.
     */
    public function test_no_se_puede_inyectar_saldo_ni_estado_banco(): void
    {
        $this->actingAsAdmin();

        $this->alta(['saldo' => 999999, 'estado_banco' => 'ACT', 'fecha_estado_banco' => '2030-01-01'])
            ->assertRedirect(route('clientes.index'));

        $cliente = \App\Models\Cliente::where('dni', 28682461)->first();

        $this->assertEquals(0, (float) $cliente->saldo, 'El saldo no debe ser inyectable por el formulario.');
        $this->assertNull($cliente->estado_banco);
        $this->assertNull($cliente->fecha_estado_banco);
    }

    public function test_cbu_obligatorio_cuando_el_medio_de_pago_es_debito(): void
    {
        $this->actingAsAdmin();

        $this->alta(['medio_pago_preferido' => 1])->assertSessionHasErrors('cbu');
    }

    public function test_edicion_legajo_tambien_normaliza_tipo_haber(): void
    {
        $this->actingAsAdmin();

        $this->alta()->assertRedirect(route('clientes.index'));
        $cliente = \App\Models\Cliente::where('dni', 28682461)->first();

        $payload = [
            'dni' => 28682461,
            'nombre' => 'Roberto Editado',
            'id_sucursal' => $cliente->id_sucursal,
            'id_localidad' => $cliente->id_localidad,
            'direccion' => 'Otra Calle 123',
            'tel1' => '2804000005',
            'medio_pago_preferido' => 0,
            'limite' => 500000,
            'max_cuotas' => 3,
            'tipo_haber' => '',   // vacío en la edición
        ];

        $this->put(route('clientes.update', $cliente->id, [], false), $payload)
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, $cliente->fresh()->tipo_haber);
        $this->assertSame('Roberto Editado', $cliente->fresh()->nombre);
    }

    public function test_buscar_cliente_retorna_json_correctamente(): void
    {
        $this->actingAsAdmin();
        $this->alta(['nombre' => 'JUAN BUSCADO', 'dni' => 12345678]);

        $response = $this->getJson(route('clientes.buscar', ['q' => 'BUSCADO']));
        $response->assertStatus(200);
        $response->assertJsonFragment(['nombre' => 'JUAN BUSCADO', 'dni' => 12345678]);
    }
}
