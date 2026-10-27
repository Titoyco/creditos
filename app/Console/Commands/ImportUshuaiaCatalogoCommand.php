<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\CondicionIva;
use App\Models\Sucursal;
use PDO;
use Exception;

class ImportUshuaiaCatalogoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-ushuaia-catalogo 
                            {--source=ushuaia : Nombre de la base de datos de origen}
                            {--fresh : Trunca y limpia las tablas de catálogo y stock antes de importar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa el catálogo de productos, categorías, subcategorías, proveedores y stock desde la base de datos ushuaia hacia lamarina_v4';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $start = microtime(true);
        $sourceDb = $this->option('source') ?: 'ushuaia';
        $fresh = true; // Siempre limpia y re-importa de manera consistente

        $this->info("==================================================================");
        $this->info("   IMPORTACIÓN DE CATÁLOGO Y STOCK: {$sourceDb} -> lamarina_v4   ");
        $this->info("==================================================================");

        // 1. Conexión a la base de datos de origen (ushuaia)
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username', 'root');
        $pass = config('database.connections.mysql.password', '');

        try {
            $sourcePdo = new PDO("mysql:host={$host};port={$port};dbname={$sourceDb};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Exception $e) {
            $this->error("Error conectando a la base de datos de origen '{$sourceDb}': " . $e->getMessage());
            return 1;
        }

        $now = now();

        // 2. Limpieza si se solicita --fresh
        if ($fresh) {
            $this->warn("Limpiando tablas de catálogo y stock previas en lamarina_v4...");
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
            DB::table('productos_movimientos')->truncate();
            DB::table('productos_stock')->truncate();
            DB::table('productos')->truncate();
            DB::table('productos_subcategorias')->truncate();
            DB::table('productos_categorias')->truncate();
            DB::table('proveedores')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            $this->info("✓ Tablas limpiadas correctamente.");
        }

        // 3. Asegurar Condiciones de IVA básicas
        $this->info("Verificando Condiciones de IVA...");
        $condiciones = [
            ['id' => 1, 'nombre' => 'RESPONSABLE INSCRIPTO', 'codigo_afip' => '01'],
            ['id' => 2, 'nombre' => 'MONOTRIBUTO', 'codigo_afip' => '06'],
            ['id' => 3, 'nombre' => 'EXENTO', 'codigo_afip' => '04'],
            ['id' => 4, 'nombre' => 'CONSUMIDOR FINAL', 'codigo_afip' => '05'],
        ];
        foreach ($condiciones as $cond) {
            CondicionIva::updateOrCreate(['id' => $cond['id']], $cond);
        }

        // 4. Importar Categorías
        $this->info("Importando categorías desde {$sourceDb}.productos_categoria...");
        $stmtCats = $sourcePdo->query("SELECT idcategoria, Categoria FROM productos_categoria ORDER BY idcategoria ASC");
        $rawCats = $stmtCats->fetchAll();
        $validCatIds = [];

        foreach ($rawCats as $c) {
            $catId = (int)$c['idcategoria'];
            $nombre = trim(preg_replace('/\s+/', ' ', $c['Categoria']));
            if ($nombre === '') {
                $nombre = "CATEGORIA {$catId}";
            }

            DB::table('productos_categorias')->updateOrInsert(
                ['id' => $catId],
                [
                    'nombre' => $nombre,
                    'descripcion' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $validCatIds[$catId] = true;
        }
        $this->info("✓ " . count($validCatIds) . " categorías importadas.");

        // 5. Importar Subcategorías
        $this->info("Importando subcategorías desde {$sourceDb}.productos_subcategoria...");
        $stmtSubcats = $sourcePdo->query("SELECT idSubcategoria, SubCategoria FROM productos_subcategoria ORDER BY idSubcategoria ASC");
        $rawSubcats = $stmtSubcats->fetchAll();
        $validSubcatIds = [];

        foreach ($rawSubcats as $s) {
            $subcatId = (int)$s['idSubcategoria'];
            $nombre = trim(preg_replace('/\s+/', ' ', $s['SubCategoria']));
            if ($nombre === '') {
                $nombre = "SUBCATEGORIA {$subcatId}";
            }

            DB::table('productos_subcategorias')->updateOrInsert(
                ['id' => $subcatId],
                [
                    'nombre' => $nombre,
                    'descripcion' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $validSubcatIds[$subcatId] = true;
        }
        $this->info("✓ " . count($validSubcatIds) . " subcategorías importadas.");

        // 6. Importar Proveedores
        $this->info("Importando proveedores referenciados en productos...");
        $stmtProvIds = $sourcePdo->query("SELECT DISTINCT proveedor FROM productos WHERE proveedor IS NOT NULL AND proveedor > 0");
        $provIds = $stmtProvIds->fetchAll(PDO::FETCH_COLUMN);

        $validProvIds = [];
        if (!empty($provIds)) {
            $inClause = implode(',', array_map('intval', $provIds));
            $stmtPers = $sourcePdo->query("SELECT IDPERSONA, legajo, RSOCIAL, Contacto, Calle, Telefonos, telefono2, Tipoiva, Cuit, Email, web, Saldo FROM personas WHERE IDPERSONA IN ({$inClause})");
            $rawPers = $stmtPers->fetchAll();

            $provBatch = [];
            foreach ($rawPers as $p) {
                $pId = (int)$p['IDPERSONA'];
                if (isset($validProvIds[$pId])) {
                    continue;
                }
                $nombre = trim(preg_replace('/\s+/', ' ', $p['RSOCIAL']));
                if ($nombre === '') {
                    $nombre = "PROVEEDOR {$pId}";
                }

                // Sanitizar CUIT
                $cuitDigits = preg_replace('/\D/', '', (string)$p['Cuit']);
                $cuit = (strlen($cuitDigits) >= 10 && strlen($cuitDigits) <= 11 && !in_array($cuitDigits, ['123', '0', '1'])) ? $cuitDigits : null;

                // Determinar Condición IVA
                $tipoIva = strtoupper((string)$p['Tipoiva']);
                $idIva = 4; // Consumidor Final default
                if (str_contains($tipoIva, 'RESP') || str_contains($tipoIva, 'INSC')) {
                    $idIva = 1;
                } elseif (str_contains($tipoIva, 'MONO')) {
                    $idIva = 2;
                } elseif (str_contains($tipoIva, 'EXEN')) {
                    $idIva = 3;
                }

                // Sanitizar Email
                $email = trim((string)$p['Email']);
                if (in_array(strtolower($email), ['a@a.com', '0', 'no', 's/e', '']) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = null;
                }

                // Legajo (3 dígitos)
                $rawLegajo = trim((string)($p['legajo'] ?? ''));
                $legajo = ($rawLegajo !== '' && is_numeric($rawLegajo)) ? str_pad($rawLegajo, 3, '0', STR_PAD_LEFT) : null;

                $provBatch[] = [
                    'id' => $pId,
                    'legajo' => $legajo,
                    'nombre' => $nombre,
                    'cuit' => $cuit,
                    'contacto' => trim((string)$p['Contacto']) ?: null,
                    'direccion' => trim((string)$p['Calle']) ?: null,
                    'id_localidad' => null,
                    'id_iva' => $idIva,
                    'tel1' => trim((string)$p['Telefonos']) ?: null,
                    'tel2' => trim((string)$p['telefono2']) ?: null,
                    'email' => $email,
                    'web' => trim((string)$p['web']) ?: null,
                    'saldo' => (float)($p['Saldo'] ?? 0),
                    'observaciones' => null,
                    'activo' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $validProvIds[$pId] = true;
            }

            foreach (array_chunk($provBatch, 200) as $chunk) {
                DB::table('proveedores')->insert($chunk);
            }
        }
        $this->info("✓ " . count($validProvIds) . " proveedores importados.");

        // 7. Leer Productos desde ushuaia.productos
        $this->info("Leyendo productos desde {$sourceDb}.productos...");
        $stmtProds = $sourcePdo->query("SELECT * FROM productos ORDER BY pos ASC");
        
        $productsByCode = [];
        $specialCodes = ['1', '2', '100'];

        while ($r = $stmtProds->fetch()) {
            $code = trim((string)$r['IdProducto']);
            if ($code === '') {
                continue;
            }

            // Normalizar descripción
            $desc = trim(preg_replace('/\s+/', ' ', (string)$r['Producto']));
            if ($desc === '') {
                $desc = "PRODUCTO {$code}";
            }

            // Detectar productos no inventariables / conceptos genéricos
            $upperDesc = strtoupper($desc);
            $isSpecial = in_array($code, $specialCodes) 
                || in_array($upperDesc, ['VARIOS', 'GASTOS DE FINANCIACION', 'ENTREGA DE SENA', 'ENTREGA DE SEÑA', 'SEÑA', 'SENA'])
                || ((int)($r['verstock'] ?? 1) === 0 && in_array($code, $specialCodes));

            $catId = isset($validCatIds[(int)$r['Categoria']]) ? (int)$r['Categoria'] : null;
            $subcatId = isset($validSubcatIds[(int)$r['Subcategoria']]) ? (int)$r['Subcategoria'] : null;
            $provId = isset($validProvIds[(int)$r['proveedor']]) ? (int)$r['proveedor'] : null;

            $costo = $isSpecial ? 0.0 : max(0.0, (float)($r['Costo'] ?? 0));
            $precio = $isSpecial ? 0.0 : max(0.0, (float)($r['lista1'] ?? 0));
            
            // Si es especial, stock = 0. Si es físico, clamp de negativos a 0.
            $rawStock = (float)($r['Stock'] ?? 0);
            $stockUshuaia = $isSpecial ? 0.0 : max(0.0, $rawStock);

            // Sanitizar fechas históricas
            $alta = (!empty($r['alta']) && !str_starts_with((string)$r['alta'], '0000')) ? (string)$r['alta'] : null;
            $venta = (!empty($r['venta']) && !str_starts_with((string)$r['venta'], '0000')) ? (string)$r['venta'] : null;
            $compra = (!empty($r['compra']) && !str_starts_with((string)$r['compra'], '0000')) ? (string)$r['compra'] : null;
            $mod = (!empty($r['MODIFICACION']) && !str_starts_with((string)$r['MODIFICACION'], '0000')) ? (string)$r['MODIFICACION'] : null;

            // Al estar ordenado por pos ASC, si hay duplicado se sobreescribe con el más reciente
            $productsByCode[$code] = [
                'codigo' => $code,
                'codigo_barra' => $code,
                'descripcion' => $desc,
                'categoria_id' => $catId,
                'subcategoria_id' => $subcatId,
                'proveedor_id' => $provId,
                'precio_costo' => $costo,
                'precio_venta' => $precio,
                'stock_minimo' => 2,
                'unidad_medida' => 'UNIDAD',
                'imagen' => null,
                'observaciones' => trim((string)($r['Obs'] ?? '')) ?: null,
                'activo' => 1,
                'controla_stock' => $isSpecial ? 0 : 1,
                'fecha_alta' => $alta,
                'fecha_ultima_venta' => $venta,
                'fecha_ultima_compra' => $compra,
                'fecha_modificacion' => $mod,
                'stock_ushuaia' => $stockUshuaia,
                'created_at' => $alta ?: $now,
                'updated_at' => $mod ?: $now,
            ];
        }

        $totalUnique = count($productsByCode);
        $this->info("✓ Procesados {$totalUnique} productos únicos para inserción.");

        // 8. Insertar Productos en lotes
        $this->info("Insertando productos en lamarina_v4.productos...");
        $bar = $this->output->createProgressBar($totalUnique);
        $bar->start();

        $prodChunks = array_chunk(array_values($productsByCode), 500);
        foreach ($prodChunks as $chunk) {
            $insertBatch = array_map(function ($item) {
                $copy = $item;
                unset($copy['stock_ushuaia']); // Solo para tabla stock
                return $copy;
            }, $chunk);

            DB::table('productos')->insert($insertBatch);
            $bar->advance(count($chunk));
        }
        $bar->finish();
        $this->newLine();
        $this->info("✓ Catálogo de productos insertado exitosamente.");

        // 9. Mapear Códigos a IDs generados
        $this->info("Mapeando IDs para generar stock de sucursales...");
        $codeToId = DB::table('productos')->pluck('id', 'codigo')->toArray();

        // Obtener sucursales
        $sucursales = Sucursal::orderBy('id')->get();
        if ($sucursales->isEmpty()) {
            $this->warn("No se encontraron sucursales registradas. Se usarán IDs 1 a 4 por defecto.");
            $sucursalIds = [1, 2, 3, 4];
        } else {
            $sucursalIds = $sucursales->pluck('id')->toArray();
        }

        // Sucursal Ushuaia San Martín es id = 1
        $idSucUshuaiaSanMartin = 1;

        // 10. Crear registros de stock para las 4 sucursales
        $this->info("Generando stock para las sucursales (Ushuaia San Martín, Antártida, Trelew, Interior)...");
        $totalStockRows = count($codeToId) * count($sucursalIds);
        $barStock = $this->output->createProgressBar($totalStockRows);
        $barStock->start();

        $stockBatch = [];
        $stockBatchCount = 0;

        foreach ($productsByCode as $code => $prodData) {
            if (!isset($codeToId[$code])) {
                continue;
            }
            $pId = $codeToId[$code];
            $stockUsh = $prodData['stock_ushuaia'];

            foreach ($sucursalIds as $sucId) {
                // Solo la sucursal 1 (Ushuaia San Martín) recibe el stock real de la base de datos ushuaia
                $stockVal = ($sucId == $idSucUshuaiaSanMartin) ? $stockUsh : 0.0;

                $stockBatch[] = [
                    'producto_id' => $pId,
                    'sucursal_id' => $sucId,
                    'stock' => $stockVal,
                    'stock_minimo' => ($sucId == $idSucUshuaiaSanMartin) ? 2 : 0,
                    'punto_compra' => ($sucId == $idSucUshuaiaSanMartin) ? 5 : 0,
                    'precio_venta' => null,
                    'ubicacion' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $stockBatchCount++;
                if (count($stockBatch) >= 1000) {
                    DB::table('productos_stock')->insert($stockBatch);
                    $barStock->advance(count($stockBatch));
                    $stockBatch = [];
                }
            }
        }

        if (!empty($stockBatch)) {
            DB::table('productos_stock')->insert($stockBatch);
            $barStock->advance(count($stockBatch));
        }
        $barStock->finish();
        $this->newLine();
        $this->info("✓ {$stockBatchCount} registros de stock creados.");

        // 11. Asegurar producto ART-009999 para compatibilidad
        if (!isset($codeToId['ART-009999'])) {
            $catVarios = DB::table('productos_categorias')->where('nombre', 'LIKE', '%VARIOS%')->value('id');
            $artVariosId = DB::table('productos')->insertGetId([
                'codigo' => 'ART-009999',
                'codigo_barra' => 'VARIOS',
                'descripcion' => 'ARTICULO GENERAL / VENTA VARIOS',
                'categoria_id' => $catVarios,
                'subcategoria_id' => null,
                'proveedor_id' => null,
                'precio_costo' => 0.00,
                'precio_venta' => 0.00,
                'stock_minimo' => 0,
                'unidad_medida' => 'UNIDAD',
                'activo' => 1,
                'controla_stock' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($sucursalIds as $sucId) {
                DB::table('productos_stock')->insert([
                    'producto_id' => $artVariosId,
                    'sucursal_id' => $sucId,
                    'stock' => 0,
                    'stock_minimo' => 0,
                    'punto_compra' => 0,
                    'precio_venta' => null,
                    'ubicacion' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->info("✓ Producto genérico auxiliar ART-009999 garantizado con stock 0.");
        }

        // Garantizar cobertura completa de combinaciones producto-sucursal
        DB::statement("
            INSERT INTO productos_stock (producto_id, sucursal_id, stock, stock_minimo, punto_compra, created_at, updated_at)
            SELECT p.id, s.id, 0, 0, 0, NOW(), NOW()
            FROM productos p
            CROSS JOIN sucursales s
            LEFT JOIN productos_stock ps ON ps.producto_id = p.id AND ps.sucursal_id = s.id
            WHERE ps.id IS NULL
        ");

        // 12. Importar historial de movimientos (Kardex) desde ushuaia.productos_stock
        $hasStockMovsTable = $sourcePdo->query("SHOW TABLES LIKE 'productos_stock'")->fetchColumn();
        $importedMovs = 0;
        if ($hasStockMovsTable) {
            $totalMovs = (int)$sourcePdo->query("SELECT COUNT(*) FROM productos_stock")->fetchColumn();
            $this->info("Importando historial de {$totalMovs} movimientos desde {$sourceDb}.productos_stock...");
            
            $barMovs = $this->output->createProgressBar($totalMovs);
            $barMovs->start();

            // Usar unbuffered query para óptimo rendimiento de memoria en streams grandes
            $unbufferedPdo = new PDO("mysql:host={$host};port={$port};dbname={$sourceDb};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ]);

            $stmtMovs = $unbufferedPdo->query("SELECT fecha, idproducto, cantidad, obs, stock, DETALLE, IDUSUARIO FROM productos_stock ORDER BY id ASC");
            
            $movBatch = [];
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

            while ($m = $stmtMovs->fetch(PDO::FETCH_ASSOC)) {
                $code = trim((string)$m['idproducto']);
                if (!isset($codeToId[$code])) {
                    $barMovs->advance();
                    continue;
                }

                $pId = $codeToId[$code];
                $obs = (string)($m['obs'] ?? '');
                $detalle = (string)($m['DETALLE'] ?? '');
                $cant = max(-999999.0, min(999999.0, (float)($m['cantidad'] ?? 0)));
                $stockNuevo = max(-999999.0, min(999999.0, (float)($m['stock'] ?? 0)));

                if (stripos($obs, 'FACTURA') !== false) {
                    $tipo = 'venta';
                } elseif (stripos($obs, 'CREDITO') !== false || stripos($obs, 'DEVOLUCION') !== false) {
                    $tipo = 'devolucion';
                } elseif (stripos($obs, 'ALTA') !== false) {
                    $tipo = 'alta';
                } elseif (stripos($obs, 'BAJA') !== false) {
                    $tipo = 'baja';
                } elseif (stripos($obs, 'AJUSTE') !== false || stripos($detalle, 'AJUSTE') !== false) {
                    $tipo = 'ajuste';
                } elseif (stripos($obs, 'TRELEW') !== false || stripos($obs, 'TW') !== false || stripos($obs, 'TRASLADO') !== false) {
                    $tipo = 'traslado_salida';
                } else {
                    $tipo = ($cant < 0 ? 'venta' : 'alta');
                }

                $f = (!empty($m['fecha']) && !str_starts_with($m['fecha'], '0000')) ? $m['fecha'] : $now;

                $movBatch[] = [
                    'producto_id' => $pId,
                    'sucursal_id' => $idSucUshuaiaSanMartin,
                    'tipo' => $tipo,
                    'cantidad' => $cant,
                    'stock_anterior' => 0,
                    'stock_nuevo' => $stockNuevo,
                    'precio_costo' => null,
                    'precio_venta' => null,
                    'usuario_id' => 1,
                    'comprobante' => mb_substr(trim($obs), 0, 100) ?: null,
                    'motivo' => mb_substr(trim($detalle), 0, 255) ?: null,
                    'observaciones' => null,
                    'created_at' => $f,
                    'updated_at' => $f,
                ];

                $importedMovs++;
                if (count($movBatch) >= 2000) {
                    DB::table('productos_movimientos')->insert($movBatch);
                    $barMovs->advance(count($movBatch));
                    $movBatch = [];
                }
            }

            if (!empty($movBatch)) {
                DB::table('productos_movimientos')->insert($movBatch);
                $barMovs->advance(count($movBatch));
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            $barMovs->finish();
            $this->newLine();
            $this->info("✓ {$importedMovs} movimientos históricos importados en productos_movimientos.");

            $this->info(" -> Consolidando volumen histórico de ventas por producto...");
            DB::statement("
                UPDATE productos p
                JOIN (
                    SELECT producto_id, ABS(SUM(cantidad)) as vendidas, COUNT(*) as ops
                    FROM productos_movimientos
                    WHERE tipo = 'venta'
                    GROUP BY producto_id
                ) m ON p.id = m.producto_id
                SET p.total_ventas_unidades = m.vendidas,
                    p.total_ventas_operaciones = m.ops
            ");
            $this->info("✓ Ventas acumuladas consolidadas en el catálogo.");
        }

        // 13. Métricas finales y resumen
        $elapsed = round(microtime(true) - $start, 2);
        $totalProds = DB::table('productos')->count();
        $totalStockRowsFinal = DB::table('productos_stock')->count();
        $totalMovsFinal = DB::table('productos_movimientos')->count();
        $stockUshuaiaStats = DB::table('productos_stock')
            ->join('productos', 'productos.id', '=', 'productos_stock.producto_id')
            ->where('productos_stock.sucursal_id', $idSucUshuaiaSanMartin)
            ->where('productos.controla_stock', 1)
            ->selectRaw('SUM(productos_stock.stock) as total_unidades, SUM(productos_stock.stock * productos.precio_costo) as valuacion_costo, SUM(productos_stock.stock * productos.precio_venta) as valuacion_venta')
            ->first();

        $this->newLine();
        $this->info("==================================================================");
        $this->info("   RESUMEN DE MIGRACIÓN EXITOSA A 'lamarina_v4'                   ");
        $this->info("==================================================================");
        $this->line(" • Total de Categorías:           " . count($validCatIds));
        $this->line(" • Total de Subcategorías:        " . count($validSubcatIds));
        $this->line(" • Total de Proveedores:          " . count($validProvIds));
        $this->line(" • Total de Productos:            " . number_format($totalProds, 0, ',', '.'));
        $this->line(" • Filas de Stock (4 sucursales): " . number_format($totalStockRowsFinal, 0, ',', '.'));
        $this->line(" • Movimientos Históricos (Kardex):" . number_format($totalMovsFinal, 0, ',', '.'));
        $this->line(" • Unidades físicas en Ushuaia:   " . number_format($stockUshuaiaStats->total_unidades ?? 0, 0, ',', '.'));
        $this->line(" • Valuación Stock (a costo):     $" . number_format($stockUshuaiaStats->valuacion_costo ?? 0, 2, ',', '.'));
        $this->line(" • Valuación Stock (a venta):     $" . number_format($stockUshuaiaStats->valuacion_venta ?? 0, 2, ',', '.'));
        $this->line(" • Tiempo total de ejecución:     {$elapsed} segundos");
        $this->info("==================================================================");

        return 0;
    }
}
