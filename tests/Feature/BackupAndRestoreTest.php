<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\UsuariosSucursalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class BackupAndRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected BackupService $backupService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UsuariosSucursalesSeeder::class);
        $this->backupService = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        // Limpiar archivos de prueba en storage/app/backups
        $dir = storage_path('app/backups');
        if (File::exists($dir)) {
            $files = File::glob($dir . '/test_*');
            foreach ($files as $f) {
                File::delete($f);
            }
            $safetyFiles = File::glob($dir . '/backup_seguridad_previo_restaurar_*');
            foreach ($safetyFiles as $f) {
                File::delete($f);
            }
        }
        parent::tearDown();
    }

    /**
     * 1. Las rutas de respaldo requieren autenticación.
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get('/sistema/backup')->assertRedirect('/login');
        $this->post('/sistema/backup/generar')->assertRedirect('/login');
        $this->post('/sistema/backup/restaurar')->assertRedirect('/login');
    }

    /**
     * 2. Cualquier usuario autenticado (operador común) puede acceder y generar una copia en ZIP.
     */
    public function test_regular_operator_can_view_backup_page_and_generate_zip(): void
    {
        $vendedor = User::where('username', 'vendedorush1')->first();
        $this->assertNotNull($vendedor);

        $response = $this->actingAs($vendedor)->get('/sistema/backup');
        $response->assertOk();
        $response->assertSee('Crear y Descargar Copia (.ZIP)');
        $response->assertSee('GENERAR Y DESCARGAR COPIA (.ZIP)');
        // El operador común ve la advertencia de que la restauración es exclusiva de administradores
        $response->assertSee('Función Exclusiva para Administradores');
        $response->assertDontSee('RESTAURAR BASE DE DATOS');

        // Generar copia
        $generateResponse = $this->actingAs($vendedor)->post('/sistema/backup/generar');
        $generateResponse->assertOk();
        $this->assertEquals('application/zip', $generateResponse->headers->get('content-type'));
    }

    /**
     * 3. Un operador común NO puede restaurar ni eliminar respaldos (HTTP 403).
     */
    public function test_regular_operator_cannot_restore_or_delete_backups(): void
    {
        $vendedor = User::where('username', 'vendedorush1')->first();

        // Intento de restaurar
        $file = UploadedFile::fake()->create('fake_backup.zip', 100, 'application/zip');
        $restoreResponse = $this->actingAs($vendedor)->post('/sistema/backup/restaurar', [
            'backup_file'     => $file,
            'confirm_restore' => '1',
        ]);
        $restoreResponse->assertStatus(403);

        // Intento de eliminar
        $deleteResponse = $this->actingAs($vendedor)->delete('/sistema/backup/archivo_inexistente.zip');
        $deleteResponse->assertStatus(403);
    }

    /**
     * 4. Un Administrador y el Superusuario sí pueden ver la opción de restauración.
     */
    public function test_admin_and_superuser_can_see_restore_section(): void
    {
        $admin = User::where('username', 'Admin')->first();
        $adminResponse = $this->actingAs($admin)->get('/sistema/backup');
        $adminResponse->assertOk();
        $adminResponse->assertSee('RESTAURAR BASE DE DATOS');
        $adminResponse->assertSee('Garantía de Seguridad Automática');

        $superuser = User::where('username', 'superuser')->first();
        $superuserResponse = $this->actingAs($superuser)->get('/sistema/backup');
        $superuserResponse->assertOk();
        $superuserResponse->assertSee('RESTAURAR BASE DE DATOS');
    }

    /**
     * 5. La restauración OBLIGATORIAMENTE genera primero una copia de seguridad preventiva antes de procesar el archivo.
     */
    public function test_restore_automatically_creates_safety_backup_first(): void
    {
        $admin = User::where('username', 'Admin')->first();

        // Crear un archivo ZIP temporal con una consulta SQL válida
        $tempDir = storage_path('app/temp_test_' . uniqid());
        File::makeDirectory($tempDir, 0755, true);
        $sqlPath = $tempDir . '/dump.sql';
        File::put($sqlPath, "-- Respaldo de prueba\nSELECT 1;\n");

        $zipPath = $tempDir . '/test_backup.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($sqlPath, 'dump.sql');
        $zip->close();

        $uploadedFile = new UploadedFile($zipPath, 'test_backup.zip', 'application/zip', null, true);

        // Realizar la restauración
        $response = $this->actingAs($admin)->post('/sistema/backup/restaurar', [
            'backup_file'     => $uploadedFile,
            'confirm_restore' => '1',
        ]);

        $response->assertRedirect('/sistema/backup');
        $response->assertSessionHas('success');

        // Verificar que en storage/app/backups se creó automáticamente la copia de seguridad previa
        $backups = $this->backupService->getBackupsList();
        $hasSafetyBackup = false;
        foreach ($backups as $b) {
            if ($b['is_pre_restore']) {
                $hasSafetyBackup = true;
                break;
            }
        }

        $this->assertTrue($hasSafetyBackup, 'Se debe haber creado automáticamente una copia de seguridad previa con el prefijo de seguridad/pre-restauración.');

        // Limpieza
        File::deleteDirectory($tempDir);
    }
}
