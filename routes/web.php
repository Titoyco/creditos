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
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\BackupController;
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

// ⚠️ Protegido con 'auth': depende de la sesión del usuario (sucursal activa + permiso 'cambiar-sucursal').
Route::post('/cambiar-sucursal', [SucursalController::class, 'updateGlobal'])
    ->name('sucursal.update.global')
    ->middleware('auth');

Route::middleware('auth')->group(function () {

    // --- MÓDULO CLIENTES ---
    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
        Route::get('/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
        Route::get('/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::get('/{cliente}/estado-cuenta', [ClienteController::class, 'estadoCuenta'])->name('clientes.estado-cuenta');
        Route::put('/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
        Route::post('/{cliente}/novedades', [ClienteController::class, 'storeNovedad'])->name('clientes.novedades.store');
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
        Route::post('/cuotas/{cuota}/anular-pago', [CreditoController::class, 'anularPago'])->name('creditos.cuotas.anular-pago');
        Route::delete('/{credito}/anular', [CreditoController::class, 'anularCredito'])->name('creditos.anular');
    });

    // --- MÓDULO SISTEMA ---
    Route::prefix('sistema')->group(function () {
        Route::get('/', function () {return view('sistema.index');})->name('sistema.index');
        Route::resource('usuarios', UserController::class);
        Route::resource('permisos', PermisoController::class);
        // ✅ ABM completo de sucursales (SucursalController implementa index/create/store/show/edit/update/destroy).
        Route::resource('sucursales', SucursalController::class)->names('sistema.sucursales');
        // Presentaciones: el controlador solo implementa index() + cambiar().
        // Se reemplaza Route::resource() (que generaba create/store/show/edit/update/destroy → HTTP 500)
        // por una ruta explícita de listado. El cambio de lote es POST y se declara ANTES del listado.
        Route::post('/presentaciones/cambiar', [PresentacionController::class, 'cambiar'])->name('sistema.presentaciones.cambiar');
        Route::get('/presentaciones', [PresentacionController::class, 'index'])->name('sistema.presentaciones.index');
        // ❌ DESHABILITADA: SucursalController no implementa cambiar() (HTTP 500);
        // el cambio de sucursal activa se hace con 'sucursal.update.global' (POST /cambiar-sucursal).
        // Route::get('/sucursales/{sucursal}/cambiar', [SucursalController::class, 'cambiar'])->name('sucursales.cambiar');

        // Auditoría del sistema
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('sistema.auditoria.index');
        Route::post('/auditoria/{audit}/rollback', [AuditoriaController::class, 'rollback'])->name('sistema.auditoria.rollback');

        // Zonas geográficas (provincias y localidades) — vista consolidada
        Route::get('/ubicaciones', [UbicacionController::class, 'index'])->name('sistema.ubicaciones.index');
        Route::post('/ubicaciones/provincias', [UbicacionController::class, 'storeProvincia'])->name('sistema.ubicaciones.provincias.store');
        Route::put('/ubicaciones/provincias/{provincia}', [UbicacionController::class, 'updateProvincia'])->name('sistema.ubicaciones.provincias.update');
        Route::delete('/ubicaciones/provincias/{provincia}', [UbicacionController::class, 'destroyProvincia'])->name('sistema.ubicaciones.provincias.destroy');
        Route::post('/ubicaciones/localidades', [UbicacionController::class, 'storeLocalidad'])->name('sistema.ubicaciones.localidades.store');
        Route::put('/ubicaciones/localidades/{localidad}', [UbicacionController::class, 'updateLocalidad'])->name('sistema.ubicaciones.localidades.update');
        Route::delete('/ubicaciones/localidades/{localidad}', [UbicacionController::class, 'destroyLocalidad'])->name('sistema.ubicaciones.localidades.destroy');

        // Copias de Seguridad (Backup y Restauración)
        Route::get('/backup', [BackupController::class, 'index'])->name('sistema.backup.index');
        Route::post('/backup/generar', [BackupController::class, 'generate'])->name('sistema.backup.generate');
        Route::get('/backup/{filename}/descargar', [BackupController::class, 'download'])->name('sistema.backup.download');
        Route::post('/backup/restaurar', [BackupController::class, 'restore'])->name('sistema.backup.restore');
        Route::delete('/backup/{filename}', [BackupController::class, 'destroy'])->name('sistema.backup.destroy');
    });

    // --- PERFIL DE USUARIO ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- MÓDULO CATÁLOGO Y STOCK DE PRODUCTOS ---
    Route::prefix('productos')->group(function () {
        Route::get('/', [\App\Http\Controllers\ProductoController::class, 'index'])->name('productos.index');
        Route::get('/nuevo', [\App\Http\Controllers\ProductoController::class, 'create'])->name('productos.create');
        Route::post('/', [\App\Http\Controllers\ProductoController::class, 'store'])->name('productos.store');
        Route::get('/traslado', [\App\Http\Controllers\StockMovimientoController::class, 'trasladoForm'])->name('productos.traslado');
        Route::post('/traslado', [\App\Http\Controllers\StockMovimientoController::class, 'trasladoStore'])->name('productos.traslado.store');
        Route::get('/buscar-traslado', [\App\Http\Controllers\StockMovimientoController::class, 'buscarParaTraslado'])->name('productos.buscar.traslado');
        Route::post('/verificar-stock-traslado', [\App\Http\Controllers\StockMovimientoController::class, 'verificarStock'])->name('productos.verificar.stock.traslado');
        Route::get('/movimientos', [\App\Http\Controllers\StockMovimientoController::class, 'index'])->name('productos.movimientos');
        Route::get('/{producto}', [\App\Http\Controllers\ProductoController::class, 'show'])->name('productos.show');
        Route::get('/{producto}/editar', [\App\Http\Controllers\ProductoController::class, 'edit'])->name('productos.edit');
        Route::put('/{producto}', [\App\Http\Controllers\ProductoController::class, 'update'])->name('productos.update');
        Route::delete('/{producto}', [\App\Http\Controllers\ProductoController::class, 'destroy'])->name('productos.destroy');
        Route::post('/{producto}/ajuste', [\App\Http\Controllers\ProductoController::class, 'ajusteStock'])->name('productos.ajuste');
        Route::post('/{producto}/traslado', [\App\Http\Controllers\ProductoController::class, 'trasladarStock'])->name('productos.trasladar');
        Route::get('/{producto}/etiqueta', [\App\Http\Controllers\ProductoController::class, 'imprimirEtiqueta'])->name('productos.etiqueta');
    });

    // --- MÓDULO MOVIMIENTOS DE STOCK ---
    Route::get('/stock/movimientos', [\App\Http\Controllers\StockMovimientoController::class, 'index'])->name('stock.movimientos');

    // --- MÓDULO CATEGORÍAS Y SUBCATEGORÍAS ---
    Route::prefix('categorias')->group(function () {
        Route::get('/', [\App\Http\Controllers\ProductoCategoriaController::class, 'index'])->name('categorias.index');
        Route::post('/', [\App\Http\Controllers\ProductoCategoriaController::class, 'store'])->name('categorias.store');
        Route::put('/{categoria}', [\App\Http\Controllers\ProductoCategoriaController::class, 'update'])->name('categorias.update');
        Route::delete('/{categoria}', [\App\Http\Controllers\ProductoCategoriaController::class, 'destroy'])->name('categorias.destroy');

        // Subcategorías Globales (independientes de categorías)
        Route::post('/subcategorias', [\App\Http\Controllers\ProductoCategoriaController::class, 'storeSubcategoriaGlobal'])->name('subcategorias.store');
        Route::post('/subcategorias/global', [\App\Http\Controllers\ProductoCategoriaController::class, 'storeSubcategoriaGlobal']);
        Route::put('/subcategorias/{subcategoria}', [\App\Http\Controllers\ProductoCategoriaController::class, 'updateSubcategoriaGlobal'])->name('subcategorias.update');
        Route::delete('/subcategorias/{subcategoria}', [\App\Http\Controllers\ProductoCategoriaController::class, 'destroySubcategoria'])->name('subcategorias.destroy');
        Route::get('/subcategorias/json', [\App\Http\Controllers\ProductoCategoriaController::class, 'getAllSubcategoriasJson'])->name('subcategorias.json');

        // Compatibilidad con endpoints previos
        Route::post('/{categoria}/subcategorias', [\App\Http\Controllers\ProductoCategoriaController::class, 'storeSubcategoria'])->name('categorias.subcategorias.store');
        Route::delete('/{categoria}/subcategorias/{subcategoria}', [\App\Http\Controllers\ProductoCategoriaController::class, 'destroySubcategoria'])->name('categorias.subcategorias.destroy');
        Route::get('/{categoria}/subcategorias/json', [\App\Http\Controllers\ProductoCategoriaController::class, 'getSubcategorias'])->name('categorias.subcategorias.json');
    });

    // --- MÓDULO PROVEEDORES (ABM) ---
    Route::resource('proveedores', \App\Http\Controllers\ProveedorController::class)->parameters(['proveedores' => 'proveedor']);

    // --- MÓDULO REMITOS DE PROVEEDORES (RECEPCIÓN DE MERCADERÍA) ---
    Route::get('/remitos', [\App\Http\Controllers\RemitoProveedorController::class, 'index'])->name('remitos.index');
    Route::get('/remitos/nuevo', [\App\Http\Controllers\RemitoProveedorController::class, 'create'])->name('remitos.create');
    Route::post('/remitos', [\App\Http\Controllers\RemitoProveedorController::class, 'store'])->name('remitos.store');
    Route::get('/remitos/buscar-productos', [\App\Http\Controllers\RemitoProveedorController::class, 'buscarProductos'])->name('remitos.buscar-productos');
    Route::post('/remitos/crear-producto-rapido', [\App\Http\Controllers\RemitoProveedorController::class, 'crearProductoRapido'])->name('remitos.crear-producto-rapido');
    Route::get('/remitos/{remito}', [\App\Http\Controllers\RemitoProveedorController::class, 'show'])->name('remitos.show');
    Route::post('/remitos/{remito}/agregar-items', [\App\Http\Controllers\RemitoProveedorController::class, 'agregarItems'])->name('remitos.agregar-items');

    // --- MÓDULO VENTAS / PUNTO DE VENTA (POS) & FACTURACIÓN ---
    Route::get('/ventas', [\App\Http\Controllers\VentaController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/crear', [\App\Http\Controllers\VentaController::class, 'create'])->name('ventas.create');
    Route::post('/ventas', [\App\Http\Controllers\VentaController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/buscar-producto', [\App\Http\Controllers\VentaController::class, 'buscarProducto'])->name('ventas.buscar-producto');
    Route::get('/ventas/clientes/{cliente}/info-credito', [\App\Http\Controllers\VentaController::class, 'infoCreditoCliente'])->name('ventas.cliente-info-credito');
    Route::get('/ventas/{venta}', [\App\Http\Controllers\VentaController::class, 'show'])->name('ventas.show');
    Route::get('/ventas/{venta}/imprimir', [\App\Http\Controllers\VentaController::class, 'imprimir'])->name('ventas.imprimir');
    Route::post('/ventas/{venta}/anular', [\App\Http\Controllers\VentaController::class, 'anular'])->name('ventas.anular');

    // --- MÓDULO RECIBOS DE COBRANZA / CUENTA CORRIENTE ---
    Route::get('/recibos', [\App\Http\Controllers\ReciboController::class, 'index'])->name('recibos.index');
    Route::get('/recibos/nuevo', [\App\Http\Controllers\ReciboController::class, 'create'])->name('recibos.create');
    Route::post('/recibos', [\App\Http\Controllers\ReciboController::class, 'store'])->name('recibos.store');
    Route::get('/recibos/{recibo}', [\App\Http\Controllers\ReciboController::class, 'show'])->name('recibos.show');
    Route::get('/recibos/{recibo}/imprimir', [\App\Http\Controllers\ReciboController::class, 'imprimir'])->name('recibos.imprimir');
    Route::post('/recibos/{recibo}/anular', [\App\Http\Controllers\ReciboController::class, 'anular'])->name('recibos.anular');
});

require __DIR__.'/auth.php';