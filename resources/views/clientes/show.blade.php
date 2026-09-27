<x-app-layout>
    <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-slate-200 pb-2">
        <div class="flex items-center space-x-3">
            <a href="{{  url()->previous()  }}" class="bg-white p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <div>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight flex items-center space-x-2">
                    <span class="uppercase">{{ $cliente->nombre }}</span>
                    @if(isset($cliente->suspendido) && $cliente->suspendido)
                        <span class="bg-rose-100 text-rose-800 text-[9px] font-black px-1.5 py-0.5 rounded border border-rose-200 uppercase">SUSPENDIDO</span>
                    @else
                        <span class="bg-emerald-100 text-emerald-800 text-[9px] font-black px-1.5 py-0.5 rounded border border-emerald-200 uppercase">ACTIVO</span>
                    @endif
                </h1>
            </div>
        </div>

        <div class="flex bg-slate-100 p-0.5 rounded-md border border-slate-200">
            <a href="{{ route('clientes.show', $cliente) }}" class="px-3 py-1 rounded text-[11px] font-black bg-white text-blue-900 shadow-sm border border-slate-200 transition-all">
                📄 Legajo Personal
            </a>
            <a href="{{ route('clientes.estado-cuenta', $cliente) }}" class="px-3 py-1 rounded text-[11px] font-bold text-slate-600 hover:text-slate-900 transition-all">
                💳 Estado de Cuenta
            </a>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-3 items-start">
        
        <div class="w-full lg:w-[66.6%] space-y-3">
            
            <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden">
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5 flex justify-between items-center">
                    <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">1. Datos Personales y Sucursal</h2>
                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-[10px] font-bold text-blue-600 hover:text-blue-800 uppercase flex items-center space-x-1">
                        <span>✏️ Editar</span>
                    </a>
                </div>
                <div class="p-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Nombre Completo</span>
                        <span class="font-bold text-slate-800 text-xs">{{ $cliente->nombre }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">DNI</span>
                        <span class="font-mono font-bold text-blue-900 text-xs tabular-nums">{{ $cliente->dni }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">CUIL / CUIT</span>
                        <span class="font-mono font-medium text-slate-700 text-xs tabular-nums">{{ $cliente->cuil ?? $cliente->cuit ?? '-' }}</span>
                    </div>
                    <div class="col-span-4 border-t border-slate-100 pt-2 mt-1">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Sucursal de Alta</span>
                        <span class="font-bold text-slate-700 text-xs">{{ $cliente->sucursal?->nombre_sucursal ?? $cliente->sucursal?->nombre ?? 'Trelew (T)' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden">
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
                    <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">2. Ubicación y Contacto</h2>
                </div>
                <div class="p-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Dirección Particular</span>
                        <span class="font-bold text-slate-800 text-xs truncate">{{ $cliente->domicilio ?? 'Sin dirección' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Localidad</span>
                        <span class="font-bold text-slate-700 text-xs">{{ is_object($cliente->localidad) ? $cliente->localidad->nombre : ($cliente->localidad ?? 'TRELEW') }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Tel. Celular</span>
                        <span class="font-mono font-bold text-slate-800 text-xs">{{ $cliente->telefono ?? $cliente->celular ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Tel. Alternativo</span>
                        <span class="font-mono font-medium text-slate-700 text-xs">{{ $cliente->telefono_2 ?? $cliente->telefono_alternativo ?? '-' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Email</span>
                        <span class="font-medium text-slate-700 text-xs truncate">{{ $cliente->email ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden">
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
                    <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">3. Laboral y Bancaria</h2>
                </div>
                <div class="p-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Lugar de Trabajo</span>
                        <span class="font-bold text-slate-800 text-xs">{{ $cliente->lugar_trabajo ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Tipo Haber (ID)</span>
                        <span class="font-bold text-slate-700 text-xs">{{ $cliente->tipo_haber_id ?? '2' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Máx Cuotas</span>
                        <span class="font-bold text-slate-700 text-xs">{{ $cliente->max_cuotas ?? '4 Cuotas' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Dirección Laboral</span>
                        <span class="font-medium text-slate-700 text-xs">{{ $cliente->direccion_laboral ?? '-' }} ({{ $cliente->localidad_laboral ?? '-' }})</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Tel. Laboral</span>
                        <span class="font-mono font-medium text-slate-700 text-xs">{{ $cliente->telefono_laboral ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden">
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
                    <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">4. Familiar de Contacto / Referencia</h2>
                </div>
                <div class="p-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Familiar</span>
                        <span class="font-bold text-slate-800 text-xs">{{ $cliente->familiar_nombre ?? '-' }} <span class="text-[10px] font-normal text-slate-500">({{ $cliente->familiar_parentesco ?? '-' }})</span></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Teléfono</span>
                        <span class="font-mono font-bold text-slate-700 text-xs">{{ $cliente->familiar_telefono ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase block leading-none mb-1">Localidad</span>
                        <span class="font-medium text-slate-700 text-xs">{{ $cliente->familiar_localidad ?? '-' }}</span>
                    </div>
                </div>
                @if(!empty($cliente->observaciones))
                    <div class="px-3 pb-3">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Observaciones Generales</span>
                        <p class="text-[11px] text-slate-700 bg-amber-50 p-2 rounded border border-amber-200 italic leading-tight">
                            {{ $cliente->observaciones }}
                        </p>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden">
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5 flex justify-between items-center">
                    <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">Firmas Autorizadas ({{ $cliente->autorizados ? $cliente->autorizados->count() : 0 }})</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[9px] uppercase text-slate-500">
                            <tr>
                                <th class="py-1.5 px-3">Nombre</th>
                                <th class="py-1.5 px-3">DNI</th>
                                <th class="py-1.5 px-3">Relación</th>
                                <th class="py-1.5 px-3 text-right">Límite ($)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($cliente->autorizados as $autorizado)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-1.5 px-3 font-bold text-slate-800">{{ $autorizado->nombre }}</td>
                                    <td class="py-1.5 px-3 font-mono">{{ $autorizado->dni }}</td>
                                    <td class="py-1.5 px-3">
                                        <span class="bg-blue-100 text-blue-800 text-[9px] font-bold px-1.5 py-0.5 rounded">{{ $autorizado->pivot->relacion_aut ?? 'ADICIONAL' }}</span>
                                    </td>
                                    <td class="py-1.5 px-3 text-right font-mono font-semibold tabular-nums">{{ number_format($autorizado->pivot->limite_aut ?? 0, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-3 px-3 text-center text-slate-400 italic text-[10px]">No hay adicionales vinculados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="w-full lg:w-[33.3%] space-y-3">
            
            <div class="bg-blue-950 text-white p-4 rounded-md shadow-sm border border-blue-900 relative overflow-hidden">
                <div class="relative z-10">
                    <span class="text-[9px] font-bold text-blue-300 uppercase tracking-widest block mb-0.5">Límite de Crédito Base</span>
                    <div class="text-2xl font-black text-white font-mono tabular-nums tracking-tight">
                        $ {{ number_format($cliente->limite_credito ?? $cliente->limite ?? 800000, 0, ',', '.') }}
                    </div>
                    <div class="border-t border-blue-800/60 mt-3 pt-2 grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-[8px] font-bold text-blue-400 uppercase block mb-0.5">CBU / CVU Preferido</span>
                            <span class="font-mono text-[10px] text-blue-100">{{ $cliente->cbu ?? 'No especificado' }}</span>
                        </div>
                        <div>
                            <span class="text-[8px] font-bold text-blue-400 uppercase block mb-0.5">Medio de Pago</span>
                            <span class="font-bold text-[10px] text-blue-100 uppercase">{{ $cliente->medio_pago ?? 'Débito' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-md border border-slate-300 shadow-sm p-3 space-y-2">
                @if(isset($cliente->suspendido) && $cliente->suspendido)
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 p-2 rounded text-center">
                        <span class="font-black text-[11px] uppercase block">⛔ CLIENTE SUSPENDIDO</span>
                    </div>
                @else
                    <a href="{{ route('creditos.create', ['id_cliente' => $cliente->id]) }}" 
                       class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] py-1.5 px-3 rounded uppercase transition-all flex items-center justify-center space-x-1 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>NUEVO CRÉDITO</span>
                    </a>
                @endif
                <a href="{{ route('clientes.estado-cuenta', $cliente->id) }}" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold text-[11px] py-1.5 px-3 rounded uppercase transition-all flex items-center justify-center space-x-1 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span>Ver Estado de Cuenta</span>
                </a>
            </div>

            <div class="bg-white rounded-md border border-slate-300 shadow-sm flex flex-col h-[350px]">
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5 flex justify-between items-center">
                    <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">Historial / Novedades</h2>
                    <span class="bg-slate-300 text-slate-700 font-bold text-[9px] px-1.5 py-0.5 rounded">{{ $cliente->novedades ? $cliente->novedades->count() : 0 }}</span>
                </div>
                
                <div class="flex-1 overflow-y-auto p-2 space-y-1.5 bg-slate-50/50">
                    @if($cliente->novedades && $cliente->novedades->count() > 0)
                        @foreach($cliente->novedades as $novedad)
                            <div class="p-2 rounded text-[11px] border-l-2 shadow-sm bg-white border border-slate-200 {{ $novedad->tipo == 0 ? 'border-l-rose-500' : 'border-l-emerald-500' }}">
                                <div class="flex justify-between items-center mb-1">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="font-bold text-slate-800 uppercase text-[9px]">{{ $novedad->user?->name ?? $novedad->usuario_nombre ?? 'Admin' }}</span>
                                        @if($novedad->metodo)
                                            <span class="text-[8px] font-bold px-1 rounded uppercase bg-slate-100 text-slate-500 border border-slate-200">{{ $novedad->metodo }}</span>
                                        @endif
                                    </div>
                                    <span class="text-[9px] font-mono text-slate-400">{{ \Carbon\Carbon::parse($novedad->created_at)->format('d/m/Y H:i') }}</span>
                                </div>
                                <p class="text-slate-600 leading-snug">{{ $novedad->mensaje }}</p>
                            </div>
                        @endforeach
                    @else
                        <div class="h-full flex items-center justify-center">
                            <span class="text-[10px] text-slate-400 font-medium italic">Sin novedades registradas.</span>
                        </div>
                    @endif
                </div>

                <div class="border-t border-slate-200 p-2 bg-slate-50">
                    <form action="#" method="POST" class="flex flex-col space-y-1.5">
                        @csrf
                        <textarea name="mensaje" rows="2" placeholder="Nueva novedad..." class="w-full text-[11px] rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 p-1.5 resize-none" required></textarea>
                        <button type="button" class="bg-blue-800 hover:bg-blue-900 text-white font-bold text-[10px] py-1.5 px-3 rounded uppercase transition-all shadow-sm w-full">
                            Registrar
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>