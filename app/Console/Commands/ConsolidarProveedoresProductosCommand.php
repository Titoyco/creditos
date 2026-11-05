<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Proveedor;
use App\Models\Producto;
use COM;
use Exception;

class ConsolidarProveedoresProductosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:consolidar-proveedores-productos 
                            {--path= : Ruta al archivo db.mdb (por defecto creditos/db.mdb)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa y consolida la vinculación entre proveedores y productos desde db.mdb hacia lamarina_v4';

    protected ?COM $conn = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $dbPath = $this->option('path') ?: base_path('db.mdb');

        if (!file_exists($dbPath)) {
            $this->error("No se encontró el archivo Access en: {$dbPath}");
            return 1;
        }

        $this->info("==================================================================");
        $this->info("  CONSOLIDACIÓN DE PROVEEDORES Y PRODUCTOS: Access MDB -> MySQL   ");
        $this->info("  Archivo MDB: {$dbPath}");
        $this->info("  Base MySQL: " . config('database.connections.mysql.database'));
        $this->info("==================================================================");

        try {
            $this->connectMdb($dbPath);
        } catch (Exception $e) {
            $this->error("Error conectando a Access db.mdb: " . $e->getMessage());
            return 1;
        }

        try {
            // 1. Importar y consolidar catálogo de proveedores faltantes
            $nuevosProveedores = $this->consolidarProveedores();

            // 2. Vincular proveedores en la tabla productos
            $productosActualizados = $this->vincularProveedoresEnProductos();

            $elapsed = round(microtime(true) - $startTime, 2);
            $this->info("\n==================================================================");
            $this->info("✓ PROCESO COMPLETADO EXITOSAMENTE EN {$elapsed}s");
            $this->info("  - Proveedores importados/consolidados: {$nuevosProveedores}");
            $this->info("  - Total productos vinculados con proveedor: {$productosActualizados}");
            $this->info("==================================================================");

            return 0;
        } catch (\Throwable $e) {
            $this->error("\nError durante la consolidación: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        } finally {
            $this->disconnectMdb();
        }
    }

    protected function connectMdb(string $dbPath): void
    {
        $providers = [
            'Microsoft.ACE.OLEDB.12.0',
            'Microsoft.ACE.OLEDB.16.0',
            'Microsoft.Jet.OLEDB.4.0',
        ];

        foreach ($providers as $prov) {
            try {
                $c = new COM("ADODB.Connection");
                $connStr = "Provider={$prov};Data Source={$dbPath};Persist Security Info=False;";
                $c->Open($connStr);
                $this->conn = $c;
                $this->info("✓ Conexión establecida con {$prov}.");
                return;
            } catch (\Throwable $e) {
                // Probar siguiente proveedor
            }
        }

        throw new Exception("No se pudo conectar a Access db.mdb con ninguno de los proveedores OLEDB disponibles.");
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
     * Paso 1: Importa cualquier proveedor que esté en PERSONAS o referenciado en PRODUCTOS y falte en MySQL.
     */
    protected function consolidarProveedores(): int
    {
        $this->info("\n[1/2] Verificando catálogo de proveedores en PERSONAS...");

        // Proveedores existentes en MySQL
        $existingProvIds = DB::table('proveedores')->pluck('id')->flip()->toArray();
        $this->info("  - Proveedores existentes en MySQL: " . count($existingProvIds));

        // Consultar personas de categoría PROVEEDOR o referenciadas en PRODUCTOS
        $rs = $this->conn->Execute("
            SELECT IDPERSONA, LEGAJO, RSOCIAL, Cuit, Telefonos, telefono2, Calle, Email, web, Categoria 
            FROM PERSONAS 
            WHERE Categoria = 'PROVEEDOR' 
               OR IDPERSONA IN (SELECT DISTINCT proveedor FROM PRODUCTOS WHERE proveedor > 0)
        ");

        $batch = [];
        $ahora = now()->toDateTimeString();

        while (!$rs->EOF) {
            $id = (int)$rs->Fields('IDPERSONA')->Value;
            if ($id > 0 && !isset($existingProvIds[$id])) {
                $nombre = strtoupper($this->cleanUtf8($rs->Fields('RSOCIAL')->Value ?? ''));
                if (!empty($nombre)) {
                    $leg = $this->cleanUtf8($rs->Fields('LEGAJO')->Value ?? '');
                    $legFormateado = is_numeric($leg) ? str_pad($leg, 3, '0', STR_PAD_LEFT) : ($leg ?: str_pad((string)$id, 3, '0', STR_PAD_LEFT));

                    // Cuit limpio
                    $rawCuit = $this->cleanUtf8($rs->Fields('Cuit')->Value ?? '');
                    $cuit = preg_replace('/[^0-9]/', '', $rawCuit);
                    if (strlen($cuit) < 8) {
                        $cuit = null;
                    }

                    $batch[] = [
                        'id' => $id,
                        'cuit' => $cuit,
                        'legajo' => $legFormateado,
                        'nombre' => $nombre,
                        'contacto' => null,
                        'direccion' => $this->cleanUtf8($rs->Fields('Calle')->Value ?? '') ?: null,
                        'id_localidad' => null,
                        'id_iva' => null,
                        'tel1' => $this->cleanUtf8($rs->Fields('Telefonos')->Value ?? '') ?: null,
                        'tel2' => $this->cleanUtf8($rs->Fields('telefono2')->Value ?? '') ?: null,
                        'email' => $this->cleanUtf8($rs->Fields('Email')->Value ?? '') ?: null,
                        'web' => $this->cleanUtf8($rs->Fields('web')->Value ?? '') ?: null,
                        'saldo' => 0.00,
                        'observaciones' => 'Importado desde Access PERSONAS (db.mdb)',
                        'activo' => 1,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                    $existingProvIds[$id] = true;
                }
            }
            $rs->MoveNext();
        }
        $rs->Close();

        if (!empty($batch)) {
            // Desactivar temporalmente auto-increment check para respetar IDs exactos de Access
            DB::table('proveedores')->insert($batch);
            $this->info("  ✓ Se insertaron " . count($batch) . " proveedores faltantes en MySQL.");
            return count($batch);
        } else {
            $this->info("  ✓ Todos los proveedores ya se encontraban registrados en MySQL.");
            return 0;
        }
    }

    /**
     * Paso 2: Actualiza proveedor_id en la tabla productos de MySQL según PRODUCTOS de db.mdb.
     */
    protected function vincularProveedoresEnProductos(): int
    {
        $this->info("\n[2/2] Vinculando proveedores a la tabla productos...");

        // Crear tabla temporal de mapeo
        DB::statement("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_productos_proveedor (
            codigo VARCHAR(100) NOT NULL PRIMARY KEY,
            proveedor_id INT NOT NULL
        )");
        DB::statement("TRUNCATE TABLE tmp_productos_proveedor");

        // Leer IdProducto y proveedor de Access
        $rs = $this->conn->Execute("SELECT IdProducto, proveedor FROM [PRODUCTOS] WHERE proveedor IS NOT NULL AND proveedor > 0");

        $batch = [];
        $totalLeidos = 0;

        while (!$rs->EOF) {
            $totalLeidos++;
            $codigo = trim((string)$rs->Fields('IdProducto')->Value);
            $provId = (int)$rs->Fields('proveedor')->Value;

            if ($codigo !== '' && $provId > 0) {
                $batch[] = [
                    'codigo' => $codigo,
                    'proveedor_id' => $provId,
                ];
            }

            if (count($batch) >= 2000) {
                DB::table('tmp_productos_proveedor')->insertOrIgnore($batch);
                $batch = [];
                $this->output->write(".");
            }

            $rs->MoveNext();
        }
        $rs->Close();

        if (!empty($batch)) {
            DB::table('tmp_productos_proveedor')->insertOrIgnore($batch);
        }

        $this->info("\n  - Registros de vinculación cargados en tabla temporal: {$totalLeidos}");

        // Actualizar tabla productos en MySQL en una sola consulta optimizada
        $affected = DB::update("
            UPDATE productos p
            INNER JOIN tmp_productos_proveedor tp ON p.codigo = tp.codigo
            INNER JOIN proveedores pr ON tp.proveedor_id = pr.id
            SET p.proveedor_id = tp.proveedor_id,
                p.updated_at = NOW()
        ");

        DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_productos_proveedor");

        $this->info("  ✓ Productos actualizados con su proveedor en MySQL: {$affected}");
        return (int)$affected;
    }

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
}
