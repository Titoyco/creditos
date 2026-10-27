<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Exception;

class ImportTrelewDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-trelew-data 
                            {--source=lamarina : Nombre de la base de datos de origen en MySQL local}
                            {--limit= : Limitar registros para pruebas rápidas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa clientes, créditos (T e I), cuotas y recibos históricos desde la base lamarina hacia lamarina_v4';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $startTime = microtime(true);
        $sourceDb = $this->option('source') ?: 'lamarina';
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info("==================================================================");
        $this->info("   MIGRACIÓN INTEGRAL TRELEW / INTERIOR: {$sourceDb} -> lamarina_v4   ");
        $this->info("==================================================================");

        // 1. Conexión a la base de origen (lamarina)
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username', 'root');
        $pass = config('database.connections.mysql.password', '');

        try {
            $sourcePdo = new PDO("mysql:host={$host};port={$port};dbname={$sourceDb};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
        } catch (Exception $e) {
            $this->error("Error conectando a la base '{$sourceDb}': " . $e->getMessage());
            return 1;
        }

        // 2. Asegurar presentaciones de Chubut (1 a 106)
        $this->info("Verificando presentaciones de Chubut (id_provincia = 1)...");
        $this->asegurarPresentacionesChubut();

        // Presentación 105 como presentación de Chubut activa por defecto
        $presChubutDefaultId = DB::table('presentaciones')
            ->where('id_provincia', 1)
            ->where('numero', 105)
            ->value('id') ?? DB::table('presentaciones')->where('id_provincia', 1)->value('id') ?? 1;

        // 3. Importar Clientes de Trelew
        $this->info("\n--- PASO 1: Importando Clientes y Autorizados ---");
        $dniToClienteId = $this->importarClientes($sourcePdo, $limit);

        // 4. Importar Créditos / Ventas
        $this->info("\n--- PASO 2: Importando Créditos (T, I, RE, DE) ---");
        $ventaMap = $this->importarCreditos($sourcePdo, $dniToClienteId, $presChubutDefaultId, $limit);

        // 5. Importar Cuotas
        $this->info("\n--- PASO 3: Importando Cuotas ---");
        $cuotaMap = $this->importarCuotas($sourcePdo, $ventaMap, $limit);

        // 6. Importar Recibos y Detalles
        $this->info("\n--- PASO 4: Importando Recibos de Cobranza ---");
        $this->importarRecibos($sourcePdo, $dniToClienteId, $ventaMap, $cuotaMap, $limit);

        // 7. Ajustar correlativos finales
        $this->info("\n--- PASO 5: Actualizando Correlativos e Índices ---");
        $this->actualizarCorrelativosFinales($sourcePdo);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("==================================================================");
        $this->info("   MIGRACIÓN DE TRELEW COMPLETADA CON ÉXITO EN {$elapsed} SEGUNDOS   ");
        $this->info("==================================================================");

        return 0;
    }

    /**
     * Asegura las presentaciones de Chubut (id_provincia = 1) del 1 al 106.
     */
    protected function asegurarPresentacionesChubut(): void
    {
        $existentes = DB::table('presentaciones')
            ->where('id_provincia', 1)
            ->pluck('numero')
            ->toArray();

        $nuevas = [];
        $now = now();
        for ($num = 1; $num <= 106; $num++) {
            if (!in_array($num, $existentes)) {
                $nuevas[] = [
                    'id_provincia'    => 1,
                    'numero'          => $num,
                    'fecha_inicio'    => $num == 105 ? '2026-09-24' : '2020-01-01',
                    'fecha_fin'       => $num == 105 ? null : '2026-09-24',
                    'interes_credito' => 0.06,
                    'interes_mora'    => 0.06,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        if (!empty($nuevas)) {
            DB::table('presentaciones')->insert($nuevas);
            $this->info("Se crearon " . count($nuevas) . " presentaciones para Chubut.");
        }
    }

    /**
     * Normaliza el tipo de crédito de 1 caracter para el esquema Laravel.
     */
    protected function normalizarTipoCredito(string $term): string
    {
        $term = strtoupper(trim($term));
        if ($term === 'RE' || $term === 'R') return 'R';
        if ($term === 'DE' || $term === 'D') return 'D';
        return 'C'; // Tanto 'T' (Trelew) como 'I' (Interior) son tipo Crédito 'C'
    }

    /**
     * Importa los clientes de lamarina.clientes y crea sus registros de autorizados.
     */
    protected function importarClientes(PDO $pdo, ?int $limit): array
    {
        $countSql = "SELECT count(*) FROM clientes";
        if ($limit) {
            $total = $limit;
        } else {
            $total = (int)$pdo->query($countSql)->fetchColumn();
        }

        $this->info("Procesando {$total} clientes...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sql = "SELECT * FROM clientes ORDER BY DNI ASC";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $pdo->query($sql);
        $now = now();
        $chunkSize = 1000;
        $batchClientes = [];

        while ($row = $stmt->fetch()) {
            $dni = (string)$row['DNI'];
            $nombre = trim($row['RSOCIAL']) ?: ('CLIENTE DNI ' . $dni);
            $sucursalId = (!empty($row['INTERIOR']) && $row['INTERIOR'] == 1) ? 4 : 3;

            $batchClientes[] = [
                'id_sucursal'           => $sucursalId,
                'id_localidad'          => 4, // Trelew
                'nombre'                => $nombre,
                'dni'                   => (int)$dni,
                'cuit'                  => null,
                'nro_cuenta'            => null,
                'direccion'             => 'S/D',
                'tel1'                  => 'S/T',
                'saldo'                 => (float)$row['SALDO'],
                'limite'                => (float)$row['LIMITE'] ?: 200000.00,
                'suspendido'            => (bool)$row['SUSPENDIDO'],
                'obs'                   => $row['OBSERVACIONES'] ?? null,
                'cbu'                   => $row['CBU'] ?? null,
                'medio_pago_preferido'  => (int)($row['MEDIO_PAGO'] ?? 1),
                'tipo_haber'            => (int)($row['TIPO_HABER'] ?? 1),
                'max_cuotas'            => (int)($row['MAX_CUOTAS'] ?? 3),
                'created_at'            => $row['FECHA_ALTA'] ?: $now,
                'updated_at'            => $row['FECHA_MODIF'] ?: $now,
            ];

            if (count($batchClientes) >= $chunkSize) {
                DB::table('clientes')->insertOrIgnore($batchClientes);
                $batchClientes = [];
            }

            $bar->advance();
        }

        if (!empty($batchClientes)) {
            DB::table('clientes')->insertOrIgnore($batchClientes);
        }

        $bar->finish();
        $this->newLine();

        // Obtener mapa de DNI -> ID
        $dniToId = DB::table('clientes')->pluck('id', 'dni')->toArray();

        // Crear titular en cliente_autorizados para los clientes que no tengan
        $this->info("Verificando registros de autorizados titulares...");
        $autorizadosExistentes = DB::table('cliente_autorizados')->pluck('id_titular')->flip()->toArray();

        $autorizadosBatch = [];
        foreach ($dniToId as $dni => $id) {
            if (!isset($autorizadosExistentes[$id])) {
                $autorizadosBatch[] = [
                    'id_titular'    => $id,
                    'id_autorizado' => $id,
                    'relacion_aut'  => 'Titular',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];

                if (count($autorizadosBatch) >= $chunkSize) {
                    DB::table('cliente_autorizados')->insert($autorizadosBatch);
                    $autorizadosBatch = [];
                }
            }
        }
        if (!empty($autorizadosBatch)) {
            DB::table('cliente_autorizados')->insert($autorizadosBatch);
        }

        $this->info("Clientes disponibles en base: " . count($dniToId));
        return $dniToId;
    }

    /**
     * Importa las ventas de lamarina.ventas como créditos.
     */
    protected function importarCreditos(PDO $pdo, array $dniToClienteId, int $presChubutDefaultId, ?int $limit): array
    {
        $countSql = "SELECT count(*) FROM ventas";
        if ($limit) {
            $total = $limit;
        } else {
            $total = (int)$pdo->query($countSql)->fetchColumn();
        }

        $this->info("Procesando {$total} créditos / ventas...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sql = "SELECT * FROM ventas ORDER BY ID_VENTA ASC";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $pdo->query($sql);
        $chunkSize = 2000;
        $batch = [];
        $now = now();

        $autMap = DB::table('cliente_autorizados')
            ->where('relacion_aut', 'Titular')
            ->pluck('id', 'id_titular')
            ->toArray();

        while ($row = $stmt->fetch()) {
            $dni = (string)$row['DNI'];
            $clienteId = $dniToClienteId[$dni] ?? null;

            if (!$clienteId) {
                $bar->advance();
                continue;
            }

            $autId = $autMap[$clienteId] ?? $clienteId;
            $rawTerm = trim($row['TERMINAL']) ?: 'T';
            $tipoCredito = $this->normalizarTipoCredito($rawTerm);
            $sucursalId = (strtoupper($rawTerm) === 'I') ? 4 : 3;
            $idVenta = (int)$row['ID_VENTA'];

            $fechaCredito = $row['FECHA_COMPRA'] ? date('Y-m-d', strtotime($row['FECHA_COMPRA'])) : date('Y-m-d');
            $fechaCancelacion = (!empty($row['FECHA_BAJA']) && $row['FECHA_BAJA'] !== '0000-00-00 00:00:00') 
                ? date('Y-m-d', strtotime($row['FECHA_BAJA'])) 
                : null;

            $batch[] = [
                'id_cliente'        => $clienteId,
                'id_sucursal'       => $sucursalId,
                'id_autorizado'     => $autId,
                'id_presentacion'   => $presChubutDefaultId,
                'credito'           => (string)$idVenta,
                'tipo_credito'      => $tipoCredito,
                'importe_original'  => (float)$row['IMPORTE'],
                'cantidad_cuotas'   => (int)$row['CANTIDAD_CUOTAS'],
                'valor_cuota_ref'   => (float)$row['VALOR_CUOTA'],
                'fecha_credito'     => $fechaCredito,
                'fecha_cancelacion' => $fechaCancelacion,
                'created_at'        => $row['FECHA_COMPRA'] ?: $now,
                'updated_at'        => $row['FECHA_BAJA'] ?: $row['FECHA_COMPRA'] ?: $now,
            ];

            if (count($batch) >= $chunkSize) {
                DB::table('creditos')->insert($batch);
                $batch = [];
            }

            $bar->advance();
        }

        if (!empty($batch)) {
            DB::table('creditos')->insert($batch);
        }

        $bar->finish();
        $this->newLine();

        // Generar mapa en memoria de clave 'TERMINAL_IDVENTA' => id_credito
        $this->info("Indexando mapa de créditos en memoria...");
        $creditosDb = DB::table('creditos')
            ->select('id', 'id_sucursal', 'tipo_credito', 'credito')
            ->get();

        $ventaMap = [];
        foreach ($creditosDb as $c) {
            if ($c->tipo_credito === 'C') {
                $termKey = ($c->id_sucursal == 4) ? 'I' : 'T';
                $ventaMap[$termKey . '_' . $c->credito] = $c->id;
            } elseif ($c->tipo_credito === 'R') {
                $ventaMap['RE_' . $c->credito] = $c->id;
                $ventaMap['R_' . $c->credito] = $c->id;
            } elseif ($c->tipo_credito === 'D') {
                $ventaMap['DE_' . $c->credito] = $c->id;
                $ventaMap['D_' . $c->credito] = $c->id;
            }
        }

        $this->info("Créditos importados y mapeados: " . count($creditosDb));
        return $ventaMap;
    }

    /**
     * Importa las cuotas de lamarina.cuotas vinculándolas a sus créditos.
     */
    protected function importarCuotas(PDO $pdo, array $ventaMap, ?int $limit): array
    {
        $countSql = "SELECT count(*) FROM cuotas";
        if ($limit) {
            $total = $limit;
        } else {
            $total = (int)$pdo->query($countSql)->fetchColumn();
        }

        $this->info("Procesando {$total} cuotas...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sql = "SELECT * FROM cuotas ORDER BY ID_VENTA ASC, CUOTA ASC";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $pdo->query($sql);
        $chunkSize = 3000;
        $batch = [];
        $now = now();

        while ($row = $stmt->fetch()) {
            $term = trim($row['TERMINAL']) ?: 'T';
            $idVenta = (int)$row['ID_VENTA'];
            $key = $term . '_' . $idVenta;

            $creditoId = $ventaMap[$key] ?? null;
            if (!$creditoId) {
                $bar->advance();
                continue;
            }

            $numCuota = (int)$row['CUOTA'];
            $fechaCobro = (!empty($row['FECHA_COBRO']) && $row['FECHA_COBRO'] !== '0000-00-00 00:00:00') 
                ? date('Y-m-d', strtotime($row['FECHA_COBRO'])) 
                : null;

            $batch[] = [
                'id_credito'        => $creditoId,
                'num_cuota'         => $numCuota,
                'valor_base'        => (float)$row['VALOR_CUOTA'],
                'intereses'         => (float)$row['INTERESES'],
                'entrego'           => (float)$row['ENTREGO'],
                'saldo_actual'      => (float)$row['A_PAGAR'],
                'fecha_cobro'       => $fechaCobro,
                'medio_pago'        => $row['MEDIO_PAGO'] ?: null,
                'created_at'        => $now,
                'updated_at'        => $fechaCobro ?: $now,
            ];

            if (count($batch) >= $chunkSize) {
                DB::table('cuotas')->insert($batch);
                $batch = [];
            }

            $bar->advance();
        }

        if (!empty($batch)) {
            DB::table('cuotas')->insert($batch);
        }

        $bar->finish();
        $this->newLine();

        // Indexar mapa de cuotas 'TERMINAL_IDVENTA_NUMCUOTA' => id_cuota
        $this->info("Indexando mapa de cuotas en memoria...");
        $cuotasDb = DB::table('cuotas')
            ->join('creditos', 'cuotas.id_credito', '=', 'creditos.id')
            ->select('cuotas.id as cuota_id', 'creditos.id_sucursal', 'creditos.tipo_credito', 'creditos.credito', 'cuotas.num_cuota')
            ->get();

        $cuotaMap = [];
        foreach ($cuotasDb as $cu) {
            if ($cu->tipo_credito === 'C') {
                $termKey = ($cu->id_sucursal == 4) ? 'I' : 'T';
                $cuotaMap[$termKey . '_' . $cu->credito . '_' . $cu->num_cuota] = $cu->cuota_id;
            } elseif ($cu->tipo_credito === 'R') {
                $cuotaMap['RE_' . $cu->credito . '_' . $cu->num_cuota] = $cu->cuota_id;
                $cuotaMap['R_' . $cu->credito . '_' . $cu->num_cuota] = $cu->cuota_id;
            } elseif ($cu->tipo_credito === 'D') {
                $cuotaMap['DE_' . $cu->credito . '_' . $cu->num_cuota] = $cu->cuota_id;
                $cuotaMap['D_' . $cu->credito . '_' . $cu->num_cuota] = $cu->cuota_id;
            }
        }

        $this->info("Cuotas importadas y mapeadas: " . count($cuotaMap));
        return $cuotaMap;
    }

    /**
     * Importa los recibos de lamarina.recibos agrupados por ID_RECIBO.
     */
    protected function importarRecibos(PDO $pdo, array $dniToClienteId, array $ventaMap, array $cuotaMap, ?int $limit): void
    {
        $sql = "SELECT * FROM recibos ORDER BY ID_RECIBO ASC, subID ASC";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();
        $totalRows = count($rows);
        $this->info("Procesando {$totalRows} renglones de recibos...");

        // Agrupar por ID_RECIBO
        $recibosGrouped = [];
        foreach ($rows as $r) {
            $idRecibo = (int)$r['ID_RECIBO'];
            $recibosGrouped[$idRecibo][] = $r;
        }

        $totalRecibos = count($recibosGrouped);
        $this->info("Total de recibos únicos a emitir: {$totalRecibos}...");

        $bar = $this->output->createProgressBar($totalRecibos);
        $bar->start();

        $batchDetalles = [];
        $now = now();

        foreach ($recibosGrouped as $numeroRecibo => $items) {
            $first = $items[0];
            $dni = (string)$first['DNI_RECIBO'];
            $clienteId = $dniToClienteId[$dni] ?? null;

            if (!$clienteId) {
                $clienteId = 1;
            }

            $rawTerm = trim($first['TERMINAL_REC'] ?? 'T');
            $tipoCredito = $this->normalizarTipoCredito($rawTerm);
            $sucursalId = (strtoupper($rawTerm) === 'I') ? 4 : 3;

            $totalCobrado = 0;
            foreach ($items as $item) {
                $totalCobrado += (float)$item['VALOR_COBRADO'];
            }

            $fechaRec = (!empty($first['FECHA_REC']) && $first['FECHA_REC'] !== '0000-00-00 00:00:00')
                ? $first['FECHA_REC']
                : $now;

            $tipoCobro = (int)($first['TIPO_COBRO'] ?? 1);
            $medioPago = ($tipoCobro === 2) ? 'Tarjeta de Débito' : 'Efectivo';

            // Insertar cabecera recibo
            $reciboId = DB::table('recibos')->insertGetId([
                'id_sucursal'   => $sucursalId,
                'id_cliente'    => $clienteId,
                'id_user'       => 1, // Superusuario / Administrador
                'numero_recibo' => $numeroRecibo,
                'fecha'         => $fechaRec,
                'importe_total' => $totalCobrado,
                'medio_pago'    => $medioPago,
                'observaciones' => "Migración histórica recibo Trelew #{$numeroRecibo}",
                'anulado'       => false,
                'created_at'    => $fechaRec,
                'updated_at'    => $fechaRec,
            ]);

            // Insertar renglones detalles
            foreach ($items as $it) {
                $term = trim($it['TERMINAL_REC'] ?? 'T');
                $idVenta = (int)$it['ID_VENTA_REC'];
                $numCuota = (int)$it['CUOTA_REC'];

                $creditoId = $ventaMap[$term . '_' . $idVenta] ?? null;
                $cuotaId = $cuotaMap[$term . '_' . $idVenta . '_' . $numCuota] ?? null;
                $importe = (float)$it['VALOR_COBRADO'];

                $batchDetalles[] = [
                    'id_recibo'      => $reciboId,
                    'id_credito'     => $creditoId,
                    'id_cuota'       => $cuotaId,
                    'num_cuota'      => $numCuota,
                    'concepto'       => "Cuota {$numCuota} - Crédito {$term}-{$idVenta}",
                    'importe'        => $importe,
                    'saldo_anterior' => $importe,
                    'saldo_nuevo'    => 0.00,
                    'created_at'     => $fechaRec,
                    'updated_at'     => $fechaRec,
                ];

                if (count($batchDetalles) >= 2000) {
                    DB::table('recibo_detalles')->insert($batchDetalles);
                    $batchDetalles = [];
                }
            }

            $bar->advance();
        }

        if (!empty($batchDetalles)) {
            DB::table('recibo_detalles')->insert($batchDetalles);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Recibos importados con éxito.");
    }

    /**
     * Actualiza el correlativo del último recibo y los índices de terminales.
     */
    protected function actualizarCorrelativosFinales(PDO $pdo): void
    {
        $maxRecibo = (int) $pdo->query("SELECT MAX(ID_RECIBO) FROM recibos")->fetchColumn();
        if ($maxRecibo > 0) {
            DB::table('sucursales')->where('id', 3)->update(['ultimo_recibo' => $maxRecibo]);
            $this->info("Sucursal Trelew Centro (id 3): correlativo de último recibo fijado en #{$maxRecibo}.");
        }

        // Actualizar terminales en sucursal_indices
        $terms = $pdo->query("SELECT * FROM terminales")->fetchAll();
        foreach ($terms as $t) {
            $idTerm = trim($t['ID_TERMINAL']);
            $maxVenta = (int)$t['ID_VENTA_TERMINAL'];
            $tipoNorm = $this->normalizarTipoCredito($idTerm);
            $sucId = (strtoupper($idTerm) === 'I') ? 4 : 3;

            DB::table('sucursal_indices')
                ->where('id_sucursal', $sucId)
                ->where('tipo_credito', $tipoNorm)
                ->update(['ultimo_indice' => $maxVenta]);

            $this->info("Terminal {$idTerm} (suc {$sucId}, tipo {$tipoNorm}): último índice fijado en {$maxVenta}");
        }
    }
}
