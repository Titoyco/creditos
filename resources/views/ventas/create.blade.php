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
        <!-- BANNER DE CONTROL DE CAJA / ESTADO DEL TURNO                        -->
        <!-- =================================================================== -->
        @if($requiereCierreAnterior && $cajaActiva)
            <div class="mb-3 p-3.5 bg-amber-50 dark:bg-amber-950/70 border-2 border-amber-500 rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-lg font-black shrink-0 shadow-sm animate-pulse">
                        ⚠️
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-amber-950 dark:text-amber-200">
                                Cierre de Jornada Anterior Pendiente (Caja #{{ $cajaActiva->id }})
                            </span>
                            <span class="text-[10px] bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 font-bold px-2 py-0.5 rounded-full">
                                Apertura: {{ $cajaActiva->fecha_apertura->format('d/m/Y H:i') }} hs
                            </span>
                        </div>
                        <p class="text-xs text-amber-800 dark:text-amber-300 mt-0.5">
                            La caja de la jornada anterior no fue cerrada. Debe ingresar el arqueo de efectivo físico (revisión de fallo de caja) e iniciar el turno de hoy antes de realizar la primera venta.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto shrink-0 justify-end">
                    <button type="button" @click="modalCierreAnterior = true"
                            class="bg-amber-600 hover:bg-amber-700 text-white font-black text-xs py-2 px-4 rounded-xl shadow transition uppercase flex items-center gap-1.5 cursor-pointer">
                        <span>⚠️ Arqueo Anterior y Abrir Hoy</span>
                    </button>
                    <a href="{{ route('cajas.index') }}" 
                       class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 font-bold text-xs py-2 px-3 rounded-xl transition hover:bg-slate-50">
                        <span>📋 Historial Cajas</span>
                    </a>
                </div>
            </div>
        @elseif($cajaActiva && $cajaActiva->esDeHoy())
            <div class="mb-3 p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm font-black shrink-0 shadow-sm">
                        ✓
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-900 dark:text-emerald-200 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                <span>Caja Abierta (Turno #{{ $cajaActiva->id }})</span>
                            </span>
                            <span class="text-[10px] bg-emerald-200 dark:bg-emerald-900 text-emerald-900 dark:text-emerald-200 font-bold px-2 py-0.5 rounded-full">
                                Hoy {{ $cajaActiva->fecha_apertura->format('H:i') }} hs
                            </span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                • Operador: <strong>{{ $cajaActiva->usuarioApertura?->name ?? 'Usuario' }}</strong>
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600 dark:text-slate-300 mt-0.5">
                            <span>Fondo Inicial: <strong class="text-slate-900 dark:text-white font-mono">${{ number_format($cajaActiva->monto_inicial_efectivo, 2, ',', '.') }}</strong></span>
                            <span>•</span>
                            <span>Ingresos (+): <strong class="text-emerald-700 dark:text-emerald-400 font-mono">+$<span x-text="formatDinero(totalIngresosCaja)">{{ number_format($totalesCajaActiva['total_ingresos'] ?? 0, 2, ',', '.') }}</span></strong></span>
                            <span>•</span>
                            <span>Retiros (-): <strong class="text-rose-700 dark:text-rose-400 font-mono">-$<span x-text="formatDinero(totalRetirosCaja)">{{ number_format($totalesCajaActiva['total_retiros'] ?? 0, 2, ',', '.') }}</span></strong></span>
                            <span>•</span>
                            <span>Efectivo en Caja: <strong class="text-emerald-700 dark:text-emerald-400 font-mono text-sm">$<span x-text="formatDinero(efectivoEsperadoCaja)">{{ number_format($totalesCajaActiva['efectivo_esperado'] ?? 0, 2, ',', '.') }}</span></strong></span>
                            <span>•</span>
                            <span>Total Facturado: <strong class="text-indigo-700 dark:text-indigo-400 font-mono">${{ number_format($totalesCajaActiva['total_general'] ?? 0, 2, ',', '.') }}</strong> ({{ $totalesCajaActiva['ventas_count'] ?? 0 }} ventas)</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto shrink-0 justify-end">
                    <button type="button" @click="abrirModalMovimiento()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-black text-xs py-2 px-3.5 rounded-xl shadow transition uppercase flex items-center gap-1.5 cursor-pointer">
                        <span>💸 Ingreso / Retiro</span>
                    </button>
                    <a href="{{ route('cajas.cerrar', $cajaActiva->id) }}" 
                       class="bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 text-white font-black text-xs py-2 px-3.5 rounded-xl shadow transition uppercase flex items-center gap-1.5">
                        <span>🔒 Cierre Z</span>
                    </a>
                    <a href="{{ route('cajas.index') }}" 
                       class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 font-bold text-xs py-2 px-3 rounded-xl transition hover:bg-slate-50">
                        <span>📋 Control Cajas</span>
                    </a>
                </div>
            </div>
        @else
            <div class="mb-3 p-3.5 bg-rose-50 dark:bg-rose-950/60 border-2 border-rose-400 dark:border-rose-700 rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center text-lg font-black shrink-0 shadow-sm animate-pulse">
                        🔒
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-rose-950 dark:text-rose-200">
                                Caja No Iniciada — Apertura Obligatoria
                            </span>
                            <span class="text-[10px] bg-rose-200 dark:bg-rose-900/80 text-rose-900 dark:text-rose-200 font-bold px-2 py-0.5 rounded-full uppercase">
                                Facturación Bloqueada
                            </span>
                        </div>
                        <p class="text-xs text-rose-800 dark:text-rose-300 mt-0.5">
                            Al inicio de la jornada se debe abrir la caja ingresando el dinero en efectivo disponible para habilitar la facturación de mostrador.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto shrink-0 justify-end">
                    <button type="button" @click="modalAbrirCaja = true"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs py-2 px-4 rounded-xl shadow transition uppercase flex items-center gap-1.5 cursor-pointer">
                        <span>🟢 Abrir Caja de Hoy</span>
                    </button>
                    <a href="{{ route('cajas.index') }}" 
                       class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 font-bold text-xs py-2 px-3 rounded-xl transition hover:bg-slate-50">
                        <span>📋 Historial Cajas</span>
                    </a>
                </div>
            </div>
        @endif

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
                                    class="text-xs font-bold text-rose-600 hover:text-rose-700 hover:underline flex items-center gap-1 transition cursor-pointer">
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
                                            <button type="button" 
                                                    @click="abrirFichaClienteModal()" 
                                                    title="Haga clic para ver o editar la ficha del cliente en modal sin salir de la facturación" 
                                                    class="text-xs font-black text-indigo-950 dark:text-indigo-200 hover:text-indigo-600 dark:hover:text-indigo-400 block uppercase tracking-tight hover:underline flex items-center gap-1 group text-left cursor-pointer">
                                                <span x-text="clienteSeleccionado.nombre"></span>
                                                <span class="text-[10px] text-indigo-500 opacity-70 group-hover:opacity-100">↗</span>
                                            </button>
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
                    <template x-if="esCredito">
                        <div class="mt-2 pt-2 border-t border-slate-800 flex items-center justify-between text-xs font-mono">
                            <span class="text-amber-400 font-bold" x-text="porcentajeInteresCuotas(cuotasPlan) > 0 ? ('Interés ' + porcentajeInteresCuotas(cuotasPlan) + '% (+$ ' + formatDinero(calcularRecargo()) + ')') : 'Sin interés (0%)'"></span>
                            <span class="text-indigo-400 font-bold uppercase text-sm" x-text="cuotasPlan + ' x $' + formatDinero(calcularValorCuota(cuotasPlan))"></span>
                        </div>
                    </template>
                </div>

                <!-- TARJETA DE MEDIOS DE PAGO (BOTONES GRANDES TABLET TOUCH CONSOLIDADOS) -->
                <div class="bg-white dark:bg-slate-800 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-xs font-black uppercase text-slate-700 dark:text-slate-300 tracking-wider flex items-center gap-1.5">
                            <span>💳</span>
                            <span>Seleccionar Medio de Pago</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono font-bold uppercase" x-text="medioPagoActual ? medioPagoActual.nombre : medioPago.replace('_', ' ')"></span>
                    </div>

                    <!-- Botones Dinámicos de Medios de Pago desde la BD consolidada -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        @foreach($mediosPago as $mp)
                            @php
                                $isCredito = ($mp->tipo_base === 'credito' || $mp->codigo === 'credito_personal');
                                $icon = $mp->icono ?: ($isCredito ? '📄' : ($mp->tipo_base === 'efectivo' ? '💵' : '💳'));
                            @endphp
                            <button type="button" 
                                    @click="seleccionarMedioPago(@js($mp))"
                                    @if($isCredito)
                                        :disabled="!clienteSeleccionado"
                                        :class="{
                                            'bg-indigo-600 text-white font-black border-indigo-600 shadow-md ring-2 ring-indigo-400/50': medioPago === '{{ $mp->codigo }}',
                                            'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100': medioPago !== '{{ $mp->codigo }}' && clienteSeleccionado,
                                            'opacity-40 cursor-not-allowed bg-slate-100 dark:bg-slate-900 text-slate-400 border-slate-200 dark:border-slate-800': !clienteSeleccionado
                                        }"
                                        title="Habilitado al seleccionar cliente registrado"
                                    @else
                                        :class="medioPago === '{{ $mp->codigo }}' 
                                            ? 'bg-slate-900 dark:bg-indigo-600 text-white font-black border-slate-900 dark:border-indigo-600 shadow-md ring-2 ring-indigo-400/50' 
                                            : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                    @endif
                                    class="py-3 px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition min-h-[58px] active:scale-98">
                                <span class="text-xl">{{ $icon }}</span>
                                <div class="text-left">
                                    <span class="tracking-tight uppercase font-black block leading-tight">{{ $mp->nombre }}</span>
                                    @if($isCredito)
                                        <span class="text-[9px] block opacity-80" x-text="clienteSeleccionado ? 'Habilitado' : 'Requiere Cliente'"></span>
                                    @elseif($mp->recargo_porcentaje > 0)
                                        <span class="text-[9px] block text-amber-500 font-mono">+{{ (float)$mp->recargo_porcentaje }}% rec.</span>
                                    @elseif($mp->descuento_porcentaje > 0)
                                        <span class="text-[9px] block text-emerald-500 font-mono">-{{ (float)$mp->descuento_porcentaje }}% desc.</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <!-- AVISO DE AUTORIZACIÓN DE CRÉDITO PERSONAL -->
                    <template x-if="esCredito && clienteSeleccionado">
                        <div class="p-2.5 rounded-xl border flex items-center justify-between text-xs"
                             :class="autorizadoPorVendedor 
                                 ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200' 
                                 : 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700 text-emerald-900 dark:text-emerald-200'">
                            <div class="flex items-center gap-2">
                                <span class="text-base" x-text="autorizadoPorVendedor ? '⚠️' : '✓'"></span>
                                <div>
                                    <span class="font-black uppercase tracking-tight block" 
                                          x-text="autorizadoPorVendedor ? 'Crédito Autorizado por Vendedor (Excepción)' : 'Crédito Personal Verificado y Autorizado'"></span>
                                    <span class="text-[11px] opacity-80" 
                                          x-text="'Margen disponible: $' + formatDinero(infoCredito?.detalle_compras?.limite_disponible || infoCredito?.estado_crediticio?.limite_disponible || 0)"></span>
                                </div>
                            </div>
                            <button type="button" @click="abrirModalCredito()" 
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300 underline hover:opacity-80 cursor-pointer">
                                Ver Estado / Reautorizar
                            </button>
                        </div>
                    </template>

                    <!-- SELECTOR DE CUOTAS (SI ES CRÉDITO PERSONAL) -->
                    <template x-if="esCredito && clienteSeleccionado">
                        <div class="pt-2.5 border-t border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase text-indigo-900 dark:text-indigo-300 flex items-center gap-1">
                                    <span>Plan de Cuotas a Crédito</span>
                                </span>
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400" 
                                      x-text="'Interés lote: ' + tasaInteres + '% por cuota' + (primeraSinInteres ? ' (1 pago sin interés)' : '')">
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
                                            <span class="text-[9px] block text-slate-400" x-text="(n > 1 ? '/ cuota' : 'Total') + ' · ' + porcentajeInteresCuotas(n) + '%'"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- PANEL DE COBRO EN EFECTIVO RÁPIDO & VUELTO (SI ES EFECTIVO) -->
                    <template x-if="esEfectivo">
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
                                : (esCredito 
                                    ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-xl hover:shadow-indigo-500/25 active:scale-98' 
                                    : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xl hover:shadow-emerald-500/25 active:scale-98')"
                            class="w-full py-4 px-4 rounded-2xl font-black text-base sm:text-lg uppercase tracking-wide transition flex items-center justify-center gap-2">
                        <span x-text="procesando ? 'Procesando...' : (esCredito ? (creditoAutorizado ? 'Facturar' : 'Verificar y Facturar Crédito') : 'PAGAR / COBRAR AHORA')"></span>
                        <span x-show="!procesando" class="font-mono text-sm sm:text-base font-bold" x-text="'($ ' + formatDinero(calcularTotal()) + ')'"></span>
                    </button>
                </div>

            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL 1: ESTADO DE CUENTA Y AUTORIZACIÓN DE CRÉDITO PERSONAL        -->
        <!-- =================================================================== -->
        <div x-show="modalCredito" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-xs flex items-start justify-center p-3 sm:p-5 pt-8 sm:pt-12 pb-8"
             @keydown.window.escape="modalCredito = false; focusBarcode();"
             x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-5xl w-full p-3.5 sm:p-5 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-3 max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150 my-auto"
                 @click.away="modalCredito = false; focusBarcode();">
                
                <!-- Encabezado del Modal -->
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 shrink-0">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                            📄
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                    Estado de Cuenta y Autorización de Crédito
                                </h3>
                                <template x-if="clienteSeleccionado">
                                    <a :href="'{{ url('/clientes') }}/' + clienteSeleccionado.id" target="_blank"
                                       class="px-2 py-0.5 rounded text-xs font-black bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 hover:underline border border-indigo-200 dark:border-indigo-800 flex items-center gap-1"
                                       title="Ver legajo completo en nueva pestaña">
                                        <span x-text="clienteSeleccionado.nombre"></span>
                                        <span>↗</span>
                                    </a>
                                </template>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                DNI: <span class="font-mono font-bold" x-text="clienteSeleccionado ? clienteSeleccionado.dni : '-'"></span> • 
                                Cuenta: <span class="font-mono font-bold" x-text="clienteSeleccionado?.nro_cuenta || '-'"></span> • 
                                Sucursal: <span class="font-bold" x-text="infoCredito?.cliente?.sucursal || 'N/A'"></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="consultarInfoCredito(clienteSeleccionado?.id)" 
                                title="Actualizar datos en vivo"
                                class="px-2 py-1.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 transition cursor-pointer">
                            🔄 Actualizar
                        </button>
                        <button type="button" @click="modalCredito = false; focusBarcode();" 
                                class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 flex items-center justify-center text-lg font-bold">
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Contenido con scroll -->
                <div class="overflow-y-auto flex-1 pr-1 space-y-4">
                    <template x-if="infoCredito">
                        <div class="space-y-4">
                            <!-- FILA SUPERIOR: DETALLE DE COMPRAS (IZQ) vs COMENTARIOS (DER) -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Columna Izquierda: DETALLE DE COMPRAS -->
                                <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
                                    <h4 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight border-b border-slate-200 dark:border-slate-700 pb-1.5">
                                        DETALLE DE COMPRAS:
                                    </h4>
                                    <div class="space-y-1.5 text-xs font-mono">
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-600 dark:text-slate-400 font-sans font-bold">TOTAL ADEUDADO:</span>
                                            <span class="text-sm font-black text-slate-900 dark:text-white" 
                                                  x-text="'$ ' + formatDinero(infoCredito.detalle_compras?.total_adeudado || infoCredito.estado_crediticio?.deuda_acumulada || 0)"></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-600 dark:text-slate-400 font-sans font-bold">PENDIENTE DE COBRO (A PAGAR):</span>
                                            <span class="text-sm font-black text-indigo-700 dark:text-indigo-300" 
                                                  x-text="'$ ' + formatDinero(infoCredito.detalle_compras?.pendiente_cobro || 0)"></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-600 dark:text-slate-400 font-sans font-bold">MORA (VENCIDO):</span>
                                            <span class="text-sm font-black" 
                                                  :class="(infoCredito.detalle_compras?.mora_vencido || infoCredito.estado_crediticio?.total_mora || 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-200'"
                                                  x-text="'$ ' + formatDinero(infoCredito.detalle_compras?.mora_vencido || infoCredito.estado_crediticio?.total_mora || 0)"></span>
                                        </div>
                                        <div class="flex justify-between items-center pt-1 border-t border-slate-200 dark:border-slate-700">
                                            <span class="text-slate-600 dark:text-slate-400 font-sans font-bold">MONTO DISPONIBLE:</span>
                                            <span class="text-sm font-black text-emerald-600 dark:text-emerald-400" 
                                                  x-text="'$ ' + formatDinero(infoCredito.detalle_compras?.limite_disponible || infoCredito.estado_crediticio?.limite_disponible || 0)"></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-600 dark:text-slate-400 font-sans font-bold">LÍMITE TOTAL:</span>
                                            <span class="font-bold text-slate-700 dark:text-slate-300" 
                                                  x-text="'$ ' + formatDinero(infoCredito.detalle_compras?.limite_total || infoCredito.estado_crediticio?.limite_total || 0)"></span>
                                        </div>
                                    </div>

                                    <!-- Modificación de Límite en Vivo -->
                                    <div class="mt-2.5 pt-2 border-t border-slate-200 dark:border-slate-700">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase block mb-1">
                                            Modificar Límite de Crédito:
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <div class="relative flex-1">
                                                <span class="absolute left-2.5 top-1.5 text-xs text-slate-400 font-bold">$</span>
                                                <input type="number" step="0.01" min="0" x-model="nuevoLimiteInput" 
                                                       placeholder="Nuevo límite"
                                                       class="w-full pl-6 pr-2 py-1 text-xs font-mono font-bold bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg">
                                            </div>
                                            <button type="button" @click="guardarNuevoLimite()" :disabled="guardandoLimite"
                                                    class="px-3 py-1 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50 shrink-0 cursor-pointer">
                                                <span x-text="guardandoLimite ? 'Guardando...' : 'Modificar Límite'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna Derecha: COMENTARIOS / NOVEDADES (fondo verde menta con scroll) -->
                                <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-1.5 mb-2">
                                            <h4 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                                COMENTARIOS / NOVEDADES:
                                            </h4>
                                            <span class="text-[10px] text-slate-500 font-bold" x-text="(infoCredito.novedades?.length || 0) + ' nota(s)'"></span>
                                        </div>

                                        <!-- Caja Verde con Scroll (estilo réplica de captura) -->
                                        <div class="bg-[#dcfce7] dark:bg-emerald-950/40 border-2 border-[#86efac] dark:border-emerald-700/80 rounded-xl p-2 h-36 overflow-y-auto space-y-1.5 shadow-inner">
                                            <template x-if="!infoCredito.novedades || infoCredito.novedades.length === 0">
                                                <p class="text-xs text-emerald-800 dark:text-emerald-300 italic p-3 text-center">
                                                    Sin comentarios registrados en el legajo del cliente.
                                                </p>
                                            </template>
                                            <template x-for="com in (infoCredito.novedades || [])" :key="com.id">
                                                <div class="p-1.5 bg-white/75 dark:bg-slate-900/70 rounded-lg border border-emerald-200/90 dark:border-emerald-800/80 text-xs flex items-start gap-2 shadow-2xs">
                                                    <span class="font-mono font-black text-emerald-900 dark:text-emerald-300 shrink-0 text-[11px]" x-text="com.fecha"></span>
                                                    <div class="text-slate-900 dark:text-slate-100 flex-grow leading-tight">
                                                        <span class="font-bold text-indigo-900 dark:text-indigo-300" x-show="com.usuario_nombre" x-text="com.usuario_nombre + ': '"></span>
                                                        <span x-text="com.mensaje"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Campo Rápido para Añadir Comentario -->
                                    <div class="mt-2 flex items-center gap-1.5">
                                        <input type="text" x-model="nuevoComentarioInput" placeholder="Añadir nota al legajo..." 
                                               @keydown.enter.prevent="guardarNuevoComentario()"
                                               class="flex-grow px-2.5 py-1 text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg">
                                        <button type="button" @click="guardarNuevoComentario()" :disabled="guardandoComentario || !nuevoComentarioInput.trim()"
                                                class="px-2.5 py-1 text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg transition disabled:opacity-40 cursor-pointer shrink-0">
                                            <span x-text="guardandoComentario ? '...' : '+ Agregar'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- FILA CENTRAL: PRÓXIMOS VENCIMIENTOS (TABLA 4 MESES) -->
                            <div class="space-y-1.5">
                                <h4 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight underline">
                                    PROXIMOS VENCIMIENTOS:
                                </h4>
                                <div class="border border-slate-300 dark:border-slate-700 rounded-xl overflow-hidden bg-white dark:bg-slate-900 shadow-sm">
                                    <!-- Cabecera de 4 Meses -->
                                    <div class="grid grid-cols-4 bg-slate-100 dark:bg-slate-800 border-b border-slate-300 dark:border-slate-700 text-center font-black text-xs py-2 text-slate-800 dark:text-slate-200">
                                        <template x-for="mes in (infoCredito.proximos_vencimientos || [])" :key="mes.numero_mes">
                                            <div class="border-r last:border-r-0 border-slate-300 dark:border-slate-700 px-1">
                                                <span x-text="mes.numero_mes + '° Mes (Lote ' + mes.lote + ')'"></span>
                                            </div>
                                        </template>
                                    </div>
                                    <!-- Celdas de 4 Meses con Cuotas y Totales -->
                                    <div class="grid grid-cols-4 min-h-[85px] divide-x divide-slate-300 dark:divide-slate-700 text-xs">
                                        <template x-for="mes in (infoCredito.proximos_vencimientos || [])" :key="'col-' + mes.numero_mes">
                                            <div class="p-2 flex flex-col justify-between bg-white dark:bg-slate-900">
                                                <div class="space-y-1 max-h-28 overflow-y-auto">
                                                    <template x-for="c in mes.cuotas" :key="c.id">
                                                        <div class="p-1 rounded bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-[11px] flex items-center justify-between">
                                                            <span class="font-bold text-indigo-900 dark:text-indigo-300 truncate" x-text="c.identificador"></span>
                                                            <span class="px-1 text-slate-500 font-bold" x-text="c.num_cuota"></span>
                                                            <span class="font-black text-slate-900 dark:text-slate-100" x-text="'$' + formatDinero(c.saldo)"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="!mes.cuotas || mes.cuotas.length === 0">
                                                        <span class="text-[10px] text-slate-400 italic block text-center py-4">- Sin cuotas -</span>
                                                    </template>
                                                </div>
                                                <div class="mt-2 pt-1.5 border-t border-slate-200 dark:border-slate-700 text-center font-mono font-bold text-xs text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800/60 py-1 rounded">
                                                    <span x-text="'Total: $ ' + formatDinero(mes.total)"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- FILA INFERIOR: CONDICIONES DE LA VENTA ACTUAL Y HABILITACIÓN POR VENDEDOR -->
                            <div class="p-3.5 bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-200 dark:border-indigo-800/80 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black uppercase text-indigo-950 dark:text-indigo-200">
                                            Compra Actual a Facturar:
                                        </span>
                                        <span class="text-base font-black font-mono text-indigo-700 dark:text-indigo-300"
                                              x-text="'$ ' + formatDinero(calcularTotal())"></span>
                                    </div>

                                    <!-- Selector de Cuotas en el Modal -->
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Plan:</span>
                                        <div class="flex items-center gap-1">
                                            @for($i = 1; $i <= 4; $i++)
                                                <button type="button" @click="cuotasPlan = {{ $i }}"
                                                        class="px-2.5 py-1 text-xs font-bold rounded-lg border transition cursor-pointer"
                                                        :class="cuotasPlan === {{ $i }} 
                                                            ? 'bg-indigo-600 text-white border-indigo-600 font-black shadow-xs' 
                                                            : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:bg-slate-100'">
                                                    {{ $i }} {{ $i === 1 ? 'Pago' : 'Pagos' }}
                                                </button>
                                            @endfor
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between text-xs gap-2">
                                    <div class="text-slate-700 dark:text-slate-300">
                                        Plan seleccionado: <span class="font-black text-slate-900 dark:text-white" x-text="cuotasPlan + (cuotasPlan === 1 ? ' cuota' : ' cuotas')"></span> de 
                                        <span class="font-mono font-black text-indigo-700 dark:text-indigo-400 text-sm" x-text="'$ ' + formatDinero(calcularValorCuota(cuotasPlan))"></span>
                                        <span class="text-[11px] opacity-80" x-text="' (Int. ' + porcentajeInteresCuotas(cuotasPlan) + '%)'"></span>
                                    </div>
                                </div>

                                <!-- SECCIÓN DE ADVERTENCIA / HABILITACIÓN DEL VENDEDOR -->
                                <template x-if="infoCredito.estado_crediticio.tiene_mora || (calcularTotal() > (infoCredito.detalle_compras?.limite_disponible || infoCredito.estado_crediticio.limite_disponible)) || infoCredito.estado_crediticio.suspendido">
                                    <div class="p-3 bg-amber-100/90 dark:bg-amber-950/80 border-2 border-amber-400 dark:border-amber-600 rounded-xl space-y-2">
                                        <div class="flex items-center gap-2 text-amber-900 dark:text-amber-200 text-xs font-black uppercase">
                                            <span>⚠️ ATENCIÓN: El cliente posee restricciones de crédito</span>
                                        </div>
                                        <ul class="text-[11px] list-disc list-inside text-amber-900 dark:text-amber-300 space-y-0.5">
                                            <template x-if="infoCredito.estado_crediticio.suspendido">
                                                <li>Cliente suspendido para créditos.</li>
                                            </template>
                                            <template x-if="infoCredito.estado_crediticio.tiene_mora">
                                                <li x-text="'Registra mora por $ ' + formatDinero(infoCredito.detalle_compras?.mora_vencido || infoCredito.estado_crediticio.total_mora) + '.'"></li>
                                            </template>
                                            <template x-if="calcularTotal() > (infoCredito.detalle_compras?.limite_disponible || infoCredito.estado_crediticio.limite_disponible)">
                                                <li x-text="'El total ($ ' + formatDinero(calcularTotal()) + ') supera el disponible ($ ' + formatDinero(infoCredito.detalle_compras?.limite_disponible || infoCredito.estado_crediticio.limite_disponible) + ').'"></li>
                                            </template>
                                        </ul>

                                        <!-- CHECKBOX EXCLUSIVO DE HABILITACIÓN POR VENDEDOR -->
                                        <div class="pt-1.5 border-t border-amber-300 dark:border-amber-700">
                                            <label class="inline-flex items-center gap-2.5 p-2 bg-white dark:bg-slate-900 rounded-lg border-2 border-amber-500 cursor-pointer shadow-sm hover:bg-amber-50 transition">
                                                <input type="checkbox" x-model="autorizadoPorVendedor" class="w-4 h-4 text-indigo-600 rounded cursor-pointer">
                                                <span class="text-xs font-black text-amber-950 dark:text-white uppercase tracking-tight">
                                                    HABILITAR PARA ESTA COMPRA (Autoriza el vendedor)
                                                </span>
                                            </label>
                                            <p class="text-[10px] text-amber-800 dark:text-amber-400 mt-1">
                                                Al marcar esta casilla, el vendedor asume la autorización excepcional para emitir la factura a crédito y quedará asentado en el legajo.
                                            </p>
                                        </div>
                                    </div>
                                </template>

                                <!-- ESTADO REGULAR APROBADO -->
                                <template x-if="!infoCredito.estado_crediticio.tiene_mora && !infoCredito.estado_crediticio.suspendido && (calcularTotal() <= (infoCredito.detalle_compras?.limite_disponible || infoCredito.estado_crediticio.limite_disponible))">
                                    <div class="p-2.5 bg-emerald-100/90 dark:bg-emerald-950/70 border border-emerald-300 dark:border-emerald-700 rounded-lg text-emerald-900 dark:text-emerald-200 text-xs flex items-center gap-2">
                                        <span class="text-base">✓</span>
                                        <span class="font-bold">Cliente en regla y con margen de crédito disponible para autorizar.</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer del Modal de Crédito -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-800 shrink-0">
                    <button type="button" @click="modalCredito = false; focusBarcode();"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 uppercase transition cursor-pointer">
                        Cancelar / Volver
                    </button>

                    <!-- Botón Principal: AUTORIZAR CRÉDITO -->
                    <button type="button" @click="confirmarAutorizacionCredito()"
                            :disabled="!puedeAutorizarCredito"
                            class="px-6 py-2.5 rounded-xl font-black text-xs uppercase tracking-tight shadow-md flex items-center gap-2 transition cursor-pointer"
                            :class="puedeAutorizarCredito 
                                ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-500/20 active:scale-98' 
                                : 'bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-600 cursor-not-allowed'">
                        <span class="text-sm">✓</span>
                        <span>AUTORIZAR CRÉDITO</span>
                    </button>
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
                        <span x-text="medioPagoActual ? (medioPagoActual.icono + ' Cobro con ' + medioPagoActual.nombre) : '💳 Cobro de Factura'"></span>
                    </h3>
                    <button type="button" @click="modalCobro = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="text-center py-2">
                    <span class="text-xs text-slate-500 uppercase font-bold block">Total a Pagar</span>
                    <span class="text-3xl font-black text-slate-900 dark:text-white font-mono tabular-nums"
                          x-text="'$ ' + formatDinero(calcularTotal())">
                    </span>
                    <template x-if="medioPagoActual && Number(medioPagoActual.recargo_porcentaje) > 0">
                        <span class="text-[10px] text-amber-500 font-mono block mt-1" x-text="'Incluye recargo del ' + medioPagoActual.recargo_porcentaje + '% (+$ ' + formatDinero(calcularRecargo()) + ')'"></span>
                    </template>
                    <template x-if="medioPagoActual && Number(medioPagoActual.descuento_porcentaje) > 0">
                        <span class="text-[10px] text-emerald-500 font-mono block mt-1" x-text="'Incluye descuento del ' + medioPagoActual.descuento_porcentaje + '% (-$ ' + formatDinero(calcularDescuento()) + ')'"></span>
                    </template>
                </div>

                <!-- Campo de referencia / comprobante si el medio de pago lo requiere -->
                <template x-if="medioPagoActual && medioPagoActual.requiere_referencia">
                    <div class="space-y-1.5 bg-slate-50 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase">N° Cupón / Comprobante / Transacción:</label>
                        <input type="text" 
                               x-model="referenciaPago" 
                               @keydown.enter.prevent="enviarVenta()"
                               placeholder="Ej: Cupón 123456 / Ref. 987654 / Cheque N°..." 
                               class="w-full text-xs font-mono font-bold py-1.5 px-3 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>
                </template>

                <!-- Calculador de Vuelto si es Efectivo -->
                <template x-if="esEfectivo">
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
                    <template x-if="ventaExitosa.pagare_url">
                        <a :href="ventaExitosa.pagare_url" target="_blank"
                           class="w-full py-2.5 px-4 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-xl shadow transition flex items-center justify-center gap-2 uppercase tracking-wide">
                            <span>📋 Imprimir Pagaré de Crédito</span>
                        </a>
                    </template>

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

        <!-- =================================================================== -->
        <!-- MODAL 5: APERTURA DE CAJA AL INICIO DE LA JORNADA                   -->
        <!-- =================================================================== -->
        <div x-show="modalAbrirCaja" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4"
             x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border-2 border-emerald-500/80 space-y-4"
                 @click.away="modalAbrirCaja = false">
                
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-400 flex items-center justify-center font-black text-base shrink-0 border border-emerald-300 dark:border-emerald-700">
                            🟢
                        </span>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                Apertura de Caja (Inicio de Jornada)
                            </h3>
                            <span class="text-[10px] text-emerald-700 dark:text-emerald-400 font-bold uppercase tracking-wider block">
                                Sucursal: {{ $sucursalActiva->nombre ?? 'Sucursal' }}
                            </span>
                        </div>
                    </div>
                    <button type="button" @click="modalAbrirCaja = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-xl border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-900 dark:text-emerald-200 space-y-1">
                    <p class="font-bold">
                        Ingrese el monto de dinero en efectivo con el que se inicia la caja.
                    </p>
                    <p class="text-[11px] text-emerald-800 dark:text-emerald-300 leading-tight">
                        Este monto corresponde al cambio o fondo de caja disponible en el cajón físico al abrir la sucursal.
                    </p>
                </div>

                <div class="space-y-3 bg-slate-50 dark:bg-slate-900/50 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-700 dark:text-slate-300 mb-1">
                            Monto Inicial en Efectivo ($ ARS):
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold text-sm">$</span>
                            <input type="number" step="0.01" min="0" 
                                   x-model.number="montoApertura"
                                   @keydown.enter.prevent="enviarAperturaCaja()"
                                   class="w-full pl-8 pr-3 font-mono font-black text-lg py-2 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-right focus:ring-2 focus:ring-emerald-500"
                                   placeholder="0.00" autofocus>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">
                            Observaciones de Apertura (Opcional):
                        </label>
                        <input type="text" x-model="obsApertura" 
                               class="w-full text-xs py-2 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                               placeholder="Ej: Cambio inicial de mostrador...">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" @click="modalAbrirCaja = false"
                            class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs py-2 px-4 rounded-xl transition uppercase">
                        Cancelar
                    </button>
                    <button type="button" @click="enviarAperturaCaja()"
                            :disabled="procesandoApertura"
                            class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black text-xs py-2 px-5 rounded-xl shadow-md transition uppercase flex items-center gap-1.5 cursor-pointer">
                        <span x-show="!procesandoApertura">✓ Confirmar Apertura de Caja</span>
                        <span x-show="procesandoApertura">Abriendo caja...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL 6: CIERRE DE JORNADA ANTERIOR + APERTURA DE HOY              -->
        <!-- =================================================================== -->
        <div x-show="modalCierreAnterior" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4"
             x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border-2 border-amber-500 space-y-4"
                 @click.away="modalCierreAnterior = false">
                
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-400 flex items-center justify-center font-black text-base shrink-0 border border-amber-300 dark:border-amber-700">
                            ⚠️
                        </span>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                Arqueo Jornada Anterior y Apertura de Hoy
                            </h3>
                            <span class="text-[10px] text-amber-700 dark:text-amber-400 font-bold uppercase tracking-wider block">
                                Cierre pendiente de Caja #<span x-text="cajaAnteriorId"></span>
                            </span>
                        </div>
                    </div>
                    <button type="button" @click="modalCierreAnterior = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 space-y-1">
                    <p class="font-bold">
                        La caja anterior no fue cerrada al terminar el día.
                    </p>
                    <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-tight">
                        Ingrese el dinero en efectivo que había en el cajón para asentar el Cierre Z y la revisión de fallo de caja de la jornada anterior, y el fondo inicial para hoy.
                    </p>
                </div>

                <div class="space-y-3 bg-slate-50 dark:bg-slate-900/50 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                    <!-- Efectivo Esperado del Sistema -->
                    <div class="flex items-center justify-between p-2.5 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                        <span class="font-bold text-slate-700 dark:text-slate-300">Efectivo Teórico Esperado (Caja Anterior):</span>
                        <span class="font-mono font-black text-slate-900 dark:text-white text-sm" x-text="'$ ' + formatDinero(efectivoEsperadoAnterior)"></span>
                    </div>

                    <!-- Input Efectivo Real Contado -->
                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-700 dark:text-slate-300 mb-1">
                            Efectivo Real Contado en el Cajón (Cierre Anterior):
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold text-sm">$</span>
                            <input type="number" step="0.01" min="0" 
                                   x-model.number="contadoAnterior"
                                   class="w-full pl-8 pr-3 font-mono font-black text-base py-2 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-right focus:ring-2 focus:ring-amber-500"
                                   placeholder="0.00">
                        </div>
                    </div>

                    <!-- Fallo de caja en vivo -->
                    <div class="p-2.5 rounded-lg border flex items-center justify-between text-xs"
                         :class="{
                             'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300': (contadoAnterior - efectivoEsperadoAnterior) === 0,
                             'bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-800 text-sky-800 dark:text-sky-300': (contadoAnterior - efectivoEsperadoAnterior) > 0,
                             'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300': (contadoAnterior - efectivoEsperadoAnterior) < 0
                         }">
                        <span class="font-bold">
                            <span x-show="(contadoAnterior - efectivoEsperadoAnterior) === 0">✓ Arqueo Exacto (Sin diferencias)</span>
                            <span x-show="(contadoAnterior - efectivoEsperadoAnterior) > 0">▲ Sobrante de Caja:</span>
                            <span x-show="(contadoAnterior - efectivoEsperadoAnterior) < 0">▼ Faltante de Caja (Fallo):</span>
                        </span>
                        <span class="font-mono font-black text-sm" 
                              x-text="'$ ' + formatDinero(Math.abs(contadoAnterior - efectivoEsperadoAnterior))">
                        </span>
                    </div>

                    <!-- Fondo Inicial para la Caja de Hoy -->
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                        <label class="block text-[11px] font-black uppercase text-emerald-700 dark:text-emerald-400 mb-1">
                            Fondo Inicial en Efectivo para la Caja de HOY ($ ARS):
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold text-sm">$</span>
                            <input type="number" step="0.01" min="0" 
                                   x-model.number="fondoHoyAnterior"
                                   class="w-full pl-8 pr-3 font-mono font-black text-base py-2 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-right focus:ring-2 focus:ring-emerald-500"
                                   placeholder="0.00">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">
                            Observaciones / Justificación de Cierre:
                        </label>
                        <input type="text" x-model="obsCierreAnterior" 
                               class="w-full text-xs py-2 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                               placeholder="Ej: Cierre automático al inicio de la siguiente jornada...">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" @click="modalCierreAnterior = false"
                            class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs py-2 px-4 rounded-xl transition uppercase">
                        Cancelar
                    </button>
                    <button type="button" @click="enviarCierreAnteriorYAbrir()"
                            :disabled="procesandoCierreAnterior"
                            class="bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-black text-xs py-2 px-5 rounded-xl shadow-md transition uppercase flex items-center gap-1.5 cursor-pointer">
                        <span x-show="!procesandoCierreAnterior">✓ Asentar Cierre Z y Abrir Hoy</span>
                        <span x-show="procesandoCierreAnterior">Procesando cierre y apertura...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL 7: INGRESO O RETIRO MANUAL DE EFECTIVO (MOVIMIENTO DE CAJA)   -->
        <!-- =================================================================== -->
        <div x-show="modalMovimientoCaja" 
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4"
             x-cloak>
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-4"
                 @click.away="modalMovimientoCaja = false">
                
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-400 flex items-center justify-center font-black text-base shrink-0 border border-blue-300 dark:border-blue-700">
                            💸
                        </span>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                Movimiento Manual de Efectivo
                            </h3>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">
                                Turno de Caja #{{ $cajaActiva->id ?? '' }} • {{ $sucursalActiva->nombre ?? 'Sucursal' }}
                            </span>
                        </div>
                    </div>
                    <button type="button" @click="modalMovimientoCaja = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <!-- Selector de Tipo: Ingreso (+) vs Retiro (-) -->
                <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl">
                    <button type="button" 
                            @click="movimientoTipo = 'retiro'"
                            :class="movimientoTipo === 'retiro' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="py-2 px-3 rounded-lg font-black text-xs uppercase transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <span>📤 Retiro de Efectivo (-)</span>
                    </button>
                    <button type="button" 
                            @click="movimientoTipo = 'ingreso'"
                            :class="movimientoTipo === 'ingreso' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="py-2 px-3 rounded-lg font-black text-xs uppercase transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <span>📥 Ingreso de Efectivo (+)</span>
                    </button>
                </div>

                <!-- Explicación -->
                <div class="p-2.5 rounded-xl border text-xs"
                     :class="movimientoTipo === 'retiro' ? 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200' : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-900 text-emerald-900 dark:text-emerald-200'">
                    <span x-show="movimientoTipo === 'retiro'" class="font-bold">
                        ⚠️ Retiro / Salida: Para pagar compras, fletes, adelantos o gastos menores. Se restará del efectivo esperado al cierre.
                    </span>
                    <span x-show="movimientoTipo === 'ingreso'" class="font-bold">
                        ✓ Ingreso / Entrada: Para cobrar un pendiente manual, inyección de cambio o corrección positiva. Se sumará al efectivo esperado.
                    </span>
                </div>

                <div class="space-y-3 bg-slate-50 dark:bg-slate-900/50 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-700 dark:text-slate-300 mb-1">
                            Monto en Efectivo ($ ARS):
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold text-sm">$</span>
                            <input type="number" step="0.01" min="0.01" 
                                   x-model.number="movimientoMonto"
                                   class="w-full pl-8 pr-3 font-mono font-black text-lg py-2 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-right focus:ring-2 focus:ring-blue-500"
                                   placeholder="0.00" autofocus>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-700 dark:text-slate-300 mb-1">
                            Motivo / Justificación del Movimiento (Obligatorio):
                        </label>
                        <input type="text" x-model="movimientoMotivo" required
                               class="w-full text-xs py-2 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                               :placeholder="movimientoTipo === 'retiro' ? 'Ej: Pago de flete mercadería / Compra de insumos...' : 'Ej: Cobro de saldo pendiente / Aporte de cambio...'">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-1">
                            Nro Comprobante / Recibo Adjunto (Opcional):
                        </label>
                        <input type="text" x-model="movimientoComprobante" 
                               class="w-full text-xs py-1.5 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                               placeholder="Ej: Ticket #1234 / Factura B-0012">
                    </div>
                </div>

                <!-- Historial reciente de movimientos en el turno actual -->
                <template x-if="movimientosCaja && movimientosCaja.length > 0">
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Movimientos Registrados en este Turno:</span>
                        <div class="max-h-28 overflow-y-auto space-y-1 pr-1">
                            <template x-for="m in movimientosCaja" :key="m.id">
                                <div class="p-2 rounded-lg bg-slate-100 dark:bg-slate-900 flex items-center justify-between text-[11px]">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black px-1.5 py-0.5 rounded text-[9px] uppercase"
                                              :class="m.tipo === 'ingreso' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300'"
                                              x-text="m.tipo === 'ingreso' ? '+ INGRESO' : '- RETIRO'">
                                        </span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="m.motivo"></span>
                                        <span x-show="m.comprobante" class="text-slate-400 text-[10px]" x-text="'(' + m.comprobante + ')'"></span>
                                    </div>
                                    <span class="font-mono font-black"
                                          :class="m.tipo === 'ingreso' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                                          x-text="(m.tipo === 'ingreso' ? '+$ ' : '-$ ') + formatDinero(m.monto)">
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" @click="modalMovimientoCaja = false"
                            class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs py-2 px-4 rounded-xl transition uppercase">
                        Cerrar
                    </button>
                    <button type="button" @click="enviarMovimientoCaja()"
                            :disabled="procesandoMovimiento"
                            :class="movimientoTipo === 'retiro' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                            class="text-white font-black text-xs py-2 px-5 rounded-xl shadow-md transition uppercase flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <span x-show="!procesandoMovimiento" x-text="movimientoTipo === 'retiro' ? '✓ Asentar Retiro de Dinero' : '✓ Asentar Ingreso de Dinero'"></span>
                        <span x-show="procesandoMovimiento">Registrando...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- MODAL FICHA Y LEGAJO DEL CLIENTE (SIN SALIR DE FACTURACIÓN)        -->
        <!-- =================================================================== -->
        <div x-show="modalFichaCliente" 
             class="fixed inset-0 z-50 overflow-hidden bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4"
             @keydown.window.escape="cerrarFichaClienteModal()"
             x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl w-full max-w-7xl h-[94vh] shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                
                <!-- Encabezado del Modal -->
                <div class="flex items-center justify-between px-4 py-3 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shrink-0">
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-sm">
                            📄
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                    Ficha y Legajo del Cliente
                                </h3>
                                <template x-if="clienteSeleccionado">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-100 dark:bg-indigo-950 text-indigo-800 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-800"
                                          x-text="clienteSeleccionado.nombre + ' (DNI: ' + clienteSeleccionado.dni + ')'">
                                    </span>
                                </template>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Ver y editar el legajo del cliente directamente sin perder los artículos ni el progreso de la factura
                            </p>
                        </div>
                    </div>

                    <!-- Botones de Acción del Modal -->
                    <div class="flex items-center gap-2">
                        <button type="button" 
                                @click="recargarFichaCliente()"
                                title="Actualizar datos de la ficha"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 flex items-center gap-1 shadow-xs transition cursor-pointer">
                            <span>🔄</span>
                            <span class="hidden sm:inline">Recargar</span>
                        </button>
                        <button type="button" 
                                @click="cerrarFichaClienteModal()"
                                class="px-3 py-1.5 rounded-lg text-xs font-black bg-rose-600 hover:bg-rose-700 text-white shadow-sm flex items-center gap-1 transition cursor-pointer">
                            <span>✕</span>
                            <span>Volver a Facturar</span>
                        </button>
                    </div>
                </div>

                <!-- Contenido con iframe optimizado embebido a altura completa -->
                <div class="flex-1 min-h-0 w-full h-[calc(94vh-65px)] bg-slate-50 dark:bg-slate-900 relative overflow-hidden">
                    <iframe id="iframeFichaCliente"
                            :src="fichaClienteUrl"
                            class="w-full h-full border-0 bg-transparent block"
                            style="width: 100%; height: 100%; min-height: calc(94vh - 65px); border: 0;"
                            loading="lazy">
                    </iframe>
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
                medioPago: '{{ $mediosPago->firstWhere("codigo", "efectivo")?->codigo ?? $mediosPago->first()?->codigo ?? "efectivo" }}',
                mediosPagoLista: @json($mediosPago),
                referenciaPago: '',
                get medioPagoActual() {
                    return this.mediosPagoLista.find(m => m.codigo === this.medioPago) || null;
                },
                get esCredito() {
                    let mp = this.medioPagoActual;
                    return (mp && (mp.tipo_base === 'credito' || mp.codigo === 'credito_personal')) || this.medioPago === 'credito_personal';
                },
                get esEfectivo() {
                    let mp = this.medioPagoActual;
                    return (mp && (mp.tipo_base === 'efectivo' || mp.permite_vuelto)) || this.medioPago === 'efectivo';
                },
                seleccionarMedioPago(mp) {
                    if (mp.tipo_base === 'credito' || mp.codigo === 'credito_personal') {
                        if (!this.clienteSeleccionado) {
                            alert('Para operar con ' + mp.nombre + ' debe seleccionar un cliente primero.');
                            this.abrirBuscadorCliente();
                            return;
                        }
                        this.abrirModalCredito();
                        return;
                    }
                    this.medioPago = mp.codigo;
                    if (this.esEfectivo) {
                        if (!this.pagaCon || this.pagaCon < this.calcularTotal()) {
                            this.pagaCon = this.calcularTotal();
                        }
                    }
                },
                cuotasPlan: 1,
                tasaInteres: {{ (float)$tasaInteresCredito }},
                primeraSinInteres: {{ ($presentacion?->primera_cuota_sin_interes ?? true) ? 'true' : 'false' }},
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

                // Estado de Autorización de Crédito Personal y Vendedor
                autorizadoPorVendedor: false,
                creditoAutorizado: false,
                nuevoLimiteInput: '',
                guardandoLimite: false,
                nuevoComentarioInput: '',
                guardandoComentario: false,

                // Modal Ficha / Legajo del Cliente
                modalFichaCliente: false,
                fichaClienteUrl: '',
                abrirFichaClienteModal(clienteId = null) {
                    let id = clienteId || this.clienteSeleccionado?.id;
                    if (!id) return;
                    this.fichaClienteUrl = '{{ url('/clientes') }}/' + id + '?embed=1';
                    this.modalFichaCliente = true;
                },
                recargarFichaCliente() {
                    let iframe = document.getElementById('iframeFichaCliente');
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.location.reload();
                    }
                },
                async cerrarFichaClienteModal() {
                    this.modalFichaCliente = false;
                    this.focusBarcode();
                    if (this.clienteSeleccionado) {
                        await this.consultarInfoCredito(this.clienteSeleccionado.id);
                    }
                },

                // Control de Caja / Turnos
                cajaEstado: '{{ $cajaActiva ? ($requiereCierreAnterior ? 'cierre_anterior' : 'abierta') : 'cerrada' }}',
                cajaId: {{ $cajaActiva->id ?? 'null' }},
                modalAbrirCaja: false,
                montoApertura: 0,
                obsApertura: '',
                procesandoApertura: false,
                modalCierreAnterior: false,
                cajaAnteriorId: {{ $cajaActiva->id ?? 'null' }},
                efectivoEsperadoAnterior: {{ (float)($totalesCajaActiva['efectivo_esperado'] ?? 0) }},
                contadoAnterior: 0,
                fondoHoyAnterior: 0,
                obsCierreAnterior: '',
                procesandoCierreAnterior: false,

                // Movimientos manuales de caja (Ingreso / Retiro)
                modalMovimientoCaja: false,
                movimientoTipo: 'retiro',
                movimientoMonto: '',
                movimientoMotivo: '',
                movimientoComprobante: '',
                procesandoMovimiento: false,
                movimientosCaja: [],
                efectivoEsperadoCaja: {{ (float)($totalesCajaActiva['efectivo_esperado'] ?? 0) }},
                totalIngresosCaja: {{ (float)($totalesCajaActiva['total_ingresos'] ?? 0) }},
                totalRetirosCaja: {{ (float)($totalesCajaActiva['total_retiros'] ?? 0) }},

                pagaCon: 0,
                procesando: false,
                ventaExitosa: {
                    id: null,
                    comprobante: '',
                    total: 0,
                    ticket_url: '#',
                    a4_url: '#',
                    redirect_url: '#',
                    id_credito: null,
                    pagare_url: null
                },

                initPos() {
                    this.focusBarcode();

                    // Atajo de teclado: espacio para cobrar, esc para volver al lector
                    window.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') {
                            this.modalCredito = false;
                            this.modalCobro = false;
                            this.modalRecuento = false;
                            this.modalFichaCliente = false;
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

                // % de interés total según cantidad de cuotas (ej: 6% -> 1:0%, 2:6%, 3:12%, 4:18%)
                porcentajeInteresCuotas(cuotas) {
                    let n = Math.max(1, parseInt(cuotas) || 1);
                    let tramos = this.primeraSinInteres ? n - 1 : n;
                    return Math.round(this.tasaInteres * tramos * 100) / 100;
                },

                calcularRecargo(cuotas = null) {
                    let sub = this.calcularSubtotal();
                    if (this.esCredito) {
                        let pct = this.porcentajeInteresCuotas(cuotas ?? this.cuotasPlan);
                        return Math.round(sub * (pct / 100) * 100) / 100;
                    }
                    let mp = this.medioPagoActual;
                    if (mp && Number(mp.recargo_porcentaje) > 0) {
                        return Math.round(sub * (Number(mp.recargo_porcentaje) / 100) * 100) / 100;
                    }
                    return 0;
                },

                calcularDescuento() {
                    let sub = this.calcularSubtotal();
                    let mp = this.medioPagoActual;
                    if (mp && Number(mp.descuento_porcentaje) > 0) {
                        return Math.round(sub * (Number(mp.descuento_porcentaje) / 100) * 100) / 100;
                    }
                    return 0;
                },

                calcularTotal(cuotas = null) {
                    return Math.max(0, Math.round((this.calcularSubtotal() + this.calcularRecargo(cuotas) - this.calcularDescuento()) * 100) / 100);
                },

                // valor_cuota = subtotal * (1 + tasa * tramos) / cuotas
                calcularValorCuota(cuotas) {
                    if (!cuotas || cuotas < 1) cuotas = 1;
                    let totalFinanciado = this.calcularTotal(cuotas);
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
                    this.creditoAutorizado = false;
                    this.autorizadoPorVendedor = false;
                    if (this.esCredito) {
                        this.medioPago = 'efectivo';
                    }
                    this.focusBarcode();
                },

                async abrirModalCredito() {
                    if (!this.clienteSeleccionado) {
                        alert('Para operar con Crédito Personal debe seleccionar un cliente primero.');
                        this.abrirBuscadorCliente();
                        return;
                    }
                    await this.consultarInfoCredito(this.clienteSeleccionado.id);
                    if (this.infoCredito && this.infoCredito.detalle_compras) {
                        this.nuevoLimiteInput = this.infoCredito.detalle_compras.limite_total;
                    } else if (this.clienteSeleccionado) {
                        this.nuevoLimiteInput = this.clienteSeleccionado.limite_efectivo || this.clienteSeleccionado.limite || 0;
                    }
                    if (!this.creditoAutorizado) {
                        this.autorizadoPorVendedor = false;
                    }
                    this.modalCredito = true;
                },

                async guardarNuevoLimite() {
                    if (!this.clienteSeleccionado) return;
                    let nuevo = parseFloat(this.nuevoLimiteInput);
                    if (isNaN(nuevo) || nuevo < 0) {
                        alert('Ingrese un límite numérico válido mayor o igual a 0.');
                        return;
                    }
                    this.guardandoLimite = true;
                    try {
                        let res = await fetch(`{{ url('/ventas/clientes') }}/${this.clienteSeleccionado.id}/actualizar-limite`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ limite: nuevo })
                        });
                        let data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || data.error || 'Error al actualizar el límite');
                        }
                        await this.consultarInfoCredito(this.clienteSeleccionado.id);
                        alert('✓ Límite de crédito actualizado correctamente a $ ' + this.formatDinero(nuevo));
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.guardandoLimite = false;
                    }
                },

                async guardarNuevoComentario() {
                    if (!this.clienteSeleccionado) return;
                    let txt = (this.nuevoComentarioInput || '').trim();
                    if (!txt) return;

                    this.guardandoComentario = true;
                    try {
                        let res = await fetch(`{{ url('/ventas/clientes') }}/${this.clienteSeleccionado.id}/comentarios`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ mensaje: txt })
                        });
                        let data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || data.error || 'Error al guardar comentario');
                        }
                        if (!this.infoCredito.novedades) {
                            this.infoCredito.novedades = [];
                        }
                        this.infoCredito.novedades.unshift(data.novedad);
                        this.nuevoComentarioInput = '';
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.guardandoComentario = false;
                    }
                },

                get puedeAutorizarCredito() {
                    if (!this.infoCredito || !this.infoCredito.estado_crediticio) return false;
                    let limiteDisp = parseFloat(this.infoCredito.detalle_compras?.limite_disponible ?? this.infoCredito.estado_crediticio.limite_disponible ?? 0);
                    let regularHabilitado = this.infoCredito.estado_crediticio.habilitado && (this.calcularTotal() <= limiteDisp);
                    return regularHabilitado || this.autorizadoPorVendedor;
                },

                confirmarAutorizacionCredito() {
                    if (!this.puedeAutorizarCredito) {
                        alert('No es posible autorizar el crédito sin la habilitación correspondiente.');
                        return;
                    }
                    this.medioPago = 'credito_personal';
                    this.creditoAutorizado = true;
                    this.modalCredito = false;
                    this.focusBarcode();
                },

                async consultarInfoCredito(clienteId) {
                    try {
                        let monto = this.calcularSubtotal();
                        let res = await fetch(`{{ url('/ventas/clientes') }}/${clienteId}/info-credito?monto=${monto}&sucursal_id={{ $sucursalActiva->id ?? 1 }}`);
                        let data = await res.json();
                        this.infoCredito = data;
                        if (data && data.cliente && this.clienteSeleccionado && this.clienteSeleccionado.id == clienteId) {
                            this.clienteSeleccionado.nombre = data.cliente.nombre;
                            this.clienteSeleccionado.dni = data.cliente.dni;
                            this.clienteSeleccionado.nro_cuenta = data.cliente.nro_cuenta;
                            this.clienteSeleccionado.limite = data.cliente.limite;
                            this.clienteSeleccionado.limite_efectivo = data.cliente.limite_efectivo;
                        }
                    } catch (e) {
                        console.error('Error consultando crédito:', e);
                    }
                },

                abrirModalPago() {
                    if (this.items.length === 0) {
                        alert('Cargue al menos un artículo en la factura para continuar.');
                        return;
                    }

                    // Guardia de Control de Caja
                    if (this.cajaEstado === 'cerrada') {
                        this.modalAbrirCaja = true;
                        return;
                    }
                    if (this.cajaEstado === 'cierre_anterior') {
                        this.modalCierreAnterior = true;
                        return;
                    }

                    if (this.esCredito) {
                        if (!this.clienteSeleccionado) {
                            alert('Para pagar a crédito debe seleccionar un cliente registrado.');
                            return;
                        }
                        // Si el crédito ya fue verificado y autorizado, emitir la factura directamente
                        if (this.creditoAutorizado) {
                            this.enviarVenta();
                            return;
                        }
                        this.abrirModalCredito();
                    } else {
                        if (this.esEfectivo) {
                            if (!this.pagaCon || this.pagaCon < this.calcularTotal()) {
                                this.pagaCon = this.calcularTotal();
                            }
                        }
                        this.modalCobro = true;
                    }
                },

                async enviarAperturaCaja() {
                    if (this.procesandoApertura) return;
                    this.procesandoApertura = true;

                    try {
                        let res = await fetch(`{{ route('cajas.abrir') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                id_sucursal: {{ $sucursalActiva->id ?? 1 }},
                                monto_inicial_efectivo: this.montoApertura || 0,
                                observaciones: this.obsApertura || null
                            })
                        });

                        let data = await res.json();
                        if (!res.ok || !data.success) {
                            alert(data.error || 'Error al realizar la apertura de caja.');
                            this.procesandoApertura = false;
                            return;
                        }

                        alert('Caja abierta correctamente. Ya puede facturar.');
                        window.location.reload();
                    } catch (e) {
                        console.error('Error abriendo caja:', e);
                        alert('Error de conexión al abrir la caja.');
                        this.procesandoApertura = false;
                    }
                },

                async enviarCierreAnteriorYAbrir() {
                    if (this.procesandoCierreAnterior) return;
                    this.procesandoCierreAnterior = true;

                    try {
                        let res = await fetch(`{{ route('cajas.cerrar-anterior-y-abrir') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                id_sucursal: {{ $sucursalActiva->id ?? 1 }},
                                id_caja_anterior: this.cajaAnteriorId,
                                efectivo_real_contado: this.contadoAnterior || 0,
                                monto_inicial_efectivo: this.fondoHoyAnterior || 0,
                                observaciones: this.obsCierreAnterior || null
                            })
                        });

                        let data = await res.json();
                        if (!res.ok || !data.success) {
                            alert(data.error || 'Error al asentar el cierre anterior y apertura.');
                            this.procesandoCierreAnterior = false;
                            return;
                        }

                        alert('Cierre de jornada anterior asentado y nueva caja abierta con éxito.');
                        window.location.reload();
                    } catch (e) {
                        console.error('Error cerrando anterior y abriendo:', e);
                        alert('Error de conexión al procesar el cierre.');
                        this.procesandoCierreAnterior = false;
                    }
                },

                async abrirModalMovimiento() {
                    if (!this.cajaId) {
                        alert('Debe abrir la caja antes de registrar movimientos.');
                        return;
                    }
                    this.modalMovimientoCaja = true;
                    await this.cargarMovimientosCaja();
                },

                async cargarMovimientosCaja() {
                    if (!this.cajaId) return;
                    try {
                        let res = await fetch(`{{ url('/cajas') }}/${this.cajaId}/movimientos`);
                        let data = await res.json();
                        this.movimientosCaja = data.movimientos || [];
                        if (data.totales) {
                            this.efectivoEsperadoCaja = data.totales.efectivo_esperado || 0;
                            this.totalIngresosCaja = data.totales.total_ingresos || 0;
                            this.totalRetirosCaja = data.totales.total_retiros || 0;
                        }
                    } catch (e) {
                        console.error('Error cargando movimientos de caja:', e);
                    }
                },

                async enviarMovimientoCaja() {
                    if (!this.cajaId) return;
                    let monto = parseFloat(this.movimientoMonto);
                    if (isNaN(monto) || monto <= 0) {
                        alert('Ingrese un monto válido mayor a cero.');
                        return;
                    }
                    if (!this.movimientoMotivo || !this.movimientoMotivo.trim()) {
                        alert('Debe ingresar el motivo o justificación del movimiento.');
                        return;
                    }

                    this.procesandoMovimiento = true;

                    try {
                        let res = await fetch(`{{ url('/cajas') }}/${this.cajaId}/movimientos`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                tipo: this.movimientoTipo,
                                monto: monto,
                                motivo: this.movimientoMotivo.trim(),
                                comprobante: this.movimientoComprobante ? this.movimientoComprobante.trim() : null
                            })
                        });

                        let data = await res.json();
                        if (!res.ok || !data.success) {
                            alert(data.error || 'Error al registrar el movimiento.');
                            this.procesandoMovimiento = false;
                            return;
                        }

                        alert(data.message || 'Movimiento registrado correctamente.');
                        this.movimientoMonto = '';
                        this.movimientoMotivo = '';
                        this.movimientoComprobante = '';
                        this.procesandoMovimiento = false;
                        await this.cargarMovimientosCaja();
                    } catch (e) {
                        console.error('Error registrando movimiento:', e);
                        alert('Error de conexión al asentar el movimiento.');
                        this.procesandoMovimiento = false;
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
                        cliente_nombre: this.clienteSeleccionado ? String(this.clienteSeleccionado.nombre) : 'CONSUMIDOR FINAL',
                        cliente_documento: (this.clienteSeleccionado && this.clienteSeleccionado.dni) ? String(this.clienteSeleccionado.dni) : null,
                        medio_pago: this.medioPago,
                        cuotas: this.esCredito ? parseInt(this.cuotasPlan || 1) : null,
                        autorizado_vendedor: this.esCredito ? (this.autorizadoPorVendedor ? true : false) : false,
                        observaciones: this.referenciaPago ? ('[Ref: ' + this.referenciaPago + ']') : null,
                        items: this.items.map(it => ({
                            id_producto: it.id_producto || null,
                            codigo_producto: it.codigo_producto ? String(it.codigo_producto) : (it.codigo ? String(it.codigo) : null),
                            descripcion: String(it.descripcion || 'ARTICULO GENERAL'),
                            cantidad: parseFloat(it.cantidad) || 1,
                            precio_unitario: parseFloat(it.precio_unitario) || 0
                        }))
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
                            if (data.requiere_apertura) {
                                this.modalAbrirCaja = true;
                            } else if (data.requiere_cierre_anterior) {
                                this.modalCierreAnterior = true;
                            }
                            let errorMsg = data.error || data.message;
                            if (data.errors) {
                                let detalles = Object.values(data.errors).flat().join('\n');
                                if (detalles && detalles !== errorMsg) {
                                    errorMsg = errorMsg ? (errorMsg + '\n\n' + detalles) : detalles;
                                }
                            }
                            alert(errorMsg || 'Ocurrió un error al procesar la venta.');
                            this.procesando = false;
                            return;
                        }

                        // Éxito: cerrar modales de cobro y mostrar modal final
                        this.modalCredito = false;
                        this.modalCobro = false;
                        this.creditoAutorizado = false;
                        this.autorizadoPorVendedor = false;
                        this.ventaExitosa = {
                            id: data.venta_id,
                            comprobante: data.comprobante,
                            total: data.total,
                            ticket_url: data.ticket_url,
                            a4_url: data.a4_url,
                            redirect_url: data.redirect_url,
                            id_credito: data.id_credito || null,
                            pagare_url: data.pagare_url || null
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
                    this.referenciaPago = '';
                    this.cuotasPlan = 1;
                    this.creditoAutorizado = false;
                    this.autorizadoPorVendedor = false;
                    this.modalExito = false;
                    this.focusBarcode();
                }
            };
        }
    </script>
</x-app-layout>
