<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Sucursal;
use COM;
use Exception;

class ImportarCajasMdbCommand extends Command
{
    protected $signature = 'app:import-cajas-mdb 
                            {--path= : Ruta al archivo db.mdb (por defecto base_path/db.mdb)}
                            {--force : Eliminar cajas previamente importadas antes de volver a procesar}';

    protected $description = 'Genera las cajas diarias históricas con sus totales por medio de pago e ingresos/egresos desde db.mdb (CAJA, MOVIMIENTO y MOVIMIENTO_PAGO)';

    protected ?COM $conn = null;

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $startTime = microtime(true);
        $dbPath = $this->option('path') ?: base_path('db.mdb');

        if (!file_exists($dbPath)) {
            $this->error("No se encontró el archivo Access en: {$dbPath}");
            return 1;
        }

        $this->info("==================================================================");
        $this->info("  GENERACIÓN DE CAJAS DIARIAS HISTÓRICAS DESDE DB.MDB            ");
        $this->info("  Origen : {$dbPath}");
        $this->info("  Tablas : CAJA, MOVIMIENTO, MOVIMIENTO_PAGO                       ");
        $this->info("  Destino: cajas_sesiones & cajas_movimientos                      ");
        $this->info("==================================================================");

        try {
            $this->connectMdb($dbPath);
        } catch (Exception $e) {
            $this->error("Error al conectar con ADODB: " . $e->getMessage());
            return 1;
        }

        DB::disableQueryLog();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        try {
            // Identificar sucursal destino (Ushuaia)
            $sucUshuaia = Sucursal::where('codigo_sucursal', 'US')->orWhere('nombre', 'like', '%Ushuaia%')->first();
            $idSucursal = $sucUshuaia?->id ?? 1;
            $this->info("Sucursal vinculada: ID {$idSucursal} ({$sucUshuaia?->nombre})");

            $this->warn("Limpiando cajas y movimientos generados previamente para sucursal {$idSucursal}...");
            DB::table('cajas_movimientos')->whereIn('id_caja_sesion', function($q) use ($idSucursal) {
                $q->select('id')->from('cajas_sesiones')->where('id_sucursal', $idSucursal);
            })->delete();
            DB::table('cajas_sesiones')->where('id_sucursal', $idSucursal)->delete();
            DB::table('ventas')->where('id_sucursal', $idSucursal)->update(['id_caja_sesion' => null]);

            // 1. Extraer movimientos de tabla CAJA
            $this->info("\n--- 1/4 LEYENDO MOVIMIENTOS DE EFECTIVO (TABLA CAJA) ---");
            $t1 = microtime(true);
            $rsCaja = $this->conn->Execute("SELECT id_caja, fecha, hora, concepto, total, Turno FROM CAJA");
            $cajaByDate = [];
            $totalCajaRows = 0;
            while (!$rsCaja->EOF) {
                $ymd = $this->parseDateYmd($rsCaja->Fields('fecha')->Value);
                if ($ymd) {
                    $tot = (float)($rsCaja->Fields('total')->Value ?? 0);
                    $conc = $this->cleanUtf8($rsCaja->Fields('concepto')->Value);
                    if ($conc === '') $conc = 'Movimiento de caja';
                    $his = $this->parseTimeHis($rsCaja->Fields('hora')->Value, '12:00:00');
                    $cajaByDate[$ymd][] = [
                        'id_caja' => $rsCaja->Fields('id_caja')->Value,
                        'hora' => $his,
                        'concepto' => $conc,
                        'total' => $tot,
                        'datetime' => "$ymd $his",
                    ];
                    $totalCajaRows++;
                }
                $rsCaja->MoveNext();
            }
            $this->info("✓ {$totalCajaRows} movimientos de caja procesados en " . round(microtime(true) - $t1, 2) . "s. (Días con ingresos/egresos: " . count($cajaByDate) . ")");

            // 2. Extraer pagos agrupados por fecha y FPAGO
            $this->info("\n--- 2/4 LEYENDO PAGOS DISCRIMINADOS POR MEDIO DE PAGO (MOVIMIENTO_PAGO) ---");
            $t2 = microtime(true);
            $rsPagos = $this->conn->Execute("
                SELECT M.fecha, P.FPAGO, SUM(P.TOTAL) as total_pago, COUNT(*) as cant_pagos
                FROM MOVIMIENTO M
                INNER JOIN MOVIMIENTO_PAGO P ON M.id_operacion = P.ID_OPERACION
                GROUP BY M.fecha, P.FPAGO
            ");
            $pagosByDate = [];
            while (!$rsPagos->EOF) {
                $ymd = $this->parseDateYmd($rsPagos->Fields('fecha')->Value);
                if ($ymd) {
                    $fpago = strtoupper(trim((string)$rsPagos->Fields('FPAGO')->Value));
                    $total = (float)($rsPagos->Fields('total_pago')->Value ?? 0);
                    $cant = (int)($rsPagos->Fields('cant_pagos')->Value ?? 0);

                    if (!isset($pagosByDate[$ymd])) {
                        $pagosByDate[$ymd] = [
                            'efectivo' => ['total' => 0.0, 'cant' => 0],
                            'debito' => ['total' => 0.0, 'cant' => 0],
                            'credito' => ['total' => 0.0, 'cant' => 0],
                            'credito_personal' => ['total' => 0.0, 'cant' => 0],
                            'transferencia' => ['total' => 0.0, 'cant' => 0],
                            'otros' => ['total' => 0.0, 'cant' => 0],
                            'total_pagos' => 0.0,
                        ];
                    }

                    $pagosByDate[$ymd]['total_pagos'] += $total;

                    if ($fpago === 'EFECTIVO') {
                        $pagosByDate[$ymd]['efectivo']['total'] += $total;
                        $pagosByDate[$ymd]['efectivo']['cant'] += $cant;
                    } elseif (in_array($fpago, ['TARJETA DEBITO', 'DEBITO'])) {
                        $pagosByDate[$ymd]['debito']['total'] += $total;
                        $pagosByDate[$ymd]['debito']['cant'] += $cant;
                    } elseif (in_array($fpago, ['TARJETA', 'TARJETA CREDITO'])) {
                        $pagosByDate[$ymd]['credito']['total'] += $total;
                        $pagosByDate[$ymd]['credito']['cant'] += $cant;
                    } elseif (in_array($fpago, ['CREDITO PERS.', 'CTA/CTE'])) {
                        $pagosByDate[$ymd]['credito_personal']['total'] += $total;
                        $pagosByDate[$ymd]['credito_personal']['cant'] += $cant;
                    } elseif (in_array($fpago, ['TRANSFERENCIA', 'MERCADO PAGO', 'MERCADO_PAGO', 'CODIGO QR'])) {
                        $pagosByDate[$ymd]['transferencia']['total'] += $total;
                        $pagosByDate[$ymd]['transferencia']['cant'] += $cant;
                    } else {
                        $pagosByDate[$ymd]['otros']['total'] += $total;
                        $pagosByDate[$ymd]['otros']['cant'] += $cant;
                    }
                }
                $rsPagos->MoveNext();
            }

            // Ventas con medio directo sin registro en MOVIMIENTO_PAGO
            $rsSinPago = $this->conn->Execute("
                SELECT M.fecha, M.formadepago, SUM(M.totalneto) as total_f, COUNT(*) as cant_f
                FROM MOVIMIENTO M
                LEFT JOIN MOVIMIENTO_PAGO P ON M.id_operacion = P.ID_OPERACION
                WHERE M.Operacion = 'VENTA' AND P.ID_OPERACION IS NULL
                GROUP BY M.fecha, M.formadepago
            ");
            while (!$rsSinPago->EOF) {
                $ymd = $this->parseDateYmd($rsSinPago->Fields('fecha')->Value);
                if ($ymd) {
                    $fpago = strtoupper(trim((string)$rsSinPago->Fields('formadepago')->Value));
                    $total = (float)($rsSinPago->Fields('total_f')->Value ?? 0);
                    $cant = (int)($rsSinPago->Fields('cant_f')->Value ?? 0);

                    if (!isset($pagosByDate[$ymd])) {
                        $pagosByDate[$ymd] = [
                            'efectivo' => ['total' => 0.0, 'cant' => 0],
                            'debito' => ['total' => 0.0, 'cant' => 0],
                            'credito' => ['total' => 0.0, 'cant' => 0],
                            'credito_personal' => ['total' => 0.0, 'cant' => 0],
                            'transferencia' => ['total' => 0.0, 'cant' => 0],
                            'otros' => ['total' => 0.0, 'cant' => 0],
                            'total_pagos' => 0.0,
                        ];
                    }
                    $pagosByDate[$ymd]['total_pagos'] += $total;

                    if ($fpago === 'EFECTIVO') {
                        $pagosByDate[$ymd]['efectivo']['total'] += $total;
                        $pagosByDate[$ymd]['efectivo']['cant'] += $cant;
                    } elseif (in_array($fpago, ['TARJETA DEBITO', 'DEBITO'])) {
                        $pagosByDate[$ymd]['debito']['total'] += $total;
                        $pagosByDate[$ymd]['debito']['cant'] += $cant;
                    } elseif (in_array($fpago, ['TARJETA', 'TARJETA CREDITO'])) {
                        $pagosByDate[$ymd]['credito']['total'] += $total;
                        $pagosByDate[$ymd]['credito']['cant'] += $cant;
                    } elseif (in_array($fpago, ['CREDITO PERS.', 'CTA/CTE'])) {
                        $pagosByDate[$ymd]['credito_personal']['total'] += $total;
                        $pagosByDate[$ymd]['credito_personal']['cant'] += $cant;
                    } elseif (in_array($fpago, ['TRANSFERENCIA', 'MERCADO PAGO', 'MERCADO_PAGO', 'CODIGO QR'])) {
                        $pagosByDate[$ymd]['transferencia']['total'] += $total;
                        $pagosByDate[$ymd]['transferencia']['cant'] += $cant;
                    } else {
                        $pagosByDate[$ymd]['otros']['total'] += $total;
                        $pagosByDate[$ymd]['otros']['cant'] += $cant;
                    }
                }
                $rsSinPago->MoveNext();
            }
            $this->info("✓ Totales de pagos agrupados procesados en " . round(microtime(true) - $t2, 2) . "s. (Días únicos con pagos: " . count($pagosByDate) . ")");

            // 3. Extraer totales y rango horario de ventas por fecha
            $this->info("\n--- 3/4 LEYENDO TOTALES DE VENTAS POR FECHA (MOVIMIENTO) ---");
            $t3 = microtime(true);
            $rsVentas = $this->conn->Execute("
                SELECT M.fecha, COUNT(*) as cant_ventas, SUM(M.totalneto) as total_ventas, MIN(M.hora) as min_hora, MAX(M.hora) as max_hora
                FROM MOVIMIENTO M
                WHERE M.Operacion = 'VENTA'
                GROUP BY M.fecha
            ");
            $ventasByDate = [];
            while (!$rsVentas->EOF) {
                $ymd = $this->parseDateYmd($rsVentas->Fields('fecha')->Value);
                if ($ymd) {
                    $ventasByDate[$ymd] = [
                        'cant_ventas' => (int)($rsVentas->Fields('cant_ventas')->Value ?? 0),
                        'total_ventas' => (float)($rsVentas->Fields('total_ventas')->Value ?? 0),
                        'min_hora' => $this->parseTimeHis($rsVentas->Fields('min_hora')->Value, '09:00:00'),
                        'max_hora' => $this->parseTimeHis($rsVentas->Fields('max_hora')->Value, '20:30:00'),
                    ];
                }
                $rsVentas->MoveNext();
            }
            $this->info("✓ Totales de ventas procesados en " . round(microtime(true) - $t3, 2) . "s. (Días únicos con ventas: " . count($ventasByDate) . ")");

            $this->conn->Close();
            $this->conn = null;

            // 4. Consolidar fechas y persistir cajas y movimientos en MySQL
            $this->info("\n--- 4/4 PERSISTIENDO CAJAS DIARIAS Y MOVIMIENTOS EN MYSQL ---");
            $t4 = microtime(true);
            $allDays = array_unique(array_merge(array_keys($cajaByDate), array_keys($pagosByDate), array_keys($ventasByDate)));
            sort($allDays);
            $totalDays = count($allDays);
            $this->info("Total de jornadas a crear/actualizar: {$totalDays}");

            $cajasBatch = [];
            foreach ($allDays as $dia) {
                $v = $ventasByDate[$dia] ?? [
                    'cant_ventas' => 0, 
                    'total_ventas' => 0.0, 
                    'min_hora' => '09:00:00', 
                    'max_hora' => '20:30:00'
                ];
                $p = $pagosByDate[$dia] ?? [
                    'efectivo' => ['total' => 0.0, 'cant' => 0],
                    'debito' => ['total' => 0.0, 'cant' => 0],
                    'credito' => ['total' => 0.0, 'cant' => 0],
                    'credito_personal' => ['total' => 0.0, 'cant' => 0],
                    'transferencia' => ['total' => 0.0, 'cant' => 0],
                    'otros' => ['total' => 0.0, 'cant' => 0],
                    'total_pagos' => 0.0,
                ];
                $movsCaja = $cajaByDate[$dia] ?? [];

                $ingresos = 0.0;
                $egresos = 0.0;
                $cantIngresos = 0;
                $cantEgresos = 0;
                foreach ($movsCaja as $m) {
                    if ($m['total'] > 0) {
                        $ingresos += $m['total'];
                        $cantIngresos++;
                    } elseif ($m['total'] < 0) {
                        $egresos += abs($m['total']);
                        $cantEgresos++;
                    }
                }

                $totalFacturado = $v['total_ventas'] > 0 ? $v['total_ventas'] : $p['total_pagos'];
                $totalEfectivo = $p['efectivo']['total'];
                $montoInicial = 0.00;
                $efectivoEsperado = round($montoInicial + $totalEfectivo + $ingresos - $egresos, 2);

                $minHora = $v['min_hora'];
                $maxHora = $v['max_hora'];
                if (!empty($movsCaja)) {
                    foreach ($movsCaja as $mc) {
                        if ($mc['hora'] < $minHora) $minHora = $mc['hora'];
                        if ($mc['hora'] > $maxHora) $maxHora = $mc['hora'];
                    }
                }

                $cajasBatch[] = [
                    'id_sucursal'                   => $idSucursal,
                    'id_usuario_apertura'           => 1,
                    'id_usuario_cierre'             => 1,
                    'fecha_apertura'                => "$dia $minHora",
                    'fecha_cierre'                  => "$dia $maxHora",
                    'monto_inicial_efectivo'        => $montoInicial,
                    'monto_final_efectivo_esperado' => $efectivoEsperado,
                    'monto_final_efectivo_real'     => $efectivoEsperado,
                    'diferencia_efectivo'           => 0.00,
                    'total_efectivo'                => $totalEfectivo,
                    'total_tarjeta_debito'          => $p['debito']['total'],
                    'total_tarjeta_credito'         => $p['credito']['total'],
                    'total_credito_personal'        => $p['credito_personal']['total'],
                    'total_transferencia'           => $p['transferencia']['total'],
                    'total_otros'                   => $p['otros']['total'],
                    'total_general'                 => $totalFacturado,
                    'cant_ventas_efectivo'          => $p['efectivo']['cant'],
                    'cant_ventas_tarjeta_debito'    => $p['debito']['cant'],
                    'cant_ventas_tarjeta_credito'   => $p['credito']['cant'],
                    'cant_ventas_credito_personal'  => $p['credito_personal']['cant'],
                    'cant_ventas_transferencia'     => $p['transferencia']['cant'],
                    'cant_ventas_otros'             => $p['otros']['cant'],
                    'cant_ventas_total'             => $v['cant_ventas'],
                    'total_ingresos_efectivo'       => $ingresos,
                    'total_retiros_efectivo'        => $egresos,
                    'cant_ingresos_efectivo'        => $cantIngresos,
                    'cant_retiros_efectivo'         => $cantEgresos,
                    'estado'                        => 'cerrada',
                    'observaciones_apertura'        => null,
                    'observaciones_cierre'          => 'Cierre histórico importado de MDB (Ushuaia)',
                    'created_at'                    => "$dia $minHora",
                    'updated_at'                    => "$dia $maxHora",
                ];
            }

            $this->info("Insertando {$totalDays} cajas diarias en MySQL...");
            $cajasChunks = array_chunk($cajasBatch, 500);
            foreach ($cajasChunks as $chunk) {
                DB::table('cajas_sesiones')->insert($chunk);
            }

            // Obtener mapa dia => id_caja_sesion
            $cajasMap = DB::table('cajas_sesiones')
                ->where('id_sucursal', $idSucursal)
                ->pluck('id', DB::raw('DATE(fecha_apertura)'))
                ->toArray();

            // Insertar movimientos de caja
            $this->info("Insertando movimientos en cajas_movimientos...");
            $movsBatch = [];
            foreach ($cajaByDate as $dia => $movs) {
                $cajaId = $cajasMap[$dia] ?? null;
                if (!$cajaId) continue;

                foreach ($movs as $mc) {
                    $movsBatch[] = [
                        'id_caja_sesion' => $cajaId,
                        'id_usuario'     => 1,
                        'tipo'           => $mc['total'] > 0 ? 'ingreso' : 'retiro',
                        'monto'          => abs($mc['total']),
                        'motivo'         => $mc['concepto'],
                        'comprobante'    => null,
                        'created_at'     => $mc['datetime'],
                        'updated_at'     => $mc['datetime'],
                    ];
                }
            }

            if (!empty($movsBatch)) {
                $movsChunks = array_chunk($movsBatch, 1000);
                foreach ($movsChunks as $chunk) {
                    DB::table('cajas_movimientos')->insert($chunk);
                }
            }
            $this->info("✓ " . count($movsBatch) . " movimientos de ingresos/egresos insertados.");

            // 5. Vincular ventas de MySQL a cada sesión de caja
            $this->info("\n--- VINCULANDO VENTAS A CADA SESIÓN DE CAJA ---");
            $affectedVentas = DB::update("
                UPDATE ventas v
                INNER JOIN cajas_sesiones c ON DATE(v.fecha) = DATE(c.fecha_apertura) AND v.id_sucursal = c.id_sucursal
                SET v.id_caja_sesion = c.id
                WHERE v.id_sucursal = {$idSucursal}
            ");
            $this->info("✓ {$affectedVentas} ventas vinculadas a sus respectivas sesiones de caja.");

            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

            $totalElapsed = round(microtime(true) - $startTime, 2);
            $this->info("\n==================================================================");
            $this->info("  PROCESO DE IMPORTACIÓN DE CAJAS FINALIZADO CON ÉXITO            ");
            $this->info("  Tiempo total: {$totalElapsed} segundos                          ");
            $this->info("==================================================================");

            return 0;

        } catch (Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            $this->error("\nError durante el proceso: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    protected function connectMdb(string $dbPath): void
    {
        $connStr = "Provider=Microsoft.ACE.OLEDB.12.0;Data Source={$dbPath};Persist Security Info=False;";
        $this->conn = new COM("ADODB.Connection");
        $this->conn->Open($connStr);
    }

    protected function cleanUtf8(?string $str): string
    {
        if ($str === null || $str === '') return '';
        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
        }
        return trim($str);
    }

    protected function parseDateYmd(?string $rawDate): ?string
    {
        if (!$rawDate) return null;
        $str = trim((string)$rawDate);
        $parts = explode(' ', $str);
        $datePart = $parts[0];
        if (strpos($datePart, '/') !== false) {
            $dp = explode('/', $datePart);
            if (count($dp) === 3) {
                $d = str_pad($dp[0], 2, '0', STR_PAD_LEFT);
                $m = str_pad($dp[1], 2, '0', STR_PAD_LEFT);
                $y = strlen($dp[2]) == 2 ? '20' . $dp[2] : $dp[2];
                return "$y-$m-$d";
            }
        }
        $time = strtotime($str);
        return $time ? date('Y-m-d', $time) : null;
    }

    protected function parseTimeHis(?string $rawTime, string $default = '12:00:00'): string
    {
        if (!$rawTime) return $default;
        $str = trim((string)$rawTime);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str)) {
            $tp = explode(':', $str);
            $h = str_pad($tp[0], 2, '0', STR_PAD_LEFT);
            $i = str_pad($tp[1], 2, '0', STR_PAD_LEFT);
            $s = isset($tp[2]) ? str_pad($tp[2], 2, '0', STR_PAD_LEFT) : '00';
            return "$h:$i:$s";
        }
        $time = strtotime($str);
        return $time ? date('H:i:s', $time) : $default;
    }
}
