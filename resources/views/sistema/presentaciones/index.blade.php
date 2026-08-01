<x-app-layout>
    <!-- Header Compacto -->
    <div class="flex items-center justify-between py-2 border-b border-slate-200 mb-4">
        
        <!-- Grupo Izquierdo: Botón Volver + Título -->
        <div class="flex items-center space-x-3">
            <a href="{{ url()->previous() }}" class="bg-white p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800 tracking-tight flex items-center space-x-2">
                    <span>Gestión de Presentaciones</span>
                    <span class="text-xs font-normal text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">Ciclos de Facturación</span>
                </h1>
            </div>
        </div>

        <!-- Si en el futuro querés agregar un botón a la derecha (como Exportar o Crear), lo ponés acá -->
        
    </div>

    <!-- Alertas (Éxito o Error) -->
    @if (session('error'))
        <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-md shadow-sm mb-4 flex items-center space-x-2">
            <span class="font-black text-xs uppercase block">⛔ ERROR:</span>
            <span class="text-xs font-bold">{{ session('error') }}</span>
        </div>
    @endif
    @if (session('success'))
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-md shadow-sm mb-4 flex items-center space-x-2">
            <span class="font-black text-xs uppercase block">✅ ÉXITO:</span>
            <span class="text-xs font-bold">{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-4 items-start">
        
        <!-- COLUMNA IZQUIERDA: Listado (70%) -->
        <div class="w-full lg:w-[70%]">
            <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-4">
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-2">
                    <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">Historial de Presentaciones</h2>
                </div>

                <table class="w-full text-left border-collapse font-sans text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-300">
                            <th class="py-1.5 px-3 border-r border-slate-200 w-24 text-center">N° Pres.</th>
                            <th class="py-1.5 px-3 border-r border-slate-200 text-center">Fecha Inicio</th>
                            <th class="py-1.5 px-3 border-r border-slate-200 text-center">Fecha Fin</th>
                            <th class="py-1.5 px-3 border-r border-slate-200 text-right w-24">Int. Crédito</th>
                            <th class="py-1.5 px-3 border-r border-slate-200 text-right w-24">Int. Mora</th>
                            <th class="py-1.5 px-3 text-center w-24">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($presentaciones as $pres)
                            @php
                                $estaAbierta = is_null($pres->fecha_fin);
                                $bgFila = $estaAbierta ? 'bg-blue-50/50 hover:bg-blue-100/50' : 'bg-white hover:bg-slate-50';
                            @endphp
                            <tr class="{{ $bgFila }} transition-colors">
                                <!-- Número -->
                                <td class="py-1.5 px-3 text-center border-r border-slate-200/60">
                                    <span class="font-mono font-black text-slate-800 text-[11px]">
                                        # {{ $pres->numero }}
                                    </span>
                                </td>

                                <!-- Fechas -->
                                <td class="py-1.5 px-3 text-center font-mono border-r border-slate-200/60 text-[11px]">
                                    <strong class="text-slate-800">{{ \Carbon\Carbon::parse($pres->fecha_inicio)->format('d/m/Y') }}</strong><br>
                                    <span class="text-[9px] text-slate-500">{{ \Carbon\Carbon::parse($pres->fecha_inicio)->format('H:i') }} hs</span>
                                </td>
                                <td class="py-1.5 px-3 text-center font-mono border-r border-slate-200/60 text-[11px]">
                                    @if($pres->fecha_fin)
                                        <strong class="text-slate-600">{{ \Carbon\Carbon::parse($pres->fecha_fin)->format('d/m/Y') }}</strong><br>
                                        <span class="text-[9px] text-slate-400">{{ \Carbon\Carbon::parse($pres->fecha_fin)->format('H:i') }} hs</span>
                                    @else
                                        <span class="text-slate-400 italic text-[10px]">- En curso -</span>
                                    @endif
                                </td>

                                <!-- Intereses -->
                                <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-700 border-r border-slate-200/60 tabular-nums">
                                    {{ $pres->interes_credito * 100 }} %
                                </td>
                                <td class="py-1.5 px-3 text-right font-mono font-bold text-red-700 border-r border-slate-200/60 tabular-nums">
                                    {{ $pres->interes_mora * 100 }} %
                                </td>

                                <!-- Estado -->
                                <td class="py-1.5 px-3 text-center">
                                    @if($estaAbierta)
                                        <span class="bg-amber-100 text-amber-800 text-[9px] font-black px-2 py-0.5 rounded uppercase border border-amber-200">
                                            ACTIVA
                                        </span>
                                    @else
                                        <span class="bg-slate-200 text-slate-500 text-[9px] font-black px-2 py-0.5 rounded uppercase border border-slate-300">
                                            CERRADA
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400 font-medium text-xs italic bg-slate-50">
                                    No hay presentaciones registradas en el sistema.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <!-- Paginador -->
                <div class="px-2 py-1 bg-slate-50 border-t border-slate-200 text-xs">
                    {{ $presentaciones->links() }}
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: Panel de Control (30%) -->
        <div class="w-full lg:w-[30%]">
            <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-4 sticky top-4">
                <div class="bg-blue-950 px-3 py-2">
                    <h2 class="font-bold text-[10px] text-blue-200 uppercase tracking-wider">Control Operativo</h2>
                </div>
                
                <div class="p-3">
                    @if($actual)
                        <div class="mb-4 bg-slate-50 p-2.5 rounded border border-slate-200">
                            <span class="text-[9px] font-bold text-slate-500 uppercase block mb-1">Presentación Vigente:</span>
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-mono font-black text-lg text-blue-900"># {{ $actual->numero }}</span>
                                <span class="bg-amber-100 text-amber-800 text-[9px] font-black px-1.5 py-0.5 rounded border border-amber-300 uppercase">En Curso</span>
                            </div>
                            <span class="text-[10px] text-slate-600 font-medium">Abierta hace <strong class="text-slate-800">{{ \Carbon\Carbon::parse($actual->fecha_inicio)->diffInDays(now()) }} días</strong>.</span>
                        </div>
                    @endif

                    <form action="{{ route('sistema.presentaciones.cambiar') }}" method="POST" onsubmit="return confirm('ATENCIÓN: Se cerrará la presentación actual, se aplicarán los intereses a los morosos y se volverán exigibles las nuevas cuotas. ¿Estás absolutamente seguro de continuar?');">
                        @csrf
                        <div class="space-y-3 mb-4 border-t border-slate-200 pt-3">
                            <span class="text-[10px] font-bold text-slate-700 uppercase block leading-tight">Configurar Siguiente Presentación (N° {{ $actual ? $actual->numero + 1 : 1 }}):</span>
                            
                            <div>
                                <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Interés de Crédito (Ej: 0.10 para 10%)</label>
                                <input type="number" step="0.001" name="interes_credito" value="{{ $actual->interes_credito ?? '0.10' }}" required 
                                       class="w-full text-xs font-mono font-bold rounded border-slate-300 focus:border-blue-500 py-1.5 px-2 bg-white shadow-sm">
                            </div>

                            <div>
                                <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Interés de Mora (Ej: 0.10 para 10%)</label>
                                <input type="number" step="0.001" name="interes_mora" value="{{ $actual->interes_mora ?? '0.10' }}" required 
                                       class="w-full text-xs font-mono font-bold rounded border-slate-300 focus:border-blue-500 py-1.5 px-2 bg-white shadow-sm text-red-800">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] py-2 px-3 rounded uppercase transition-all shadow-sm flex items-center justify-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                            <span>Cerrar Ciclo y Abrir Nuevo</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>