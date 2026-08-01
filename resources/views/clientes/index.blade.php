<x-app-layout>
    <div class="flex items-center justify-between py-2 border-b border-slate-200 mb-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight flex items-center space-x-2">
                <span>Gestión de Clientes</span>
                <span class="text-xs font-normal text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">Titulares, Firmas Autorizadas y Límites</span>
            </h1>
        </div>
        
        <a href="{{ route('clientes.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition-all flex items-center space-x-1 shadow-sm">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
            <span>NUEVO CLIENTE</span>
        </a>
    </div>

    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 mb-4 shadow-sm">
        <form method="GET" action="{{ route('clientes.index') }}" class="space-y-2">
            
            <div class="flex gap-2">
                <div class="relative flex-grow">
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Buscar por DNI o Nombre (Ej: marta, 796855)..." 
                           class="w-full text-xs py-1.5 px-3 rounded-md border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-white shadow-sm">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-1.5 px-4 rounded-md shadow-sm transition flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Buscar
                </button>
                @if(request()->anyFilled(['search', 'id_sucursal', 'id_localidad', 'id_provincia', 'suspendido', 'estado_banco']))
                    <a href="{{ route('clientes.index') }}" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs py-1.5 px-3 rounded-md transition flex items-center">
                        Limpiar
                    </a>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2 pt-1.5 border-t border-slate-200/70">
                <select name="id_sucursal" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="">Todas las Sucursales</option>
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}" {{ request('id_sucursal') == $sucursal->id ? 'selected' : '' }}>{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>

                <select name="id_provincia" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="">Todas las Provincias</option>
                    @foreach($provincias as $provincia)
                        <option value="{{ $provincia->id }}" {{ request('id_provincia') == $provincia->id ? 'selected' : '' }}>{{ $provincia->nombre }}</option>
                    @endforeach
                </select>

                <select name="id_localidad" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="">Todas las Localidades</option>
                    @foreach($localidades as $localidad)
                        <option value="{{ $localidad->id }}" {{ request('id_localidad') == $localidad->id ? 'selected' : '' }}>{{ $localidad->nombre }}</option>
                    @endforeach
                </select>

                <select name="suspendido" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="todos" {{ request('suspendido') == 'todos' || !request()->has('suspendido') ? 'selected' : '' }}>Todos los Tipos</option>
                    <option value="0" {{ request('suspendido') === '0' ? 'selected' : '' }}>Normales</option>
                    <option value="1" {{ request('suspendido') === '1' ? 'selected' : '' }}>Suspendidos / Adicionales</option>
                </select>

                <select name="estado_banco" onchange="this.form.submit()" class="text-xs py-1 px-2 border-slate-300 rounded-md bg-white font-medium text-slate-600">
                    <option value="">Todos los Estados CBU</option>
                    @foreach($estadosBanco as $est)
                        <option value="{{ $est->codigo }}" {{ request('estado_banco') == $est->codigo ? 'selected' : '' }}>{{ $est->codigo }} - {{ $est->descripcion }}</option>
                    @endforeach
                </select>

                <label class="space-x-1.5 cursor-pointer hover:text-blue-700 transition-colors">
                    <input type="checkbox" name="has_saldo" value="1" {{ request('has_saldo') ? 'checked' : '' }} 
                           class="rounded border-slate-400 text-blue-600 focus:ring-blue-500 h-3 w-3" onchange="this.form.submit()">
                    <span>Saldo > 0</span>
                </label>

            </div>
        </form>
    </div>

    <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-4">
        <table class="w-full text-left border-collapse font-sans text-xs">
            <thead>
                <tr class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-300">
                    <th class="py-1.5 px-3 border-r border-slate-200 w-28">DNI</th>
                    <th class="py-1.5 px-3 border-r border-slate-200">Cliente / Firma Autorizada</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 w-40">Localidad</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 w-28 text-right">Saldo</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 w-32">Teléfono</th>
                    <th class="py-1.5 px-3 w-[260px] text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($clientes as $cliente)
                    @php
                        $estaSuspendido = (int)$cliente->suspendido === 1;
                        $bgFila = $estaSuspendido ? 'bg-rose-50/60 hover:bg-rose-100/80' : 'bg-white hover:bg-blue-50/40';
                    @endphp

                    <tr class="{{ $bgFila }} transition-colors">
                        <td class="py-1.5 px-3 font-mono font-bold text-slate-900 border-r border-slate-200/60">
                            <a href="{{ route('clientes.estado-cuenta', $cliente->id) }}" title="Estado de Cuenta" class="hover:bg-slate-300  px-1.5 py-0.5 rounded uppercase transition-all shadow-sm">
                            {{ $cliente->dni }}
                            </a>
                        </td>
                        
                        <td class="py-1.5 px-3 border-r border-slate-200/60">
                            
                            <div class="flex items-center space-x-1.5">
                                <a href="{{ route('clientes.estado-cuenta', $cliente->id) }}" title="Estado de Cuenta" class="hover:bg-slate-300 text-white font-bold text-[9px] px-1.5 py-0.5 rounded uppercase transition-all shadow-sm">
                                @if($estaSuspendido)
                                    <span class="bg-rose-700 text-white text-[9px] font-black px-1.5 py-0.5 rounded uppercase leading-none">SUSPENDIDO</span>
                                    <span class="font-bold text-rose-950 uppercase truncate">{{ $cliente->nombre }}</span>
                                @else
                                    <span class="bg-blue-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded uppercase leading-none">TITULAR</span>
                                    <span class="font-bold text-slate-900 uppercase truncate">{{ $cliente->nombre }}</span>
                                @endif
                                </a>
                            </div>
                        </td>
                        
                        <td class="py-1.5 px-3 text-slate-600 border-r border-slate-200/60 uppercase text-[10px] truncate">
                            {{ $cliente->localidad->nombre ?? 'S/D' }}
                        </td>
                        
                        <td class="py-1.5 px-3 font-mono font-bold text-right text-slate-900 border-r border-slate-200/60 tabular-nums">
                            $ {{ number_format($cliente->saldo, 2, ',', '.') }}
                        </td>

                        <td class="py-1.5 px-3 font-mono text-slate-700 border-r border-slate-200/60 text-[11px]">
                            {{ $cliente->tel1 ?? '-' }}
                        </td>

                        <td class="py-1 px-2 text-center">
                            <div class="flex flex-col items-end space-y-1">
                                <div class="flex items-center justify-end space-x-1 ">
                                    @if(!$estaSuspendido)
                                        <a href="{{ route('creditos.create', ['id_cliente' => $cliente->id]) }}" title="Nuevo Crédito" class="w-32 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px] px-1.5 py-0.5 rounded uppercase transition-all shadow-sm flex items-center">
                                            + CRÉDITO
                                        </a>
                                    @else
                                        <span class="w-32 bg-slate-200 text-slate-500 font-bold text-[9px] px-1.5 py-0.5 rounded uppercase italic border border-slate-300">
                                            SUSPENDIDO
                                        </span>
                                    @endif


                                    
                                    <a href="{{ route('clientes.estado-cuenta', $cliente->id) }}" title="Estado de Cuenta" class="p-0.5 text-slate-400 hover:text-blue-700 hover:bg-blue-100 rounded transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('clientes.show', $cliente->id) }}" title="Ver Legajo" class="p-0.5 text-slate-400 hover:text-blue-700 hover:bg-blue-100 rounded transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </a>
                                    <a href="{{ route('clientes.edit', $cliente->id) }}" title="Editar" class="p-0.5 text-slate-400 hover:text-blue-600 hover:bg-blue-100 rounded transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </a>
                                    <a href="{{ route('creditos.index', ['cliente_id' => $cliente->id, 'estado' => 'all']) }}" title="Ver Listado de Créditos" class="p-0.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-100 rounded transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    </a>
                                    <form action="{{ route('clientes.destroy', $cliente->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar Cliente" class="p-0.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded transition-all" onclick="return confirm('¿Seguro que deseas eliminar este registro?')">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>

                    @foreach ($cliente->autorizados as $autorizado)
                        @php
                            $padreSuspendido = (int)$cliente->suspendido === 1;
                        @endphp
                        <tr class="bg-slate-50/70 hover:bg-slate-100/90 transition-colors text-[11px] border-b border-slate-200 border-l-2 border-l-blue-400">
                            <td class="py-1 px-3 pl-8 font-mono text-slate-500 border-r border-slate-200/60">
                                └ {{ $autorizado->dni }}
                            </td>
                            
                            <td class="py-1 px-3 border-r border-slate-200/60">
                                <div class="flex items-center space-x-1.5">
                                    <span class="bg-slate-300 text-slate-700 text-[8px] font-bold px-1 py-0.5 rounded uppercase leading-none">AUT</span>
                                    <span class="font-medium text-slate-700 uppercase truncate">{{ $autorizado->nombre }}</span>
                                    <span class="text-[9px] text-slate-400">({{ $autorizado->pivot->relacion_aut ?? 'Familiar' }})</span>
                                </div>
                            </td>
                            
                            <td class="py-1 px-3 text-slate-500 border-r border-slate-200/60 uppercase text-[10px] italic">
                                {{ $autorizado->localidad->nombre ?? 'Heredada' }}
                            </td>
                            
                            <td class="py-1 px-3 font-mono text-right text-slate-400 border-r border-slate-200/60 tabular-nums italic">
                                </td>

                            <td class="py-1 px-3 font-mono text-slate-500 border-r border-slate-200/60 text-[11px]">
                                {{ $autorizado->tel1 ?? '-' }}
                            </td>

                            <td class="py-1 px-2 text-center">
                                <div class="flex flex-col items-end space-y-1">
                                    <div class="flex items-center justify-end space-x-1">
                                        @if(!$padreSuspendido)
                                            <a href="{{ route('creditos.create', ['id_cliente' => $cliente->id, 'id_autorizado' => $autorizado->id]) }}" title="Crédito Adicional" class="w-32 bg-emerald-100 border border-amber-300 hover:bg-amber-600 hover:text-white text-emerald-800 font-bold text-[9px] px-1.5 py-0.5 rounded uppercase transition-all flex items-center">
                                                + CRED. AUTORIZADO
                                            </a>
                                        @endif
                                        <a href="{{ route('clientes.show', $autorizado->id) }}" title="Legajo Autorizado" class="p-0.5 text-slate-400 hover:text-blue-700 hover:bg-blue-100 rounded transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </a>
                                        <a href="{{ route('clientes.edit', $autorizado->id) }}" title="Editar Autorizado" class="p-0.5 text-slate-400 hover:text-blue-600 hover:bg-blue-100 rounded transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </a>
                                        <form action="{{ route('clientes.destroy', $autorizado->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Desvincular Autorizado" class="p-0.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded transition-all" onclick="return confirm('¿Desvincular a esta firma autorizada?')">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400 font-medium text-xs italic bg-slate-50">
                            No se encontraron clientes registrados en el padrón.
                        </td>
                    </tr>
                @endforelse    
            </tbody>
        </table>
    </div>

    <div class="px-1 text-xs">
        {{ $clientes->appends(request()->query())->links() }}
    </div>
</x-app-layout>