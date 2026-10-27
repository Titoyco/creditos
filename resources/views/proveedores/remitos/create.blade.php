<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8" 
         x-data="remitoForm({
             sucursalId: '{{ $sucursalActivaId }}',
             proveedorId: '{{ $proveedorSeleccionadoId ?? '' }}',
             fechaHoy: '{{ date('Y-m-d') }}',
             searchUrl: '{{ route('remitos.buscar-productos') }}',
             crearProductoUrl: '{{ route('remitos.crear-producto-rapido') }}'
         })">

        <!-- Encabezado -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700 mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('remitos.index') }}" 
                   class="bg-white dark:bg-slate-800 p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                        Cargar Remito de Mercadería
                    </h1>
                    <p class="text-xs text-slate-500 font-medium">
                        Ingreso de artículos a stock, actualización de costos de compra y fijación de precios de venta sugeridos.
                    </p>
                </div>
            </div>

            <!-- Botón Acceso Rápido Proveedores -->
            <a href="{{ route('proveedores.create') }}" target="_blank"
               class="text-xs font-bold text-blue-700 dark:text-blue-400 hover:underline inline-flex items-center gap-1">
                <span>+ Alta Proveedor Nuevo</span>
                <span class="text-[10px] text-slate-400">↗</span>
            </a>
        </div>

        <form action="{{ route('remitos.store') }}" method="POST" @submit="validarEnvio($event)">
            @csrf

            <!-- 1. ENCABEZADO DEL REMITO -->
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm mb-6">
                <div class="flex items-center gap-2 mb-4 pb-2 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 flex items-center justify-center font-black text-xs">1</span>
                    <h2 class="text-xs font-black uppercase text-slate-800 dark:text-slate-200 tracking-wider">
                        Datos del Comprobante y Proveedor
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Fecha Recepción -->
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                            Fecha de Recepción <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" name="fecha_recepcion" x-model="fechaRecepcion" required
                               class="w-full text-xs font-mono font-bold rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3 shadow-sm">
                    </div>

                    <!-- Número de Remito -->
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                            N° de Remito Proveedor <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="numero_remito" x-model="numeroRemito" placeholder="Ej: R-0001-00028475" required
                               class="w-full text-xs font-mono font-bold uppercase rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3 shadow-sm">
                    </div>

                    <!-- Proveedor -->
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                            Proveedor Emisor <span class="text-rose-500">*</span>
                        </label>
                        <select name="proveedor_id" x-model="proveedorId" required
                                class="w-full text-xs font-bold rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3 shadow-sm">
                            <option value="">Seleccione el proveedor...</option>
                            @foreach($proveedores as $prov)
                                <option value="{{ $prov->id }}">
                                    {{ $prov->legajo ? "[{$prov->legajo}] " : '' }}{{ $prov->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sede de Stock Destino -->
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                            Sucursal de Ingreso <span class="text-rose-500">*</span>
                        </label>
                        <select name="sucursal_id" x-model="sucursalId" required
                                class="w-full text-xs font-bold rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3 shadow-sm">
                            @foreach($sucursales as $suc)
                                <option value="{{ $suc->id }}">
                                    {{ $suc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Observaciones -->
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                            Observaciones de la Recepción (Opcional)
                        </label>
                        <input type="text" name="observaciones" placeholder="Detalles de bultos, precintos, transportista o entrega parcial..."
                               class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3">
                    </div>
                </div>
            </div>

            <!-- 2. BÚSQUEDA Y AGREGADO DE ARTÍCULOS -->
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 pb-2 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 flex items-center justify-center font-black text-xs">2</span>
                        <h2 class="text-xs font-black uppercase text-slate-800 dark:text-slate-200 tracking-wider">
                            Renglones de Mercadería a Ingresar
                        </h2>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="abrirModalNuevo()"
                                class="px-3.5 py-1.5 text-xs font-black uppercase text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 rounded-xl hover:bg-indigo-100 transition shadow-sm flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>+ Nuevo Artículo en Catálogo</span>
                        </button>
                    </div>
                </div>

                <!-- Buscador de Artículos Existentes -->
                <div class="relative mb-5">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input type="text" x-model="busqueda" @input.debounce.250ms="buscarProductos()"
                                   @keydown.escape="resultados = []"
                                   placeholder="Escanear código de barra (EAN-13) o buscar por código / descripción..."
                                   class="w-full text-xs py-2.5 pl-10 pr-4 rounded-2xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white font-medium shadow-inner focus:ring-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Dropdown de Resultados -->
                    <div x-show="resultados.length > 0" @click.away="resultados = []"
                         class="absolute z-50 left-0 right-0 mt-1.5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">
                        <template x-for="item in resultados" :key="item.id">
                            <div @click="agregarItem(item)" 
                                 class="p-3 hover:bg-blue-50 dark:hover:bg-slate-700/60 cursor-pointer flex items-center justify-between transition">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-black text-xs text-blue-700 dark:text-blue-400" x-text="item.codigo"></span>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white" x-text="item.descripcion"></span>
                                    </div>
                                    <div class="flex items-center gap-3 mt-0.5 text-[11px] text-slate-400">
                                        <span x-text="'Barra: ' + (item.codigo_barra || 'N/A')"></span>
                                        <span>•</span>
                                        <span x-text="item.categoria"></span>
                                        <span>•</span>
                                        <span x-text="'Stock actual sede: ' + item.stock_actual + ' u.'"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] text-slate-400 block">Costo / Venta actual:</span>
                                    <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200" 
                                          x-text="'$' + Number(item.precio_costo).toFixed(2) + ' / $' + Number(item.precio_venta).toFixed(2)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Tabla de Renglones Cargados -->
                <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/80 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400 font-black uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <th class="py-2.5 px-3">Artículo</th>
                                <th class="py-2.5 px-2 text-center w-28">Cantidad</th>
                                <th class="py-2.5 px-2 text-right w-36">Precio Compra ($)</th>
                                <th class="py-2.5 px-2 text-right w-44">P. Venta Sugerido (x2)</th>
                                <th class="py-2.5 px-2 text-right w-40">Precio Venta ($)</th>
                                <th class="py-2.5 px-3 text-right w-32">Subtotal Costo</th>
                                <th class="py-2.5 px-2 text-center w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700 font-medium">
                            <template x-for="(row, idx) in items" :key="row.producto_id">
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                                    <!-- Artículo -->
                                    <td class="py-2.5 px-3">
                                        <input type="hidden" :name="'items[' + idx + '][producto_id]'" :value="row.producto_id">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-black text-blue-700 dark:text-blue-400 text-xs" x-text="row.codigo"></span>
                                            <span class="font-bold text-slate-900 dark:text-white" x-text="row.descripcion"></span>
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                            <span x-text="'Stock actual: ' + row.stock_actual + ' unid.'"></span>
                                        </div>
                                    </td>

                                    <!-- Cantidad -->
                                    <td class="py-2.5 px-2 text-center">
                                        <input type="number" min="0.01" step="any" required
                                               :name="'items[' + idx + '][cantidad]'"
                                               x-model.number="row.cantidad"
                                               class="w-24 text-center font-mono font-bold text-xs py-1.5 px-2 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm focus:border-blue-500">
                                    </td>

                                    <!-- Precio de Compra (Costo) -->
                                    <td class="py-2.5 px-2 text-right">
                                        <input type="number" min="0" step="0.01" required
                                               :name="'items[' + idx + '][precio_compra]'"
                                               x-model.number="row.precio_compra"
                                               @input="actualizarSugerido(row)"
                                               class="w-32 text-right font-mono font-bold text-xs py-1.5 px-2 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm focus:border-blue-500">
                                    </td>

                                    <!-- Sugerido x2 con botón rápido -->
                                    <td class="py-2.5 px-2 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400"
                                                  x-text="'$' + (row.precio_compra * 2).toFixed(2)"></span>
                                            <button type="button" @click="row.precio_venta = Math.round(row.precio_compra * 2 * 100) / 100"
                                                    title="Aplicar sugerido (x2)"
                                                    class="px-1.5 py-0.5 text-[9px] font-black uppercase rounded bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100">
                                                Usar x2
                                            </button>
                                        </div>
                                        <span class="text-[9px] text-slate-400 block" x-text="'Actual: $' + Number(row.precio_venta_original || 0).toFixed(2)"></span>
                                    </td>

                                    <!-- Precio Venta Final Editable -->
                                    <td class="py-2.5 px-2 text-right">
                                        <input type="number" min="0" step="0.01" required
                                               :name="'items[' + idx + '][precio_venta]'"
                                               x-model.number="row.precio_venta"
                                               class="w-32 text-right font-mono font-black text-xs py-1.5 px-2 rounded-lg border-emerald-300 dark:border-emerald-700 bg-emerald-50/30 dark:bg-slate-900 text-emerald-800 dark:text-emerald-300 shadow-sm focus:border-emerald-500">
                                    </td>

                                    <!-- Subtotal Costo -->
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                        <span x-text="'$' + (row.cantidad * row.precio_compra).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </td>

                                    <!-- Quitar Renglón -->
                                    <td class="py-2.5 px-2 text-center">
                                        <button type="button" @click="quitarItem(idx)" 
                                                class="text-slate-400 hover:text-rose-600 transition p-1">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="items.length === 0">
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400 italic text-xs">
                                        No hay artículos agregados aún. Usa el buscador de arriba o el botón "+ Nuevo Artículo en Catálogo".
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. BARRA INFERIOR DE TOTALES Y BOTONES DE CONFIRMACIÓN -->
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-lg flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <!-- Métricas acumuladas -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Artículos</span>
                        <span class="text-xl font-black text-slate-900 dark:text-white" x-text="items.length + ' renglones'"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Total Unidades</span>
                        <span class="text-xl font-black text-slate-900 dark:text-white font-mono" x-text="calcularTotalUnidades()"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-blue-700 dark:text-blue-400 block">Total Costo Compra</span>
                        <span class="text-xl font-black text-blue-900 dark:text-blue-300 font-mono" x-text="'$' + calcularTotalCosto()"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-emerald-600 block">Total Venta Estimado</span>
                        <span class="text-xl font-black text-emerald-600 font-mono" x-text="'$' + calcularTotalVenta()"></span>
                    </div>
                </div>

                <!-- Botones de Acción (Parcial vs Completo) -->
                <div class="flex items-center gap-3 justify-end">
                    <!-- Guardar Parcial -->
                    <button type="submit" name="estado" value="parcial"
                            :disabled="items.length === 0"
                            class="px-4 py-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 font-black text-xs uppercase tracking-wide transition shadow-sm disabled:opacity-50">
                        Guardar Entrega Parcial
                    </button>

                    <!-- Guardar Completo -->
                    <button type="submit" name="estado" value="completado"
                            :disabled="items.length === 0"
                            class="px-5 py-2.5 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-black text-xs uppercase tracking-wider transition shadow-md disabled:opacity-50 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Recepcionar y Finalizar Remito</span>
                    </button>
                </div>
            </div>

        </form>

        <!-- MODAL PARA CREAR NUEVO ARTÍCULO ON-THE-FLY -->
        <div x-show="modalNuevo" style="display: none;"
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-lg w-full p-6 text-left"
                 @click.away="modalNuevo = false">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700 mb-4">
                    <div>
                        <h3 class="text-base font-black uppercase text-slate-900 dark:text-white">
                            Alta Rápida de Artículo
                        </h3>
                        <p class="text-xs text-slate-500">Crea el producto para incorporarlo directamente a este remito.</p>
                    </div>
                    <button type="button" @click="modalNuevo = false" class="text-slate-400 hover:text-slate-600 text-lg font-black">&times;</button>
                </div>

                <div class="space-y-3.5">
                    <!-- Descripción -->
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                            Descripción / Nombre del Artículo <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" x-model="nuevoProd.descripcion" placeholder="Ej: REMERA ESTAMPADA ALGODÓN TALLE M"
                               class="w-full text-xs font-bold uppercase rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Código Interno -->
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Código Interno</label>
                            <input type="text" x-model="nuevoProd.codigo" placeholder="Dejar vacío para automático"
                                   class="w-full text-xs font-mono font-bold uppercase rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3">
                        </div>

                        <!-- Código de Barras -->
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Código de Barras (EAN-13)</label>
                            <input type="text" x-model="nuevoProd.codigo_barra" placeholder="Escaneado con pistola..."
                                   class="w-full text-xs font-mono font-bold rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Categoría -->
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Categoría</label>
                            <select x-model="nuevoProd.categoria_id"
                                    class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-2.5">
                                <option value="">General</option>
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Subcategoría -->
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Subcategoría</label>
                            <select x-model="nuevoProd.subcategoria_id"
                                    class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-2.5">
                                <option value="">Sin Subcategoría</option>
                                @foreach($subcategorias as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <!-- Precio Costo -->
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                Precio Compra ($) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" min="0" step="0.01" x-model.number="nuevoProd.precio_costo"
                                   @input="nuevoProd.precio_venta = Math.round(nuevoProd.precio_costo * 2 * 100) / 100"
                                   placeholder="0.00"
                                   class="w-full text-xs font-mono font-bold text-right rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white py-2 px-3">
                        </div>

                        <!-- Precio Venta Sugerido (x2) y editable -->
                        <div>
                            <label class="block text-[10px] font-black uppercase text-emerald-600 mb-1">
                                Precio Venta Público ($) (Sugerido x2)
                            </label>
                            <input type="number" min="0" step="0.01" x-model.number="nuevoProd.precio_venta"
                                   placeholder="0.00"
                                   class="w-full text-xs font-mono font-black text-right rounded-xl border-emerald-300 dark:border-emerald-700 bg-emerald-50/40 dark:bg-slate-900 text-emerald-800 dark:text-emerald-300 py-2 px-3">
                        </div>
                    </div>

                    <template x-if="nuevoProdError">
                        <p class="text-xs text-rose-600 font-bold" x-text="nuevoProdError"></p>
                    </template>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100 dark:border-slate-700 mt-5">
                    <button type="button" @click="modalNuevo = false"
                            class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-800">
                        Cancelar
                    </button>
                    <button type="button" @click="guardarNuevoProducto()"
                            :disabled="guardandoNuevo"
                            class="px-4 py-2 text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md transition disabled:opacity-50">
                        <span x-text="guardandoNuevo ? 'Guardando...' : 'Crear y Agregar al Remito'"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Script Alpine.js del Remito -->
    <script>
        function remitoForm(config) {
            return {
                sucursalId: config.sucursalId,
                proveedorId: config.proveedorId,
                fechaRecepcion: config.fechaHoy,
                numeroRemito: '',
                busqueda: '',
                resultados: [],
                items: [],
                modalNuevo: false,
                guardandoNuevo: false,
                nuevoProdError: '',
                nuevoProd: {
                    descripcion: '',
                    codigo: '',
                    codigo_barra: '',
                    categoria_id: '',
                    subcategoria_id: '',
                    precio_costo: 0,
                    precio_venta: 0
                },

                buscarProductos() {
                    const term = this.busqueda.trim();
                    if (term.length < 1) {
                        this.resultados = [];
                        return;
                    }
                    fetch(`${config.searchUrl}?q=${encodeURIComponent(term)}&sucursal_id=${this.sucursalId}`)
                        .then(res => res.json())
                        .then(data => {
                            this.resultados = data;
                        })
                        .catch(() => {
                            this.resultados = [];
                        });
                },

                agregarItem(producto) {
                    // Si ya está en la lista, incrementa cantidad
                    const exist = this.items.find(i => i.producto_id === producto.id);
                    if (exist) {
                        exist.cantidad += 1;
                    } else {
                        const costo = Number(producto.precio_costo) || 0;
                        const sugerido = Math.round(costo * 2 * 100) / 100;
                        const venta = Number(producto.precio_venta) > 0 ? Number(producto.precio_venta) : sugerido;

                        this.items.push({
                            producto_id: producto.id,
                            codigo: producto.codigo,
                            descripcion: producto.descripcion,
                            stock_actual: producto.stock_actual,
                            cantidad: 1,
                            precio_compra: costo,
                            precio_venta: venta,
                            precio_venta_original: producto.precio_venta,
                            precio_venta_sugerido: sugerido
                        });
                    }
                    this.busqueda = '';
                    this.resultados = [];
                },

                actualizarSugerido(row) {
                    row.precio_venta_sugerido = Math.round(Number(row.precio_compra) * 2 * 100) / 100;
                },

                quitarItem(index) {
                    this.items.splice(index, 1);
                },

                calcularTotalUnidades() {
                    return this.items.reduce((acc, i) => acc + (Number(i.cantidad) || 0), 0);
                },

                calcularTotalCosto() {
                    const sum = this.items.reduce((acc, i) => acc + ((Number(i.cantidad) || 0) * (Number(i.precio_compra) || 0)), 0);
                    return sum.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                },

                calcularTotalVenta() {
                    const sum = this.items.reduce((acc, i) => acc + ((Number(i.cantidad) || 0) * (Number(i.precio_venta) || 0)), 0);
                    return sum.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                },

                abrirModalNuevo() {
                    if (!this.proveedorId) {
                        alert('Selecciona primero el proveedor en el encabezado del remito.');
                        return;
                    }
                    this.nuevoProd = {
                        descripcion: '',
                        codigo: '',
                        codigo_barra: '',
                        categoria_id: '',
                        subcategoria_id: '',
                        precio_costo: 0,
                        precio_venta: 0
                    };
                    this.nuevoProdError = '';
                    this.modalNuevo = true;
                },

                guardarNuevoProducto() {
                    if (!this.nuevoProd.descripcion.trim()) {
                        this.nuevoProdError = 'Ingresa la descripción del artículo.';
                        return;
                    }
                    if (!this.proveedorId) {
                        this.nuevoProdError = 'Selecciona un proveedor para asociar el artículo.';
                        return;
                    }

                    this.guardandoNuevo = true;
                    this.nuevoProdError = '';

                    fetch(config.crearProductoUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            descripcion: this.nuevoProd.descripcion,
                            codigo: this.nuevoProd.codigo,
                            codigo_barra: this.nuevoProd.codigo_barra,
                            categoria_id: this.nuevoProd.categoria_id || null,
                            subcategoria_id: this.nuevoProd.subcategoria_id || null,
                            proveedor_id: this.proveedorId,
                            precio_costo: this.nuevoProd.precio_costo,
                            precio_venta: this.nuevoProd.precio_venta
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.guardandoNuevo = false;
                        if (data.success && data.producto) {
                            this.agregarItem(data.producto);
                            this.modalNuevo = false;
                        } else {
                            this.nuevoProdError = data.message || 'Error al guardar el producto.';
                        }
                    })
                    .catch(err => {
                        this.guardandoNuevo = false;
                        this.nuevoProdError = 'Error de conexión con el servidor.';
                    });
                },

                validarEnvio(e) {
                    if (this.items.length === 0) {
                        e.preventDefault();
                        alert('Debes agregar al menos un artículo al remito.');
                    }
                }
            };
        }
    </script>
</x-app-layout>
