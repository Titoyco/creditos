<?php

namespace Tests\Feature;

use App\Models\MedioPago;
use App\Models\Recibo;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedioPagoAbmTest extends TestCase
{
    use RefreshDatabase;

    private function r(string $name, mixed $params = []): string
    {
        return route($name, $params, false);
    }

    private function actingAsAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin QA',
            'username' => 'admin.qa',
            'password' => 'secreto',
            'is_admin' => true,
        ]);

        $this->actingAs($user);
        $this->get('/up'); // Calentamiento del kernel de testing

        return $user;
    }

    private function crearSucursal(string $nombre = 'Sucursal Central', string $codigo = 'C'): Sucursal
    {
        return Sucursal::create([
            'nombre' => $nombre,
            'codigo_sucursal' => $codigo,
        ]);
    }

    public function test_invitados_no_pueden_acceder_al_abm_de_medios_de_pago(): void
    {
        $this->get('/up');
        $res = $this->get($this->r('sistema.medios-pago.index'));
        $res->assertRedirect();
    }

    public function test_admin_puede_listar_medios_de_pago(): void
    {
        $this->actingAsAdmin();

        $res = $this->get($this->r('sistema.medios-pago.index'));
        $res->assertOk();
        $res->assertSee('Medios de Pago');
        $res->assertSee('Efectivo');
        $res->assertSee('Tarjeta Débito');
    }

    public function test_admin_puede_crear_nuevo_medio_de_pago(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'nombre' => 'Mercado Pago QR',
            'codigo' => 'mercado_pago_qr',
            'tipo_base' => 'transferencia',
            'recargo_porcentaje' => 0,
            'descuento_porcentaje' => 5,
            'requiere_referencia' => 1,
            'permite_vuelto' => 0,
            'icono' => '📱',
            'color' => 'cyan',
            'orden' => 10,
            'activo' => 1,
        ];

        $res = $this->post($this->r('sistema.medios-pago.store'), $payload);
        $res->assertRedirect($this->r('sistema.medios-pago.index'));
        $res->assertSessionHas('success');

        $this->assertDatabaseHas('medios_pago', [
            'codigo' => 'mercado_pago_qr',
            'nombre' => 'Mercado Pago QR',
            'tipo_base' => 'transferencia',
            'descuento_porcentaje' => 5,
        ]);
    }

    public function test_admin_puede_editar_medio_de_pago(): void
    {
        $this->actingAsAdmin();

        $mp = MedioPago::create([
            'nombre' => 'Cheque Propio',
            'codigo' => 'cheque_propio',
            'tipo_base' => 'cheque',
            'recargo_porcentaje' => 2.5,
            'descuento_porcentaje' => 0,
            'requiere_referencia' => true,
            'permite_vuelto' => false,
            'icono' => '📜',
            'color' => 'amber',
            'orden' => 20,
            'activo' => true,
        ]);

        $updatePayload = [
            'nombre' => 'Cheque Diferido 30 Días',
            'codigo' => 'cheque_diferido_30',
            'tipo_base' => 'cheque',
            'recargo_porcentaje' => 4.5,
            'descuento_porcentaje' => 0,
            'requiere_referencia' => 1,
            'permite_vuelto' => 0,
            'icono' => '📜',
            'color' => 'amber',
            'orden' => 25,
            'activo' => 1,
        ];

        $res = $this->put($this->r('sistema.medios-pago.update', $mp->id), $updatePayload);
        $res->assertRedirect($this->r('sistema.medios-pago.index'));

        $this->assertDatabaseHas('medios_pago', [
            'id' => $mp->id,
            'nombre' => 'Cheque Diferido 30 Días',
            'recargo_porcentaje' => 4.5,
        ]);
    }

    public function test_admin_puede_alternar_estado_activo(): void
    {
        $this->actingAsAdmin();

        $mp = MedioPago::create([
            'nombre' => 'Pago Fácil',
            'codigo' => 'pago_facil',
            'tipo_base' => 'otros',
            'activo' => true,
        ]);

        // Toggle a falso
        $res = $this->patch($this->r('sistema.medios-pago.toggle-activo', $mp->id));
        $res->assertRedirect();
        $this->assertFalse((bool) $mp->fresh()->activo);

        // Toggle de vuelta a verdadero
        $this->patch($this->r('sistema.medios-pago.toggle-activo', $mp->id));
        $this->assertTrue((bool) $mp->fresh()->activo);
    }

    public function test_permite_eliminar_medio_de_pago_sin_movimientos_asociados(): void
    {
        $this->actingAsAdmin();

        $mp = MedioPago::create([
            'nombre' => 'Medio Efimero',
            'codigo' => 'medio_efimero',
            'tipo_base' => 'otros',
            'activo' => true,
        ]);

        $this->assertDatabaseHas('medios_pago', ['id' => $mp->id]);

        $res = $this->delete($this->r('sistema.medios-pago.destroy', $mp->id));
        $res->assertRedirect($this->r('sistema.medios-pago.index'));
        $res->assertSessionHas('success');

        $this->assertDatabaseMissing('medios_pago', ['id' => $mp->id]);
    }

    public function test_bloquea_eliminacion_si_tiene_ventas_asociadas(): void
    {
        $admin = $this->actingAsAdmin();
        $sucursal = $this->crearSucursal();

        $mp = MedioPago::create([
            'nombre' => 'Tarjeta Naranja',
            'codigo' => 'tarjeta_naranja',
            'tipo_base' => 'tarjeta_credito',
            'activo' => true,
        ]);

        // Asociar una venta al medio de pago
        Venta::create([
            'id_sucursal' => $sucursal->id,
            'id_usuario' => $admin->id,
            'numero_factura' => 1,
            'comprobante' => '0001-00000001',
            'tipo_comprobante' => 'FACTURA_B',
            'fecha' => now(),
            'medio_pago' => 'tarjeta_naranja',
            'id_medio_pago' => $mp->id,
            'subtotal' => 1500,
            'recargo' => 0,
            'descuento' => 0,
            'total' => 1500,
            'estado' => 'completada',
        ]);

        $this->assertTrue($mp->fresh()->tieneMovimientos());

        // Intentar eliminar
        $res = $this->delete($this->r('sistema.medios-pago.destroy', $mp->id));
        $res->assertRedirect($this->r('sistema.medios-pago.index'));
        $res->assertSessionHas('error');

        // Verificar que NO fue eliminado
        $this->assertDatabaseHas('medios_pago', ['id' => $mp->id]);
    }

    public function test_bloquea_eliminacion_si_tiene_recibos_asociados(): void
    {
        $admin = $this->actingAsAdmin();
        $sucursal = $this->crearSucursal();

        $provincia = \App\Models\Provincia::firstOrCreate(['id' => 1], ['nombre' => 'Tierra del Fuego']);
        $localidad = \App\Models\Localidad::firstOrCreate(['id' => 1], ['nombre' => 'Ushuaia', 'id_provincia' => $provincia->id, 'codigo_postal' => '9410']);

        $cliente = \App\Models\Cliente::create([
            'dni' => 12345678,
            'nombre' => 'Cliente Test',
            'id_sucursal' => $sucursal->id,
            'medio_pago_preferido' => 0,
            'limite' => 1000,
            'max_cuotas' => 3,
            'direccion' => 'Calle 1',
            'id_localidad' => $localidad->id,
            'tel1' => '11223344',
        ]);

        $mp = MedioPago::create([
            'nombre' => 'Transferencia Galicia',
            'codigo' => 'transferencia_galicia',
            'tipo_base' => 'transferencia',
            'activo' => true,
        ]);

        // Asociar un recibo al medio de pago
        Recibo::create([
            'id_sucursal' => $sucursal->id,
            'id_cliente' => $cliente->id,
            'id_user' => $admin->id,
            'numero_recibo' => 999,
            'fecha' => now(),
            'importe_total' => 3000,
            'medio_pago' => 'Transferencia Galicia',
            'id_medio_pago' => $mp->id,
            'anulado' => false,
        ]);

        $this->assertTrue($mp->fresh()->tieneMovimientos());

        // Intentar eliminar
        $res = $this->delete($this->r('sistema.medios-pago.destroy', $mp->id));
        $res->assertRedirect($this->r('sistema.medios-pago.index'));
        $res->assertSessionHas('error');

        // Debe seguir existiendo en la base de datos
        $this->assertDatabaseHas('medios_pago', ['id' => $mp->id]);
    }

    public function test_bloquea_eliminacion_si_tiene_cuotas_asociadas(): void
    {
        $this->actingAsAdmin();
        $sucursal = $this->crearSucursal();

        $provincia = \App\Models\Provincia::firstOrCreate(['id' => 1], ['nombre' => 'Tierra del Fuego']);
        $localidad = \App\Models\Localidad::firstOrCreate(['id' => 1], ['nombre' => 'Ushuaia', 'id_provincia' => $provincia->id, 'codigo_postal' => '9410']);
        $presentacion = \App\Models\Presentacion::create([
            'id_provincia' => $provincia->id,
            'numero' => 1,
            'fecha_inicio' => now()->subMonth(),
            'fecha_fin' => now(),
            'interes_credito' => 0,
            'interes_mora' => 0,
        ]);

        $cliente = \App\Models\Cliente::create([
            'dni' => 87654321,
            'nombre' => 'Cliente Cuota',
            'id_sucursal' => $sucursal->id,
            'medio_pago_preferido' => 0,
            'limite' => 1000,
            'max_cuotas' => 3,
            'direccion' => 'Calle 2',
            'id_localidad' => $localidad->id,
            'tel1' => '11223344',
        ]);

        $credito = \App\Models\Credito::create([
            'id_cliente'        => $cliente->id,
            'id_sucursal'       => $sucursal->id,
            'id_presentacion'   => $presentacion->id,
            'credito'           => '8001',
            'importe_original'  => 10000.00,
            'total_credito'     => 10000.00,
            'cantidad_cuotas'   => 1,
            'valor_cuota_ref'   => 10000.00,
            'fecha_credito'     => now(),
            'tipo_credito'      => 'C',
            'tipo_terminal'     => 'T',
            'terminal'          => 'T',
            'id_venta_orig'     => 9999,
        ]);

        $mp = MedioPago::create([
            'nombre' => 'Débito Automático Banco Macro',
            'codigo' => 'debito_macro',
            'tipo_base' => 'banco',
            'activo' => true,
        ]);

        \App\Models\Cuota::create([
            'id_credito'    => $credito->id,
            'num_cuota'     => 1,
            'valor_base'    => 10000.00,
            'intereses'     => 0,
            'entrego'       => 10000.00,
            'saldo_actual'  => 0,
            'fecha_cobro'   => now(),
            'id_medio_pago' => $mp->id,
            'medio_pago'    => 'banco',
        ]);

        $this->assertTrue($mp->fresh()->tieneMovimientos());

        // Intentar eliminar
        $res = $this->delete($this->r('sistema.medios-pago.destroy', $mp->id));
        $res->assertRedirect($this->r('sistema.medios-pago.index'));
        $res->assertSessionHas('error');

        // Debe seguir existiendo en la base de datos
        $this->assertDatabaseHas('medios_pago', ['id' => $mp->id]);
    }
}
