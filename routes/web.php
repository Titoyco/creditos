<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CreditoController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\SucursalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\PresentacionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. La Raíz (Dashboard)
Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// 2. Ruta de Login (Pública)
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// ⚠️ Protegido con 'auth': depende de la sesión del usuario (sucursal activa + permiso 'cambiar-sucursal').
Route::post('/cambiar-sucursal', [SucursalController::class, 'updateGlobal'])
    ->name('sucursal.update.global')
    ->middleware('auth');

Route::middleware('auth')->group(function () {

    // --- MÓDULO CLIENTES ---
    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
        Route::get('/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::get('/{cliente}/estado-cuenta', [ClienteController::class, 'estadoCuenta'])->name('clientes.estado-cuenta');
        Route::put('/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
        // ❌ DESHABILITADAS: ClienteController no implementa creditos() ni movimientos() (HTTP 500).
        // Reemplazadas funcionalmente por 'clientes.show' y 'clientes.estado-cuenta'.
        // Route::get('/{cliente}/creditos', [ClienteController::class, 'creditos'])->name('clientes.creditos');
        // Route::get('/{cliente}/movimientos', [ClienteController::class, 'movimientos'])->name('clientes.movimientos');
    });

    // --- MÓDULO CRÉDITOS ---
    Route::prefix('creditos')->group(function () {
        // ⚠️ RUTAS ESTÁTICAS PRIMERO (Evita que "nuevo" sea tomado como ID)
        Route::get('/', [CreditoController::class, 'index'])->name('creditos.index');
        Route::get('/nuevo', [CreditoController::class, 'create'])->name('creditos.create');
        Route::post('/', [CreditoController::class, 'store'])->name('creditos.store');

        // ⚠️ RUTAS CON PARÁMETRO {credito} DESPUÉS
        Route::get('/{credito}', [CreditoController::class, 'show'])->name('creditos.show');
        // ❌ DESHABILITADAS: CreditoController no implementa edit()/update()/destroy() (HTTP 500).
        // Un crédito se edita vía 'creditos.pagar' (pago de cuota) y se anula vía lote/rendición.
        // Route::get('/{credito}/editar', [CreditoController::class, 'edit'])->name('creditos.edit');
        // Route::put('/{credito}', [CreditoController::class, 'update'])->name('creditos.update');
        // Route::delete('/{credito}', [CreditoController::class, 'destroy'])->name('creditos.destroy');
        
        // Corregido: Se quitó el prefijo duplicado "/creditos/" adentro del grupo
        Route::get('/{credito}/pagare', [CreditoController::class, 'imprimirPagare'])->name('creditos.pagare');
        
        // ❌ DESHABILITADAS: no existen cuotas()/showCuota()/editCuota()/updateCuota() en CreditoController (HTTP 500).
        // La cobranza se resuelve con 'creditos.pagar' y el detalle con 'creditos.show'.
        // Route::get('/{credito}/cuotas', [CreditoController::class, 'cuotas'])->name('creditos.cuotas');
        // Route::get('/{credito}/cuotas/{cuota}', [CreditoController::class, 'showCuota'])->name('creditos.cuotas.show');
        // Route::get('/{credito}/cuotas/{cuota}/editar', [CreditoController::class, 'editCuota'])->name('creditos.cuotas.edit');
        // Route::put('/{credito}/cuotas/{cuota}', [CreditoController::class, 'updateCuota'])->name('creditos.cuotas.update');
        Route::post('/{cuota}/pagar', [CreditoController::class, 'pagarCuota'])->name('creditos.pagar');
    });

    // --- MÓDULO SISTEMA ---
    Route::prefix('sistema')->group(function () {
        Route::get('/', function () {return view('sistema.index');})->name('sistema.index');
        Route::resource('usuarios', UserController::class);
        Route::resource('permisos', PermisoController::class);
        Route::resource('sucursales', SucursalController::class);
        // ✅ Presentaciones: el controlador solo implementa index() + cambiar().
        // Se reemplaza Route::resource() (que generaba create/store/show/edit/update/destroy → HTTP 500)
        // por una ruta explícita de listado. El cambio de lote es POST y se declara ANTES del listado.
        Route::post('/presentaciones/cambiar', [PresentacionController::class, 'cambiar'])->name('sistema.presentaciones.cambiar');
        Route::get('/presentaciones', [PresentacionController::class, 'index'])->name('sistema.presentaciones.index');
        // ❌ DESHABILITADA: SucursalController no implementa cambiar() (HTTP 500);
        // el cambio de sucursal activa se hace con 'sucursal.update.global' (POST /cambiar-sucursal).
        // Route::get('/sucursales/{sucursal}/cambiar', [SucursalController::class, 'cambiar'])->name('sucursales.cambiar');

                
        Route::get('/provincias', [UbicacionController::class, 'provincias'])->name('provincias.index');
        Route::get('/localidades', [UbicacionController::class, 'localidades'])->name('localidades.index');
        
        
    });

    // --- PERFIL DE USUARIO ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';