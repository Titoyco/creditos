<x-app-layout>
    <!-- Header Compacto -->
    <div class="flex items-center justify-between py-2 border-b border-slate-200 mb-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight flex items-center space-x-2">
                <span>Cartera de Créditos</span>
                <span class="text-xs font-normal text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">Monitoreo y Estados de Cuenta</span>
            </h1>
        </div>
        
        <div class="flex space-x-2">
            <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition-all shadow-sm flex items-center space-x-1">
                <span>EXPORTAR EXCEL</span>
            </button>
        </div>
    </div>

    <!-- Panel de Búsqueda y Filtros (Ribbon Denso) -->
    <div class="bg-slate-50 p-2.5 rounded-md border border-slate-200 mb-4 shadow-sm">
        <form action="{{ route('creditos.index') }}" method="GET" class="space-y-2">
            @if(request('cliente_id'))
                <input type="hidden" name="cliente_id" value="{{ request('cliente_id') }}">
            @endif
            
            <!-- Buscador Principal -->
            <div class="flex gap-2">
                <div class="relative flex-grow">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Nro crédito, cliente o autorizado..." 
                           class="w-full text-xs py-1.5 px-3 rounded-md border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-white shadow-sm">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-1.5 px-4 rounded-md shadow-sm transition">
                    Buscar
                </button>
                @if(request()->anyFilled(['search', 'sucursal', 'tipo', 'estado', 'has_interes', 'has_entrego', 'has_saldo']))
                    <a href="{{ route('creditos.index') }}" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs py-1.5 px-3 rounded-md transition flex items-center">
                        Limpiar
                    </a>
                @endif
            </div>
            
            <!-- Filtros Secundarios -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1.5 border-t border-slate-200/70">
                <select name="sucursal" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="all">Todas las Sucursales</option>
                    @foreach($sucursales as $suc)
                        @php
                            $seleccionadaEnRequest = request('sucursal');
                            $idAComparar = $seleccionadaEnRequest !== null ? $seleccionadaEnRequest : config('app.sucursal_activa.id');
                        @endphp
                        <option value="{{ $suc->id }}" {{ (string)$idAComparar === (string)$suc->id ? 'selected' : '' }}>
                            {{ $suc->nombre }}
                        </option>
                    @endforeach
                </select>

                <select name="tipo" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="all">Todos los Tipos</option>
                    <option value="C" {{ request('tipo') === 'C' ? 'selected' : '' }}>C - Crédito</option>
                    <option value="D" {{ request('tipo') === 'D' ? 'selected' : '' }}>D - Débito</option>
                    <option value="R" {{ request('tipo') === 'R' ? 'selected' : '' }}>R - Refinanciación</option>
                </select>

                <select name="estado" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="all" {{ request('estado') === 'all' ? 'selected' : '' }}>Todos los Estados</option>
                    <option value="abiertos" {{ request('estado', 'abiertos') === 'abiertos' ? 'selected' : '' }}>Abiertos</option>
                    <option value="cancelados" {{ request('estado') === 'cancelados' ? 'selected' : '' }}>Cancelados</option>
                </select>
            </div>

            <!-- Filtros Financieros Rápidos (Checkboxes) -->
            <div class="flex items-center space-x-5 pt-2 mt-2 border-t border-slate-200/70 text-[10px] text-slate-700 font-bold uppercase tracking-wider">
                <label class="flex items-center space-x-1.5 cursor-pointer hover:text-blue-700 transition-colors">
                    <input type="checkbox" name="has_interes" value="1" {{ request('has_interes') ? 'checked' : '' }} 
                           class="rounded border-slate-400 text-blue-600 focus:ring-blue-500 h-3 w-3" onchange="this.form.submit()">
                    <span>Interés > 0</span>
                </label>
                
                <label class="flex items-center space-x-1.5 cursor-pointer hover:text-blue-700 transition-colors">
                    <input type="checkbox" name="has_entrego" value="1" {{ request('has_entrego') ? 'checked' : '' }} 
                           class="rounded border-slate-400 text-blue-600 focus:ring-blue-500 h-3 w-3" onchange="this.form.submit()">
                    <span>Entregó > 0</span>
                </label>

                <label class="flex items-center space-x-1.5 cursor-pointer hover:text-blue-700 transition-colors">
                    <input type="checkbox" name="has_saldo" value="1" {{ request('has_saldo') ? 'checked' : '' }} 
                           class="rounded border-slate-400 text-blue-600 focus:ring-blue-500 h-3 w-3" onchange="this.form.submit()">
                    <span>A Pagar > 0</span>
                </label>
            </div>
        </form>
    </div>

    <!-- Data-Grid Estilo Excel -->
    <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-4">
        
        <!-- Indicador de Filtro por Cliente Activo -->
        @if(request('cliente_id') && isset($creditos->first()->cliente))
            <div class="bg-blue-50 border-b border-blue-200 px-3 py-1.5 flex justify-between items-center">
                <span class="text-[10px] text-blue-800 font-bold uppercase tracking-wider">Filtrando por cliente: {{ $creditos->first()->cliente->nombre }}</span>
                <a href="{{ route('creditos.index') }}" class="text-[10px] text-blue-600 hover:text-blue-900 font-black">✕ Quitar filtro</a>
            </div>
        @endif

        <table class="w-full text-left border-collapse font-sans text-xs">
            <thead>
                <tr class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-300">
                    <th class="py-1.5 px-3 border-r border-slate-200 w-24">ID Gestión</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 w-24 text-center">Fecha</th>
                    <th class="py-1.5 px-3 border-r border-slate-200">Cliente</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-24">Valor Base</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-24">Intereses</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-24">Entregó</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-28">A Pagar</th>
                    <th class="py-1.5 px-3 text-center w-20">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($creditos as $credito)
                    @php
                        $esCancelado = !is_null($credito->fecha_cancelacion);
                        $bgFila = $esCancelado ? 'bg-slate-50/60 text-slate-500' : 'bg-white hover:bg-blue-50/40 text-slate-800';
                    @endphp
                    <tr class="{{ $bgFila }} transition-colors">
                        
                        <!-- Columna ID -->
                        <td class="py-1.5 px-3 border-r border-slate-200/60">
                            <div class="flex flex-col space-y-0.5">
                                <a href="{{ route('creditos.show', $credito) }}" class="font-mono font-bold {{ $esCancelado ? 'text-slate-500 line-through' : 'text-blue-700 hover:underline' }} text-[11px]">
                                    {{ $credito->full_identificador }}
                                </a>
                                @if($esCancelado)
                                    <span class="bg-slate-200 text-slate-500 text-[8px] font-black px-1 py-0.5 rounded uppercase w-max">Cancelado</span>
                                @else
                                    <span class="bg-amber-100 text-amber-800 text-[8px] font-black px-1 py-0.5 rounded uppercase w-max">Abierto</span>
                                @endif
                            </div>
                        </td>

                        <!-- Columna Fecha -->
                        <td class="py-1.5 px-2 text-center font-mono border-r border-slate-200/60">
                            <div class="text-[10px] font-bold text-slate-700">
                                {{ $credito->fecha_credito ? \Carbon\Carbon::parse($credito->fecha_credito)->format('d/m/Y') : '-' }}
                            </div>
                            <div class="text-[9px] text-slate-400">
                                {{ $credito->fecha_credito ? \Carbon\Carbon::parse($credito->fecha_credito)->format('H:i') : '' }}
                            </div>
                        </td>

                        <!-- Columna Cliente (Achicada, con truncate) -->
                        <td class="py-1.5 px-3 border-r border-slate-200/60 max-w-[150px] truncate">
                            <div class="font-bold uppercase text-[11px] truncate" title="{{ $credito->cliente->nombre }}">
                                {{ $credito->cliente->nombre }}
                            </div>
                            <div class="text-[9px] font-bold text-slate-400">DNI: {{ $credito->cliente->dni }}</div>
                        </td>

                        <!-- Columnas Numéricas -->
                        <!-- Valor Base: Negro normal -->
                        <td class="py-1.5 px-3 text-right font-mono font-bold border-r border-slate-200/60 tabular-nums text-slate-900">
                            $ {{ number_format($credito->total_valor_base ?? 0, 2, ',', '.') }}
                        </td>

                        <!-- Intereses: Bordó / Rojo Oscuro -->
                        <td class="py-1.5 px-3 text-right font-mono font-bold border-r border-slate-200/60 tabular-nums {{ $esCancelado ? 'text-slate-400' : 'text-red-800' }}">
                            $ {{ number_format($credito->total_intereses ?? 0, 2, ',', '.') }}
                        </td>

                        <!-- Entregó: Negro normal -->
                        <td class="py-1.5 px-3 text-right font-mono font-bold border-r border-slate-200/60 tabular-nums text-slate-900">
                            $ {{ number_format($credito->total_entrego ?? 0, 2, ',', '.') }}
                        </td>

                        <!-- A Pagar: Verde Esmeralda -->
                        <td class="py-1.5 px-3 text-right font-mono font-black border-r border-slate-200/60 tabular-nums {{ $esCancelado ? 'line-through text-slate-400' : 'text-emerald-700' }}">
                            $ {{ number_format($credito->total_saldo_actual ?? 0, 2, ',', '.') }}
                        </td>

                        <!-- Acciones -->
                        <td class="py-1 px-2 text-center">
                            <a href="{{ route('creditos.show', $credito) }}" class="inline-flex items-center bg-slate-100 border border-slate-200 hover:bg-blue-600 hover:text-white text-slate-700 px-2 py-0.5 rounded text-[10px] font-bold transition-all uppercase shadow-sm">
                                Detalle →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400 font-medium text-xs italic bg-slate-50">
                            No se encontraron créditos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Paginador -->
        @if($creditos->hasPages())
            <div class="px-2 py-1 bg-slate-50 border-t border-slate-200 text-xs">
                {{ $creditos->links() }}
            </div>
        @endif
    </div>
</x-app-layout>