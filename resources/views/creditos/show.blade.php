<x-app-layout>
    <!-- Header Compacto -->
    <div class="flex items-center justify-between py-2 border-b border-slate-200 mb-3">
        <div class="flex items-center space-x-3">
            <a href="{{ url()->previous() }}" class="bg-white p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800 tracking-tight flex items-center space-x-2">
                    <span>Crédito <span class="font-mono text-blue-700">#{{ $credito->full_identificador }}</span></span>
                    @if($credito->fecha_cancelacion)
                        <span class="bg-slate-200 text-slate-600 text-[10px] font-black px-2 py-0.5 rounded border border-slate-300 uppercase">CANCELADO</span>
                    @else
                        <span class="bg-amber-100 text-amber-800 text-[10px] font-black px-2 py-0.5 rounded border border-amber-200 uppercase">ABIERTO</span>
                    @endif
                </h1>
            </div>
        </div>

        <div class="flex space-x-2">
            <a href="{{ route('creditos.pagare', $credito) }}" target="_blank" class="bg-white border border-slate-300 text-slate-700 px-3 py-1.5 rounded-md text-xs font-bold shadow-sm hover:bg-slate-50 transition-all uppercase flex items-center">
                📄 Reimprimir Pagaré
            </a>
        </div>
    </div>

    <!-- Panel de Datos Rápidos y Cinta de KPIs -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
        
        <!-- Datos del Cliente y Sucursal -->
        <div class="bg-slate-50 p-3 rounded-md border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <span class="text-[9px] font-bold text-slate-500 uppercase block mb-0.5">Cliente Titular</span>
                    <div class="text-xs font-bold text-slate-800 uppercase truncate">{{ $credito->cliente->nombre }}</div>
                    <div class="text-[9px] font-bold text-slate-400">DNI: {{ $credito->cliente->dni }}</div>
                </div>
                <div>
                    <span class="text-[9px] font-bold text-slate-500 uppercase block mb-0.5">Autorizado</span>
                    <div class="text-xs font-bold text-slate-800 uppercase truncate">
                        {{ $credito->autorizado && $credito->autorizado->cliente ? $credito->autorizado->cliente->nombre : 'TITULAR' }}
                    </div>
                    <div class="text-[9px] font-bold text-blue-600">SUC: {{ $credito->sucursal->nombre ?? 'N/D' }}</div>
                </div>
                <div class="col-span-2 border-t border-slate-200 pt-1.5 mt-1 flex justify-between">
                    <div>
                        <span class="text-[9px] font-bold text-slate-500 uppercase">Otorgado:</span>
                        <span class="text-[10px] font-bold text-slate-800">{{ $credito->fecha_credito ? \Carbon\Carbon::parse($credito->fecha_credito)->format('d/m/Y') : 'N/D' }}</span>
                    </div>
                    @if($credito->fecha_cancelacion)
                        <div>
                            <span class="text-[9px] font-bold text-slate-500 uppercase">Cancelado:</span>
                            <span class="text-[10px] font-bold text-slate-600">{{ \Carbon\Carbon::parse($credito->fecha_cancelacion)->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Cinta KPI Financiera (Ocupa 2 columnas) -->
        <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div class="bg-white p-3 rounded-md border border-slate-200 shadow-sm flex flex-col justify-center">
                <span class="text-[9px] font-bold text-slate-500 uppercase block">Importe Original</span>
                <div class="text-lg font-black text-slate-900 font-mono tabular-nums mt-0.5">$ {{ number_format($credito->importe_original, 2, ',', '.') }}</div>
            </div>
            <div class="bg-white p-3 rounded-md border border-slate-200 shadow-sm flex flex-col justify-center">
                <span class="text-[9px] font-bold text-slate-500 uppercase block">Total Intereses</span>
                <div class="text-lg font-black text-red-800 font-mono tabular-nums mt-0.5">$ {{ number_format($totalIntereses, 2, ',', '.') }}</div>
            </div>
            <div class="bg-white p-3 rounded-md border border-slate-200 shadow-sm flex flex-col justify-center">
                <span class="text-[9px] font-bold text-slate-500 uppercase block">Total Cobrado</span>
                <div class="text-lg font-black text-slate-900 font-mono tabular-nums mt-0.5">$ {{ number_format($totalEntrego, 2, ',', '.') }}</div>
            </div>
            <div class="bg-emerald-50 p-3 rounded-md border border-emerald-200 shadow-sm flex flex-col justify-center">
                <span class="text-[9px] font-bold text-emerald-800 uppercase block">Saldo Pendiente</span>
                <div class="text-lg font-black text-emerald-700 font-mono tabular-nums mt-0.5">$ {{ number_format($totalSaldo, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <!-- Data-Grid de Cuotas -->
    <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-4">
        <div class="bg-slate-100 border-b border-slate-300 px-3 py-2 flex justify-between items-center">
            <h2 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">Plan de Cuotas ({{ $credito->cuotas->count() }})</h2>
        </div>

        <table class="w-full text-left border-collapse font-sans text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-300">
                    <th class="py-1.5 px-3 border-r border-slate-200 text-center w-20">N° Cuota</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-28">Valor Base</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-28">Intereses</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-28">Entregó</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-right w-32">Saldo Actual</th>
                    <th class="py-1.5 px-3 border-r border-slate-200 text-center w-24">Fecha Cobro</th>
                    <th class="py-1.5 px-3 text-center w-24">Medio Pago</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($credito->cuotas as $cuota)
                    <tr class="hover:bg-blue-50/40 transition-colors bg-white">
                        <td class="py-1.5 px-3 text-center border-r border-slate-200/60">
                            <span class="font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 text-[10px]">
                                {{ $cuota->num_cuota }} / {{ $credito->cantidad_cuotas }}
                            </span>
                        </td>
                        <td class="py-1.5 px-3 text-right font-mono font-bold border-r border-slate-200/60 tabular-nums text-slate-900">
                            $ {{ number_format($cuota->valor_base, 2, ',', '.') }}
                        </td>
                        <td class="py-1.5 px-3 text-right font-mono font-bold border-r border-slate-200/60 tabular-nums text-red-800">
                            $ {{ number_format($cuota->intereses, 2, ',', '.') }}
                        </td>
                        <td class="py-1.5 px-3 text-right font-mono font-bold border-r border-slate-200/60 tabular-nums text-slate-900">
                            $ {{ number_format($cuota->entrego, 2, ',', '.') }}
                        </td>
                        <td class="py-1.5 px-3 text-right font-mono font-black border-r border-slate-200/60 tabular-nums text-emerald-700">
                            $ {{ number_format($cuota->saldo_actual, 2, ',', '.') }}
                        </td>
                        <td class="py-1.5 px-3 text-center font-mono border-r border-slate-200/60 text-[11px] text-slate-600">
                            {{ $cuota->fecha_cobro ? \Carbon\Carbon::parse($cuota->fecha_cobro)->format('d/m/Y') : '-' }}
                        </td>
                        <td class="py-1.5 px-3 text-center">
                            @if($cuota->medio_pago)
                                <span class="text-blue-700 font-bold text-[9px] uppercase">{{ $cuota->medio_pago }}</span>
                            @else
                                <span class="text-slate-400 text-[10px]">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400 font-medium text-xs italic bg-slate-50">
                            No hay cuotas registradas para este crédito.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>