<x-app-layout>
    <!-- Encabezado y Pestañas -->
    <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-slate-200 pb-2">
        <div class="flex items-center space-x-3">
            <a href="{{ route('clientes.index') }}" class="bg-white p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <div>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight flex items-center space-x-2">
                    <span class="uppercase">{{ $cliente->nombre }}</span>
                    <span class="bg-blue-100 text-blue-800 text-[10px] font-black px-2 py-0.5 rounded border border-blue-200 uppercase">DNI: {{ $cliente->dni }}</span>
                </h1>
            </div>
        </div>

        <div class="flex bg-slate-100 p-0.5 rounded-md border border-slate-200">
            <a href="{{ route('clientes.show', $cliente) }}" class="px-3 py-1 rounded text-[11px] font-bold text-slate-600 hover:text-slate-900 transition-all">
                📄 Legajo Personal
            </a>
            <a href="{{ route('clientes.estado-cuenta', $cliente) }}" class="px-3 py-1 rounded text-[11px] font-black bg-white text-blue-900 shadow-sm border border-slate-200 transition-all">
                💳 Estado de Cuenta
            </a>
        </div>
    </div>

    <!-- Cinta KPI Financiera -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 mb-4">
        <div class="bg-white p-2 rounded-md border border-slate-200 shadow-sm flex flex-col justify-between">
            <span class="text-[9px] font-bold text-slate-500 uppercase">Total Deuda Base</span>
            <span class="font-mono font-black text-slate-900 text-sm tabular-nums">$ {{ number_format($totalValorBase, 2, ',', '.') }}</span>
        </div>
        <div class="bg-white p-2 rounded-md border border-slate-200 shadow-sm flex flex-col justify-between">
            <span class="text-[9px] font-bold text-slate-500 uppercase">Total Intereses</span>
            <span class="font-mono font-black text-red-800 text-sm tabular-nums">$ {{ number_format($totalIntereses, 2, ',', '.') }}</span>
        </div>
        <div class="bg-white p-2 rounded-md border border-slate-200 shadow-sm flex flex-col justify-between">
            <span class="text-[9px] font-bold text-slate-500 uppercase">Total Entregado</span>
            <span class="font-mono font-black text-slate-900 text-sm tabular-nums">$ {{ number_format($totalEntregado, 2, ',', '.') }}</span>
        </div>
        <div class="bg-emerald-50 p-2 rounded-md border border-emerald-200 shadow-sm flex flex-col justify-between">
            <span class="text-[9px] font-bold text-emerald-800 uppercase">Total a Pagar</span>
            <span class="font-mono font-black text-emerald-700 text-sm tabular-nums">$ {{ number_format($totalAPagar, 2, ',', '.') }}</span>
        </div>
        <div class="bg-blue-900 p-2 rounded-md border border-blue-950 shadow-sm flex flex-col justify-between">
            <span class="text-[9px] font-bold text-blue-200 uppercase">A Vencer</span>
            <span class="font-mono font-black text-white text-sm tabular-nums">$ {{ number_format($totalRestante, 2, ',', '.') }}</span>
        </div>
    </div>

    <form action="#" method="POST">
        @csrf
        
        <!-- Toolbar Tabla -->
        <div class="bg-slate-50 p-2 rounded-t-md border border-slate-300 border-b-0 flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-2 pl-1">
                <span class="text-xs font-bold text-slate-800 uppercase">Créditos Activos</span>
                <span class="text-[9px] font-black bg-slate-200 border border-slate-300 text-slate-600 px-1.5 py-0.5 rounded">{{ $creditosAbiertos->count() }}</span>
            </div>
            <div class="flex space-x-2">
                <button type="button" class="bg-emerald-100 hover:bg-emerald-600 hover:text-white text-emerald-900 border border-emerald-300 px-3 py-1 rounded text-[10px] font-bold uppercase transition-all shadow-sm">
                    💵 Cobrar Sel.
                </button>
                <button type="button" class="bg-amber-100 hover:bg-amber-500 hover:text-white text-amber-900 border border-amber-300 px-3 py-1 rounded text-[10px] font-bold uppercase transition-all shadow-sm">
                    🔄 Refinanciar Sel.
                </button>
            </div>
        </div>

        <div class="bg-white rounded-b-md border border-slate-300 shadow-sm overflow-hidden mb-4">
            <table class="w-full text-left border-collapse font-sans text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-300">
                        <th class="py-1.5 px-2 text-center border-r border-slate-200 w-10">
                            <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-3 w-3">
                        </th>
                        <th class="py-1.5 px-3 border-r border-slate-200 w-32">Identificador</th>
                        <th class="py-1.5 px-3 border-r border-slate-200 w-24 text-center">Fecha</th>
                        
                        <!-- Columna de Plan -->
                        <th class="py-1.5 px-3 border-r border-slate-200 text-center w-36">Plan (Cuotas)</th>
                        
                        <!-- Nueva Columna: Intereses -->
                        <th class="py-1.5 px-3 border-r border-slate-200 text-right w-24">Intereses</th>
                        
                        <th class="py-1.5 px-3 border-r border-slate-200 text-right w-24">Pagado</th>
                        <th class="py-1.5 px-3 border-r border-slate-200 text-right w-28">Saldo Actual</th>
                        <th class="py-1.5 px-3 text-center w-24">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($creditosAbiertos as $credito)
                    @php
                        $pagadoCredito = $credito->cuotas->sum('entrego');
                        $saldoCredito  = $credito->cuotas->sum('saldo_actual');
                        $interesesCredito = $credito->cuotas->sum('intereses'); // CÁLCULO DE INTERESES DEL CRÉDITO
                        
                        // Cálculo del valor de cuota 
                        $cantCuotas = $credito->cantidad_cuotas ?? 1;
                        $valorCuotaDisplay = $credito->valor_cuota_ref ?? $credito->valor_cuota ?? ($cantCuotas > 0 ? $credito->importe_original / $cantCuotas : $credito->importe_original);
                    @endphp
                    <tr class="hover:bg-blue-50/40 transition-colors">
                        <td class="py-1.5 px-2 text-center border-r border-slate-200/60">
                            <input type="checkbox" name="creditos_ids[]" value="{{ $credito->id }}" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-3 w-3">
                        </td>
                        <td class="py-1.5 px-3 border-r border-slate-200/60">
                            <a href="{{ route('creditos.show', $credito) }}" class="font-mono font-bold text-blue-700 hover:text-blue-500 hover:underline transition-colors text-[11px]">
                                {{ $credito->full_identificador }}
                            </a>
                        </td>
                        <td class="py-1.5 px-3 text-center text-[11px] font-mono text-slate-600 border-r border-slate-200/60">
                            {{ $credito->fecha_credito ? \Carbon\Carbon::parse($credito->fecha_credito)->format('d/m/Y') : '-' }}
                        </td>
                        
                        <!-- Plan (Cuotas) -->
                        <td class="py-1 px-3 text-center border-r border-slate-200/60 leading-tight">
                            <span class="text-[10px] text-slate-500"><strong class="text-slate-800">{{ $cantCuotas }}</strong> cuotas de</span><br>
                            <span class="font-mono font-bold text-slate-900 text-[11px] tabular-nums">$ {{ number_format($valorCuotaDisplay, 2, ',', '.') }}</span>
                        </td>

                        <!-- Intereses (Bordó) -->
                        <td class="py-1.5 px-3 text-right font-mono text-red-800 font-bold text-[11px] border-r border-slate-200/60 tabular-nums">
                            $ {{ number_format($interesesCredito, 2, ',', '.') }}
                        </td>
                        
                        <!-- Pagado (Negro) -->
                        <td class="py-1.5 px-3 text-right font-mono text-slate-900 font-bold text-[11px] border-r border-slate-200/60 tabular-nums">
                            $ {{ number_format($pagadoCredito, 2, ',', '.') }}
                        </td>
                        
                        <!-- Saldo Actual (Verde Esmeralda) -->
                        <td class="py-1.5 px-3 text-right font-mono font-black text-emerald-700 text-[11px] border-r border-slate-200/60 tabular-nums">
                            $ {{ number_format($saldoCredito, 2, ',', '.') }}
                        </td>
                        
                        <td class="py-1 px-2 text-center">
                            <a href="{{ route('creditos.show', $credito) }}" class="inline-flex items-center bg-slate-100 border border-slate-200 hover:bg-blue-600 hover:text-white text-slate-700 px-2 py-0.5 rounded text-[10px] font-bold transition-all uppercase shadow-sm">
                                Detalle →
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400 font-medium text-xs italic bg-slate-50">
                            El cliente no tiene créditos abiertos pendientes.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</x-app-layout>