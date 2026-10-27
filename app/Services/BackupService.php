<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    /**
     * Directorio donde se almacenan los respaldos ZIP.
     */
    public function getBackupDirectory(): string
    {
        $path = storage_path('app/backups');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        return $path;
    }

    /**
     * Obtiene el listado de archivos de respaldo existentes con sus metadatos.
     */
    public function getBackupsList(): array
    {
        $dir = $this->getBackupDirectory();
        $files = File::glob($dir . '/*.zip');
        $backups = [];

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $sizeBytes = File::size($filePath);
            $modTime = File::lastModified($filePath);

            $backups[] = [
                'filename'        => $filename,
                'path'            => $filePath,
                'size_bytes'      => $sizeBytes,
                'size_human'      => $this->formatBytes($sizeBytes),
                'timestamp'       => $modTime,
                'date'            => date('d/m/Y H:i:s', $modTime),
                'is_pre_restore'  => str_contains($filename, 'pre_restauracion') || str_contains($filename, 'seguridad'),
            ];
        }

        // Ordenar del más reciente al más antiguo
        usort($backups, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Encuentra la ruta al ejecutable mysqldump en el sistema o WAMP/XAMPP.
     */
    public function findMysqldumpBinary(): ?string
    {
        if (config('database.default') !== 'mysql') {
            return null;
        }

        // 1. Probar en PATH del sistema
        try {
            $p = new Process(['mysqldump', '--version']);
            $p->run();
            if ($p->isSuccessful()) {
                return 'mysqldump';
            }
        } catch (Exception $e) {
            // No está en PATH
        }

        // 2. Probar rutas conocidas en Windows (WAMP64, WAMP, XAMPP)
        $candidates = array_merge(
            glob('C:/wamp64/bin/mysql/mysql*/bin/mysqldump.exe') ?: [],
            glob('C:/wamp/bin/mysql/mysql*/bin/mysqldump.exe') ?: [],
            glob('C:/xampp/mysql/bin/mysqldump.exe') ?: [],
            glob('D:/wamp64/bin/mysql/mysql*/bin/mysqldump.exe') ?: [],
            glob('/usr/bin/mysqldump') ?: [],
            glob('/usr/local/bin/mysqldump') ?: []
        );

        foreach ($candidates as $binary) {
            if (File::exists($binary)) {
                return $binary;
            }
        }

        return null;
    }

    /**
     * Encuentra la ruta al ejecutable mysql en el sistema o WAMP/XAMPP.
     */
    public function findMysqlBinary(): ?string
    {
        if (config('database.default') !== 'mysql') {
            return null;
        }

        // 1. Probar en PATH del sistema
        try {
            $p = new Process(['mysql', '--version']);
            $p->run();
            if ($p->isSuccessful()) {
                return 'mysql';
            }
        } catch (Exception $e) {
            // No está en PATH
        }

        // 2. Probar rutas conocidas en Windows (WAMP64, WAMP, XAMPP)
        $candidates = array_merge(
            glob('C:/wamp64/bin/mysql/mysql*/bin/mysql.exe') ?: [],
            glob('C:/wamp/bin/mysql/mysql*/bin/mysql.exe') ?: [],
            glob('C:/xampp/mysql/bin/mysql.exe') ?: [],
            glob('D:/wamp64/bin/mysql/mysql*/bin/mysql.exe') ?: [],
            glob('/usr/bin/mysql') ?: [],
            glob('/usr/local/bin/mysql') ?: []
        );

        foreach ($candidates as $binary) {
            if (File::exists($binary)) {
                return $binary;
            }
        }

        return null;
    }

    /**
     * Genera una copia de seguridad completa de la base de datos y la comprime en ZIP.
     *
     * @param string|null $prefix Prefijo personalizado para el nombre de archivo
     * @return array Metadatos del respaldo generado (filename, path, size_bytes, size_human)
     */
    public function createBackup(?string $prefix = null): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $dir = $this->getBackupDirectory();
        $dbName = config('database.connections.' . config('database.default') . '.database');
        $cleanPrefix = $prefix ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix) : 'backup_lamarina_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dbName);
        $timestamp = date('Y-m-d_H-i-s');
        $baseName = "{$cleanPrefix}_{$timestamp}";

        $sqlPath = $dir . "/{$baseName}.sql";
        $zipPath = $dir . "/{$baseName}.zip";

        $dumpBinary = $this->findMysqldumpBinary();

        if ($dumpBinary && config('database.default') === 'mysql') {
            $this->dumpWithMysqldump($dumpBinary, $sqlPath);
        } else {
            $this->dumpWithPhpPdo($sqlPath);
        }

        if (!File::exists($sqlPath) || File::size($sqlPath) === 0) {
            throw new Exception("Error al generar el volcado SQL de la base de datos.");
        }

        // Comprimir en archivo ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("No se pudo crear el archivo ZIP de respaldo.");
        }

        $zip->addFile($sqlPath, "{$baseName}.sql");
        $zip->close();

        // Eliminar el archivo .sql intermedio para ahorrar espacio
        if (File::exists($sqlPath)) {
            File::delete($sqlPath);
        }

        $sizeBytes = File::size($zipPath);

        Log::info("Backup creado con éxito: {$baseName}.zip ({$sizeBytes} bytes)");

        return [
            'filename'   => "{$baseName}.zip",
            'path'       => $zipPath,
            'size_bytes' => $sizeBytes,
            'size_human' => $this->formatBytes($sizeBytes),
        ];
    }

    /**
     * Restaura la base de datos a partir de un archivo subido (.zip o .sql).
     * Por estricta seguridad, OBLIGATORIAMENTE genera primero una copia de resguardo
     * de la base de datos actual antes de realizar la sobrescritura.
     *
     * @param string $uploadedFilePath Ruta temporal del archivo subido
     * @param string $originalName Nombre original del archivo
     * @return array Resultado de la operación con nombre del resguardo previo y archivo restaurado
     */
    public function restoreDatabase(string $uploadedFilePath, string $originalName): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        // PASO 1 OBLIGATORIO: Generar copia de seguridad previa automática
        $safetyBackup = $this->createBackup('backup_seguridad_previo_restaurar');

        // PASO 2: Extraer el SQL del archivo subido
        $tempExtractDir = storage_path('app/temp_restore_' . uniqid());
        File::makeDirectory($tempExtractDir, 0755, true);

        $sqlFilePath = null;

        try {
            if (str_ends_with(strtolower($originalName), '.zip')) {
                $zip = new ZipArchive();
                if ($zip->open($uploadedFilePath) !== true) {
                    throw new Exception("El archivo ZIP de respaldo proporcionado está corrupto o no se puede abrir.");
                }

                $zip->extractTo($tempExtractDir);
                $zip->close();

                // Buscar el archivo .sql extraído
                $extractedSqls = File::glob($tempExtractDir . '/*.sql');
                if (empty($extractedSqls)) {
                    // Buscar recursivamente si estaba dentro de subcarpetas
                    $extractedSqls = File::allFiles($tempExtractDir);
                    $extractedSqls = array_filter($extractedSqls, fn ($f) => str_ends_with(strtolower($f->getFilename()), '.sql'));
                }

                if (empty($extractedSqls)) {
                    throw new Exception("El archivo ZIP subido no contiene ningún archivo .sql con la base de datos.");
                }

                $sqlFilePath = reset($extractedSqls);
                if (is_object($sqlFilePath)) {
                    $sqlFilePath = $sqlFilePath->getRealPath();
                }
            } elseif (str_ends_with(strtolower($originalName), '.sql')) {
                $sqlFilePath = $uploadedFilePath;
            } else {
                throw new Exception("Formato de archivo no soportado. Debe ser un archivo .zip o .sql.");
            }

            if (!File::exists($sqlFilePath) || File::size($sqlFilePath) === 0) {
                throw new Exception("El archivo SQL de restauración está vacío o no es legible.");
            }

            // PASO 3: Ejecutar la restauración en la base de datos
            $mysqlBinary = $this->findMysqlBinary();

            if ($mysqlBinary && config('database.default') === 'mysql') {
                $this->restoreWithMysql($mysqlBinary, $sqlFilePath);
            } else {
                $this->restoreWithPhpPdo($sqlFilePath);
            }

            Log::info("Base de datos restaurada exitosamente desde '{$originalName}'. Resguardo previo: {$safetyBackup['filename']}");

            return [
                'success'        => true,
                'restored_file'  => $originalName,
                'safety_backup'  => $safetyBackup['filename'],
                'safety_path'    => $safetyBackup['path'],
            ];
        } finally {
            // Limpiar archivos temporales
            if (File::isDirectory($tempExtractDir)) {
                File::deleteDirectory($tempExtractDir);
            }
        }
    }

    /**
     * Elimina un archivo de respaldo específico.
     */
    public function deleteBackup(string $filename): bool
    {
        $cleanName = basename($filename);
        $filePath = $this->getBackupDirectory() . '/' . $cleanName;

        if (File::exists($filePath) && str_ends_with(strtolower($cleanName), '.zip')) {
            return File::delete($filePath);
        }

        return false;
    }

    /**
     * Exporta con mysqldump nativo (rápido y con soporte para millones de registros).
     */
    protected function dumpWithMysqldump(string $binary, string $outputSqlPath): void
    {
        $config = config('database.connections.mysql');
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string)($config['port'] ?? '3306');
        $user = $config['username'] ?? 'root';
        $pass = $config['password'] ?? '';
        $database = $config['database'];

        $command = [
            $binary,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $user,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--result-file=' . $outputSqlPath,
            $database,
        ];

        $env = [];
        if (!empty($pass)) {
            $env['MYSQL_PWD'] = $pass;
        }

        $process = new Process($command, null, $env, null, 300);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning("mysqldump falló, ejecutando volcado PHP alternativo: " . $process->getErrorOutput());
            $this->dumpWithPhpPdo($outputSqlPath);
        }
    }

    /**
     * Importa con mysql CLI nativo.
     */
    protected function restoreWithMysql(string $binary, string $inputSqlPath): void
    {
        $config = config('database.connections.mysql');
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string)($config['port'] ?? '3306');
        $user = $config['username'] ?? 'root';
        $pass = $config['password'] ?? '';
        $database = $config['database'];

        $command = [
            $binary,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $user,
            '--default-character-set=utf8mb4',
            $database,
        ];

        $env = [];
        if (!empty($pass)) {
            $env['MYSQL_PWD'] = $pass;
        }

        $sqlStream = fopen($inputSqlPath, 'r');
        $process = new Process($command, null, $env, $sqlStream, 300);
        $process->run();
        if (is_resource($sqlStream)) {
            fclose($sqlStream);
        }

        if (!$process->isSuccessful()) {
            throw new Exception("Error al restaurar con cliente MySQL: " . $process->getErrorOutput());
        }
    }

    /**
     * Volcado alternativo 100% en PHP mediante PDO (portátil en cualquier servidor).
     */
    protected function dumpWithPhpPdo(string $outputSqlPath): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();

        $handle = fopen($outputSqlPath, 'w');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo de salida para escribir el respaldo.");
        }

        fwrite($handle, "-- Backup Base de Datos La Marina\n");
        fwrite($handle, "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n");

        if ($driver === 'mysql') {
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
            fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $dbName = config('database.connections.mysql.database');
            $keyName = "Tables_in_" . $dbName;

            foreach ($tables as $t) {
                $tableName = $t->$keyName ?? array_values((array)$t)[0];

                fwrite($handle, "-- --------------------------------------------------------\n");
                fwrite($handle, "-- Estructura de tabla `{$tableName}`\n");
                fwrite($handle, "-- --------------------------------------------------------\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");

                $createResult = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $createSql = $createResult[0]->{'Create Table'} ?? '';
                fwrite($handle, $createSql . ";\n\n");

                // Volcar datos
                $this->writeTableRowsToSql($pdo, $handle, $tableName, $driver);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } elseif ($driver === 'sqlite') {
            $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $t) {
                $tableName = $t->name;
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($handle, $t->sql . ";\n\n");
                $this->writeTableRowsToSql($pdo, $handle, $tableName, $driver);
            }
        }

        fclose($handle);
    }

    /**
     * Escribe las filas de una tabla al archivo SQL en bloques para no saturar memoria.
     */
    protected function writeTableRowsToSql($pdo, $handle, string $tableName, string $driver): void
    {
        $stmt = $pdo->query("SELECT * FROM " . ($driver === 'mysql' ? "`{$tableName}`" : "\"{$tableName}\""));
        if (!$stmt) {
            return;
        }

        $first = true;
        $count = 0;
        $batchSize = 250;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($first || ($count % $batchSize === 0)) {
                if (!$first) {
                    fwrite($handle, ";\n");
                }
                fwrite($handle, "INSERT INTO " . ($driver === 'mysql' ? "`{$tableName}`" : "\"{$tableName}\"") . " VALUES\n");
                $first = false;
            } else {
                fwrite($handle, ",\n");
            }

            $values = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $values[] = "NULL";
                } elseif (is_numeric($val) && !str_starts_with((string)$val, '0')) {
                    $values[] = $val;
                } else {
                    $values[] = $pdo->quote((string)$val);
                }
            }

            fwrite($handle, "(" . implode(", ", $values) . ")");
            $count++;
        }

        if (!$first) {
            fwrite($handle, ";\n\n");
        }
    }

    /**
     * Restauración alternativa en PHP mediante PDO por comandos separados.
     */
    protected function restoreWithPhpPdo(string $sqlFilePath): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("SET FOREIGN_KEY_CHECKS = 0;");
        }

        $handle = fopen($sqlFilePath, 'r');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo SQL para lectura.");
        }

        $query = '';
        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            $query .= $line;
            if (str_ends_with($trimmed, ';')) {
                try {
                    DB::unprepared($query);
                } catch (Exception $e) {
                    Log::warning("Aviso durante ejecución de consulta de restauración: " . $e->getMessage());
                }
                $query = '';
            }
        }

        fclose($handle);

        if ($driver === 'mysql') {
            DB::statement("SET FOREIGN_KEY_CHECKS = 1;");
        }
    }

    /**
     * Formatea bytes a una unidad legible (KB, MB, GB).
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
