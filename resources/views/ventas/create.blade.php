<x-app-layout>
    <div class="max-w-7xl mx-auto py-2" x-data="posApp()" x-init="initPos()">
        
        <!-- Header Superior del POS -->
        <div class="flex items-center justify-between py-2 border-b border-slate-200 dark:border-slate-800 mb-3">
            <div class="flex items-center space-x-3">
                <a href="{{ route('ventas.index') }}" title="Volver al Historial de Ventas"
                   class="bg-white dark:bg-slate-800 p-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-xs">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2">
                        <span>Punto de Venta (POS) & Facturación</span>
                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full {{ $esTdf ? 'bg-sky-100 dark:bg-sky-950/70 text-sky-800 dark:text-sky-300 border border-sky-300 dark:border-sky-800' : 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800' }}">
                            {{ $sucursalActiva->nombre ?? 'Sucursal' }} ({{ $sucursalActiva->codigo_sucursal ?? 'N/A' }})
                        </span>
                    </h1>
                    <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        <span>Lote / Presentación: <strong>#{{ $presentacion->numero ?? 'Sin Lote' }}</strong></span>
                        <span>•</span>
                        <span>Tasa Financiación Lote: <strong>{{ number_format($tasaInteresCredito, 1) }}%</strong></span>
                        @if($esTdf)
                            <span>•</span>
                            <span class="text-sky-600 dark:text-sky-400 font-bold">Modo Escáner Activo (Rebaja de Stock)</span>
                        @else
                            <span>•</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">Modo Mostrador Trelew (Venta Directa / Varios)</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <button type="button" @click="limpiarVenta()" 
                        class="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs py-1.5 px-3 rounded-lg transition flex items-center gap-1 uppercase">
                    <span>🗑️ Limpiar Factura</span>
                </button>
                <a href="{{ route('ventas.index') }}" 
                   class="bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-bold text-xs py-1.5 px-3 rounded-lg transition flex items-center gap-1 uppercase">
                    <span>Historial Ventas</span>
                </a>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- CUADRÍCULA VERTICAL EN 2 COLUMNAS (50% IZQUIERDA / 50% DERECHA)     -->
        <!-- =================================================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            
            <!-- =============================================================== -->
            <!-- COLUMNA IZQUIERDA (50%): CLIENTE + CARGA + GRILLA DE FACTURA     -->
            <!-- =============================================================== -->
            <div class="space-y-3">
                
                <!-- 1. Tarjeta de Datos del Cliente -->
                <div class="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-xs font-black uppercase text-slate-700 dark:text-slate-300 tracking-wider flex items-center gap-1.5">
                            <span>👤</span>
                            <span>Datos del Cliente</span>
                        </span>

                        <template x-if="clienteSeleccionado">
                            <button type="button" @click="deseleccionarCliente()" 
                                    class="text-xs font-bold text-rose-600 hover:text-rose-700 hover:underline flex items-center gap-1 transition">
                                <span>✕ Volver a Consumidor Final</span>
                            </button>
                        </template>
                    </div>

                    <!-- CASO A: CONSUMIDOR FINAL (ESTADO POR DEFECTO) -->
                    <template x-if="!clienteSeleccionado">
                        <div class="space-y-2">
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900/80 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-black text-xs shadow-inner">
                                        CF
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-tight">CONSUMIDOR FINAL</span>
                                            <span class="text-[9px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold px-1.5 py-0.5 rounded uppercase">
                                                Por Defecto
                                            </span>
                                        </div>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">Cliente genérico de mostrador (Efectivo / Tarjeta)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Buscador Rápido de Cliente Registrado -->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" 
                                       x-model="clienteQuery"
                                       @input.debounce.250ms="buscarClientes()"
                                       placeholder="Buscar cliente registrado por DNI o Apellido y Nombre..."
                                       class="w-full text-xs py-2 pl-9 pr-3 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-inner">
                                
                                <!-- Dropdown de resultados de clientes -->
                                <div x-show="sugerenciasClientes.length > 0"
                                     @click.away="sugerenciasClientes = []"
                                     class="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 max-h-56 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
                                     x-cloak>
                                    <template x-for="cli in sugerenciasClientes" :key="cli.id">
                                        <button type="button" @click="seleccionarCliente(cli)"
                                                class="w-full text-left p-2.5 hover:bg-indigo-50 dark:hover:bg-slate-800 transition flex items-center justify-between">
                                            <div>
                                                <span class="font-bold text-slate-800 dark:text-white text-xs block" x-text="cli.nombre"></span>
                                                <span class="text-[10px] font-mono text-slate-400" x-text="'DNI: ' + cli.dni + ' | Cta: ' + (cli.nro_cuenta || '-')"></span>
                                            </div>
                                            <span class="text-[9px] px-2 py-0.5 rounded font-black uppercase"
                                                  :class="cli.suspendido ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'"
                                                  x-text="cli.suspendido ? 'Suspendido' : 'Habilitado'">
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- CASO B: CLIENTE REGISTRADO SELECCIONADO -->
                    <template x-if="clienteSeleccionado">
                        <div class="space-y-2">
                            <div class="p-2.5 bg-indigo-50/80 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black text-xs shadow-sm">
                                            👤
                                        </div>
                                        <div>
                                            <span class="text-xs font-black text-indigo-950 dark:text-indigo-200 block uppercase tracking-tight" x-text="clienteSeleccionado.nombre"></span>
                                            <div class="flex items-center gap-2 text-[11px] font-mono text-indigo-700 dark:text-indigo-400 mt-0.5">
                                                <span x-text="'DNI: ' + clienteSeleccionado.dni"></span>
                                                <template x-if="clienteSeleccionado.nro_cuenta">
                                                    <span x-text="'• Cta: ' + clienteSeleccionado.nro_cuenta"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Badge de Estado Crediticio -->
                                    <template x-if="infoCredito">
                                        <div>
                                            <span x-show="infoCredito.estado_crediticio.habilitado" 
                                                  class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 shadow-2xs">
                                                ✓ Habilitado
                                            </span>
                                            <span x-show="!infoCredito.estado_crediticio.habilitado && infoCredito.estado_crediticio.suspendido" 
                                                  class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase bg-rose-100 dark:bg-rose-950/80 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-700 shadow-2xs">
                                                ⛔ Suspendido
                                            </span>
                                            <span x-show="!infoCredito.estado_crediticio.habilitado && !infoCredito.estado_crediticio.suspendido && infoCredito.estado_crediticio.tiene_mora" 
                                                  class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700 shadow-2xs">
                                                ⚠️ Con Mora
                                            </span>
                                        </div>
                                    </template>
                                </div>

                                <!-- Métricas Financieras del Cliente -->
                                <template x-if="infoCredito">
                                    <div class="mt-2 pt-2 border-t border-indigo-200/80 dark:border-indigo-900/60 grid grid-cols-3 gap-2 text-center">
                                        <div class="bg-white/80 dark:bg-slate-900/80 p-1 rounded-lg border border-indigo-100 dark:border-indigo-900">
                                            <span class="text-[9px] text-slate-500 dark:text-slate-400 font-bold uppercase block">Disponible</span>
                                            <span class="font-mono font-black text-emerald-700 dark:text-emerald-400 text-xs" 
                                                  x-text="'$ ' + formatDinero(infoCredito.estado_crediticio.limite_disponible)"></span>
                                        </div>
                                        <div class="bg-white/80 dark:bg-slate-900/80 p-1 rounded-lg border border-indigo-100 dark:border-indigo-900">
                                            <span class="text-[9px] text-slate-500 dark:text-slate-400 font-bold uppercase block">Deuda Actual</span>
                                            <span class="font-mono font-bold text-slate-700 dark:text-slate-300 text-xs" 
                                                  x-text="'$ ' + formatDinero(infoCredito.estado_crediticio.deuda_acumulada)"></span>
                                        </div>
                                        <div class="bg-white/80 dark:bg-slate-900/80 p-1 rounded-lg border border-indigo-100 dark:border-indigo-900">
                                            <span class="text-[9px] text-slate-500 dark:text-slate-400 font-bold uppercase block">Tope Cuotas</span>
                                            <span class="font-mono font-black text-indigo-700 dark:text-indigo-400 text-xs" 
                                                  x-text="infoCredito.estado_crediticio.max_cuotas + ' pagos'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- 2. Barra de Carga Rápida: Escáner + Artículo Genérico VARIOS -->
                <div class="space-y-2">
                    <!-- Input Escáner de Código de Barras / Búsqueda -->
                    <div class="bg-white dark:bg-slate-800 p-2.5 rounded-xl border-2 border-indigo-500/40 dark:border-indigo-500/50 shadow-sm relative">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-indigo-600 dark:text-indigo-400">
                                    <svg class="w-4 h-4 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                </div>
                                <input type="text" 
                                       x-ref="barcodeInput"
                                       x-model="barcodeQuery"
                                       @keydown.enter.prevent="procesarCodigoBarra()"
                                       @input.debounce.250ms="buscarProductosSugeridos()"
                                       placeholder="Escanee código de barra o descripción..."
                                       class="w-full text-xs font-mono py-2 pl-9 pr-16 rounded-lg border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500 shadow-inner"
                                       autocomplete="off">
                                
                                <div class="absolute inset-y-0 right-0 pr-2 flex items-center space-x-1">
                                    <span class="text-[9px] bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-mono px-1.5 py-0.5 rounded font-bold">
                                        ENTER
                                    </span>
                                </div>
                            </div>

                            <button type="button" @click="procesarCodigoBarra()"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-2 px-3 rounded-lg shadow-sm transition flex items-center gap-1 uppercase shrink-0">
                                <span>Agregar</span>
                            </button>
                        </div>

                        <!-- Dropdown de sugerencias de productos -->
                        <div x-show="sugerenciasProductos.length > 0"
                             @click.away="sugerenciasProductos = []"
                             class="absolute left-2.5 right-2.5 top-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 max-h-56 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
                             x-cloak>
                            <template x-for="prod in sugerenciasProductos" :key="prod.id">
                                <button type="button" @click="intentarAgregarProducto(prod); sugerenciasProductos = []; barcodeQuery = ''; focusBarcode();"
                                        class="w-full text-left p-2.5 hover:bg-indigo-50 dark:hover:bg-slate-800 transition flex items-center justify-between">
                                    <div>
                                        <span class="font-bold text-slate-800 dark:text-white text-xs block" x-text="prod.descripcion"></span>
                                        <span class="text-[10px] font-mono text-slate-400" x-text="'Cód: ' + prod.codigo + ' | EAN: ' + (prod.codigo_barra || '-')"></span>
                                    </div>
                                    <div class="text-right flex flex-col items-end">
                                        <span class="text-xs font-mono font-black text-indigo-700 dark:text-indigo-400 block" x-text="'$ ' + formatDinero(prod.precio_venta)"></span>
                                        <template x-if="prod.controla_stock">
                                            <span class="text-[10px] font-black px-1.5 py-0.5 rounded tracking-tight mt-0.5 inline-block"
                                                  :class="prod.stock > 0 ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800' : 'bg-rose-100 dark:bg-rose-950/80 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-800'"
                                                  x-text="prod.stock > 0 ? 'Stock: ' + prod.stock : '⚠️ SIN STOCK (0)'">
                                            </span>
                                        </template>
                                        <template x-if="!prod.controla_stock">
                                            <span class="text-[9px] font-bold text-slate-400">Sin límite</span>
                                        </template>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Carga Rápida de Artículo Genérico VARIOS -->
                    <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-slate-800 dark:to-slate-800/90 p-2 rounded-xl border border-amber-200 dark:border-amber-800/50 shadow-xs flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center font-black text-xs shrink-0">
                                ⚡
                            </span>
                            <div>
                                <span class="text-[11px] font-black uppercase text-amber-900 dark:text-amber-200 block leading-tight">
                                    Carga Rápida de Artículo Genérico (VARIOS)
                                </span>
                                <span class="text-[9px] text-amber-700 dark:text-amber-400 leading-none">
                                    Venta de mostrador sin código
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 flex-grow justify-end">
                            <div class="relative w-28">
                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-xs font-black text-slate-400">$</span>
                                <input type="number" step="0.01" min="1" 
                                       x-model="montoGenerico" 
                                       @keydown.enter.prevent="agregarArticuloGenerico()"
                                       placeholder="0.00"
                                       class="w-full text-xs font-mono font-bold py-1 pl-5 pr-1.5 rounded-lg border-amber-300 dark:border-amber-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <button type="button" @click="agregarArticuloGenerico()"
                                    class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs py-1 px-2.5 rounded-lg shadow-xs transition uppercase shrink-0">
                                + Varios
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. Tabla Compacta de Artículos Cargados en la Factura -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div class="p-2.5 bg-slate-100 dark:bg-slate-900/90 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <span class="text-xs font-black uppercase text-slate-700 dark:text-slate-300 tracking-wider flex items-center gap-1.5">
                            <span>🛒</span>
                            <span>Detalle de Factura (<span x-text="items.length"></span> artículos)</span>
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold" x-text="items.length > 0 ? 'Total parcial: $ ' + formatDinero(calcularSubtotal()) : ''"></span>
                    </div>

                    <div class="overflow-x-auto min-h-[240px] max-h-[380px] overflow-y-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700 text-[10px] uppercase font-bold sticky top-0 z-10">
                                    <th class="py-2 px-2.5 w-8">#</th>
                                    <th class="py-2 px-2.5">Artículo / Descripción</th>
                                    <th class="py-2 px-1 text-center w-24">Cantidad</th>
                                    <th class="py-2 px-2 text-right w-24">P. Unitario</th>
                                    <th class="py-2 px-2.5 text-right w-24">Subtotal</th>
                                    <th class="py-2 px-1 text-center w-10">✕</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-sans">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40 transition">
                                        {{-- Nro --}}
                                        <td class="py-2 px-2.5 font-mono text-[10px] text-slate-400" x-text="index + 1"></td>
                                        
                                        {{-- Descripción --}}
                                        <td class="py-2 px-2.5">
                                            <span class="font-bold text-slate-900 dark:text-white block text-xs" x-text="item.descripcion"></span>
                                            <div class="flex items-center gap-1.5 text-[10px] font-mono text-slate-400">
                                                <span x-text="'Cód: ' + (item.codigo || '-')"></span>
                                                <template x-if="item.controla_stock">
                                                    <span class="text-sky-600 dark:text-sky-400 font-bold" x-text="'(Stock: ' + item.stock + ')'"></span>
                                                </template>
                                            </div>
                                        </td>

                                        {{-- Cantidad con Botones Tablet +/- --}}
                                        <td class="py-2 px-1 text-center">
                                            <div class="inline-flex items-center border border-slate-300 dark:border-slate-600 rounded-lg overflow-hidden bg-white dark:bg-slate-900 shadow-2xs">
                                                <button type="button" @click="cambiarCantidad(index, -1)" 
                                                        class="px-2 py-0.5 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 font-black text-xs leading-none transition">
                                                    -
                                                </button>
                                                <input type="number" min="0.01" step="1" 
                                                       x-model.number="item.cantidad"
                                                       @change="validarItem(index)"
                                                       class="w-10 text-center text-xs font-bold font-mono py-0.5 px-0 border-0 bg-transparent text-slate-900 dark:text-white focus:ring-0">
                                                <button type="button" @click="cambiarCantidad(index, 1)" 
                                                        class="px-2 py-0.5 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 font-black text-xs leading-none transition">
                                                    +
                                                </button>
                                            </div>
                                        </td>

                                        {{-- Precio Unitario --}}
                                        <td class="py-2 px-2 text-right">
                                            <input type="number" step="0.01" min="0" 
                                                   x-model.number="item.precio_unitario"
                                                   @change="validarItem(index)"
                                                   class="w-20 text-right text-xs font-mono font-bold py-0.5 px-1 rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:ring-1 focus:ring-indigo-500">
                                        </td>

                                        {{-- Subtotal --}}
                                        <td class="py-2 px-2.5 text-right font-mono font-black text-slate-900 dark:text-white tabular-nums text-xs"
                                            x-text="'$ ' + formatDinero(item.cantidad * item.precio_unitario)">
                                        </td>

                                        {{-- Botón Quitar --}}
                                        <td class="py-2 px-1 text-center">
                                            <button type="button" @click="quitarItem(index)" 
                                                    class="text-rose-500 hover:text-rose-700 p-1 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded transition text-xs font-bold" title="Eliminar fila">
                                                ✕
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="items.length === 0">
                                    <tr>
                                        <td colspan="6" class="py-12 text-center text-slate-400 dark:text-slate-500">
                                            <div class="text-3xl mb-1.5">🛒</div>
                                            <p class="font-bold text-xs">Factura vacía</p>
                                            <p class="text-[11px] mt-0.5">Escanee un código de barras o use la carga rápida de artículos varios.</p>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Barra de Atajos de Teclado -->
                <div class="flex flex-wrap items-center justify-between text-[10px] text-slate-400 dark:text-slate-500 px-1 font-mono">
                    <span>💡 [ENTER] Agregar código escaneado</span>
                    <span>💡 [ESC] Enfocar escáner</span>
                    <span>💡 [ESPACIO] Abrir cobro</span>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- COLUMNA DERECHA (50%): MEDIO DE PAGO & VISOR REGISTRADORA TOTAL -->
            <!-- =============================================================== -->
            <div class="space-y-3">
                
                <!-- VISOR DIGITAL ESTILO REGISTRADORA / TABLET POS (GRAN TOTAL) -->
                <div class="bg-slate-950 dark:bg-black rounded-2xl p-4 border-2 border-slate-800 shadow-xl flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-center justify-between text-xs font-mono tracking-widest uppercase mb-1">
                        <span class="text-emerald-500/90 font-bold flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>TOTAL FACTURADO</span>
                        </span>
                        <span class="text-slate-400 text-xs" x-text="'SUBTOTAL: $ ' + formatDinero(calcularSubtotal())"></span>
                    </div>

                    <div class="flex items-baseline justify-between my-1">
                        <span class="text-4xl sm:text-5xl lg:text-5xl xl:text-6xl font-mono font-black text-white dark:text-emerald-400 tracking-tight tabular-nums"
                              x-text="'$ ' + formatDinero(calcularTotal())">
                        </span>

                        <template x-if="items.length > 0">
                            <span class="text-xs font-mono font-bold text-slate-400" x-text="items.length + ' art.'"></span>
                        </template>
                    </div>

                    <!-- Detalle de Financiación si es Crédito Personal -->
                    <template x-if="medioPago === 'credito_personal'">
                        <div class="mt-2 pt-2 border-t border-slate-800 flex items-center justify-between text-xs font-mono">
                            <span class="text-amber-400 font-bold" x-text="'Interés ' + tasaInteres + '% (+$ ' + formatDinero(calcularRecargo()) + ')'"></span>
                            <span class="text-indigo-400 font-bold uppercase text-sm" x-text="cuotasPlan + ' x $' + formatDinero(calcularValorCuota(cuotasPlan))"></span>
                        </div>
                    </template>
                </div>

                <!-- TARJETA DE MEDIOS DE PAGO (BOTONES GRANDES TABLET TOUCH) -->
                <div class="bg-white dark:bg-slate-800 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-xs font-black uppercase text-slate-700 dark:text-slate-300 tracking-wider flex items-center gap-1.5">
                            <span>💳</span>
                            <span>Seleccionar Medio de Pago</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono font-bold uppercase" x-text="medioPago.replace('_', ' ')"></span>
                    </div>

                    <!-- 4 Botones Grandes de Medios de Pago -->
                    <div class="grid grid-cols-2 gap-2.5">
                        {{-- Botón 1: Efectivo --}}
                        <button type="button" @click="medioPago = 'efectivo'"
                                :class="medioPago === 'efectivo' 
                                    ? 'bg-emerald-600 text-white font-black border-emerald-600 shadow-md ring-2 ring-emerald-400/50' 
                                    : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                class="py-3 px-3 rounded-xl border text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition min-h-[58px] active:scale-98">
                            <span class="text-xl">💵</span>
                            <span class="tracking-tight uppercase font-black">Efectivo</span>
                        </button>

                        {{-- Botón 2: Débito --}}
                        <button type="button" @click="medioPago = 'tarjeta_debito'"
                                :class="medioPago === 'tarjeta_debito' 
                                    ? 'bg-cyan-600 text-white font-black border-cyan-600 shadow-md ring-2 ring-cyan-400/50' 
                                    : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                class="py-3 px-3 rounded-xl border text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition min-h-[58px] active:scale-98">
                            <span class="text-xl">💳</span>
                            <span class="tracking-tight uppercase font-black">T. Débito</span>
                        </button>

                        {{-- Botón 3: Tarjeta Crédito --}}
                        <button type="button" @click="medioPago = 'tarjeta_credito'"
                                :class="medioPago === 'tarjeta_credito' 
                                    ? 'bg-blue-600 text-white font-black border-blue-600 shadow-md ring-2 ring-blue-400/50' 
                                    : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                class="py-3 px-3 rounded-xl border text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition min-h-[58px] active:scale-98">
                            <span class="text-xl">💳</span>
                            <span class="tracking-tight uppercase font-black">T. Crédito</span>
                        </button>

                        {{-- Botón 4: Crédito Personal --}}
                        <button type="button" 
                                @click="if (clienteSeleccionado) { medioPago = 'credito_personal'; } else { alert('Para pagar a crédito personal debe seleccionar un cliente registrado.'); }"
                                :disabled="!clienteSeleccionado"
                                :class="{
                                    'bg-indigo-600 text-white font-black border-indigo-600 shadow-md ring-2 ring-indigo-400/50': medioPago === 'credito_personal',
                                    'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100': medioPago !== 'credito_personal' && clienteSeleccionado,
                                    'opacity-40 cursor-not-allowed bg-slate-100 dark:bg-slate-900 text-slate-400 border-slate-200 dark:border-slate-800': !clienteSeleccionado
                                }"
                                class="py-3 px-3 rounded-xl border text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition min-h-[58px] active:scale-98"
                                title="Habilitado al seleccionar cliente registrado">
                            <span class="text-xl">📄</span>
                            <div class="text-left">
                                <span class="tracking-tight uppercase font-black block">Crédito</span>
                                <span class="text-[9px] block opacity-80" x-text="clienteSeleccionado ? 'Habilitado' : 'Requiere Cliente'"></span>
                            </div>
                        </button>
                    </div>

                    <!-- SELECTOR DE CUOTAS (SI ES CRÉDITO PERSONAL) -->
                    <template x-if="medioPago === 'credito_personal' && clienteSeleccionado">
                        <div class="pt-2.5 border-t border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase text-indigo-900 dark:text-indigo-300 flex items-center gap-1">
                                    <span>Plan de Cuotas a Crédito</span>
                                </span>
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400" 
                                      x-text="'Interés lote: ' + tasaInteres + '%'">
                                </span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <template x-for="n in [1, 2, 3, 4]" :key="n">
                                    <button type="button" @click="cuotasPlan = n"
                                            :disabled="infoCredito && n > infoCredito.estado_crediticio.max_cuotas"
                                            :class="{
                                                'bg-indigo-700 text-white font-black border-indigo-800 shadow-md ring-2 ring-indigo-400': cuotasPlan === n,
                                                'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 hover:border-indigo-300': cuotasPlan !== n,
                                                'opacity-30 cursor-not-allowed': infoCredito && n > infoCredito.estado_crediticio.max_cuotas
                                            }"
                                            class="p-2.5 rounded-xl border text-left transition flex flex-col justify-between active:scale-98 min-h-[64px]">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black uppercase" x-text="n + (n === 1 ? ' Pago' : ' Pagos')"></span>
                                            <span x-show="cuotasPlan === n" class="text-white text-xs font-bold">✓</span>
                                        </div>
                                        <div class="mt-1">
                                            <span class="text-xs font-mono font-bold tabular-nums block"
                                                  :class="cuotasPlan === n ? 'text-amber-300' : 'text-indigo-600 dark:text-indigo-400'"
                                                  x-text="'$ ' + formatDinero(calcularValorCuota(n))">
                                            </span>
                                            <span class="text-[9px] block text-slate-400" x-text="n > 1 ? '/ cuota' : 'Total'"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- PANEL DE COBRO EN EFECTIVO RÁPIDO & VUELTO (SI ES EFECTIVO) -->
                    <template x-if="medioPago === 'efectivo'">
                        <div class="pt-2.5 border-t border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-700 dark:text-slate-300 uppercase">Cálculo Rápido de Vuelto:</span>
                                <button type="button" @click="pagaCon = calcularTotal()" class="text-xs font-bold text-emerald-600 hover:underline">
                                    Importe Exacto
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-0.5">Paga con:</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-xs font-black text-slate-400">$</span>
                                        <input type="number" step="100" 
                                               x-model.number="pagaCon" 
                                               @keydown.enter.prevent="abrirModalPago()"
                                               placeholder="0.00"
                                               class="w-full text-sm font-mono font-bold py-1.5 pl-6 pr-2 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                    </div>
                                </div>

                                <div class="bg-slate-50 dark:bg-slate-900 p-2 rounded-lg border border-slate-200 dark:border-slate-700 flex flex-col justify-between">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase">Vuelto a entregar:</span>
                                    <span class="font-mono font-black text-base tabular-nums"
                                          :class="(pagaCon - calcularTotal()) >= 0 ? 'text-emerald-600' : 'text-slate-400'"
                                          x-text="'$ ' + formatDinero(Math.max(0, pagaCon - calcularTotal()))">
                                    </span>
                                </div>
                            </div>

                            <!-- Botones rápidos de billetes comunes -->
                            <div class="flex flex-wrap gap-1 pt-1">
                                <button type="button" @click="pagaCon = 2000" class="px-2 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 rounded text-[10px] font-bold font-mono">$2.000</button>
                                <button type="button" @click="pagaCon = 5000" class="px-2 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 rounded text-[10px] font-bold font-mono">$5.000</button>
                                <button type="button" @click="pagaCon = 10000" class="px-2 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 rounded text-[10px] font-bold font-mono">$10.000</button>
                                <button type="button" @click="pagaCon = 20000" class="px-2 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 rounded text-[10px] font-bold font-mono">$20.000</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- BOTÓN PRINCIPAL DE COBRO (TABLET ACCIÓN PRIMARIA GIGANTE) -->
                <div>
                    <button type="button" 
                            @click="abrirModalPago()"
                            :disabled="items.length === 0 || procesando"
                            :class="items.length === 0 
                                ? 'bg-slate-200 dark:bg-slate-700 cursor-not-allowed text-slate-400 dark:text-slate-500' 
                                : (medioPago === 'credito_personal' 
                                    ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-xl hover:shadow-indigo-500/25 active:scale-98' 
                                    : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xl hover:shadow-emerald-500/25 active:scale-98')"
                            class="w-full py-4 px-4 rounded-2xl font-black text-base sm:text-lg uppercase tracking-wide transition flex items-center justify-center gap-2">
                        <span x-text="procesando ? 'Procesando...' : (medioPago === 'credito_personal' ? 'Verificar y Facturar Crédito' : 'PAGAR / COBRAR AHORA')"></span>
                        <span x-show="!procesando" class="font-mono text-sm sm:text-base font-bold" x-text="'($ ' + formatDinero(calcularTotal()) + ')'"></span>
                    </button>
                </div>

            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL 1: VERIFICACIÓN Y AUTORIZACIÓN DE CRÉDITO PERSONAL            -->
        <!-- =================================================================== -->
        <div x-show="modalCredito" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4"
             x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-4"
                 @click.away="modalCredito = false">
                
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center font-bold text-sm">
                            📄
                        </span>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white uppercase">
                                Verificación de Crédito Personal
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Control de legajo y mora en cuenta corriente</p>
                        </div>
                    </div>
                    <button type="button" @click="modalCredito = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <!-- Diagnóstico Crediticio del Cliente -->
                <template x-if="infoCredito">
                    <div class="space-y-3">
                        <!-- Ficha del Cliente -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl text-xs space-y-1">
                            <div class="flex justify-between font-bold">
                                <span class="text-slate-500">Titular:</span>
                                <span class="text-slate-900 dark:text-white" x-text="infoCredito.cliente.nombre"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">DNI:</span>
                                <span class="font-mono text-slate-700 dark:text-slate-300" x-text="infoCredito.cliente.dni"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Límite Disponible:</span>
                                <span class="font-mono font-bold text-emerald-600" x-text="'$ ' + formatDinero(infoCredito.estado_crediticio.limite_disponible)"></span>
                            </div>
                        </div>

                        <!-- ALERTA SI ESTÁ SUSPENDIDO -->
                        <div x-show="infoCredito.estado_crediticio.suspendido" 
                             class="p-3 bg-rose-50 dark:bg-rose-950/60 border-l-4 border-rose-500 rounded-r-lg text-rose-800 dark:text-rose-200 text-xs space-y-1">
                            <span class="font-black uppercase block">⛔ CLIENTE SUSPENDIDO</span>
                            <p>El cliente se encuentra bloqueado para emitir operaciones a crédito personal.</p>
                        </div>

                        <!-- ALERTA SI TIENE MORA -->
                        <div x-show="infoCredito.estado_crediticio.tiene_mora" 
                             class="p-3 bg-amber-50 dark:bg-amber-950/60 border-l-4 border-amber-500 rounded-r-lg text-amber-800 dark:text-amber-200 text-xs space-y-1">
                            <span class="font-black uppercase block">⚠️ CLIENTE CON MORA REGISTRADA</span>
                            <p class="font-bold" x-text="'Registra ' + infoCredito.estado_crediticio.cuotas_mora_count + ' cuota(s) impagas con mora por un total de $ ' + formatDinero(infoCredito.estado_crediticio.total_mora) + '.'"></p>
                            <ul class="list-disc list-inside text-[11px] mt-1 space-y-0.5 text-amber-900 dark:text-amber-300">
                                <template x-for="(mora, mIdx) in infoCredito.estado_crediticio.detalle_mora" :key="mIdx">
                                    <li x-text="mora"></li>
                                </template>
                            </ul>
                        </div>

                        <!-- ALERTA SI EXCEDE LÍMITE -->
                        <div x-show="!infoCredito.estado_crediticio.suspendido && !infoCredito.estado_crediticio.tiene_mora && calcularTotal() > infoCredito.estado_crediticio.limite_disponible" 
                             class="p-3 bg-amber-50 dark:bg-amber-950/60 border-l-4 border-amber-500 rounded-r-lg text-amber-800 dark:text-amber-200 text-xs space-y-1">
                            <span class="font-black uppercase block">⚠️ SUPERA EL LÍMITE DISPONIBLE</span>
                            <p x-text="'El importe total ($ ' + formatDinero(calcularTotal()) + ') excede el disponible actual ($ ' + formatDinero(infoCredito.estado_crediticio.limite_disponible) + ').'"></p>
                        </div>

                        <!-- TODO OK: HABILITADO -->
                        <div x-show="infoCredito.estado_crediticio.habilitado && calcularTotal() <= infoCredito.estado_crediticio.limite_disponible" 
                             class="p-3 bg-emerald-50 dark:bg-emerald-950/60 border-l-4 border-emerald-500 rounded-r-lg text-emerald-800 dark:text-emerald-200 text-xs">
                            <span class="font-black uppercase block">✓ CRÉDITO APROBADO</span>
                            <p>El cliente se encuentra al día, sin mora y con margen de crédito suficiente.</p>
                        </div>

                        <!-- Resumen de las Cuotas a Generar con Interés 6% -->
                        <div class="p-3 bg-indigo-50 dark:bg-indigo-950/50 rounded-xl border border-indigo-200 dark:border-indigo-800 text-xs space-y-1">
                            <div class="flex justify-between">
                                <span class="font-bold text-slate-700 dark:text-slate-300">Total a Financiar (con interés):</span>
                                <span class="font-mono font-black text-indigo-700 dark:text-indigo-300" x-text="'$ ' + formatDinero(calcularTotal())"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-bold text-slate-700 dark:text-slate-300">Plan Seleccionado:</span>
                                <span class="font-black text-slate-900 dark:text-white" x-text="cuotasPlan + (cuotasPlan === 1 ? ' Pago' : ' Pagos')"></span>
                            </div>
                            <div class="flex justify-between text-sm pt-1 border-t border-indigo-200/60 dark:border-indigo-900/60">
                                <span class="font-black text-indigo-900 dark:text-indigo-200">Importe de cada Cuota:</span>
                                <span class="font-mono font-black text-indigo-700 dark:text-indigo-400" x-text="'$ ' + formatDinero(calcularValorCuota(cuotasPlan))"></span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Botones del Modal de Crédito -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <!-- Si NO puede pagar a crédito (Mora / Suspendido / Límite): Botón para volver y pagar con efectivo/tarjeta -->
                    <template x-if="infoCredito && (!infoCredito.estado_crediticio.habilitado || calcularTotal() > infoCredito.estado_crediticio.limite_disponible)">
                        <div class="w-full space-y-2">
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 text-center">
                                Debido a la condición de la cuenta, la venta no puede realizarse a crédito. Puede cobrar con otros medios de pago:
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" @click="cambiarMedioYFacturar('efectivo')"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2 px-2 rounded-lg transition text-center uppercase">
                                    💵 Efectivo
                                </button>
                                <button type="button" @click="cambiarMedioYFacturar('tarjeta_debito')"
                                        class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-xs py-2 px-2 rounded-lg transition text-center uppercase">
                                    💳 Débito
                                </button>
                                <button type="button" @click="cambiarMedioYFacturar('tarjeta_credito')"
                                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2 px-2 rounded-lg transition text-center uppercase">
                                    💳 Tarjeta
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Si está HABILITADO para crédito -->
                    <template x-if="infoCredito && infoCredito.estado_crediticio.habilitado && calcularTotal() <= infoCredito.estado_crediticio.limite_disponible">
                        <div class="flex items-center justify-end gap-2 w-full">
                            <button type="button" @click="modalCredito = false"
                                    class="bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 font-bold text-xs py-2 px-4 rounded-lg transition uppercase">
                                Volver a Factura
                            </button>
                            <button type="button" @click="enviarVenta()"
                                    :disabled="procesando"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs py-2 px-5 rounded-lg shadow transition uppercase flex items-center gap-1">
                                <span x-text="procesando ? 'Emitiendo...' : 'Confirmar y Facturar Crédito'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL 2: COBRO RÁPIDO EFECTIVO / TARJETA                            -->
        <!-- =================================================================== -->
        <div x-show="modalCobro" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4"
             x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-4"
                 @click.away="modalCobro = false">
                
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <h3 class="text-base font-black text-slate-900 dark:text-white uppercase flex items-center gap-2">
                        <span x-text="medioPago === 'efectivo' ? '💵 Cobro en Efectivo' : '💳 Cobro con Tarjeta'"></span>
                    </h3>
                    <button type="button" @click="modalCobro = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="text-center py-2">
                    <span class="text-xs text-slate-500 uppercase font-bold block">Total a Pagar</span>
                    <span class="text-3xl font-black text-slate-900 dark:text-white font-mono tabular-nums"
                          x-text="'$ ' + formatDinero(calcularTotal())">
                    </span>
                </div>

                <!-- Calculador de Vuelto si es Efectivo -->
                <template x-if="medioPago === 'efectivo'">
                    <div class="space-y-3 bg-slate-50 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Paga con:</label>
                            <input type="number" step="100" 
                                   x-model.number="pagaCon" 
                                   @keydown.enter.prevent="enviarVenta()"
                                   placeholder="0.00"
                                   class="w-full text-base font-mono font-bold py-1.5 px-3 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                        </div>

                        <!-- Botones de billetes rápidos -->
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="pagaCon = calcularTotal()" class="px-2 py-1 bg-white dark:bg-slate-800 border rounded text-[10px] font-bold">Exacto</button>
                            <button type="button" @click="pagaCon = 2000" class="px-2 py-1 bg-white dark:bg-slate-800 border rounded text-[10px] font-bold">$2.000</button>
                            <button type="button" @click="pagaCon = 5000" class="px-2 py-1 bg-white dark:bg-slate-800 border rounded text-[10px] font-bold">$5.000</button>
                            <button type="button" @click="pagaCon = 10000" class="px-2 py-1 bg-white dark:bg-slate-800 border rounded text-[10px] font-bold">$10.000</button>
                            <button type="button" @click="pagaCon = 20000" class="px-2 py-1 bg-white dark:bg-slate-800 border rounded text-[10px] font-bold">$20.000</button>
                        </div>

                        <!-- Vuelto calculado -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                            <span class="font-bold text-slate-600 dark:text-slate-400">Vuelto a entregar:</span>
                            <span class="font-mono font-black text-base tabular-nums"
                                  :class="(pagaCon - calcularTotal()) >= 0 ? 'text-emerald-600' : 'text-slate-400'"
                                  x-text="'$ ' + formatDinero(Math.max(0, pagaCon - calcularTotal()))">
                            </span>
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" @click="modalCobro = false" 
                            class="bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 font-bold text-xs py-2 px-4 rounded-lg uppercase">
                        Cancelar
                    </button>
                    <button type="button" @click="enviarVenta()"
                            :disabled="procesando"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs py-2 px-6 rounded-lg shadow uppercase">
                        <span x-text="procesando ? 'Facturando...' : 'Emitir Factura'"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL 3: VENTA EXITOSA & IMPRESIÓN DE COMPROBANTES                  -->
        <!-- =================================================================== -->
        <div x-show="modalExito" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/80 backdrop-blur-xs flex items-center justify-center p-4"
             x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 text-center space-y-4">
                
                <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto text-3xl font-black shadow-inner">
                    ✓
                </div>

                <div>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest block">¡Venta Registrada Exitosamente!</span>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white font-mono mt-1" x-text="ventaExitosa.comprobante"></h3>
                    <p class="text-xs text-slate-500 mt-1" x-text="'Total Facturado: $ ' + formatDinero(ventaExitosa.total)"></p>
                </div>

                <!-- Botones de Impresión Oficial -->
                <div class="space-y-2 pt-2">
                    <a :href="ventaExitosa.ticket_url" target="_blank"
                       class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow transition flex items-center justify-center gap-2 uppercase">
                        <span>🖨️ Imprimir Ticket Térmico (80mm)</span>
                    </a>

                    <a :href="ventaExitosa.a4_url" target="_blank"
                       class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow transition flex items-center justify-center gap-2 uppercase">
                        <span>📄 Imprimir Factura A4</span>
                    </a>

                    <a :href="ventaExitosa.redirect_url"
                       class="w-full py-2 px-4 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition flex items-center justify-center gap-2 uppercase">
                        <span>👁️ Ver Ficha de la Factura</span>
                    </a>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-700">
                    <button type="button" @click="iniciarNuevaVenta()" 
                            class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold text-xs uppercase">
                        ➕ Iniciar Siguiente Venta de Mostrador
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL 4: CORRECCIÓN POR RECUENTO FÍSICO / SIN STOCK                 -->
        <!-- =================================================================== -->
        <div x-show="modalRecuento" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4"
             x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border-2 border-amber-500/80 dark:border-amber-500/60 space-y-4"
                 @click.away="modalRecuento = false; focusBarcode();">
                
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-400 flex items-center justify-center font-bold text-base shrink-0 border border-amber-300 dark:border-amber-700">
                            ⚠️
                        </span>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                Artículo sin Stock Disponible
                            </h3>
                            <span class="text-[10px] text-amber-700 dark:text-amber-400 font-bold uppercase tracking-wider block">
                                Sucursal: {{ $sucursalActiva->nombre ?? 'Trelew' }}
                            </span>
                        </div>
                    </div>
                    <button type="button" @click="modalRecuento = false; focusBarcode();" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <!-- Detalle del Producto -->
                <div class="bg-slate-50 dark:bg-slate-900/70 p-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white block text-sm" x-text="productoSinStock?.descripcion"></span>
                            <span class="font-mono text-[10px] text-slate-500" x-text="'Cód: ' + (productoSinStock?.codigo || '-') + ' | EAN: ' + (productoSinStock?.codigo_barra || '-')"></span>
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-black text-indigo-700 dark:text-indigo-400 text-sm block" x-text="'$ ' + formatDinero(productoSinStock?.precio_venta)"></span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/70 dark:border-slate-800">
                        <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">Stock actual en sistema:</span>
                        <span class="text-xs font-mono font-black px-2 py-0.5 rounded"
                              :class="(productoSinStock?.stock || 0) <= 0 ? 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-800' : 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300'"
                              x-text="(productoSinStock?.stock || 0) + ' unidades'">
                        </span>
                    </div>
                </div>

                <!-- Explicación operativa -->
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 space-y-1">
                    <p class="font-bold">
                        ¿Desea registrar una corrección de stock por recuento físico en mostrador?
                    </p>
                    <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-tight">
                        Al autorizar el recuento, se registrará el ingreso por ajuste físico de la cantidad indicada para habilitar la venta y que, al emitir la factura, el stock se descuente y vuelva a quedar en cero (evitando generar stock negativo).
                    </p>
                </div>

                <!-- Input Cantidad a ingresar por recuento -->
                <div class="space-y-3 bg-white dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-700 dark:text-slate-300 mb-1">
                            Cantidad a ingresar por recuento físico:
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="if (recuentoCantidad > 1) recuentoCantidad--"
                                    class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 font-black text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition">
                                -
                            </button>
                            <input type="number" min="0.01" step="1" 
                                   x-model.number="recuentoCantidad"
                                   @keydown.enter.prevent="confirmarRecuento()"
                                   class="w-24 text-center font-mono font-black text-base py-1.5 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                            <button type="button" @click="recuentoCantidad++"
                                    class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 font-black text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition">
                                +
                            </button>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold ml-2">
                                unidad(es) físicas encontradas
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">
                            Motivo de la corrección:
                        </label>
                        <input type="text" x-model="recuentoMotivo" 
                               class="w-full text-xs py-1.5 px-3 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                               placeholder="Recuento físico previo a facturación en mostrador">
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" @click="modalRecuento = false; focusBarcode();"
                            class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold text-xs py-2 px-4 rounded-xl transition uppercase">
                        Cancelar (No Cargar)
                    </button>
                    <button type="button" @click="confirmarRecuento()"
                            :disabled="procesandoRecuento"
                            class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black text-xs py-2 px-5 rounded-xl shadow-md transition uppercase flex items-center gap-1.5">
                        <span x-show="!procesandoRecuento">✓ Autorizar Recuento y Cargar (+<span x-text="recuentoCantidad"></span>)</span>
                        <span x-show="procesandoRecuento">Procesando ajuste...</span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Script de Reactividad Alpine.js del POS -->
    <script>
        function posApp() {
            return {
                items: [],
                barcodeQuery: '',
                sugerenciasProductos: [],
                clienteSeleccionado: null,
                clienteQuery: '',
                sugerenciasClientes: [],
                medioPago: 'efectivo',
                cuotasPlan: 1,
                tasaInteres: {{ (float)$tasaInteresCredito }},
                montoGenerico: '',
                infoCredito: null,
                modalCredito: false,
                modalCobro: false,
                modalExito: false,
                modalRecuento: false,
                productoSinStock: null,
                recuentoCantidad: 1,
                recuentoMotivo: 'Recuento físico previo a facturación en mostrador',
                procesandoRecuento: false,
                pagaCon: 0,
                procesando: false,
                ventaExitosa: {
                    id: null,
                    comprobante: '',
                    total: 0,
                    ticket_url: '#',
                    a4_url: '#',
                    redirect_url: '#'
                },

                initPos() {
                    this.focusBarcode();

                    // Atajo de teclado: espacio para cobrar, esc para volver al lector
                    window.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') {
                            this.modalCredito = false;
                            this.modalCobro = false;
                            this.modalRecuento = false;
                            this.focusBarcode();
                        }
                    });
                },

                focusBarcode() {
                    this.$nextTick(() => {
                        if (this.$refs.barcodeInput) {
                            this.$refs.barcodeInput.focus();
                        }
                    });
                },

                calcularSubtotal() {
                    let sum = 0;
                    for (let it of this.items) {
                        sum += (Number(it.cantidad) || 0) * (Number(it.precio_unitario) || 0);
                    }
                    return Math.round(sum * 100) / 100;
                },

                calcularRecargo() {
                    if (this.medioPago === 'credito_personal' && this.tasaInteres > 0) {
                        return Math.round(this.calcularSubtotal() * (this.tasaInteres / 100) * 100) / 100;
                    }
                    return 0;
                },

                calcularTotal() {
                    return Math.round((this.calcularSubtotal() + this.calcularRecargo()) * 100) / 100;
                },

                calcularValorCuota(cuotas) {
                    let totalFinanciado = this.calcularTotal();
                    if (!cuotas || cuotas < 1) cuotas = 1;
                    return Math.round((totalFinanciado / cuotas) * 100) / 100;
                },

                formatDinero(val) {
                    if (val === undefined || val === null || isNaN(val)) return '0,00';
                    return Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                // Procesamiento de código de barras
                async procesarCodigoBarra() {
                    let q = this.barcodeQuery.trim();
                    if (!q) return;

                    try {
                        let res = await fetch(`{{ route('ventas.buscar-producto') }}?q=${encodeURIComponent(q)}&sucursal_id={{ $sucursalActiva->id ?? 1 }}`);
                        let data = await res.json();

                        if (data.exact && data.producto) {
                            this.intentarAgregarProducto(data.producto);
                            this.barcodeQuery = '';
                            this.sugerenciasProductos = [];
                            this.focusBarcode();
                        } else if (data.productos && data.productos.length === 1) {
                            this.intentarAgregarProducto(data.productos[0]);
                            this.barcodeQuery = '';
                            this.sugerenciasProductos = [];
                            this.focusBarcode();
                        } else if (data.productos && data.productos.length > 1) {
                            this.sugerenciasProductos = data.productos;
                        } else {
                            alert(`No se encontró ningún artículo con el código "${q}".`);
                            this.barcodeQuery = '';
                        }
                    } catch (e) {
                        console.error('Error buscando producto:', e);
                    }
                },

                async buscarProductosSugeridos() {
                    let q = this.barcodeQuery.trim();
                    if (q.length < 2) {
                        this.sugerenciasProductos = [];
                        return;
                    }

                    try {
                        let res = await fetch(`{{ route('ventas.buscar-producto') }}?q=${encodeURIComponent(q)}&sucursal_id={{ $sucursalActiva->id ?? 1 }}`);
                        let data = await res.json();
                        this.sugerenciasProductos = data.productos || [];
                    } catch (e) {
                        console.error('Error en sugerencias:', e);
                    }
                },

                intentarAgregarProducto(prod, cantidadDeseada = 1) {
                    if (!prod) return false;
                    let exist = this.items.find(it => it.id_producto === prod.id);
                    let cantActual = exist ? exist.cantidad : 0;
                    let cantFinal = cantActual + cantidadDeseada;

                    // Si controla stock y la cantidad supera el stock disponible en la sucursal
                    if (prod.controla_stock && cantFinal > (Number(prod.stock) || 0)) {
                        let faltante = Math.max(1, cantFinal - Math.max(0, Number(prod.stock) || 0));
                        this.abrirModalRecuento(prod, cantFinal, faltante);
                        return false;
                    }

                    this.agregarItem(prod, cantidadDeseada);
                    return true;
                },

                abrirModalRecuento(prod, cantDeseada, faltante) {
                    this.productoSinStock = {
                        id: prod.id,
                        codigo: prod.codigo,
                        codigo_barra: prod.codigo_barra,
                        descripcion: prod.descripcion,
                        precio_venta: prod.precio_venta,
                        controla_stock: prod.controla_stock,
                        stock: Number(prod.stock) || 0,
                        cantDeseada: cantDeseada
                    };
                    this.recuentoCantidad = Math.max(1, Math.round(faltante));
                    this.recuentoMotivo = 'Recuento físico previo a facturación en mostrador';
                    this.modalRecuento = true;
                },

                async confirmarRecuento() {
                    if (!this.productoSinStock || this.procesandoRecuento) return;

                    let cantAjuste = parseFloat(this.recuentoCantidad);
                    if (isNaN(cantAjuste) || cantAjuste <= 0) {
                        alert('Por favor ingrese una cantidad válida mayor a cero.');
                        return;
                    }

                    this.procesandoRecuento = true;

                    try {
                        let res = await fetch(`{{ route('ventas.ajuste-recuento-rapido') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                id_producto: this.productoSinStock.id,
                                id_sucursal: {{ $sucursalActiva->id ?? 1 }},
                                cantidad: cantAjuste,
                                motivo: this.recuentoMotivo || 'Recuento físico previo a facturación en mostrador'
                            })
                        });

                        let data = await res.json();

                        if (!res.ok || !data.success) {
                            alert(data.error || data.message || 'Error al registrar el recuento.');
                            this.procesandoRecuento = false;
                            return;
                        }

                        // Actualizar datos de stock del producto retornado
                        let prodActualizado = data.producto;

                        // Actualizar stock en lista de sugerencias si está abierta
                        let enSugerencias = this.sugerenciasProductos.find(p => p.id === prodActualizado.id);
                        if (enSugerencias) {
                            enSugerencias.stock = prodActualizado.stock;
                        }

                        // Cantidad requerida a agregar a la factura
                        let cantAAgregar = 1;
                        if (this.productoSinStock.cantDeseada) {
                            let yaEnCarrito = this.items.find(it => it.id_producto === prodActualizado.id)?.cantidad || 0;
                            cantAAgregar = Math.max(1, this.productoSinStock.cantDeseada - yaEnCarrito);
                        }

                        this.agregarItem(prodActualizado, cantAAgregar);

                        this.modalRecuento = false;
                        this.productoSinStock = null;
                        this.barcodeQuery = '';
                        this.sugerenciasProductos = [];
                        this.focusBarcode();

                    } catch (e) {
                        console.error('Error al registrar recuento:', e);
                        alert('Error de conexión al registrar el recuento físico.');
                    } finally {
                        this.procesandoRecuento = false;
                    }
                },

                agregarItem(prod, cantidad = 1) {
                    let exist = this.items.find(it => it.id_producto === prod.id);
                    if (exist) {
                        exist.cantidad += cantidad;
                        exist.stock = prod.stock;
                    } else {
                        this.items.push({
                            id_producto: prod.id,
                            codigo_producto: prod.codigo,
                            codigo: prod.codigo,
                            codigo_barra: prod.codigo_barra,
                            descripcion: prod.descripcion,
                            cantidad: cantidad,
                            precio_unitario: Number(prod.precio_venta) || 0,
                            controla_stock: prod.controla_stock,
                            stock: prod.stock
                        });
                    }
                },

                agregarArticuloGenerico() {
                    let monto = parseFloat(this.montoGenerico);
                    if (isNaN(monto) || monto <= 0) {
                        alert('Ingrese un monto válido para el artículo varios.');
                        return;
                    }

                    this.items.push({
                        id_producto: {{ $productoGenerico->id ?? 'null' }},
                        codigo_producto: '{{ $productoGenerico->codigo ?? '1' }}',
                        codigo: '{{ $productoGenerico->codigo ?? '1' }}',
                        codigo_barra: '{{ $productoGenerico->codigo_barra ?? '1' }}',
                        descripcion: 'VARIOS - ARTICULO GENERAL',
                        cantidad: 1,
                        precio_unitario: monto,
                        controla_stock: false,
                        stock: 9999
                    });

                    this.montoGenerico = '';
                    this.focusBarcode();
                },

                cambiarCantidad(index, delta) {
                    if (!this.items[index]) return;
                    let item = this.items[index];
                    let nueva = item.cantidad + delta;
                    if (nueva <= 0) {
                        this.quitarItem(index);
                        return;
                    }
                    if (delta > 0 && item.controla_stock && nueva > (Number(item.stock) || 0)) {
                        let faltante = nueva - Math.max(0, Number(item.stock) || 0);
                        this.abrirModalRecuento(item, nueva, faltante);
                        return;
                    }
                    item.cantidad = nueva;
                },

                validarItem(index) {
                    if (!this.items[index]) return;
                    let item = this.items[index];
                    if (item.cantidad <= 0) item.cantidad = 1;
                    if (item.precio_unitario < 0) item.precio_unitario = 0;
                    if (item.controla_stock && item.cantidad > (Number(item.stock) || 0)) {
                        let solicitada = item.cantidad;
                        let faltante = solicitada - Math.max(0, Number(item.stock) || 0);
                        item.cantidad = Math.max(1, Math.min(solicitada - 1, Number(item.stock) || 0));
                        this.abrirModalRecuento(item, solicitada, faltante);
                    }
                },

                quitarItem(index) {
                    this.items.splice(index, 1);
                    this.focusBarcode();
                },

                limpiarVenta() {
                    if (this.items.length > 0 && !confirm('¿Desea vaciar todos los artículos cargados?')) {
                        return;
                    }
                    this.items = [];
                    this.focusBarcode();
                },

                // Búsqueda y selección de clientes
                async buscarClientes() {
                    let q = this.clienteQuery.trim();
                    if (q.length < 2) {
                        this.sugerenciasClientes = [];
                        return;
                    }

                    try {
                        let res = await fetch(`{{ route('clientes.buscar') }}?q=${encodeURIComponent(q)}`);
                        let data = await res.json();
                        this.sugerenciasClientes = data || [];
                    } catch (e) {
                        console.error('Error buscando clientes:', e);
                    }
                },

                async seleccionarCliente(cli) {
                    this.clienteSeleccionado = cli;
                    this.sugerenciasClientes = [];
                    this.clienteQuery = '';
                    await this.consultarInfoCredito(cli.id);
                },

                deseleccionarCliente() {
                    this.clienteSeleccionado = null;
                    this.infoCredito = null;
                    if (this.medioPago === 'credito_personal') {
                        this.medioPago = 'efectivo';
                    }
                    this.focusBarcode();
                },

                async consultarInfoCredito(clienteId) {
                    try {
                        let monto = this.calcularSubtotal();
                        let res = await fetch(`{{ url('/ventas/clientes') }}/${clienteId}/info-credito?monto=${monto}&sucursal_id={{ $sucursalActiva->id ?? 1 }}`);
                        let data = await res.json();
                        this.infoCredito = data;
                    } catch (e) {
                        console.error('Error consultando crédito:', e);
                    }
                },

                abrirModalPago() {
                    if (this.items.length === 0) {
                        alert('Cargue al menos un artículo en la factura para continuar.');
                        return;
                    }

                    if (this.medioPago === 'credito_personal') {
                        if (!this.clienteSeleccionado) {
                            alert('Para pagar a crédito debe seleccionar un cliente registrado.');
                            return;
                        }
                        this.consultarInfoCredito(this.clienteSeleccionado.id);
                        this.modalCredito = true;
                    } else {
                        if (!this.pagaCon || this.pagaCon < this.calcularTotal()) {
                            this.pagaCon = this.calcularTotal();
                        }
                        this.modalCobro = true;
                    }
                },

                cambiarMedioYFacturar(nuevoMedio) {
                    this.medioPago = nuevoMedio;
                    this.modalCredito = false;
                    this.modalCobro = true;
                    this.pagaCon = this.calcularTotal();
                },

                async enviarVenta() {
                    if (this.procesando) return;
                    this.procesando = true;

                    let payload = {
                        _token: '{{ csrf_token() }}',
                        id_sucursal: {{ $sucursalActiva->id ?? 1 }},
                        id_cliente: this.clienteSeleccionado ? this.clienteSeleccionado.id : null,
                        cliente_nombre: this.clienteSeleccionado ? this.clienteSeleccionado.nombre : 'CONSUMIDOR FINAL',
                        cliente_documento: this.clienteSeleccionado ? this.clienteSeleccionado.dni : null,
                        medio_pago: this.medioPago,
                        cuotas: this.medioPago === 'credito_personal' ? this.cuotasPlan : null,
                        items: this.items
                    };

                    try {
                        let res = await fetch(`{{ route('ventas.store') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });

                        let data = await res.json();

                        if (!res.ok) {
                            alert(data.error || 'Ocurrió un error al procesar la venta.');
                            this.procesando = false;
                            return;
                        }

                        // Éxito: cerrar modales de cobro y mostrar modal final
                        this.modalCredito = false;
                        this.modalCobro = false;
                        this.ventaExitosa = {
                            id: data.venta_id,
                            comprobante: data.comprobante,
                            total: data.total,
                            ticket_url: data.ticket_url,
                            a4_url: data.a4_url,
                            redirect_url: data.redirect_url
                        };
                        this.modalExito = true;
                    } catch (e) {
                        console.error('Error al enviar venta:', e);
                        alert('Error de conexión al emitir la factura.');
                    } finally {
                        this.procesando = false;
                    }
                },

                iniciarNuevaVenta() {
                    this.items = [];
                    this.clienteSeleccionado = null;
                    this.infoCredito = null;
                    this.medioPago = 'efectivo';
                    this.cuotasPlan = 1;
                    this.modalExito = false;
                    this.focusBarcode();
                }
            };
        }
    </script>
</x-app-layout>
