<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Sucursal;
use App\Models\Presentacion;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreditoIdentificadorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Sucursal $sucTrelew;
    protected Sucursal $sucInterior;
    protected Sucursal $sucUshuaia;
    protected Cliente $cliente;
    protected Presentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\UsuariosSucursalesSeeder::class);
        $this->seed(\Database\Seeders\PresentacionSeeder::class);

        $this->user = User::where('is_admin', true)->first() ?? User::factory()->create(['is_admin' => 1]);
        $this->sucTrelew = Sucursal::where('codigo_sucursal', 'TW')->firstOrFail();
        $this->sucInterior = Sucursal::where('codigo_sucursal', 'IN')->firstOrFail();
        $this->sucUshuaia = Sucursal::where('codigo_sucursal', 'US')->firstOrFail();

        $this->cliente = Cliente::create([
            'id_sucursal'  => $this->sucTrelew->id,
            'id_localidad' => 1,
            'nombre'       => 'Cliente Prueba Numeración',
            'dni'          => 99887766,
            'direccion'    => 'Mitre 500',
            'tel1'         => '2804112233',
            'saldo'        => 0,
            'limite'       => 500000,
        ]);

        $this->presentacion = Presentacion::firstOrFail();
    }

    public function test_formato_oficial_identificador_credito(): void
    {
        // 1. Crédito regular en Trelew -> C0001234TW
        $cTrelew = new Credito([
            'credito'      => '1234',
            'tipo_credito' => 'C',
            'id_sucursal'  => $this->sucTrelew->id,
        ]);
        $cTrelew->setRelation('sucursal', $this->sucTrelew);
        $this->assertSame('C0001234TW', $cTrelew->full_identificador);

        // 2. Crédito regular en Interior / Camión -> C0001234IN
        $cInterior = new Credito([
            'credito'      => '1234',
            'tipo_credito' => 'C',
            'id_sucursal'  => $this->sucInterior->id,
        ]);
        $cInterior->setRelation('sucursal', $this->sucInterior);
        $this->assertSame('C0001234IN', $cInterior->full_identificador);

        // 3. Refinanciación en Trelew -> R0001234TW
        $cRef = new Credito([
            'credito'      => '1234',
            'tipo_credito' => 'R',
            'id_sucursal'  => $this->sucTrelew->id,
        ]);
        $cRef->setRelation('sucursal', $this->sucTrelew);
        $this->assertSame('R0001234TW', $cRef->full_identificador);

        // 4. Débito en Trelew -> D0001234TW
        $cDeb = new Credito([
            'credito'      => '1234',
            'tipo_credito' => 'D',
            'id_sucursal'  => $this->sucTrelew->id,
        ]);
        $cDeb->setRelation('sucursal', $this->sucTrelew);
        $this->assertSame('D0001234TW', $cDeb->full_identificador);

        // 5. Crédito regular en Ushuaia -> C0001234US
        $cUshuaia = new Credito([
            'credito'      => '1234',
            'tipo_credito' => 'C',
            'id_sucursal'  => $this->sucUshuaia->id,
        ]);
        $cUshuaia->setRelation('sucursal', $this->sucUshuaia);
        $this->assertSame('C0001234US', $cUshuaia->full_identificador);
    }

    public function test_busqueda_de_credito_por_identificador_compuesto(): void
    {
        $aut = \App\Models\ClienteAutorizado::create([
            'id_titular'    => $this->cliente->id,
            'id_autorizado' => $this->cliente->id,
            'relacion_aut'  => 'Titular',
        ]);

        $cred = Credito::create([
            'id_cliente'        => $this->cliente->id,
            'id_autorizado'     => $aut->id_autorizado,
            'id_sucursal'       => $this->sucTrelew->id,
            'id_presentacion'   => $this->presentacion->id,
            'credito'           => '887711',
            'tipo_credito'      => 'C',
            'importe_original'  => 50000,
            'cantidad_cuotas'   => 2,
            'valor_cuota_ref'   => 25000,
            'fecha_credito'     => now()->toDateString(),
        ]);

        $this->actingAs($this->user);

        // Búsqueda usando el código compuesto completo "C0887711TW"
        $response = $this->get(route('creditos.index', ['search' => 'C0887711TW', 'sucursal' => 'all']));
        $response->assertStatus(200);
        $response->assertSee('C0887711TW');
    }

    public function test_cuota_progreso_segun_presentacion_origen_y_actual(): void
    {
        Presentacion::clearActiveNumbersCache();

        // Creamos presentación de origen #100
        $presOrigen = Presentacion::create([
            'id_provincia'    => 1,
            'numero'          => 100,
            'fecha_inicio'    => '2026-01-01',
            'fecha_fin'       => '2026-01-31',
            'interes_credito' => 0.06,
            'interes_mora'    => 0.06,
        ]);

        // Presentación activa en #102
        $presActiva102 = Presentacion::create([
            'id_provincia'    => 1,
            'numero'          => 102,
            'fecha_inicio'    => '2026-03-01',
            'fecha_fin'       => null,
            'interes_credito' => 0.06,
            'interes_mora'    => 0.06,
        ]);

        $credito = new Credito([
            'credito'          => '555',
            'tipo_credito'     => 'C',
            'cantidad_cuotas'  => 4,
            'id_sucursal'      => $this->sucTrelew->id,
            'id_presentacion'  => $presOrigen->id,
        ]);
        $credito->setRelation('presentacion', $presOrigen);
        $credito->setRelation('sucursal', $this->sucTrelew);

        // Caso 1: Origen 100, Activa 102, 4 cuotas => "2 / 4"
        Presentacion::clearActiveNumbersCache();
        $this->assertSame('2 / 4', $credito->cuota_progreso);

        // Caso 2: Origen 100, Activa 110, 4 cuotas => "4 / 4" (tope)
        $presActiva102->update(['fecha_fin' => '2026-03-31']);
        Presentacion::create([
            'id_provincia'    => 1,
            'numero'          => 110,
            'fecha_inicio'    => '2026-11-01',
            'fecha_fin'       => null,
            'interes_credito' => 0.06,
            'interes_mora'    => 0.06,
        ]);
        Presentacion::clearActiveNumbersCache();
        $this->assertSame('4 / 4', $credito->cuota_progreso);
    }

    public function test_resumen_y_columna_cuotas_en_listado_de_creditos(): void
    {
        $aut = \App\Models\ClienteAutorizado::firstOrCreate([
            'id_titular'    => $this->cliente->id,
            'id_autorizado' => $this->cliente->id,
        ], [
            'relacion_aut'  => 'Titular',
        ]);

        $cred = Credito::create([
            'id_cliente'        => $this->cliente->id,
            'id_autorizado'     => $aut->id_autorizado,
            'id_sucursal'       => $this->sucTrelew->id,
            'id_presentacion'   => $this->presentacion->id,
            'credito'           => '991122',
            'tipo_credito'      => 'C',
            'importe_original'  => 30000,
            'cantidad_cuotas'   => 3,
            'valor_cuota_ref'   => 10000,
            'fecha_credito'     => now()->toDateString(),
        ]);

        \App\Models\Cuota::create([
            'id_credito'   => $cred->id,
            'num_cuota'    => 1,
            'valor_base'   => 10000,
            'intereses'    => 0,
            'entrego'      => 0,
            'saldo_actual' => 10000,
        ]);

        $this->actingAs($this->user);

        $response = $this->get(route('creditos.index', ['search' => '991122', 'sucursal' => 'all']));
        $response->assertStatus(200);
        $response->assertSee('Cantidad de Créditos');
        $response->assertSee('Total a Pagar');
        $response->assertSee('Cuota');
        $response->assertSee('$ 10.000,00');

        // Verificar que en página 2 el Total a Pagar siga siendo el total de la consulta completa
        $responsePage2 = $this->get(route('creditos.index', ['search' => '991122', 'sucursal' => 'all', 'page' => 2]));
        $responsePage2->assertStatus(200);
        $responsePage2->assertSee('$ 10.000,00');

        // Verificar descarga / exportación a Excel (CSV con cabeceras y delimitador)
        $exportResponse = $this->get(route('creditos.index', ['export' => 'excel', 'search' => '991122', 'sucursal' => 'all']));
        $exportResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $exportResponse->headers->get('content-type'));
    }

    public function test_ordenamiento_columnas_y_valor_cuota(): void
    {
        $aut = \App\Models\ClienteAutorizado::firstOrCreate([
            'id_titular'    => $this->cliente->id,
            'id_autorizado' => $this->cliente->id,
        ], [
            'relacion_aut'  => 'Titular',
        ]);

        // Crear 2 créditos con distintos valores
        $c1 = Credito::create([
            'id_cliente'        => $this->cliente->id,
            'id_autorizado'     => $aut->id_autorizado,
            'id_sucursal'       => $this->sucTrelew->id,
            'id_presentacion'   => $this->presentacion->id,
            'credito'           => '880001',
            'tipo_credito'      => 'C',
            'importe_original'  => 15000,
            'cantidad_cuotas'   => 3,
            'valor_cuota_ref'   => 5000,
            'fecha_credito'     => now()->subDays(5)->toDateString(),
        ]);
        \App\Models\Cuota::create([
            'id_credito'   => $c1->id,
            'num_cuota'    => 1,
            'valor_base'   => 15000,
            'intereses'    => 0,
            'entrego'      => 0,
            'saldo_actual' => 15000,
        ]);

        $c2 = Credito::create([
            'id_cliente'        => $this->cliente->id,
            'id_autorizado'     => $aut->id_autorizado,
            'id_sucursal'       => $this->sucTrelew->id,
            'id_presentacion'   => $this->presentacion->id,
            'credito'           => '880002',
            'tipo_credito'      => 'C',
            'importe_original'  => 90000,
            'cantidad_cuotas'   => 3,
            'valor_cuota_ref'   => 30000,
            'fecha_credito'     => now()->subDays(1)->toDateString(),
        ]);
        \App\Models\Cuota::create([
            'id_credito'   => $c2->id,
            'num_cuota'    => 1,
            'valor_base'   => 90000,
            'intereses'    => 0,
            'entrego'      => 0,
            'saldo_actual' => 90000,
        ]);

        $this->actingAs($this->user);

        // 1. Verificación de encabezados Valor Cuota y Total
        $response = $this->get(route('creditos.index', ['sort' => 'valor_cuota', 'direction' => 'desc', 'sucursal' => 'all']));
        $response->assertStatus(200);
        $response->assertSee('Valor Cuota');
        $response->assertSee('Total');
        $response->assertSee('$ 30.000,00');
        $response->assertSee('$ 5.000,00');

        // 2. Comprobar que en orden desc de valor_cuota, c2 aparece antes que c1
        $content = $response->getContent();
        $posC2 = strpos($content, 'C0880002TW');
        $posC1 = strpos($content, 'C0880001TW');
        $this->assertTrue($posC2 !== false && $posC1 !== false);
        $this->assertLessThan($posC1, $posC2, 'C0880002TW debe aparecer antes que C0880001TW en orden desc por valor cuota');

        // 3. Comprobar orden asc de valor_cuota
        $responseAsc = $this->get(route('creditos.index', ['sort' => 'valor_cuota', 'direction' => 'asc', 'sucursal' => 'all']));
        $responseAsc->assertStatus(200);
        $contentAsc = $responseAsc->getContent();
        $posC1Asc = strpos($contentAsc, 'C0880001TW');
        $posC2Asc = strpos($contentAsc, 'C0880002TW');
        $this->assertLessThan($posC2Asc, $posC1Asc, 'C0880001TW debe aparecer antes que C0880002TW en orden asc por valor cuota');

        // 4. Comprobar orden por total
        $responseTotal = $this->get(route('creditos.index', ['sort' => 'total', 'direction' => 'asc', 'sucursal' => 'all']));
        $responseTotal->assertStatus(200);

        // 5. Comprobar orden por a_pagar
        $responseAPagar = $this->get(route('creditos.index', ['sort' => 'a_pagar', 'direction' => 'desc', 'sucursal' => 'all']));
        $responseAPagar->assertStatus(200);
    }

    public function test_lotes_ushuaia_generacion_y_cuota_progreso(): void
    {
        Presentacion::clearActiveNumbersCache();

        // Lote 1 de Ushuaia: fecha MDB 28/02/2009 -> Cierre (-7 días) = 21/02/2009
        $lote1 = Presentacion::create([
            'id_provincia'    => 2,
            'numero'          => 1,
            'fecha_inicio'    => '2009-01-22',
            'fecha_fin'       => '2009-02-21',
            'interes_credito' => 0.00,
            'interes_mora'    => 0.00,
        ]);

        // Lote 2 de Ushuaia: fecha MDB 31/03/2009 -> Cierre (-7 días) = 24/03/2009
        $lote2 = Presentacion::create([
            'id_provincia'    => 2,
            'numero'          => 2,
            'fecha_inicio'    => '2009-02-22',
            'fecha_fin'       => '2009-03-24',
            'interes_credito' => 0.00,
            'interes_mora'    => 0.00,
        ]);

        // Lote 209 (Agosto 2026)
        $lote209 = Presentacion::create([
            'id_provincia'    => 2,
            'numero'          => 209,
            'fecha_inicio'    => '2026-07-25',
            'fecha_fin'       => '2026-08-24',
            'interes_credito' => 0.00,
            'interes_mora'    => 0.00,
        ]);

        // Lote 211 (Octubre 2026) -> Inició 24/09/2026 y cerró el 24/10/2026
        $lote211 = Presentacion::create([
            'id_provincia'    => 2,
            'numero'          => 211,
            'fecha_inicio'    => '2026-09-24',
            'fecha_fin'       => '2026-10-24',
            'interes_credito' => 0.00,
            'interes_mora'    => 0.00,
        ]);

        // Lote 212 (Noviembre 2026) -> Inició 25/10/2026, activo y actualmente abierto (fecha_fin = null)
        $lote212 = Presentacion::create([
            'id_provincia'    => 2,
            'numero'          => 212,
            'fecha_inicio'    => '2026-10-25',
            'fecha_fin'       => null,
            'interes_credito' => 0.00,
            'interes_mora'    => 0.00,
        ]);

        $this->assertEquals('2009-02-21', $lote1->fecha_fin);
        $this->assertEquals('2009-03-24', $lote2->fecha_fin);
        $this->assertEquals('2026-10-24', $lote211->fecha_fin);
        $this->assertNull($lote212->fecha_fin);
        $this->assertEquals(212, Presentacion::getNumeroActivaParaProvincia(2));

        // Crédito originado en Lote 212 (abierto): diferencia = 212 - 212 = 0 -> Cuota 0 / 3
        $creditoLote212 = new Credito([
            'id_sucursal'      => $this->sucUshuaia->id,
            'id_presentacion'  => $lote212->id,
            'cantidad_cuotas'  => 3,
            'valor_cuota_ref'  => 50000,
        ]);
        $creditoLote212->setRelation('presentacion', $lote212);
        $creditoLote212->setRelation('sucursal', $this->sucUshuaia);
        $this->assertSame('0 / 3', $creditoLote212->cuota_progreso);

        // Crédito originado en Lote 211 (cerrado el 24/10): diferencia = 212 - 211 = 1 -> Cuota 1 / 3
        $creditoLote211 = new Credito([
            'id_sucursal'      => $this->sucUshuaia->id,
            'id_presentacion'  => $lote211->id,
            'cantidad_cuotas'  => 3,
            'valor_cuota_ref'  => 50000,
        ]);
        $creditoLote211->setRelation('presentacion', $lote211);
        $creditoLote211->setRelation('sucursal', $this->sucUshuaia);
        $this->assertSame('1 / 3', $creditoLote211->cuota_progreso);

        // Crédito originado en Lote 1 con 3 cuotas: diferencia = 212 - 1 = 211 >= 3 -> Cuota 3 / 3 (tope)
        $creditoLote1 = new Credito([
            'id_sucursal'      => $this->sucUshuaia->id,
            'id_presentacion'  => $lote1->id,
            'cantidad_cuotas'  => 3,
            'valor_cuota_ref'  => 1000,
        ]);
        $creditoLote1->setRelation('presentacion', $lote1);
        $creditoLote1->setRelation('sucursal', $this->sucUshuaia);
        $this->assertSame('3 / 3', $creditoLote1->cuota_progreso);
    }
}


