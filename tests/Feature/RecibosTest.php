<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Recibo;
use App\Models\Presentacion;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RecibosTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Sucursal $sucursal;
    protected Cliente $cliente;
    protected Credito $credito;
    protected Cuota $cuota1;
    protected Cuota $cuota2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\UsuariosSucursalesSeeder::class);
        $this->seed(\Database\Seeders\PresentacionSeeder::class);

        $this->admin = User::where('is_admin', true)->first();
        $this->sucursal = Sucursal::first();

        // Inicializar numeración
        $this->sucursal->update(['ultimo_recibo' => 26416]);

        $this->cliente = Cliente::create([
            'id_sucursal'  => $this->sucursal->id,
            'id_localidad' => 1,
            'nombre'       => 'TEST RECEPTOR COBRANZA',
            'dni'          => '33444555',
            'direccion'    => 'San Martin 123',
            'tel1'         => '2804123456',
            'limite'       => 200000,
            'saldo'        => 20000.00,
        ]);

        $aut = \App\Models\ClienteAutorizado::create([
            'id_titular'    => $this->cliente->id,
            'id_autorizado' => $this->cliente->id,
            'relacion_aut'  => 'Titular',
        ]);

        $presentacion = Presentacion::first();

        $this->credito = Credito::create([
            'id_cliente'        => $this->cliente->id,
            'id_sucursal'       => $this->sucursal->id,
            'id_autorizado'     => $aut->id,
            'id_presentacion'   => $presentacion?->id ?? 1,
            'credito'           => '5001',
            'importe_original'  => 20000.00,
            'total_credito'     => 20000.00,
            'cantidad_cuotas'   => 2,
            'valor_cuota_ref'   => 10000.00,
            'fecha_credito'     => now()->subDays(10),
            'tipo_credito'      => 'C',
            'tipo_terminal'     => 'T',
            'terminal'          => 'T',
            'id_venta_orig'     => 9999,
        ]);

        $this->cuota1 = Cuota::create([
            'id_credito'   => $this->credito->id,
            'num_cuota'    => 1,
            'valor_base'   => 10000.00,
            'intereses'    => 0,
            'entrego'      => 0,
            'saldo_actual' => 10000.00,
            'fecha_vencimiento' => now()->addDays(20),
        ]);

        $this->cuota2 = Cuota::create([
            'id_credito'   => $this->credito->id,
            'num_cuota'    => 2,
            'valor_base'   => 10000.00,
            'intereses'    => 0,
            'entrego'      => 0,
            'saldo_actual' => 10000.00,
            'fecha_vencimiento' => now()->addDays(50),
        ]);
    }

    public function test_index_recibos_carga_correctamente(): void
    {
        $response = $this->actingAs($this->admin)->get(route('recibos.index'));
        $response->assertStatus(200);
        $response->assertSee('Recibos de Cobranza');
    }

    public function test_create_recibos_con_cliente_muestra_cuotas(): void
    {
        $response = $this->actingAs($this->admin)->get(route('recibos.create', ['cliente_id' => $this->cliente->id]));
        $response->assertStatus(200);
        $response->assertSee('TEST RECEPTOR COBRANZA');
        $response->assertSee('Cuotas Exigibles');
    }

    public function test_emitir_recibo_parcial_actualiza_cuota_y_saldo(): void
    {
        $payload = [
            'cliente_id'    => $this->cliente->id,
            'sucursal_id'   => $this->sucursal->id,
            'medio_pago'    => 'Efectivo',
            'fecha'         => now()->format('Y-m-d'),
            'observaciones' => 'Cobro prueba parcial',
            'items'         => [
                [
                    'cuota_id' => $this->cuota1->id,
                    'monto'    => 6000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('recibos.store'), $payload);
        $response->assertRedirect();

        // Validar creación de Recibo
        $this->assertDatabaseHas('recibos', [
            'id_cliente'    => $this->cliente->id,
            'numero_recibo' => 26417,
            'importe_total' => 6000.00,
            'anulado'       => false,
        ]);

        // Validar amortización de Cuota
        $this->cuota1->refresh();
        $this->assertEquals(6000.00, (float)$this->cuota1->entrego);
        $this->assertEquals(4000.00, (float)$this->cuota1->saldo_actual);

        // Validar saldo del cliente (20.000 - 6.000 = 14.000)
        $this->cliente->refresh();
        $this->assertEquals(14000.00, (float)$this->cliente->saldo);

        // El crédito no debe estar cancelado todavía
        $this->credito->refresh();
        $this->assertNull($this->credito->fecha_cancelacion);
    }

    public function test_emitir_recibo_total_cancela_credito_automaticamente(): void
    {
        $payload = [
            'cliente_id'    => $this->cliente->id,
            'sucursal_id'   => $this->sucursal->id,
            'medio_pago'    => 'Transferencia Bancaria',
            'fecha'         => now()->format('Y-m-d'),
            'observaciones' => 'Cancelación total',
            'items'         => [
                [
                    'cuota_id' => $this->cuota1->id,
                    'monto'    => 10000.00,
                ],
                [
                    'cuota_id' => $this->cuota2->id,
                    'monto'    => 10000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('recibos.store'), $payload);
        $response->assertRedirect();

        $this->cuota1->refresh();
        $this->cuota2->refresh();
        $this->assertEquals(0.00, (float)$this->cuota1->saldo_actual);
        $this->assertEquals(0.00, (float)$this->cuota2->saldo_actual);

        // Crédito cancelado automáticamente
        $this->credito->refresh();
        $this->assertNotNull($this->credito->fecha_cancelacion);

        // Saldo cliente a 0
        $this->cliente->refresh();
        $this->assertEquals(0.00, (float)$this->cliente->saldo);
    }

    public function test_anulacion_de_recibo_revierte_cuotas_y_reabre_credito(): void
    {
        // 1. Emitir recibo total
        $payload = [
            'cliente_id'    => $this->cliente->id,
            'sucursal_id'   => $this->sucursal->id,
            'medio_pago'    => 'Efectivo',
            'fecha'         => now()->format('Y-m-d'),
            'items'         => [
                ['cuota_id' => $this->cuota1->id, 'monto' => 10000.00],
                ['cuota_id' => $this->cuota2->id, 'monto' => 10000.00],
            ],
        ];

        $this->actingAs($this->admin)->post(route('recibos.store'), $payload);

        $recibo = Recibo::latest('id')->first();
        $this->assertNotNull($recibo);
        $this->assertFalse((bool)$recibo->anulado);

        $this->credito->refresh();
        $this->assertNotNull($this->credito->fecha_cancelacion);

        // 2. Anular el recibo
        $response = $this->actingAs($this->admin)->post(route('recibos.anular', $recibo->id), [
            'motivo' => 'Error de cajero, pago no acreditado',
        ]);

        $response->assertRedirect();

        $recibo->refresh();
        $this->assertTrue((bool)$recibo->anulado);
        $this->assertEquals('Error de cajero, pago no acreditado', $recibo->motivo_anulacion);

        // 3. Verificar que las cuotas vuelvan a su estado original
        $this->cuota1->refresh();
        $this->cuota2->refresh();
        $this->assertEquals(10000.00, (float)$this->cuota1->saldo_actual);
        $this->assertEquals(0.00, (float)$this->cuota1->entrego);
        $this->assertEquals(10000.00, (float)$this->cuota2->saldo_actual);
        $this->assertEquals(0.00, (float)$this->cuota2->entrego);

        // 4. Crédito reabierto
        $this->credito->refresh();
        $this->assertNull($this->credito->fecha_cancelacion);

        // 5. Saldo del cliente restituido a 20.000
        $this->cliente->refresh();
        $this->assertEquals(20000.00, (float)$this->cliente->saldo);
    }

    public function test_emitir_recibo_con_excedente_genera_saldo_a_favor(): void
    {
        // Entrega de 15.000 para cuota 1 de 10.000 y remanente de 5.000 a Saldo a Favor
        $payload = [
            'cliente_id'    => $this->cliente->id,
            'sucursal_id'   => $this->sucursal->id,
            'medio_pago'    => 'Efectivo',
            'fecha'         => now()->format('Y-m-d'),
            'observaciones' => 'Entrega con excedente a favor',
            'items'         => [
                [
                    'cuota_id' => $this->cuota1->id,
                    'monto'    => 10000.00,
                ],
            ],
            'monto_a_favor' => 5000.00,
        ];

        $response = $this->actingAs($this->admin)->post(route('recibos.store'), $payload);
        $response->assertRedirect();

        // Verificar recibo con saldo_a_favor_generado
        $recibo = Recibo::latest('id')->first();
        $this->assertEquals(15000.00, (float)$recibo->importe_total);
        $this->assertEquals(5000.00, (float)$recibo->saldo_a_favor_generado);

        // Cuota 1 saldada
        $this->cuota1->refresh();
        $this->assertEquals(0.00, (float)$this->cuota1->saldo_actual);
        $this->assertEquals(10000.00, (float)$this->cuota1->entrego);

        // Cliente tiene saldo a favor de 5.000 y deuda pendiente de 10.000 (de la cuota 2)
        $this->cliente->refresh();
        $this->assertEquals(5000.00, (float)$this->cliente->saldo_a_favor);
        $this->assertEquals(10000.00, (float)$this->cliente->saldo);

        // Verificar renglón de Saldo a Favor en recibo_detalles
        $this->assertDatabaseHas('recibo_detalles', [
            'id_recibo'   => $recibo->id,
            'id_cuota'    => null,
            'id_credito'  => null,
            'importe'     => 5000.00,
        ]);
    }

    public function test_anulacion_recibo_con_saldo_a_favor_revierte_excedente_en_cliente(): void
    {
        // 1. Emitir recibo con saldo a favor
        $payload = [
            'cliente_id'    => $this->cliente->id,
            'sucursal_id'   => $this->sucursal->id,
            'medio_pago'    => 'Efectivo',
            'fecha'         => now()->format('Y-m-d'),
            'items'         => [
                ['cuota_id' => $this->cuota1->id, 'monto' => 10000.00],
            ],
            'monto_a_favor' => 8000.00,
        ];

        $this->actingAs($this->admin)->post(route('recibos.store'), $payload);

        $this->cliente->refresh();
        $this->assertEquals(8000.00, (float)$this->cliente->saldo_a_favor);

        $recibo = Recibo::latest('id')->first();

        // 2. Anular recibo
        $response = $this->actingAs($this->admin)->post(route('recibos.anular', $recibo->id), [
            'motivo' => 'Anulación por error en cobro de excedente',
        ]);

        $response->assertRedirect();

        // 3. Saldo a favor revertido a 0 y cuota reabierta
        $this->cliente->refresh();
        $this->assertEquals(0.00, (float)$this->cliente->saldo_a_favor);
        $this->assertEquals(20000.00, (float)$this->cliente->saldo);

        $this->cuota1->refresh();
        $this->assertEquals(10000.00, (float)$this->cuota1->saldo_actual);
        $this->assertEquals(0.00, (float)$this->cuota1->entrego);
    }
}
