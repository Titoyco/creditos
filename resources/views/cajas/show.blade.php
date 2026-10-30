<x-app-layout>
    <div class="max-w-7xl mx-auto py-5 px-3 sm:px-6 space-y-6">

        <!-- Barra Superior con Botón Volver y Acciones -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('cajas.index', ['sucursal' => $caja->id_sucursal]) }}" 
                   class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs py-2 px-3 rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                    <span>⬅️ Volver a Cajas</span>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                            Detalle de Caja #{{ $caja->id }}
                        </h1>
                        <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full {{ $caja->estado === 'abierta' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300' : 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200' }}">
                            {{ $caja->estado }}
                        </span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            {{ $caja->sucursal?->nombre }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Jornada del <strong>{{ $caja->fecha_apertura->format('d/m/Y') }}</strong> • Apertura: {{ $caja->fecha_apertura->format('H:i') }} hs @if($caja->fecha_cierre) • Cierre: {{ $caja->fecha_cierre->format('H:i') }} hs @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('cajas.comprobante', ['caja' => $caja->id, 'formato' => 'ticket']) }}" target="_blank"
                   class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2 px-3.5 rounded-xl transition uppercase flex items-center gap-1.5 shadow-xs">
                    <span>🖨️ Ticket Cierre Z (80mm)</span>
                </a>
                <a href="{{ route('cajas.comprobante', ['caja' => $caja->id, 'formato' => 'a4']) }}" target="_blank"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-2 px-3.5 rounded-xl transition uppercase flex items-center gap-1.5 shadow-xs">
                    <span>📄 Comprobante A4</span>
                </a>
            </div>
        </div>

        <!-- TARJETA PRINCIPAL: ARQUEO DE EFECTIVO Y MOVIMIENTOS -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs">
            <h2 class="text-xs font-black uppercase text-slate-400 tracking-wider mb-4 flex items-center gap-2">
                <span>💵 Arqueo y Conciliación de Efectivo Físico</span>
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 text-center">
                <!-- 1. Fondo Inicial -->
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80">
                    <div class="text-[10px] font-bold uppercase text-slate-500">1. Fondo Inicial</div>
                    <div class="text-sm sm:text-base font-black font-mono text-slate-800 dark:text-slate-100 mt-1">
                        ${{ number_format($caja->monto_inicial_efectivo, 2, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Apertura</div>
                </div>

                <!-- 2. Ventas Efectivo -->
                <div class="p-3 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60">
                    <div class="text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-400">2. Cobros Efectivo</div>
                    <div class="text-sm sm:text-base font-black font-mono text-emerald-800 dark:text-emerald-200 mt-1">
                        +${{ number_format($caja->total_efectivo, 2, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {{ $caja->cant_ventas_efectivo }} ventas
                    </div>
                </div>

                <!-- 3. Ingresos Manuales -->
                <div class="p-3 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60">
                    <div class="text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-400">3. Ingresos (+)</div>
                    <div class="text-sm sm:text-base font-black font-mono text-emerald-800 dark:text-emerald-200 mt-1">
                        +${{ number_format($caja->total_ingresos_efectivo, 2, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {{ $caja->cant_ingresos_efectivo }} movs
                    </div>
                </div>

                <!-- 4. Egresos Manuales -->
                <div class="p-3 rounded-xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/60">
                    <div class="text-[10px] font-bold uppercase text-rose-700 dark:text-rose-400">4. Egresos / Retiros (-)</div>
                    <div class="text-sm sm:text-base font-black font-mono text-rose-800 dark:text-rose-200 mt-1">
                        -${{ number_format($caja->total_retiros_efectivo, 2, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-rose-600 dark:text-rose-400 mt-0.5">
                        {{ $caja->cant_retiros_efectivo }} movs
                    </div>
                </div>

                <!-- 5. Efectivo Teórico Esperado -->
                <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800">
                    <div class="text-[10px] font-bold uppercase text-indigo-700 dark:text-indigo-400">5. Teórico Esperado</div>
                    <div class="text-sm sm:text-base font-black font-mono text-indigo-900 dark:text-indigo-200 mt-1">
                        ${{ number_format($caja->monto_final_efectivo_esperado ?? ($caja->monto_inicial_efectivo + $caja->total_efectivo + $caja->total_ingresos_efectivo - $caja->total_retiros_efectivo), 2, ',', '.') }}
                    </div>
                    <div class="text-[10px] text-indigo-500 mt-0.5">Cálculo de sistema</div>
                </div>

                <!-- 6. Efectivo Real Contado -->
                <div class="p-3 rounded-xl bg-slate-900 text-white dark:bg-slate-950 border border-slate-700">
                    <div class="text-[10px] font-bold uppercase text-slate-300">6. Real Contado</div>
                    <div class="text-sm sm:text-base font-black font-mono text-white mt-1">
                        @if($caja->fecha_cierre)
                            ${{ number_format($caja->monto_final_efectivo_real, 2, ',', '.') }}
                        @else
                            <span class="text-amber-400 text-xs">En curso</span>
                        @endif
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Cierre Z</div>
                </div>

                <!-- 7. Diferencia / Fallo de Caja -->
                @php
                    $dif = (float)($caja->diferencia_efectivo ?? 0);
                @endphp
                <div class="p-3 rounded-xl {{ abs($dif) < 0.01 ? 'bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-700' : ($dif > 0 ? 'bg-sky-50 dark:bg-sky-950/30 border border-sky-300 dark:border-sky-700' : 'bg-rose-100 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700') }}">
                    <div class="text-[10px] font-bold uppercase {{ abs($dif) < 0.01 ? 'text-emerald-700 dark:text-emerald-300' : ($dif > 0 ? 'text-sky-700 dark:text-sky-300' : 'text-rose-700 dark:text-rose-300') }}">
                        7. Fallo / Diferencia
                    </div>
                    <div class="text-sm sm:text-base font-black font-mono mt-1 {{ abs($dif) < 0.01 ? 'text-emerald-700 dark:text-emerald-400' : ($dif > 0 ? 'text-sky-700 dark:text-sky-400' : 'text-rose-700 dark:text-rose-400') }}">
                        @if(!$caja->fecha_cierre)
                            <span class="text-slate-400 text-xs">-</span>
                        @elseif(abs($dif) < 0.01)
                            $0,00
                        @elseif($dif > 0)
                            +${{ number_format($dif, 2, ',', '.') }}
                        @else
                            -${{ number_format(abs($dif), 2, ',', '.') }}
                        @endif
                    </div>
                    <div class="text-[10px] text-slate-500 mt-0.5">
                        {{ abs($dif) < 0.01 ? 'Caja exacta' : ($dif > 0 ? 'Sobrante' : 'Faltante') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- TOTALES DISCRIMINADOS POR MEDIO DE PAGO -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-black uppercase text-slate-400 tracking-wider flex items-center gap-2">
                    <span>💳 Ventas y Cobros por Medio de Pago</span>
                </h2>
                <div class="text-right">
                    <span class="text-xs text-slate-500 uppercase font-bold">Total Facturado:</span>
                    <strong class="font-mono text-base font-black text-indigo-700 dark:text-indigo-400 ml-1">
                        ${{ number_format($caja->total_general, 2, ',', '.') }}
                    </strong>
                    <span class="text-xs text-slate-400 font-bold ml-1">({{ $caja->cant_ventas_total }} ventas)</span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @if(!empty($totales['por_medio']))
                    @foreach($totales['por_medio'] as $mpCodigo => $mpData)
                        @if($mpData['cant'] > 0 || in_array($mpCodigo, ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'credito_personal', 'transferencia']))
                            @php
                                $color = $mpData['color'] ?? 'slate';
                                $borderBg = match($color) {
                                    'emerald' => 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-800 dark:text-emerald-300',
                                    'sky', 'cyan' => 'border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20 text-sky-800 dark:text-sky-300',
                                    'blue', 'indigo' => 'border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/20 text-indigo-800 dark:text-indigo-300',
                                    'amber', 'yellow' => 'border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-950/20 text-amber-800 dark:text-amber-300',
                                    'teal' => 'border-teal-200 dark:border-teal-800 bg-teal-50/50 dark:bg-teal-950/20 text-teal-800 dark:text-teal-300',
                                    'purple', 'violet' => 'border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-950/20 text-purple-800 dark:text-purple-300',
                                    default => 'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 text-slate-700 dark:text-slate-300',
                                };
                            @endphp
                            <div class="p-3.5 rounded-xl border {{ $borderBg }}">
                                <div class="flex items-center justify-between text-xs font-bold">
                                    <span class="truncate mr-1">{{ $mpData['icono'] ?? '💳' }} {{ $mpData['nombre'] }}</span>
                                    <span class="text-[10px] bg-white/80 dark:bg-slate-800/80 px-1.5 py-0.5 rounded font-black shadow-2xs">
                                        {{ $mpData['cant'] }}
                                    </span>
                                </div>
                                <div class="text-base font-black font-mono text-slate-900 dark:text-white mt-2">
                                    ${{ number_format($mpData['total'], 2, ',', '.') }}
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $caja->total_general > 0 ? round(($mpData['total'] / $caja->total_general) * 100, 1) : 0 }}% del total
                                </div>
                            </div>
                        @endif
                    @endforeach
                @else
                    <!-- Fallback estándar si por_medio no está inicializado -->
                    <div class="p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20">
                        <div class="flex items-center justify-between text-xs font-bold text-emerald-800 dark:text-emerald-300">
                            <span>💵 Efectivo</span>
                            <span class="text-[10px] bg-emerald-200/80 dark:bg-emerald-900 px-1.5 py-0.5 rounded font-black">{{ $caja->cant_ventas_efectivo }}</span>
                        </div>
                        <div class="text-base font-black font-mono text-emerald-900 dark:text-emerald-200 mt-2">${{ number_format($caja->total_efectivo, 2, ',', '.') }}</div>
                    </div>
                @endif
            </div>
        </div>

        <!-- SECCIÓN 1: MOVIMIENTOS DE CAJA (INGRESOS Y RETIROS) -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2">
                        <span>💸 Movimientos de Dinero en Caja (Ingresos y Egresos)</span>
                        <span class="text-xs bg-slate-200 dark:bg-slate-700 px-2 py-0.5 rounded-full font-mono">
                            {{ count($movimientos) }}
                        </span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Gastos operativos, retiros justificados e ingresos extraordinarios de dinero en efectivo.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-900/80 text-slate-600 dark:text-slate-400 uppercase text-[10px] font-black border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-2.5 px-4">Hora</th>
                            <th class="py-2.5 px-4 text-center">Tipo</th>
                            <th class="py-2.5 px-4">Concepto / Motivo</th>
                            <th class="py-2.5 px-4">Comprobante</th>
                            <th class="py-2.5 px-4 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-mono">
                        @forelse($movimientos as $mov)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="py-2.5 px-4 text-slate-600 dark:text-slate-300">
                                    {{ $mov->created_at->format('H:i:s') }}
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    @if($mov->tipo === 'ingreso')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                            + Ingreso
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-700">
                                            - Retiro / Gasto
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-4 font-sans font-bold text-slate-800 dark:text-slate-200">
                                    {{ $mov->motivo }}
                                </td>
                                <td class="py-2.5 px-4 text-slate-500 font-sans">
                                    {{ $mov->comprobante ?: '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-black {{ $mov->tipo === 'ingreso' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                    {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 font-sans">
                                    No se registraron movimientos manuales de ingresos ni egresos en este día.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECCIÓN 2: VENTAS DEL DÍA -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2">
                        <span>🛒 Ventas Registradas en el Turno</span>
                        <span class="text-xs bg-slate-200 dark:bg-slate-700 px-2 py-0.5 rounded-full font-mono">
                            {{ $ventas->total() }}
                        </span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Operaciones de venta cobradas en esta caja con detalle de cliente y medio de pago.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-900/80 text-slate-600 dark:text-slate-400 uppercase text-[10px] font-black border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-2.5 px-4">Hora</th>
                            <th class="py-2.5 px-4">Comprobante / Nro</th>
                            <th class="py-2.5 px-4">Cliente</th>
                            <th class="py-2.5 px-4">Medio de Pago</th>
                            <th class="py-2.5 px-4 text-center">Estado</th>
                            <th class="py-2.5 px-4 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse($ventas as $v)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="py-2.5 px-4 font-mono text-slate-600 dark:text-slate-400">
                                    {{ $v->fecha ? $v->fecha->format('H:i:s') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                    <a href="{{ route('ventas.show', $v->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $v->comprobante ?: ('# ' . $v->id) }} ↗
                                    </a>
                                </td>
                                <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">
                                    @if($v->cliente)
                                        <a href="{{ route('clientes.show', $v->cliente->id) }}" class="font-bold hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                            {{ $v->cliente->nombre }}
                                        </a>
                                    @else
                                        {{ $v->nombre_cliente_ocasional ?? 'Consumidor Final' }}
                                    @endif
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200">
                                        {{ $v->medioPago?->icono ?: '💳' }} {{ $v->medioPago?->nombre ?: str_replace('_', ' ', $v->medio_pago) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $v->estado === 'completada' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $v->estado }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-black text-indigo-700 dark:text-indigo-400">
                                    ${{ number_format($v->total, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400 font-sans">
                                    No hay ventas asociadas a este turno.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($ventas->hasPages())
                <div class="p-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                    {{ $ventas->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
