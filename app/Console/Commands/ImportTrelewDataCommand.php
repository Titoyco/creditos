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
    protected $description = 'Concilia localidades, clientes, autorizados, créditos, cuotas, novedades y recibos de Trelew e Interior desde lamarina hacia lamarina_v4';

    /**
     * Mapeo de conflicto de ID_LOC de Chubut con IDs reservados en Ushuaia/TDF/CABA
     */
    protected array $conflictMap = [
        310 => 351, // UZCUDUN (en v4 310 es USHUAIA)
        311 => 352, // VALLE DEL RIO CHUBUT (en v4 311 es TOLHUIN)
        313 => 353, // VALLE FRIO (en v4 313 es RIO GRANDE)
        314 => 354, // VALLE GARIN (en v4 314 es CABA)
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '1536M');
        set_time_limit(0);

        $startTime = microtime(true);
        $sourceDb = $this->option('source') ?: 'lamarina';
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info("==================================================================");
        $this->info("   CONCILIACIÓN INTEGRAL CHUBUT: {$sourceDb} -> lamarina_v4   ");
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

        // 2. Sincronizar Localidades de Chubut
        $this->info("\n--- PASO 0: Sincronizando Localidades de Chubut (320 localidades) ---");
        $locMapping = $this->sincronizarLocalidadesChubut($sourcePdo);

        // 3. Sincronizar Presentaciones de Chubut
        $this->info("\n--- PASO 1: Sincronizando Presentaciones de Chubut (1 a 125, actual: 106) ---");
        $presMap = $this->sincronizarPresentacionesChubut($sourcePdo);

        // 4. Conciliar Clientes de Trelew e Interior con sus contactos y localidades
        $this->info("\n--- PASO 2: Conciliando Clientes, Domicilios, Localidades y Teléfonos ---");
        $dniToClienteId = $this->conciliarClientesChubut($sourcePdo, $locMapping, $limit);

        // 5. Sincronizar Autorizados (Titulares y Adicionales)
        $this->info("\n--- PASO 3: Sincronizando Autorizados y Familiares ---");
        $autLookup = $this->sincronizarAutorizados($sourcePdo, $dniToClienteId);

        // 6. Sincronizar Créditos / Ventas
        $this->info("\n--- PASO 4: Sincronizando Créditos (Terminales T e I) ---");
        $ventaMap = $this->sincronizarCreditos($sourcePdo, $dniToClienteId, $autLookup, $presMap, $limit);

        // 7. Sincronizar Cuotas
        $this->info("\n--- PASO 5: Sincronizando Cuotas y Estados de Cobro ---");
        $cuotaMap = $this->sincronizarCuotas($sourcePdo, $ventaMap, $limit);

        // 8. Sincronizar Recibos y Detalles
        $this->info("\n--- PASO 6: Sincronizando Recibos de Cobranza Históricos ---");
        $this->sincronizarRecibos($sourcePdo, $dniToClienteId, $ventaMap, $cuotaMap, $limit);

        // 9. Sincronizar Novedades de Clientes
        $this->info("\n--- PASO 7: Sincronizando Novedades y Comentarios de Clientes ---");
        $this->sincronizarNovedades($sourcePdo, $dniToClienteId);

        // 10. Ajustar correlativos finales
        $this->info("\n--- PASO 8: Actualizando Correlativos e Índices Finales ---");
        $this->actualizarCorrelativosFinales($sourcePdo);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("==================================================================");
        $this->info("   CONCILIACIÓN Y ACTUALIZACIÓN COMPLETADA CON ÉXITO EN {$elapsed}s   ");
        $this->info("==================================================================");

        return 0;
    }

    /**
     * Sincroniza las 320 localidades de Chubut evitando colisiones de ID con Ushuaia/TDF/CABA.
     */
    protected function sincronizarLocalidadesChubut(PDO $pdo): array
    {
        $srcLocs = $pdo->query("SELECT * FROM localidades ORDER BY ID_LOC ASC")->fetchAll(PDO::FETCH_ASSOC);
        $now = now();
        $locMapping = [];
        $inserted = 0;
        $updated = 0;

        foreach ($srcLocs as $sl) {
            $srcId = (int)$sl['ID_LOC'];
            $targetId = $this->conflictMap[$srcId] ?? $srcId;
            $locMapping[$srcId] = $targetId;

            $nombre = mb_strtoupper(trim($sl['NOMBRE_LOC']), 'UTF-8');
            $cp = !empty($sl['CP_LOC']) ? (string)$sl['CP_LOC'] : null;

            $existing = DB::table('localidades')->where('id', $targetId)->first();
            if (!$existing) {
                DB::table('localidades')->insert([
                    'id'            => $targetId,
                    'id_provincia'  => 1, // Chubut
                    'nombre'        => $nombre,
                    'codigo_postal' => $cp,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                $inserted++;
            } else {
                if ($existing->id_provincia == 1) {
                    DB::table('localidades')->where('id', $targetId)->update([
                        'nombre'        => $nombre,
                        'codigo_postal' => $cp,
                        'updated_at'    => $now,
                    ]);
                    $updated++;
                }
            }
        }

        $this->info("Localidades sincronizadas: {$inserted} creadas, {$updated} actualizadas.");
        return $locMapping;
    }

    /**
     * Sincroniza presentaciones de Chubut hasta la 125, fijando la 106 como activa.
     */
    protected function sincronizarPresentacionesChubut(PDO $pdo): array
    {
        $var = $pdo->query("SELECT * FROM variables WHERE ID = 1")->fetch(PDO::FETCH_ASSOC);
        $presActualChubut = !empty($var['PRESENTACION']) ? (int)$var['PRESENTACION'] : 106;
        $fechaPresActual = !empty($var['FECHA_PRES']) ? $var['FECHA_PRES'] : '2026-10-23';
        $interes = !empty($var['INTERES']) ? (float)$var['INTERES'] : 0.06;
        $interesMora = !empty($var['INTERES_MORA']) ? (float)$var['INTERES_MORA'] : 0.06;

        $now = now();

        // 1. Cerrar presentación 105 si no tiene fecha de fin
        DB::table('presentaciones')
            ->where('id_provincia', 1)
            ->where('numero', 105)
            ->whereNull('fecha_fin')
            ->update([
                'fecha_fin'  => $fechaPresActual,
                'updated_at' => $now,
            ]);

        // 2. Crear o actualizar hasta presentación 125 para soportar vencimientos futuros de cuotas
        $maxNum = max(125, $presActualChubut + 15);
        $existentes = DB::table('presentaciones')
            ->where('id_provincia', 1)
            ->pluck('id', 'numero')
            ->toArray();

        for ($num = 1; $num <= $maxNum; $num++) {
            if (!isset($existentes[$num])) {
                $fIni = ($num === $presActualChubut) ? $fechaPresActual : '2020-01-01';
                $fFin = ($num === $presActualChubut) ? null : '2026-10-23';

                $id = DB::table('presentaciones')->insertGetId([
                    'id_provincia'              => 1,
                    'numero'                    => $num,
                    'fecha_inicio'              => $fIni,
                    'fecha_fin'                 => $fFin,
                    'interes_credito'           => $interes,
                    'interes_mora'              => $interesMora,
                    'primera_cuota_sin_interes' => 1,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ]);
                $existentes[$num] = $id;
            } elseif ($num === $presActualChubut) {
                DB::table('presentaciones')
                    ->where('id', $existentes[$num])
                    ->update([
                        'fecha_inicio'              => $fechaPresActual,
                        'fecha_fin'                 => null,
                        'interes_credito'           => $interes,
                        'interes_mora'              => $interesMora,
                        'primera_cuota_sin_interes' => 1,
                        'updated_at'                => $now,
                    ]);
            }
        }

        $this->info("Presentaciones de Chubut verificadas (Presentación activa: #{$presActualChubut} desde {$fechaPresActual}).");
        return $existentes;
    }

    /**
     * Normaliza el tipo de crédito de 1 caracter para el esquema Laravel.
     */
    protected function normalizarTipoCredito(string $term): string
    {
        $term = strtoupper(trim($term));
        if ($term === 'RE' || $term === 'R') return 'R';
        if ($term === 'DE' || $term === 'D') return 'D';
        return 'C'; // 'T' (Trelew) e 'I' (Interior) son tipo Crédito 'C'
    }

    /**
     * Concilia los clientes de Chubut con sus domicilios, teléfonos y localidades reales de contactos.
     */
    protected function conciliarClientesChubut(PDO $pdo, array $locMapping, ?int $limit): array
    {
        // 1. Precompilar contactos más recientes/completos por DNI
        $this->info("Compilando agenda de contactos históricos...");
        $contactosRaw = $pdo->query("
            SELECT DNI, DOMICILIO, LOCALIDAD, TEL1, TEL2, MAIL, OBSERVACIONES 
            FROM contactos 
            ORDER BY ID_CONTACTO ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $contactosMap = [];
        foreach ($contactosRaw as $c) {
            $dni = (string)$c['DNI'];
            if (!isset($contactosMap[$dni])) {
                $contactosMap[$dni] = [
                    'domicilio' => null,
                    'localidad' => null,
                    'tel1' => null,
                    'tel2' => null,
                    'mail' => null,
                    'obs' => null,
                ];
            }
            $dom = trim($c['DOMICILIO'] ?? '');
            if ($dom !== '') $contactosMap[$dni]['domicilio'] = $dom;

            $loc = (int)($c['LOCALIDAD'] ?? 0);
            if ($loc > 0) {
                $contactosMap[$dni]['localidad'] = $locMapping[$loc] ?? $loc;
            }

            $t1 = trim($c['TEL1'] ?? '');
            if ($t1 !== '') $contactosMap[$dni]['tel1'] = $t1;

            $t2 = trim($c['TEL2'] ?? '');
            if ($t2 !== '') $contactosMap[$dni]['tel2'] = $t2;

            $m = trim($c['MAIL'] ?? '');
            if ($m !== '') $contactosMap[$dni]['mail'] = $m;

            $o = trim($c['OBSERVACIONES'] ?? '');
            if ($o !== '') $contactosMap[$dni]['obs'] = $o;
        }

        // 2. Precompilar telefonos_interior
        $telIntRaw = $pdo->query("SELECT DNI, NOMBRE_LOC, TEL1, TEL2 FROM telefonos_interior")->fetchAll(PDO::FETCH_ASSOC);
        $locNameToId = DB::table('localidades')
            ->where('id_provincia', 1)
            ->pluck('id', 'nombre')
            ->toArray();

        foreach ($telIntRaw as $ti) {
            $dni = (string)$ti['DNI'];
            if (!isset($contactosMap[$dni])) {
                $contactosMap[$dni] = [
                    'domicilio' => null,
                    'localidad' => null,
                    'tel1' => null,
                    'tel2' => null,
                    'mail' => null,
                    'obs' => null,
                ];
            }
            if (empty($contactosMap[$dni]['tel1']) && !empty(trim($ti['TEL1'] ?? ''))) {
                $contactosMap[$dni]['tel1'] = trim($ti['TEL1']);
            }
            if (empty($contactosMap[$dni]['tel2']) && !empty(trim($ti['TEL2'] ?? ''))) {
                $contactosMap[$dni]['tel2'] = trim($ti['TEL2']);
            }
            if (empty($contactosMap[$dni]['localidad']) && !empty(trim($ti['NOMBRE_LOC'] ?? ''))) {
                $nom = mb_strtoupper(trim($ti['NOMBRE_LOC']), 'UTF-8');
                if (isset($locNameToId[$nom])) {
                    $contactosMap[$dni]['localidad'] = $locNameToId[$nom];
                }
            }
        }

        // 3. Procesar clientes
        $totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
        if ($limit) $totalClientes = min($totalClientes, $limit);

        $this->info("Procesando {$totalClientes} clientes de Chubut...");
        $bar = $this->output->createProgressBar($totalClientes);
        $bar->start();

        // Indexar clientes existentes en v4 por DNI
        $existingClients = DB::table('clientes')->pluck('id', 'dni')->toArray();

        $sql = "SELECT * FROM clientes ORDER BY DNI ASC";
        if ($limit) $sql .= " LIMIT {$limit}";
        $stmt = $pdo->query($sql);

        $now = now();
        $batchInsert = [];
        $actualizados = 0;
        $insertados = 0;

        while ($row = $stmt->fetch()) {
            $dniInt = (int)$row['DNI'];
            $dniStr = (string)$dniInt;
            $nombre = trim($row['RSOCIAL']) ?: ('CLIENTE DNI ' . $dniStr);
            $isInterior = (!empty($row['INTERIOR']) && $row['INTERIOR'] == 1);
            $sucursalId = $isInterior ? 4 : 3;

            $cinfo = $contactosMap[$dniStr] ?? [];
            $domicilio = !empty($cinfo['domicilio']) ? $cinfo['domicilio'] : 'S/D';
            $tel1 = !empty($cinfo['tel1']) ? $cinfo['tel1'] : 'S/T';
            $tel2 = !empty($cinfo['tel2']) ? $cinfo['tel2'] : null;
            $email = !empty($cinfo['mail']) ? $cinfo['mail'] : null;

            // Determinar localidad:
            $localidadId = $cinfo['localidad'] ?? null;
            if (!$localidadId) {
                // Default: Trelew (306)
                $localidadId = 306;
            }

            // Datos del cliente
            $saldo = (float)($row['SALDO'] ?? 0);
            $limite = (float)($row['LIMITE'] ?? 0) ?: 200000.00;
            $suspendido = (bool)($row['SUSPENDIDO'] ?? false);
            $obs = !empty($row['OBSERVACIONES']) ? $row['OBSERVACIONES'] : ($cinfo['obs'] ?? null);
            $cbu = $row['CBU'] ?? null;
            $medioPago = (int)($row['MEDIO_PAGO'] ?? 1);
            $tipoHaber = (int)($row['TIPO_HABER'] ?? 1);
            $maxCuotas = (int)($row['MAX_CUOTAS'] ?? 3);
            $fechaAlta = !empty($row['FECHA_ALTA']) && $row['FECHA_ALTA'] !== '0000-00-00 00:00:00' ? $row['FECHA_ALTA'] : $now;
            $fechaModif = !empty($row['FECHA_MODIF']) && $row['FECHA_MODIF'] !== '0000-00-00 00:00:00' ? $row['FECHA_MODIF'] : $now;

            if (isset($existingClients[$dniInt])) {
                // Actualizar cliente existente (corrigiendo id_localidad, direccion, telefonos, saldo y límites)
                DB::table('clientes')
                    ->where('id', $existingClients[$dniInt])
                    ->update([
                        'id_sucursal'          => $sucursalId,
                        'id_localidad'         => $localidadId,
                        'nombre'               => $nombre,
                        'direccion'            => $domicilio,
                        'tel1'                 => $tel1,
                        'tel2'                 => $tel2,
                        'email'                => $email,
                        'saldo'                => $saldo,
                        'limite'               => $limite,
                        'suspendido'           => $suspendido,
                        'obs'                  => $obs,
                        'cbu'                  => $cbu,
                        'medio_pago_preferido' => $medioPago,
                        'tipo_haber'           => $tipoHaber,
                        'max_cuotas'           => $maxCuotas,
                        'updated_at'           => $fechaModif,
                    ]);
                $actualizados++;
            } else {
                $batchInsert[] = [
                    'id_sucursal'          => $sucursalId,
                    'id_localidad'         => $localidadId,
                    'nombre'               => $nombre,
                    'dni'                  => $dniInt,
                    'cuit'                 => null,
                    'nro_cuenta'           => null,
                    'direccion'            => $domicilio,
                    'tel1'                 => $tel1,
                    'tel2'                 => $tel2,
                    'email'                => $email,
                    'saldo'                => $saldo,
                    'saldo_a_favor'        => 0.00,
                    'limite'               => $limite,
                    'max_cuotas'           => $maxCuotas,
                    'suspendido'           => $suspendido,
                    'obs'                  => $obs,
                    'cbu'                  => $cbu,
                    'medio_pago_preferido' => $medioPago,
                    'tipo_haber'           => $tipoHaber,
                    'created_at'           => $fechaAlta,
                    'updated_at'           => $fechaModif,
                ];
                $insertados++;

                if (count($batchInsert) >= 500) {
                    DB::table('clientes')->insert($batchInsert);
                    $batchInsert = [];
                }
            }

            $bar->advance();
        }

        if (!empty($batchInsert)) {
            DB::table('clientes')->insert($batchInsert);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Clientes: {$actualizados} actualizados con su localidad y domicilio real, {$insertados} nuevos insertados.");

        return DB::table('clientes')->pluck('id', 'dni')->toArray();
    }

    /**
     * Sincroniza los autorizados (titulares y familiares adicionales).
     */
    protected function sincronizarAutorizados(PDO $pdo, array $dniToClienteId): array
    {
        $now = now();

        // 1. Indexar todos los pares (id_titular, id_autorizado) existentes para no violar la clave única
        $this->info("Verificando registros en cliente_autorizados...");
        $existentesRows = DB::table('cliente_autorizados')->select('id', 'id_titular', 'id_autorizado')->get();
        $existentesPares = [];
        foreach ($existentesRows as $er) {
            $existentesPares[$er->id_titular . '_' . $er->id_autorizado] = $er->id;
        }

        // 2. Asegurar titular (self) para cada cliente
        $batchSelf = [];
        $nuevosTitulares = 0;
        foreach ($dniToClienteId as $dni => $clienteId) {
            $key = $clienteId . '_' . $clienteId;
            if (!isset($existentesPares[$key])) {
                $batchSelf[] = [
                    'id_titular'       => $clienteId,
                    'id_autorizado'    => $clienteId,
                    'relacion_aut'     => 'Titular',
                    'fecha_autorizado' => null,
                    'limite_aut'       => null,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
                $existentesPares[$key] = true;
                $nuevosTitulares++;

                if (count($batchSelf) >= 500) {
                    DB::table('cliente_autorizados')->insertOrIgnore($batchSelf);
                    $batchSelf = [];
                }
            }
        }
        if (!empty($batchSelf)) {
            DB::table('cliente_autorizados')->insertOrIgnore($batchSelf);
        }
        $this->info("Titulares autorizados asegurados ({$nuevosTitulares} nuevos).");

        // 3. Importar autorizados/adicionales de lamarina.autorizados
        $this->info("Importando autorizados y adicionales familiares...");
        $autSrc = $pdo->query("SELECT * FROM autorizados ORDER BY ID_AUTORIZACION ASC")->fetchAll(PDO::FETCH_ASSOC);

        $batchAdic = [];
        $nuevosAdic = 0;
        foreach ($autSrc as $a) {
            $dniTitular = (int)$a['DNI_TITULAR'];
            $dniAut = (int)$a['DNI_AUTORIZADO'];

            $titularId = $dniToClienteId[$dniTitular] ?? null;
            if (!$titularId) continue;

            $autId = $dniToClienteId[$dniAut] ?? null;
            if (!$autId) {
                // Si el DNI autorizado no está como cliente titular, crear registro base
                $autId = DB::table('clientes')->insertGetId([
                    'id_sucursal'          => 3,
                    'id_localidad'         => 306,
                    'nombre'               => 'AUTORIZADO DNI ' . $dniAut,
                    'dni'                  => $dniAut,
                    'cuit'                 => null,
                    'nro_cuenta'           => null,
                    'direccion'            => 'S/D',
                    'tel1'                 => 'S/T',
                    'saldo'                => 0.00,
                    'saldo_a_favor'        => 0.00,
                    'limite'               => 0.00,
                    'max_cuotas'           => 3,
                    'suspendido'           => 0,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
                $dniToClienteId[$dniAut] = $autId;
            }

            $key = $titularId . '_' . $autId;
            if (!isset($existentesPares[$key])) {
                $relacion = trim($a['RELACION_AUT'] ?? '') ?: 'Familiar';
                $fechaAut = !empty($a['FECHA_AUTORIZADO']) && $a['FECHA_AUTORIZADO'] !== '0000-00-00' ? $a['FECHA_AUTORIZADO'] : null;
                $limiteAut = (float)($a['LIMITE_AUT'] ?? 0) ?: 50000.00;

                $batchAdic[] = [
                    'id_titular'       => $titularId,
                    'id_autorizado'    => $autId,
                    'relacion_aut'     => $relacion,
                    'fecha_autorizado' => $fechaAut,
                    'limite_aut'       => $limiteAut,
                    'created_at'       => $fechaAut ?: $now,
                    'updated_at'       => $fechaAut ?: $now,
                ];
                $existentesPares[$key] = true;
                $nuevosAdic++;

                if (count($batchAdic) >= 500) {
                    DB::table('cliente_autorizados')->insertOrIgnore($batchAdic);
                    $batchAdic = [];
                }
            }
        }

        if (!empty($batchAdic)) {
            DB::table('cliente_autorizados')->insertOrIgnore($batchAdic);
        }

        $this->info("Autorizados adicionales sincronizados: {$nuevosAdic} nuevos.");

        // Crear mapa para resolución rápida al importar ventas
        $todosAut = DB::table('cliente_autorizados')
            ->join('clientes as t', 'cliente_autorizados.id_titular', '=', 't.id')
            ->leftJoin('clientes as a', 'cliente_autorizados.id_autorizado', '=', 'a.id')
            ->select('cliente_autorizados.id', 't.dni as dni_titular', 'a.dni as dni_autorizado', 'cliente_autorizados.relacion_aut')
            ->get();

        $autLookup = [];
        foreach ($todosAut as $ta) {
            $autLookup[$ta->dni_titular . '_' . $ta->dni_autorizado] = $ta->id;
            if ($ta->relacion_aut === 'Titular') {
                $autLookup[$ta->dni_titular . '_0'] = $ta->id;
            }
        }

        return $autLookup;
    }

    /**
     * Sincroniza las ventas de lamarina.ventas hacia creditos.
     */
    protected function sincronizarCreditos(PDO $pdo, array $dniToClienteId, array $autLookup, array $presMap, ?int $limit): array
    {
        $totalVentas = (int)$pdo->query("SELECT COUNT(*) FROM ventas")->fetchColumn();
        if ($limit) $totalVentas = min($totalVentas, $limit);

        $this->info("Indexando créditos existentes en sucursales 3 y 4...");
        $creditosDb = DB::table('creditos')
            ->whereIn('id_sucursal', [3, 4])
            ->select('id', 'id_sucursal', 'tipo_credito', 'credito', 'fecha_cancelacion')
            ->get();

        $ventaMap = [];
        $canceladosMap = [];
        foreach ($creditosDb as $c) {
            $termKey = ($c->id_sucursal == 4) ? 'I' : 'T';
            $ventaMap[$termKey . '_' . $c->credito] = $c->id;
            $canceladosMap[$termKey . '_' . $c->credito] = $c->fecha_cancelacion;
        }

        $this->info("Créditos preexistentes en base: " . count($ventaMap));
        $this->info("Procesando {$totalVentas} créditos de origen...");

        $bar = $this->output->createProgressBar($totalVentas);
        $bar->start();

        $sql = "SELECT * FROM ventas ORDER BY ID_VENTA ASC";
        if ($limit) $sql .= " LIMIT {$limit}";
        $stmt = $pdo->query($sql);

        $now = now();
        $batch = [];
        $chunkSize = 1000;
        $insertados = 0;
        $actualizados = 0;

        $defaultPresId = $presMap[105] ?? ($presMap[106] ?? 208);

        while ($row = $stmt->fetch()) {
            $rawTerm = trim($row['TERMINAL'] ?: 'T');
            $idVenta = (string)((int)$row['ID_VENTA']);
            $key = $rawTerm . '_' . $idVenta;

            $fechaBaja = (!empty($row['FECHA_BAJA']) && $row['FECHA_BAJA'] !== '0000-00-00 00:00:00') 
                ? date('Y-m-d', strtotime($row['FECHA_BAJA'])) 
                : null;

            // Si ya existe, verificar si requiere actualizar fecha de cancelación
            if (isset($ventaMap[$key])) {
                $idCred = $ventaMap[$key];
                if ($fechaBaja && empty($canceladosMap[$key])) {
                    DB::table('creditos')->where('id', $idCred)->update(['fecha_cancelacion' => $fechaBaja]);
                    $canceladosMap[$key] = $fechaBaja;
                    $actualizados++;
                }
                $bar->advance();
                continue;
            }

            $dni = (string)$row['DNI'];
            $clienteId = $dniToClienteId[$dni] ?? null;
            if (!$clienteId) {
                $bar->advance();
                continue;
            }

            $sucursalId = (strtoupper($rawTerm) === 'I') ? 4 : 3;
            $tipoCredito = $this->normalizarTipoCredito($rawTerm);

            // Autorizado
            $dniAut = (int)($row['AUTORIZADO'] ?? 0);
            $autId = $autLookup[$dni . '_' . $dniAut] ?? ($autLookup[$dni . '_0'] ?? null);

            // Presentación
            $fechaCredito = !empty($row['FECHA_COMPRA']) && $row['FECHA_COMPRA'] !== '0000-00-00 00:00:00'
                ? date('Y-m-d', strtotime($row['FECHA_COMPRA']))
                : date('Y-m-d');

            if ($fechaCredito >= '2026-10-23' && isset($presMap[106])) {
                $idPresentacion = $presMap[106];
            } else {
                $idPresentacion = $defaultPresId;
            }

            $batch[] = [
                'id_cliente'        => $clienteId,
                'id_sucursal'       => $sucursalId,
                'id_autorizado'     => $autId,
                'id_presentacion'   => $idPresentacion,
                'credito'           => $idVenta,
                'tipo_credito'      => $tipoCredito,
                'importe_original'  => (float)$row['IMPORTE'],
                'cantidad_cuotas'   => (int)$row['CANTIDAD_CUOTAS'],
                'valor_cuota_ref'   => (float)$row['VALOR_CUOTA'],
                'fecha_credito'     => $fechaCredito,
                'fecha_cancelacion' => $fechaBaja,
                'created_at'        => $row['FECHA_COMPRA'] ?: $now,
                'updated_at'        => $row['FECHA_BAJA'] ?: $row['FECHA_COMPRA'] ?: $now,
            ];
            $insertados++;

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
        $this->info("Créditos: {$insertados} nuevos insertados, {$actualizados} actualizados con fecha de cancelación.");

        // Reconstruir mapa de créditos completo
        $creditosFinal = DB::table('creditos')
            ->whereIn('id_sucursal', [3, 4])
            ->select('id', 'id_sucursal', 'tipo_credito', 'credito')
            ->get();

        $ventaMapFinal = [];
        foreach ($creditosFinal as $c) {
            $termKey = ($c->id_sucursal == 4) ? 'I' : 'T';
            $ventaMapFinal[$termKey . '_' . $c->credito] = $c->id;
        }

        return $ventaMapFinal;
    }

    /**
     * Sincroniza las cuotas de lamarina.cuotas.
     */
    protected function sincronizarCuotas(PDO $pdo, array $ventaMap, ?int $limit): array
    {
        $totalCuotas = (int)$pdo->query("SELECT COUNT(*) FROM cuotas")->fetchColumn();
        if ($limit) $totalCuotas = min($totalCuotas, $limit);

        $this->info("Indexando cuotas existentes en base local...");
        $cuotasDb = DB::table('cuotas')
            ->join('creditos', 'cuotas.id_credito', '=', 'creditos.id')
            ->whereIn('creditos.id_sucursal', [3, 4])
            ->select('cuotas.id', 'creditos.id_sucursal', 'creditos.credito', 'cuotas.num_cuota', 'cuotas.saldo_actual', 'cuotas.entrego', 'cuotas.fecha_cobro')
            ->get();

        $cuotaMap = [];
        $cuotaState = [];
        foreach ($cuotasDb as $cu) {
            $termKey = ($cu->id_sucursal == 4) ? 'I' : 'T';
            $key = $termKey . '_' . $cu->credito . '_' . $cu->num_cuota;
            $cuotaMap[$key] = $cu->id;
            $cuotaState[$key] = [
                'saldo' => (float)$cu->saldo_actual,
                'entrego' => (float)$cu->entrego,
                'fecha_cobro' => $cu->fecha_cobro,
            ];
        }

        $this->info("Cuotas preexistentes indexadas: " . count($cuotaMap));
        $this->info("Procesando {$totalCuotas} cuotas de origen...");

        $bar = $this->output->createProgressBar($totalCuotas);
        $bar->start();

        $sql = "SELECT * FROM cuotas ORDER BY ID_VENTA ASC, CUOTA ASC";
        if ($limit) $sql .= " LIMIT {$limit}";
        $stmt = $pdo->query($sql);

        $now = now();
        $batch = [];
        $chunkSize = 2000;
        $insertadas = 0;
        $actualizadas = 0;

        while ($row = $stmt->fetch()) {
            $term = trim($row['TERMINAL'] ?: 'T');
            $idVenta = (string)((int)$row['ID_VENTA']);
            $numCuota = (int)$row['CUOTA'];
            $key = $term . '_' . $idVenta . '_' . $numCuota;

            $creditoId = $ventaMap[$term . '_' . $idVenta] ?? null;
            if (!$creditoId) {
                $bar->advance();
                continue;
            }

            $saldoActual = (float)$row['A_PAGAR'];
            $entrego = (float)$row['ENTREGO'];
            $fechaCobro = (!empty($row['FECHA_COBRO']) && $row['FECHA_COBRO'] !== '0000-00-00 00:00:00')
                ? date('Y-m-d', strtotime($row['FECHA_COBRO']))
                : null;
            $medioPago = $row['MEDIO_PAGO'] ?: null;
            $bancoEnvio = $row['BANCO_ENVIO'] ?: null;

            if (isset($cuotaMap[$key])) {
                $idCuota = $cuotaMap[$key];
                $st = $cuotaState[$key];

                // Verificar si hubo pago o cambio de saldo
                if (abs($st['saldo'] - $saldoActual) > 0.01 || abs($st['entrego'] - $entrego) > 0.01 || $st['fecha_cobro'] !== $fechaCobro) {
                    DB::table('cuotas')->where('id', $idCuota)->update([
                        'saldo_actual' => $saldoActual,
                        'entrego'      => $entrego,
                        'fecha_cobro'  => $fechaCobro,
                        'medio_pago'   => $medioPago,
                        'banco_envio'  => $bancoEnvio,
                        'updated_at'   => $fechaCobro ?: $now,
                    ]);
                    $actualizadas++;
                }
                $bar->advance();
                continue;
            }

            $batch[] = [
                'id_credito'        => $creditoId,
                'num_cuota'         => $numCuota,
                'valor_base'        => (float)$row['VALOR_CUOTA'],
                'intereses'         => (float)$row['INTERESES'],
                'entrego'           => $entrego,
                'saldo_actual'      => $saldoActual,
                'fecha_cobro'       => $fechaCobro,
                'medio_pago'        => $medioPago,
                'banco_envio'       => $bancoEnvio,
                'created_at'        => $now,
                'updated_at'        => $fechaCobro ?: $now,
            ];
            $insertadas++;

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
        $this->info("Cuotas: {$insertadas} nuevas insertadas, {$actualizadas} actualizadas.");

        // Recargar mapa final de cuotas
        $cuotasFinal = DB::table('cuotas')
            ->join('creditos', 'cuotas.id_credito', '=', 'creditos.id')
            ->whereIn('creditos.id_sucursal', [3, 4])
            ->select('cuotas.id', 'creditos.id_sucursal', 'creditos.credito', 'cuotas.num_cuota')
            ->get();

        $cuotaMapFinal = [];
        foreach ($cuotasFinal as $cu) {
            $termKey = ($cu->id_sucursal == 4) ? 'I' : 'T';
            $cuotaMapFinal[$termKey . '_' . $cu->credito . '_' . $cu->num_cuota] = $cu->id;
        }

        return $cuotaMapFinal;
    }

    /**
     * Sincroniza los recibos faltantes de lamarina.recibos hacia recibos y recibo_detalles.
     */
    protected function sincronizarRecibos(PDO $pdo, array $dniToClienteId, array $ventaMap, array $cuotaMap, ?int $limit): void
    {
        // 1. Obtener recibos existentes en v4
        $recibosExistentes = DB::table('recibos')
            ->whereIn('id_sucursal', [3, 4])
            ->pluck('id', 'numero_recibo')
            ->toArray();

        $this->info("Recibos preexistentes en base: " . count($recibosExistentes));

        $sql = "SELECT * FROM recibos ORDER BY ID_RECIBO ASC, subID ASC";
        if ($limit) $sql .= " LIMIT {$limit}";
        $rows = $pdo->query($sql)->fetchAll();

        // Agrupar por ID_RECIBO
        $recibosGrouped = [];
        foreach ($rows as $r) {
            $idRecibo = (int)$r['ID_RECIBO'];
            if (!isset($recibosExistentes[$idRecibo])) {
                $recibosGrouped[$idRecibo][] = $r;
            }
        }

        $totalNuevos = count($recibosGrouped);
        $this->info("Total de recibos nuevos a emitir: {$totalNuevos}...");

        if ($totalNuevos === 0) {
            $this->info("Todos los recibos ya estaban sincronizados.");
            return;
        }

        $bar = $this->output->createProgressBar($totalNuevos);
        $bar->start();

        $batchDetalles = [];
        $now = now();

        foreach ($recibosGrouped as $numeroRecibo => $items) {
            $first = $items[0];
            $dni = (string)$first['DNI_RECIBO'];
            $clienteId = $dniToClienteId[$dni] ?? 1;

            $rawTerm = trim($first['TERMINAL_REC'] ?? 'T');
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
                'id_user'       => 1, // Superusuario
                'numero_recibo' => $numeroRecibo,
                'fecha'         => $fechaRec,
                'importe_total' => $totalCobrado,
                'medio_pago'    => $medioPago,
                'observaciones' => "Migración histórica recibo Chubut #{$numeroRecibo}",
                'anulado'       => false,
                'created_at'    => $fechaRec,
                'updated_at'    => $fechaRec,
            ]);

            // Insertar renglones detalles
            foreach ($items as $it) {
                $term = trim($it['TERMINAL_REC'] ?? 'T');
                $idVenta = (string)((int)$it['ID_VENTA_REC']);
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

                if (count($batchDetalles) >= 1000) {
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
        $this->info("Recibos nuevos importados con éxito.");
    }

    /**
     * Sincroniza las novedades de clientes de lamarina.novedades hacia cliente_novedades.
     */
    protected function sincronizarNovedades(PDO $pdo, array $dniToClienteId): void
    {
        $totalNov = (int)$pdo->query("SELECT COUNT(*) FROM novedades")->fetchColumn();
        $this->info("Total novedades en origen: {$totalNov}...");

        $existentesCount = DB::table('cliente_novedades')
            ->where('origen_accion', 'historico_chubut')
            ->count();

        if ($existentesCount >= $totalNov) {
            $this->info("Novedades de Chubut ya sincronizadas ({$existentesCount} registros).");
            return;
        }

        $stmt = $pdo->query("SELECT * FROM novedades ORDER BY ID_NOVEDAD ASC");
        $batch = [];
        $now = now();
        $insertadas = 0;

        while ($r = $stmt->fetch()) {
            $dni = (string)$r['DNI_NOVEDAD'];
            $clienteId = $dniToClienteId[$dni] ?? null;
            if (!$clienteId) continue;

            $fecha = !empty($r['FECHA']) && $r['FECHA'] !== '0000-00-00 00:00:00' ? $r['FECHA'] : $now;
            $mensaje = trim($r['NOVEDAD'] ?? '');
            if ($mensaje === '') continue;

            $tipo = (int)($r['TIPO_NOVEDAD'] ?? 1);
            $metodo = trim($r['METODO'] ?? '') ?: 'SISTEMA';

            $batch[] = [
                'id_cliente'     => $clienteId,
                'user_id'        => 1,
                'usuario_nombre' => 'Sistema Chubut',
                'mensaje'        => $mensaje,
                'origen_accion'  => 'historico_chubut',
                'tipo'           => $tipo,
                'metodo'         => $metodo,
                'created_at'     => $fecha,
                'updated_at'     => $fecha,
            ];
            $insertadas++;

            if (count($batch) >= 1000) {
                DB::table('cliente_novedades')->insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('cliente_novedades')->insert($batch);
        }

        $this->info("Novedades sincronizadas: {$insertadas} insertadas en el historial de clientes.");
    }

    /**
     * Actualiza el correlativo del último recibo y los índices de terminales.
     */
    protected function actualizarCorrelativosFinales(PDO $pdo): void
    {
        // 1. Último recibo
        $maxRecibo = (int) $pdo->query("SELECT MAX(ID_RECIBO) FROM recibos")->fetchColumn();
        $var = $pdo->query("SELECT N_RECIBO FROM variables WHERE ID = 1")->fetch(PDO::FETCH_ASSOC);
        $reciboVar = (int)($var['N_RECIBO'] ?? 0);
        $reciboDefinitivo = max($maxRecibo, $reciboVar);

        if ($reciboDefinitivo > 0) {
            DB::table('sucursales')->where('id', 3)->update(['ultimo_recibo' => $reciboDefinitivo]);
            $this->info("Sucursal Trelew Centro (id 3): correlativo de último recibo fijado en #{$reciboDefinitivo}.");
        }

        // 2. Terminales e índices
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
