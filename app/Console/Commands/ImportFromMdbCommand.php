<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Cliente;
use App\Models\ClienteAutorizado;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\ProductoSubcategoria;
use App\Models\ProductoStock;
use App\Models\Presentacion;
use App\Models\Sucursal;
use COM;
use Exception;

class ImportFromMdbCommand extends Command
{
    protected $signature = 'app:import-mdb 
                            {--section=all : Secciones a importar: all, clientes, productos, stock, creditos, ventas}
                            {--path= : Ruta absoluta al archivo db.mdb}';

    protected $description = 'Importa clientes, productos, stock y créditos desde creditos/db.mdb (Access) hacia MySQL lamarina_v4';

    protected ?COM $conn = null;
    protected array $personaToClienteId = [];

    public function handle(): int
    {
        $startTime = microtime(true);
        $section = strtolower($this->option('section') ?: 'all');
        $dbPath = $this->option('path') ?: base_path('db.mdb');

        if (!file_exists($dbPath)) {
            $this->error("No se encontró el archivo Access en: {$dbPath}");
            return 1;
        }

        $this->info("==================================================================");
        $this->info("   IMPORTACIÓN DESDE ACCESS DB.MDB A MYSQL (lamarina_v4)          ");
        $this->info("   Archivo: {$dbPath}");
        $this->info("   Sección: {$section}");
        $this->info("==================================================================");

        try {
            $this->connectMdb($dbPath);
        } catch (Exception $e) {
            $this->error("Error al conectar a Access db.mdb: " . $e->getMessage());
            return 1;
        }

        // Asegurar sucursal Ushuaia
        $sucUshuaia = Sucursal::where('codigo_sucursal', 'US')->first();
        $idSucursalUshuaia = $sucUshuaia?->id ?? 1;

        // Ejecutar las secciones solicitadas
        try {
            if ($section === 'all' || $section === 'clientes') {
                $this->importClientes($idSucursalUshuaia);
            }

            if ($section === 'all' || $section === 'productos') {
                $this->importProductos();
            }

            if ($section === 'all' || $section === 'stock') {
                $this->importStock($idSucursalUshuaia);
            }

            if ($section === 'all' || $section === 'creditos') {
                $this->importCreditos($idSucursalUshuaia);
            }

            if ($section === 'ventas') {
                $this->disconnectMdb();
                $this->call('app:import-ventas-ushuaia', ['--path' => $dbPath]);
                return 0;
            }
        } catch (\Throwable $e) {
            $this->error("Error crítico durante la importación: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        } finally {
            $this->disconnectMdb();
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("\n✓ PROCESO COMPLETADO EXITOSAMENTE EN {$elapsed} SEGUNDOS.");
        return 0;
    }

    protected function connectMdb(string $dbPath): void
    {
        $this->info("Conectando con ADODB OLEDB a db.mdb...");
        $connStr = "Provider=Microsoft.ACE.OLEDB.12.0;Data Source={$dbPath};Persist Security Info=False;";
        $this->conn = new COM("ADODB.Connection");
        $this->conn->Open($connStr);
        $this->info("✓ Conexión establecida.");
    }

    protected function disconnectMdb(): void
    {
        if ($this->conn) {
            try {
                $this->conn->Close();
            } catch (\Throwable $e) {}
            $this->conn = null;
        }
    }

    /**
     * IMPORTACIÓN 1: CLIENTES Y AUTORIZADOS
     */
    protected function importClientes(int $idSucursal): void
    {
        $this->info("\n--- IMPORTANDO CLIENTES (PERSONAS + CREDITOS_PERSONAS) ---");

        // Mapa de localidades por nombre normalizado
        $localidadesMap = [
            'USHUAIA'      => 310,
            'TOLHUIN'      => 311,
            'RIO GRANDE'   => 313,
            'RÍO GRANDE'   => 313,
            'BUENOS AIRES' => 314,
            'CABA'         => 314,
        ];

        $sql = "SELECT 
                    p.IDPERSONA, p.LEGAJO, p.RSOCIAL, p.Calle, p.localidad, p.CP, p.Provincia, 
                    p.Telefonos, p.telefono2, p.Cuit, p.Email, p.Limite, p.Saldo, 
                    p.comentario, p.moroso, p.SUSPENDIDO_CTACTE,
                    cp.ADICIONAL, cp.DNIADICIONAL, cp.SUC, cp.[NRO CTA] as NRO_CTA, cp.JUBILADO
                FROM PERSONAS p
                LEFT JOIN CREDITOS_PERSONAS cp ON p.IDPERSONA = cp.IDPERSONA";

        $rs = $this->conn->Execute($sql);
        $totalPersonas = 0;
        $insertados = 0;
        $actualizados = 0;
        $autorizadosCount = 0;

        DB::beginTransaction();

        while (!$rs->EOF) {
            $totalPersonas++;
            $idPersona = (int)$rs->Fields("IDPERSONA")->Value;
            $rawNombre = $this->cleanUtf8($rs->Fields("RSOCIAL")->Value);
            $nombreLimpio = $rawNombre !== '' ? $rawNombre : "CLIENTE USHUAIA #{$idPersona}";

            $rawCuit = $this->cleanUtf8($rs->Fields("Cuit")->Value);
            $digitsOnly = preg_replace('/[^0-9]/', '', $rawCuit);
            if (!empty($digitsOnly)) {
                if (strlen($digitsOnly) === 11 && in_array(substr($digitsOnly, 0, 2), ['20', '23', '24', '27', '30', '33', '34'])) {
                    $dni = substr($digitsOnly, 2, 8);
                } else {
                    $dni = $digitsOnly;
                }
            } else {
                $dni = (string)$idPersona;
            }

            $rawLoc = strtoupper($this->cleanUtf8($rs->Fields("localidad")->Value));
            $idLocalidad = $localidadesMap[$rawLoc] ?? 310; // Default Ushuaia

            $direccion = $this->cleanUtf8($rs->Fields("Calle")->Value);
            $tel1 = $this->cleanUtf8($rs->Fields("Telefonos")->Value);
            $tel2 = $this->cleanUtf8($rs->Fields("telefono2")->Value);
            $email = $this->cleanUtf8($rs->Fields("Email")->Value);
            $limite = (float)($rs->Fields("Limite")->Value ?? 0);
            $saldo = (float)($rs->Fields("Saldo")->Value ?? 0);
            $nroCta = $this->cleanUtf8($rs->Fields("NRO_CTA")->Value ?? '');
            $legajo = $this->cleanUtf8($rs->Fields("LEGAJO")->Value ?? '');
            $comentario = $this->cleanUtf8($rs->Fields("comentario")->Value ?? '');

            $esSuspendido = ((string)$rs->Fields("SUSPENDIDO_CTACTE")->Value === '1' || (string)$rs->Fields("moroso")->Value === '1');
            $estado = $esSuspendido ? 'suspendido' : 'activo';

            $obs = "MDB IDPERSONA: {$idPersona}";
            if ($legajo !== '') $obs .= " | Legajo: {$legajo}";
            if ($comentario !== '') $obs .= " | " . $comentario;

            // Buscar si ya existe por DNI o por obs con MDB IDPERSONA
            $cliente = Cliente::where('dni', $dni)
                ->orWhere('obs', 'LIKE', "MDB IDPERSONA: {$idPersona}%")
                ->first();

            if ($cliente) {
                $cliente->update([
                    'nombre'       => $nombreLimpio,
                    'direccion'    => $direccion ?: $cliente->direccion,
                    'id_localidad' => $idLocalidad,
                    'tel1'         => $tel1 ?: $cliente->tel1,
                    'tel2'         => $tel2 ?: $cliente->tel2,
                    'email'        => $email ?: $cliente->email,
                    'limite'       => $limite > 0 ? $limite : $cliente->limite,
                    'saldo'        => $saldo != 0 ? $saldo : $cliente->saldo,
                    'nro_cuenta'   => $nroCta ?: $cliente->nro_cuenta,
                    'estado'       => $estado,
                ]);
                $actualizados++;
            } else {
                $cliente = Cliente::create([
                    'id_sucursal'  => $idSucursal,
                    'id_localidad' => $idLocalidad,
                    'nombre'       => $nombreLimpio,
                    'dni'          => $dni,
                    'cuit'         => $rawCuit ?: null,
                    'direccion'    => $direccion ?: 'Sin dirección',
                    'tel1'         => $tel1 ?: '0',
                    'tel2'         => $tel2 ?: null,
                    'email'        => $email ?: null,
                    'saldo'        => $saldo,
                    'limite'       => $limite > 0 ? $limite : 500000.00,
                    'estado'       => $estado,
                    'nro_cuenta'   => $nroCta ?: null,
                    'obs'          => $obs,
                ]);
                $insertados++;
            }

            $this->personaToClienteId[$idPersona] = $cliente->id;

            // Manejo de adicional en CREDITOS_PERSONAS
            $adicional = $this->cleanUtf8($rs->Fields("ADICIONAL")->Value ?? '');
            $dniAdicional = preg_replace('/[^0-9]/', '', (string)($rs->Fields("DNIADICIONAL")->Value ?? ''));
            if ($adicional !== '' && $adicional !== '0') {
                $relacionTexto = mb_substr('Adicional: ' . $adicional . ($dniAdicional ? " (DNI {$dniAdicional})" : ''), 0, 250);
                ClienteAutorizado::updateOrCreate([
                    'id_titular'    => $cliente->id,
                    'id_autorizado' => $cliente->id,
                ], [
                    'relacion_aut'     => $relacionTexto,
                    'fecha_autorizado' => now()->toDateString(),
                    'limite_aut'       => $cliente->limite,
                ]);
                $autorizadosCount++;
            }

            $rs->MoveNext();
        }
        $rs->Close();

        DB::commit();

        $this->info("✓ Clientes procesados: {$totalPersonas} (Insertados: {$insertados}, Actualizados: {$actualizados}, Adicionales: {$autorizadosCount})");
    }

    /**
     * IMPORTACIÓN 2: PRODUCTOS, CATEGORÍAS Y SUBCATEGORÍAS
     */
    protected function importProductos(): void
    {
        $this->info("\n--- IMPORTANDO CATEGORÍAS Y SUBCATEGORÍAS ---");

        // 1. Asegurar Categorías de MDB
        $rsCats = $this->conn->Execute("SELECT idcategoria, Categoria FROM PRODUCTOS_CATEGORIA");
        while (!$rsCats->EOF) {
            $catId = (int)$rsCats->Fields("idcategoria")->Value;
            $catNombre = $this->cleanUtf8($rsCats->Fields("Categoria")->Value);
            if ($catNombre !== '') {
                ProductoCategoria::updateOrCreate(['id' => $catId], ['nombre' => $catNombre]);
            }
            $rsCats->MoveNext();
        }
        $rsCats->Close();

        // 2. Asegurar Subcategorías de MDB
        $rsSub = $this->conn->Execute("SELECT idSubcategoria, SubCategoria FROM PRODUCTOS_SUBCATEGORIA");
        while (!$rsSub->EOF) {
            $subId = (int)$rsSub->Fields("idSubcategoria")->Value;
            $subNombre = $this->cleanUtf8($rsSub->Fields("SubCategoria")->Value);
            if ($subNombre !== '') {
                ProductoSubcategoria::updateOrCreate(['id' => $subId], ['nombre' => $subNombre]);
            }
            $rsSub->MoveNext();
        }
        $rsSub->Close();

        $this->info("\n--- IMPORTANDO CATÁLOGO DE PRODUCTOS (15.142 productos) ---");
        $rsProd = $this->conn->Execute("SELECT * FROM [PRODUCTOS]");
        $totalProd = 0;
        $batch = [];
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        while (!$rsProd->EOF) {
            $totalProd++;
            $codigo = $this->cleanUtf8($rsProd->Fields("IdProducto")->Value);
            $descripcion = $this->cleanUtf8($rsProd->Fields("Producto")->Value);
            if ($descripcion === '') {
                $descripcion = "PRODUCTO {$codigo}";
            }

            $catId = (int)($rsProd->Fields("Categoria")->Value ?? 1);
            $subId = (int)($rsProd->Fields("Subcategoria")->Value ?? 1);
            $costo = (float)($rsProd->Fields("Costo")->Value ?? 0);
            $precio = (float)($rsProd->Fields("lista1")->Value ?? 0);
            $obs = $this->cleanUtf8($rsProd->Fields("Obs")->Value ?? '');
            $verStock = (string)($rsProd->Fields("verstock")->Value ?? '1');

            $fechaAlta = $this->parseDate($rsProd->Fields("alta")->Value);
            $fechaVenta = $this->parseDate($rsProd->Fields("venta")->Value);
            $fechaCompra = $this->parseDate($rsProd->Fields("compra")->Value);
            $fechaMod = $this->parseDate($rsProd->Fields("MODIFICACION")->Value);

            $batch[] = [
                'codigo'                   => $codigo,
                'codigo_barra'             => $codigo,
                'descripcion'              => $descripcion,
                'categoria_id'             => $catId > 0 ? $catId : 1,
                'subcategoria_id'          => $subId > 0 ? $subId : 1,
                'proveedor_id'             => null,
                'precio_costo'             => $costo,
                'precio_venta'             => $precio,
                'stock_minimo'             => 5.00,
                'unidad_medida'            => 'UNIDAD',
                'imagen'                   => null,
                'observaciones'            => $obs ?: null,
                'activo'                   => 1,
                'controla_stock'           => ($verStock === '1' || $verStock === '') ? 1 : 0,
                'total_ventas_unidades'    => 0.00,
                'total_ventas_operaciones' => 0,
                'fecha_alta'               => $fechaAlta,
                'fecha_ultima_venta'       => $fechaVenta,
                'fecha_ultima_compra'      => $fechaCompra,
                'fecha_modificacion'       => $fechaMod,
                'created_at'               => $now,
                'updated_at'               => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('productos')->upsert($batch, ['codigo'], [
                    'codigo_barra', 'descripcion', 'categoria_id', 'subcategoria_id', 
                    'precio_costo', 'precio_venta', 'observaciones', 'controla_stock', 
                    'fecha_alta', 'fecha_ultima_venta', 'fecha_ultima_compra', 'fecha_modificacion', 'updated_at'
                ]);
                $batch = [];
                $this->output->write(".");
            }

            $rsProd->MoveNext();
        }
        $rsProd->Close();

        if (!empty($batch)) {
            DB::table('productos')->upsert($batch, ['codigo'], [
                'codigo_barra', 'descripcion', 'categoria_id', 'subcategoria_id', 
                'precio_costo', 'precio_venta', 'observaciones', 'controla_stock', 
                'fecha_alta', 'fecha_ultima_venta', 'fecha_ultima_compra', 'fecha_modificacion', 'updated_at'
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        $this->info("\n✓ Catálogo de productos importado: {$totalProd} registros.");

        $this->importVentasTotales();
    }

    /**
     * IMPORTACIÓN 2.1: TOTALES HISTÓRICOS DE VENTAS DESDE MOVIMIENTO_DETALLE
     */
    protected function importVentasTotales(): void
    {
        $this->info("\n--- CONSOLIDANDO VENTAS HISTÓRICAS DESDE MOVIMIENTO_DETALLE ---");
        try {
            $rs = $this->conn->Execute("
                SELECT idproducto, SUM(cantidad) as total_unidades, COUNT(*) as total_operaciones 
                FROM [MOVIMIENTO_DETALLE] 
                WHERE idproducto IS NOT NULL 
                GROUP BY idproducto
            ");

            DB::statement("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_ventas (
                codigo VARCHAR(100) PRIMARY KEY,
                unidades DECIMAL(12,2),
                operaciones INT
            )");
            DB::statement("TRUNCATE TABLE tmp_ventas");

            $batch = [];
            while (!$rs->EOF) {
                $code = trim((string)$rs->Fields("idproducto")->Value);
                $unidades = max(0.0, (float)($rs->Fields("total_unidades")->Value ?? 0));
                $ops = max(0, (int)($rs->Fields("total_operaciones")->Value ?? 0));
                
                if ($code !== '') {
                    $batch[] = [
                        'codigo' => $code,
                        'unidades' => $unidades,
                        'operaciones' => $ops
                    ];
                }

                if (count($batch) >= 2000) {
                    DB::table('tmp_ventas')->insert($batch);
                    $batch = [];
                }

                $rs->MoveNext();
            }
            if (!empty($batch)) {
                DB::table('tmp_ventas')->insert($batch);
            }
            $rs->Close();

            $affected = DB::update("
                UPDATE productos p
                JOIN tmp_ventas tv ON p.codigo = tv.codigo
                SET p.total_ventas_unidades = tv.unidades,
                    p.total_ventas_operaciones = tv.operaciones
            ");

            $this->info("✓ {$affected} productos actualizados con historial de ventas reales.");
        } catch (\Throwable $e) {
            $this->warn("! No se pudo consolidar MOVIMIENTO_DETALLE: " . $e->getMessage());
        }
    }

    /**
     * IMPORTACIÓN 3: STOCK EN SUCURSAL USHUAIA
     */
    protected function importStock(int $idSucursal): void
    {
        $this->info("\n--- IMPORTANDO STOCK POR PRODUCTO A SUCURSAL USHUAIA (ID: {$idSucursal}) ---");

        // Cargar mapa de codigo -> id de producto en MySQL
        $productosMap = DB::table('productos')->pluck('id', 'codigo')->toArray();

        $rsStock = $this->conn->Execute("SELECT IdProducto, Stock, lista1 FROM [PRODUCTOS] WHERE Stock IS NOT NULL");
        $batch = [];
        $totalStock = 0;
        $now = now()->toDateTimeString();

        while (!$rsStock->EOF) {
            $totalStock++;
            $codigo = trim((string)$rsStock->Fields("IdProducto")->Value);
            $stockVal = (float)($rsStock->Fields("Stock")->Value ?? 0);
            $precio = (float)($rsStock->Fields("lista1")->Value ?? 0);

            if (isset($productosMap[$codigo])) {
                $batch[] = [
                    'producto_id'  => $productosMap[$codigo],
                    'sucursal_id'  => $idSucursal,
                    'stock'        => $stockVal,
                    'stock_minimo' => 5.00,
                    'punto_compra' => 0.00,
                    'precio_venta' => $precio,
                    'ubicacion'    => 'Ushuaia - San Martín',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            if (count($batch) >= 1000) {
                DB::table('productos_stock')->upsert($batch, ['producto_id', 'sucursal_id'], [
                    'stock', 'stock_minimo', 'precio_venta', 'ubicacion', 'updated_at'
                ]);
                $batch = [];
                $this->output->write(".");
            }

            $rsStock->MoveNext();
        }
        $rsStock->Close();

        if (!empty($batch)) {
            DB::table('productos_stock')->upsert($batch, ['producto_id', 'sucursal_id'], [
                'stock', 'stock_minimo', 'precio_venta', 'ubicacion', 'updated_at'
            ]);
        }

        $this->info("\n✓ Stock asignado: {$totalStock} registros sincronizados en sucursal Ushuaia.");
    }

    /**
     * IMPORTACIÓN 4: CREDITOS Y CUOTAS
     */
    protected function importCreditos(int $idSucursal): void
    {
        $this->info("\n--- IMPORTANDO CREDITOS Y CUOTAS (MOVIMIENTO_CREDITOS + MOVIMIENTO) ---");

        // Si el mapa personaToClienteId está vacío, precargarlo desde la BD
        if (empty($this->personaToClienteId)) {
            $this->info("Cargando mapa de clientes existentes...");
            $clientesObs = Cliente::where('obs', 'LIKE', 'MDB IDPERSONA:%')->select('id', 'obs')->get();
            foreach ($clientesObs as $c) {
                if (preg_match('/MDB IDPERSONA:\s*(\d+)/', $c->obs, $m)) {
                    $this->personaToClienteId[(int)$m[1]] = $c->id;
                }
            }

            $dnisMap = Cliente::pluck('id', 'dni')->toArray();
            $rsP = $this->conn->Execute("SELECT IDPERSONA, Cuit FROM PERSONAS");
            while (!$rsP->EOF) {
                $idp = (int)$rsP->Fields("IDPERSONA")->Value;
                if (!isset($this->personaToClienteId[$idp])) {
                    $cuit = preg_replace('/[^0-9]/', '', (string)$rsP->Fields("Cuit")->Value);
                    $dni = (strlen($cuit) === 11 && in_array(substr($cuit, 0, 2), ['20', '23', '24', '27', '30', '33', '34'])) ? substr($cuit, 2, 8) : $cuit;
                    if (!empty($dni) && isset($dnisMap[$dni])) {
                        $this->personaToClienteId[$idp] = $dnisMap[$dni];
                    }
                }
                $rsP->MoveNext();
            }
            $rsP->Close();
            $this->info("Mapa cargado con " . count($this->personaToClienteId) . " clientes vinculados.");
        }

        // Sincronizar lotes de Ushuaia a partir de MOVIMIENTO_CREDITOS.FECHA (-7 días de cierre)
        $ymToPresIdMap = $this->sincronizarLotesUshuaia();
        $defaultPresId = reset($ymToPresIdMap) ?: 210;

        $sql = "SELECT 
                    mc.ID_OPERACION, mc.TOTAL, mc.CUOTA, mc.RESTA, mc.CUOTA_ATRASADA, mc.ATRASADO, mc.FECHA AS FECHA_VENC,
                    m.idcliente, m.fecha AS FECHA_MOV, m.Comprobante, m.ncomprobante, m.estado AS ESTADO_MOV
                FROM MOVIMIENTO_CREDITOS mc
                INNER JOIN MOVIMIENTO m ON mc.ID_OPERACION = m.id_operacion
                WHERE mc.TOTAL > 0 AND mc.CUOTA > 0
                ORDER BY mc.ID_OPERACION ASC";

        $rs = $this->conn->Execute($sql);
        $totalCreditos = 0;
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // Mapa de créditos ya existentes para no duplicar (credito + id_sucursal)
        $existingCreditos = DB::table('creditos')
            ->where('id_sucursal', $idSucursal)
            ->pluck('id', 'credito')
            ->toArray();

        $creditosToInsert = [];
        $creditosActualizados = 0;
        $creditosNuevos = 0;

        while (!$rs->EOF) {
            $totalCreditos++;
            $idOperacion = (string)$rs->Fields("ID_OPERACION")->Value;
            $idPersona = (int)$rs->Fields("idcliente")->Value;

            $idCliente = $this->personaToClienteId[$idPersona] ?? null;
            if (!$idCliente) {
                // Si la persona no está mapeada, saltar
                $rs->MoveNext();
                continue;
            }

            $cantCuotas = (int)$rs->Fields("CUOTA")->Value;
            $valorCuota = (float)$rs->Fields("TOTAL")->Value;
            $cuotasResta = (int)$rs->Fields("RESTA")->Value;
            $importeOriginal = round($valorCuota * $cantCuotas, 2);

            $rawFechaVenc = $rs->Fields("FECHA_VENC")->Value;
            $parsedFechaVenc = $this->parseDate($rawFechaVenc);

            $fechaCredito = $this->parseDate($rs->Fields("FECHA_MOV")->Value) 
                ?: $parsedFechaVenc 
                ?: now()->toDateString();

            // Determinar presentación según la fecha de MOVIMIENTO_CREDITOS
            $ymMc = $parsedFechaVenc ? substr($parsedFechaVenc, 0, 7) : '2009-02';
            if ($ymMc < '2009-02') {
                $ymMc = '2009-02';
            }
            $idPresentacionCredito = $ymToPresIdMap[$ymMc] ?? $defaultPresId;

            $esCancelado = ($cuotasResta <= 0);
            $fechaCancelacion = $esCancelado ? ($parsedFechaVenc ?: $fechaCredito) : null;

            $creditosToInsert[] = [
                'credito_code'      => $idOperacion,
                'id_cliente'        => $idCliente,
                'id_sucursal'       => $idSucursal,
                'id_autorizado'     => null,
                'id_presentacion'   => $idPresentacionCredito,
                'credito'           => $idOperacion,
                'tipo_credito'      => 'C',
                'importe_original'  => $importeOriginal,
                'cantidad_cuotas'   => $cantCuotas,
                'valor_cuota_ref'   => $valorCuota,
                'fecha_credito'     => $fechaCredito,
                'fecha_cancelacion' => $fechaCancelacion,
                'cuotas_resta'      => $cuotasResta,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            if (count($creditosToInsert) >= 1000) {
                $this->processCreditosBatch($creditosToInsert, $existingCreditos, $creditosNuevos, $creditosActualizados);
                $creditosToInsert = [];
                $this->output->write(".");
            }

            $rs->MoveNext();
        }
        $rs->Close();

        if (!empty($creditosToInsert)) {
            $this->processCreditosBatch($creditosToInsert, $existingCreditos, $creditosNuevos, $creditosActualizados);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        $this->info("\n✓ Créditos procesados: {$totalCreditos} (Nuevos: {$creditosNuevos}, Actualizados: {$creditosActualizados}) con sus respectivas cuotas.");
    }

    /**
     * Procesa e inserta un lote de créditos y sus cuotas correspondientes con transacción en bloque
     */
    protected function processCreditosBatch(array $batch, array &$existingCreditos, int &$nuevos, int &$actualizados): void
    {
        $cuotasData = [];
        $now = now()->toDateTimeString();
        $creditoIdsToClean = [];

        DB::transaction(function () use ($batch, &$existingCreditos, &$nuevos, &$actualizados, &$cuotasData, &$creditoIdsToClean, $now) {
            foreach ($batch as $item) {
                $code = $item['credito_code'];
                $cuotasResta = $item['cuotas_resta'];
                unset($item['credito_code'], $item['cuotas_resta']);

                if (isset($existingCreditos[$code])) {
                    $creditoId = $existingCreditos[$code];
                    DB::table('creditos')->where('id', $creditoId)->update($item);
                    $actualizados++;
                } else {
                    $creditoId = DB::table('creditos')->insertGetId($item);
                    $existingCreditos[$code] = $creditoId;
                    $nuevos++;
                }

                $creditoIdsToClean[] = $creditoId;

                $cantCuotas = $item['cantidad_cuotas'];
                $valorCuota = $item['valor_cuota_ref'];
                $cuotasPagadas = max(0, $cantCuotas - $cuotasResta);

                for ($i = 1; $i <= $cantCuotas; $i++) {
                    $esPagada = ($i <= $cuotasPagadas);
                    $cuotasData[] = [
                        'id_credito'        => $creditoId,
                        'num_cuota'         => $i,
                        'valor_base'        => $valorCuota,
                        'intereses'         => 0.00,
                        'entrego'           => $esPagada ? $valorCuota : 0.00,
                        'saldo_actual'      => $esPagada ? 0.00 : $valorCuota,
                        'fecha_cobro'       => $esPagada ? $item['fecha_credito'] : null,
                        'medio_pago'        => $esPagada ? 'BTF' : null,
                        'referencia_pago'   => null,
                        'banco_envio'       => 'BTF',
                        'fecha_ultimo_envio'=> null,
                        'estado_bancario'   => $esPagada ? 'COB' : null,
                        'obs_devolucion'    => null,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }

            // Limpieza y carga de cuotas en bloque
            if (!empty($creditoIdsToClean)) {
                DB::table('cuotas')->whereIn('id_credito', $creditoIdsToClean)->delete();
            }

            if (!empty($cuotasData)) {
                // Insertar cuotas en chunks de 1000
                foreach (array_chunk($cuotasData, 1000) as $cuotasChunk) {
                    DB::table('cuotas')->insert($cuotasChunk);
                }
            }
        });
    }

    /**
     * Parsea fechas que vienen de ADODB COM variant
     */
    protected function parseDate(mixed $rawDate): ?string
    {
        if (empty($rawDate)) return null;
        if (is_object($rawDate)) $rawDate = (string)$rawDate;
        $rawDate = trim($rawDate);
        if ($rawDate === '' || $rawDate === 'NULL') return null;

        try {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $rawDate, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }
            $d = new \DateTime($rawDate);
            return $d->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Sanitiza y convierte strings desde codificación Access Windows-1252 a UTF-8 válido
     */
    protected function cleanUtf8(mixed $val): string
    {
        if ($val === null) return '';
        $val = (string)$val;
        if (!mb_check_encoding($val, 'UTF-8')) {
            $val = mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
        }
        $val = str_replace(["\xB4", "´", "`"], "'", $val);
        // Quitar caracteres nulos o no imprimibles
        $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
        return trim(preg_replace('/\s+/u', ' ', $val));
    }

    /**
     * Sincroniza las presentaciones históricas y vigentes de Tierra del Fuego (id_provincia = 2)
     * basándose en las fechas mensuales de MOVIMIENTO_CREDITOS (fecha de cierre = FECHA - 7 días).
     * Retorna mapa: ['YYYY-MM' => id_presentacion]
     */
    protected function sincronizarLotesUshuaia(): array
    {
        $this->info("Sincronizando lotes de Ushuaia a partir de MOVIMIENTO_CREDITOS...");

        $sql = "SELECT FECHA, COUNT(*) as cant 
                FROM MOVIMIENTO_CREDITOS 
                WHERE TOTAL > 0 AND CUOTA > 0 AND FECHA IS NOT NULL 
                GROUP BY FECHA";

        $rs = $this->conn->Execute($sql);
        $fechasRaw = [];
        while (!$rs->EOF) {
            $rawFecha = $rs->Fields("FECHA")->Value;
            $cant = (int)$rs->Fields("cant")->Value;
            $parsed = $this->parseDate($rawFecha);
            if ($parsed) {
                if (!isset($fechasRaw[$parsed])) $fechasRaw[$parsed] = 0;
                $fechasRaw[$parsed] += $cant;
            }
            $rs->MoveNext();
        }
        $rs->Close();

        ksort($fechasRaw);

        // Agrupar por mes; créditos previos a 2009-02 se consolidan en el lote 1
        $monthlyLotes = [];
        foreach ($fechasRaw as $fechaStr => $cant) {
            $ym = substr($fechaStr, 0, 7);
            if ($ym < '2009-02') {
                $ym = '2009-02';
            }
            if (!isset($monthlyLotes[$ym])) {
                $monthlyLotes[$ym] = [
                    'ym'        => $ym,
                    'max_fecha' => $fechaStr,
                ];
            }
            if ($fechaStr > $monthlyLotes[$ym]['max_fecha']) {
                $monthlyLotes[$ym]['max_fecha'] = $fechaStr;
            }
        }

        ksort($monthlyLotes);

        $numeroLote = 1;
        $ymToLoteNum = [];
        $lotesData = [];
        $prevCierre = null;

        foreach ($monthlyLotes as $ym => $info) {
            $dtFechaMdb = new \DateTime($info['max_fecha']);
            $dtCierre = clone $dtFechaMdb;
            $dtCierre->modify('-7 days');

            if ($prevCierre === null) {
                $dtInicio = clone $dtCierre;
                $dtInicio->modify('-1 month +1 day');
            } else {
                $dtInicio = clone $prevCierre;
                $dtInicio->modify('+1 day');
            }
            $prevCierre = clone $dtCierre;

            // El último lote generado (Lote 212 / Nov 2026) es el lote activo abierto (inició el 25/10/2026)
            // Lote 211 cerró el 24/10/2026.
            $esUltimoLote = ($numeroLote === count($monthlyLotes));
            $fechaFin = $esUltimoLote ? null : $dtCierre->format('Y-m-d');

            $lotesData[$numeroLote] = [
                'id_provincia'    => 2,
                'numero'          => $numeroLote,
                'fecha_inicio'    => $dtInicio->format('Y-m-d'),
                'fecha_fin'       => $fechaFin,
                'interes_credito' => 0.00,
                'interes_mora'    => 0.00,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            $ymToLoteNum[$ym] = $numeroLote;
            $numeroLote++;
        }

        // Consultar presentaciones existentes de TDF en MySQL
        $presExistentes = DB::table('presentaciones')
            ->where('id_provincia', 2)
            ->pluck('id', 'numero')
            ->toArray();

        foreach ($lotesData as $num => $data) {
            if (isset($presExistentes[$num])) {
                DB::table('presentaciones')
                    ->where('id', $presExistentes[$num])
                    ->update([
                        'fecha_inicio' => $data['fecha_inicio'],
                        'fecha_fin'    => $data['fecha_fin'],
                        'updated_at'   => now(),
                    ]);
            } else {
                $insertedId = DB::table('presentaciones')->insertGetId($data);
                $presExistentes[$num] = $insertedId;
            }
        }

        Presentacion::clearActiveNumbersCache();

        // Mapear cada YM a su id_presentacion en MySQL
        $ymToPresId = [];
        foreach ($ymToLoteNum as $ym => $num) {
            if (isset($presExistentes[$num])) {
                $ymToPresId[$ym] = $presExistentes[$num];
            }
        }

        $this->info("✓ Sincronizados " . count($lotesData) . " lotes de TDF. Lote activo: #212 (Inició 25/10/2026, actualmente abierto).");
        return $ymToPresId;
    }
}

