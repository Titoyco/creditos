<x-app-layout>
    <div class="space-y-4">
        {{-- Encabezado --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>Auditoría de Movimientos de Stock</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Historial cronológico de altas, ventas, mermas, ajustes y traslados inter-sucursales.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('productos.traslado') }}" class="px-3 py-1.5 text-xs font-bold text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 border border-amber-300 dark:border-amber-700 rounded-lg shadow-2xs transition-all">
                    + Nuevo Traslado
                </a>
                <a href="{{ route('productos.index') }}" class="px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg shadow-2xs transition-all">
                    &larr; Catálogo
                </a>
            </div>
        </div>

        {{-- Filtros de Auditoría --}}
        <div class="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-4 shadow-2xs">
            <form method="GET" action="{{ route('stock.movimientos') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 mb-1">Buscar Comprobante o Artículo</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Ej: REM-001, Pantalon..." 
                           class="w-full text-xs py-1.5 px-3 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 mb-1">Tipo de Movimiento</label>
                    <select name="tipo" onchange="this.form.submit()" class="w-full text-xs py-1.5 px-2.5 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200">
                        <option value="">Todos los Tipos</option>
                        <option value="alta" {{ request('tipo') == 'alta' ? 'selected' : '' }}>Alta / Compra</option>
                        <option value="baja" {{ request('tipo') == 'baja' ? 'selected' : '' }}>Baja / Merma</option>
                        <option value="traslado_salida" {{ request('tipo') == 'traslado_salida' ? 'selected' : '' }}>Traslado (Salida)</option>
                        <option value="traslado_entrada" {{ request('tipo') == 'traslado_entrada' ? 'selected' : '' }}>Traslado (Entrada)</option>
                        <option value="venta" {{ request('tipo') == 'venta' ? 'selected' : '' }}>Salida por Venta</option>
                        <option value="correccion" {{ request('tipo') == 'correccion' ? 'selected' : '' }}>Ajuste de Conteo</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 mb-1">Sucursal</label>
                    <select name="sucursal_id" onchange="this.form.submit()" class="w-full text-xs py-1.5 px-2.5 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200">
                        <option value="">Todas las Sedes</option>
                        @foreach($sucursales as $suc)
                            <option value="{{ $suc->id }}" {{ request('sucursal_id') == $suc->id ? 'selected' : '' }}>{{ $suc->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 mb-1">Fecha Desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" onchange="this.form.submit()" 
                           class="w-full text-xs py-1.5 px-2.5 rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2 px-3 rounded-lg shadow-2xs transition">
                        Filtrar
                    </button>
                    @if(request()->anyFilled(['search', 'tipo', 'sucursal_id', 'fecha_desde', 'producto_id']))
                        <a href="{{ route('stock.movimientos') }}" class="bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 font-bold text-xs py-2 px-3 rounded-lg transition">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Tabla de Movimientos --}}
        <div class="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xs overflow-hidden">
            <table class="w-full text-left text-xs font-sans border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/60 text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700 select-none">
                        <th class="py-2.5 px-3 w-36">Fecha / Hora</th>
                        <th class="py-2.5 px-3">Artículo</th>
                        <th class="py-2.5 px-3 w-36">Sucursal</th>
                        <th class="py-2.5 px-3 w-32">Operación</th>
                        <th class="py-2.5 px-3 w-24 text-right">Cantidad</th>
                        <th class="py-2.5 px-3 w-28 text-right">Stock Final</th>
                        <th class="py-2.5 px-3">Comprobante / Detalle</th>
                        <th class="py-2.5 px-3 w-28">Usuario</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($movimientos as $mov)
                        @php
                            $badgeClass = match($mov->tipo) {
                                'alta' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
                                'ingreso_remito' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300',
                                'baja' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
                                'traslado_salida' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
                                'traslado_entrada' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
                                'venta' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300',
                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                            };
                            $labelOperacion = match($mov->tipo) {
                                'ingreso_remito' => 'Remito Prov.',
                                default => str_replace('_', ' ', $mov->tipo),
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="py-2 px-3 font-mono text-slate-500 dark:text-slate-400">
                                {{ $mov->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="py-2 px-3">
                                <a href="{{ route('productos.show', $mov->producto_id) }}" class="font-bold text-slate-800 dark:text-slate-100 hover:text-blue-600 block truncate max-w-sm">
                                    {{ $mov->producto->descripcion ?? 'Artículo Eliminado' }}
                                </a>
                                <span class="text-[10px] font-mono text-slate-400">{{ $mov->producto->codigo ?? '' }}</span>
                            </td>
                            <td class="py-2 px-3 font-semibold text-slate-700 dark:text-slate-300">
                                {{ $mov->sucursal->nombre ?? '-' }}
                            </td>
                            <td class="py-2 px-3">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-black uppercase {{ $badgeClass }}">
                                    {{ $labelOperacion }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-right font-mono font-bold tabular-nums {{ (float)$mov->cantidad < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ (float)$mov->cantidad > 0 ? '+' : '' }}{{ (float)$mov->cantidad }}
                            </td>
                            <td class="py-2 px-3 text-right font-mono text-slate-700 dark:text-slate-300 tabular-nums">
                                {{ (float)$mov->stock_nuevo }}
                            </td>
                            <td class="py-2 px-3 text-slate-600 dark:text-slate-400">
                                @if($mov->referencia_type === 'App\Models\RemitoProveedor' && $mov->referencia_id)
                                    <a href="{{ route('remitos.show', $mov->referencia_id) }}" class="font-bold font-mono text-blue-700 dark:text-blue-400 hover:underline">
                                        {{ $mov->comprobante ?? '-' }}
                                    </a>
                                @else
                                    <span class="font-bold font-mono text-slate-800 dark:text-slate-200">{{ $mov->comprobante ?? '-' }}</span>
                                @endif
                                @if($mov->motivo)
                                    <span class="text-[10px] text-slate-400 block truncate max-w-xs">{{ $mov->motivo }}</span>
                                @endif
                                @if($mov->sucursalOrigen && $mov->sucursalDestino)
                                    <span class="text-[9px] text-amber-700 dark:text-amber-400 block">
                                        {{ $mov->sucursalOrigen->nombre }} &rarr; {{ $mov->sucursalDestino->nombre }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-slate-500 font-mono text-[11px]">
                                {{ $mov->usuario->username ?? $mov->usuario->name ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400 italic">
                                No se encontraron movimientos de stock registrados con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-1 text-xs">
            {{ $movimientos->appends(request()->query())->links() }}
        </div>
    </div>
</x-app-layout>
