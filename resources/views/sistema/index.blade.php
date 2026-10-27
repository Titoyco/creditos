<x-app-layout>
    <!-- Header Compacto -->
    <div class="flex items-center justify-between py-2 border-b border-slate-200 dark:border-slate-800 mb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-white tracking-tight flex items-center space-x-2">
                <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Opciones del Sistema</span>
                <span class="text-xs font-normal text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700">Administración General</span>
            </h1>
        </div>
    </div>

    <!-- Grilla de Tarjetas (Dashboard de Configuración) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        
        <!-- 1. Presentaciones -->
        <a href="{{ route('sistema.presentaciones.index') }}" class="bg-white dark:bg-slate-800/90 rounded-md border border-slate-300 dark:border-slate-700 shadow-sm p-4 hover:border-blue-500 dark:hover:border-blue-400 hover:ring-1 hover:ring-blue-500 transition-all flex flex-col group">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2 bg-slate-100 dark:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded text-slate-600 dark:text-slate-300 group-hover:bg-blue-600 dark:group-hover:bg-blue-600 group-hover:border-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h2 class="font-bold text-[11px] text-slate-800 dark:text-slate-100 uppercase tracking-wider group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Presentaciones</h2>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium leading-tight">
                Gestión de ciclos de facturación, apertura/cierre de presentaciones y configuración de tasas de interés.
            </p>
        </a>

        <!-- 2. Sucursales -->
        <a href="{{ route('sistema.sucursales.index') }}" class="bg-white dark:bg-slate-800/90 rounded-md border border-slate-300 dark:border-slate-700 shadow-sm p-4 hover:border-blue-500 dark:hover:border-blue-400 hover:ring-1 hover:ring-blue-500 transition-all flex flex-col group">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2 bg-slate-100 dark:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded text-slate-600 dark:text-slate-300 group-hover:bg-blue-600 dark:group-hover:bg-blue-600 group-hover:border-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <h2 class="font-bold text-[11px] text-slate-800 dark:text-slate-100 uppercase tracking-wider group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Sucursales</h2>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium leading-tight">
                Administración de puntos de venta, domicilios, códigos internos y configuración general por sucursal.
            </p>
        </a>

        <!-- 3. Usuarios -->
        <a href="{{ route('usuarios.index') }}" class="bg-white dark:bg-slate-800/90 rounded-md border border-slate-300 dark:border-slate-700 shadow-sm p-4 hover:border-blue-500 dark:hover:border-blue-400 hover:ring-1 hover:ring-blue-500 transition-all flex flex-col group">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2 bg-slate-100 dark:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded text-slate-600 dark:text-slate-300 group-hover:bg-blue-600 dark:group-hover:bg-blue-600 group-hover:border-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <h2 class="font-bold text-[11px] text-slate-800 dark:text-slate-100 uppercase tracking-wider group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Usuarios del Sistema</h2>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium leading-tight">
                Alta y baja de operadores, asignación de roles, claves y control de acceso a los módulos.
            </p>
        </a>

        <!-- 4. Localidades y Provincias -->
        <a href="{{ route('sistema.ubicaciones.index') }}" class="bg-white dark:bg-slate-800/90 rounded-md border border-slate-300 dark:border-slate-700 shadow-sm p-4 hover:border-blue-500 dark:hover:border-blue-400 hover:ring-1 hover:ring-blue-500 transition-all flex flex-col group">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2 bg-slate-100 dark:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded text-slate-600 dark:text-slate-300 group-hover:bg-blue-600 dark:group-hover:bg-blue-600 group-hover:border-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h2 class="font-bold text-[11px] text-slate-800 dark:text-slate-100 uppercase tracking-wider group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Localidades y Provincias</h2>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium leading-tight">
                ABM de zonas geográficas para la correcta segmentación y registro de domicilios de los clientes.
            </p>
        </a>

        <!-- 5. Auditoría del Sistema -->
        <a href="{{ route('sistema.auditoria.index') }}" class="bg-white dark:bg-slate-800/90 rounded-md border border-slate-300 dark:border-slate-700 shadow-sm p-4 hover:border-blue-500 dark:hover:border-blue-400 hover:ring-1 hover:ring-blue-500 transition-all flex flex-col group">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2 bg-slate-100 dark:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded text-slate-600 dark:text-slate-300 group-hover:bg-blue-600 dark:group-hover:bg-blue-600 group-hover:border-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h2 class="font-bold text-[11px] text-slate-800 dark:text-slate-100 uppercase tracking-wider group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Auditoría del Sistema</h2>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium leading-tight">
                Trazabilidad de cambios, historial de operaciones por usuario y reversión de transacciones modificadas.
            </p>
        </a>

        <!-- 6. Compras a Proveedores -->
        <a href="{{ route('proveedores.index') }}" class="bg-white dark:bg-slate-800/90 rounded-md border border-slate-300 dark:border-slate-700 shadow-sm p-4 hover:border-blue-500 dark:hover:border-blue-400 hover:ring-1 hover:ring-blue-500 transition-all flex flex-col group">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2 bg-slate-100 dark:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded text-slate-600 dark:text-slate-300 group-hover:bg-blue-600 dark:group-hover:bg-blue-600 group-hover:border-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h2 class="font-bold text-[11px] text-slate-800 dark:text-slate-100 uppercase tracking-wider group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Compras a Proveedores</h2>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium leading-tight">
                Gestión de proveedores, facturas de compra, remitos de recepción, ingreso a stock y actualización de costos.
            </p>
        </a>

    </div>
</x-app-layout>