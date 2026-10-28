<x-app-layout>
    <div class="max-w-4xl mx-auto py-5 px-3 space-y-5" 
         x-data="{
             teoricoEfectivo: {{ (float) $totales['efectivo_esperado'] }},
             realContado: {{ (float) $totales['efectivo_esperado'] }},
             formatDinero(val) {
                 if (val === undefined || val === null || isNaN(val)) return '0,00';
                 return Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
             },
             diferencia() {
                 return Math.round((Number(this.realContado || 0) - this.teoricoEfectivo) * 100) / 100;
             }
         }">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('cajas.index') }}" 
                   class="bg-white dark:bg-slate-800 p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2">
                        <span>🔒 Arqueo & Cierre Z de Caja</span>
                        <span class="text-xs font-mono font-black px-2 py-0.5 rounded-full bg-slate-900 text-white dark:bg-white dark:text-slate-900">
                            #{{ $caja->id }}
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Sucursal: <strong>{{ $caja->sucursal?->nombre }}</strong> • Apertura: <strong>{{ $caja->fecha_apertura->format('d/m/Y H:i') }} hs</strong> por <strong>{{ $caja->usuarioApertura?->name }}</strong>
                    </p>
                </div>
            </div>

            <a href="{{ route('ventas.create') }}" 
               class="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-bold text-xs py-2 px-3 rounded-xl transition uppercase">
                Volver al POS
            </a>
        </div>

        @if(session('error'))
            <div class="p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs rounded-xl">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('cajas.cerrar.store', $caja->id) }}" class="space-y-5"
              onsubmit="return confirm('¿Está seguro de cerrar definitivamente la caja? Se generará el Cierre Z oficial y no se podrán agregar más ventas a este turno.');">
            @csrf

            <!-- 1. RESUMEN POR MEDIOS DE PAGO (SIMILAR AL CIERRE Z DE REGISTRADORA) -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/80 pb-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                        <span>📊</span>
                        <span>Resumen de Ventas por Medio de Pago (Cierre Z)</span>
                    </span>
                    <span class="text-xs font-bold text-slate-500">
                        {{ $totales['ventas_count'] }} comprobante(s) emitido(s)
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <!-- Efectivo -->
                    <div class="p-3 rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/50 dark:bg-emerald-950/20">
                        <div class="flex items-center justify-between text-xs text-emerald-800 dark:text-emerald-300 font-bold">
                            <span>💵 Efectivo</span>
                            <span class="text-[10px] font-mono bg-emerald-100 dark:bg-emerald-900 px-1.5 py-0.5 rounded">
                                {{ $totales['efectivo_count'] }} op.
                            </span>
                        </div>
                        <div class="text-lg font-black font-mono text-emerald-900 dark:text-emerald-200 mt-1">
                            ${{ number_format($totales['efectivo_total'], 2, ',', '.') }}
                        </div>
                    </div>

                    <!-- Débito -->
                    <div class="p-3 rounded-xl border border-sky-200 dark:border-sky-900 bg-sky-50/50 dark:bg-sky-950/20">
                        <div class="flex items-center justify-between text-xs text-sky-800 dark:text-sky-300 font-bold">
                            <span>💳 Tarjeta Débito</span>
                            <span class="text-[10px] font-mono bg-sky-100 dark:bg-sky-900 px-1.5 py-0.5 rounded">
                                {{ $totales['debito_count'] }} op.
                            </span>
                        </div>
                        <div class="text-lg font-black font-mono text-sky-900 dark:text-sky-200 mt-1">
                            ${{ number_format($totales['debito_total'], 2, ',', '.') }}
                        </div>
                    </div>

                    <!-- Crédito Bancario -->
                    <div class="p-3 rounded-xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50/50 dark:bg-indigo-950/20">
                        <div class="flex items-center justify-between text-xs text-indigo-800 dark:text-indigo-300 font-bold">
                            <span>💳 Tarjeta Crédito</span>
                            <span class="text-[10px] font-mono bg-indigo-100 dark:bg-indigo-900 px-1.5 py-0.5 rounded">
                                {{ $totales['credito_count'] }} op.
                            </span>
                        </div>
                        <div class="text-lg font-black font-mono text-indigo-900 dark:text-indigo-200 mt-1">
                            ${{ number_format($totales['credito_total'], 2, ',', '.') }}
                        </div>
                    </div>

                    <!-- Crédito Personal -->
                    <div class="p-3 rounded-xl border border-purple-200 dark:border-purple-900 bg-purple-50/50 dark:bg-purple-950/20">
                        <div class="flex items-center justify-between text-xs text-purple-800 dark:text-purple-300 font-bold">
                            <span>📋 Crédito Personal</span>
                            <span class="text-[10px] font-mono bg-purple-100 dark:bg-purple-900 px-1.5 py-0.5 rounded">
                                {{ $totales['credito_personal_count'] }} op.
                            </span>
                        </div>
                        <div class="text-lg font-black font-mono text-purple-900 dark:text-purple-200 mt-1">
                            ${{ number_format($totales['credito_personal_total'], 2, ',', '.') }}
                        </div>
                    </div>

                    <!-- Transferencias -->
                    <div class="p-3 rounded-xl border border-teal-200 dark:border-teal-900 bg-teal-50/50 dark:bg-teal-950/20">
                        <div class="flex items-center justify-between text-xs text-teal-800 dark:text-teal-300 font-bold">
                            <span>📱 Transferencia / MP</span>
                            <span class="text-[10px] font-mono bg-teal-100 dark:bg-teal-900 px-1.5 py-0.5 rounded">
                                {{ $totales['transferencia_count'] + $totales['otros_count'] }} op.
                            </span>
                        </div>
                        <div class="text-lg font-black font-mono text-teal-900 dark:text-teal-200 mt-1">
                            ${{ number_format($totales['transferencia_total'] + $totales['otros_total'], 2, ',', '.') }}
                        </div>
                    </div>

                    <!-- TOTAL GENERAL FACTURADO -->
                    <div class="p-3 rounded-xl border-2 border-slate-900 dark:border-white bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-sm">
                        <div class="flex items-center justify-between text-xs font-bold opacity-80 uppercase tracking-wider">
                            <span>Total Facturado</span>
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-white/20 dark:bg-slate-900/20">
                                {{ $totales['ventas_count'] }} total
                            </span>
                        </div>
                        <div class="text-xl font-black font-mono mt-1">
                            ${{ number_format($totales['total_general'], 2, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. ARQUEO DE EFECTIVO FÍSICO Y FALLO DE CAJA -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/80 pb-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                        <span>💰</span>
                        <span>Arqueo de Efectivo Físico & Fallo de Caja</span>
                    </span>
                    <span class="text-[11px] text-slate-500 font-mono">
                        Apertura: ${{ number_format($caja->monto_inicial_efectivo, 2, ',', '.') }} + Ventas Ef.: ${{ number_format($totales['efectivo_total'], 2, ',', '.') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Detalle Teórico -->
                    <div class="space-y-2 p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                            <span>Fondo inicial de apertura:</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white">
                                +${{ number_format($caja->monto_inicial_efectivo, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                            <span>Ventas cobradas en efectivo:</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white">
                                +${{ number_format($totales['efectivo_total'], 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-emerald-700 dark:text-emerald-400 font-bold">
                            <span>Ingresos justificados de efectivo:</span>
                            <span class="font-mono">
                                +${{ number_format($totales['total_ingresos'] ?? 0, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-rose-700 dark:text-rose-400 font-bold">
                            <span>Retiros justificados de efectivo:</span>
                            <span class="font-mono">
                                -${{ number_format($totales['total_retiros'] ?? 0, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-slate-200 dark:border-slate-700 font-black text-sm">
                            <span class="text-slate-900 dark:text-white">Efectivo Teórico Esperado:</span>
                            <span class="font-mono text-emerald-700 dark:text-emerald-400">
                                ${{ number_format($totales['efectivo_esperado'], 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Input Efectivo Real Contado -->
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase text-slate-800 dark:text-slate-200">
                            Efectivo Físico Contado en Cajón ($ ARS):
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 font-bold text-base">$</span>
                            <input type="number" step="0.01" min="0" required
                                   name="monto_final_efectivo_real"
                                   x-model.number="realContado"
                                   class="w-full pl-9 pr-4 py-2.5 rounded-xl font-mono font-black text-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-right focus:ring-2 focus:ring-indigo-500 shadow-inner"
                                   placeholder="0.00">
                        </div>
                        <p class="text-[10px] text-slate-500">
                            Ingrese el dinero en billetes y monedas contado al momento del cierre.
                        </p>
                    </div>
                </div>

                <!-- Tarjeta Fallo de Caja en Tiempo Real -->
                <div class="p-3.5 rounded-xl border flex items-center justify-between text-xs transition"
                     :class="{
                         'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200': diferencia() === 0,
                         'bg-sky-50 dark:bg-sky-950/40 border-sky-300 dark:border-sky-800 text-sky-900 dark:text-sky-200': diferencia() > 0,
                         'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-200': diferencia() < 0
                     }">
                    <div>
                        <div class="font-black text-sm flex items-center gap-1.5 uppercase">
                            <span x-show="diferencia() === 0">✓ Arqueo Exacto (Sin Diferencias)</span>
                            <span x-show="diferencia() > 0">▲ Sobrante de Caja</span>
                            <span x-show="diferencia() < 0">▼ Fallo de Caja (Faltante)</span>
                        </div>
                        <span class="text-[11px] opacity-80" x-show="diferencia() === 0">
                            El dinero contado coincide con el esperado por el sistema.
                        </span>
                        <span class="text-[11px] opacity-80" x-show="diferencia() > 0">
                            Hay más efectivo físico que el registrado por las ventas.
                        </span>
                        <span class="text-[11px] opacity-80" x-show="diferencia() < 0">
                            Falta efectivo en el cajón respecto al esperado por el sistema.
                        </span>
                    </div>

                    <div class="text-right">
                        <span class="text-xs uppercase font-bold block opacity-75">Diferencia:</span>
                        <span class="font-mono font-black text-xl" x-text="(diferencia() > 0 ? '+$ ' : (diferencia() < 0 ? '-$ ' : '$ ')) + formatDinero(Math.abs(diferencia()))"></span>
                    </div>
                </div>

                @if($caja->movimientos->isNotEmpty())
                <!-- TABLA DE MOVIMIENTOS REGISTRADOS EN ESTA CAJA -->
                <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                        <span>📋</span>
                        <span>Detalle de Movimientos Justificados en este Turno ({{ $caja->movimientos->count() }})</span>
                    </span>

                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-50 dark:bg-slate-900/80 text-slate-600 dark:text-slate-400 uppercase text-[10px] font-black border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-2 px-3">Hora</th>
                                    <th class="py-2 px-3">Tipo</th>
                                    <th class="py-2 px-3">Motivo / Justificación</th>
                                    <th class="py-2 px-3">Comprobante</th>
                                    <th class="py-2 px-3">Operador</th>
                                    <th class="py-2 px-3 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 bg-white dark:bg-slate-900/40">
                                @foreach($caja->movimientos as $mov)
                                    <tr>
                                        <td class="py-2 px-3 font-mono text-slate-500">{{ $mov->created_at->format('H:i') }} hs</td>
                                        <td class="py-2 px-3">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase {{ $mov->tipo === 'ingreso' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300' }}">
                                                {{ $mov->tipo === 'ingreso' ? '+ Ingreso' : '- Retiro' }}
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 font-bold text-slate-800 dark:text-slate-200">{{ $mov->motivo }}</td>
                                        <td class="py-2 px-3 text-slate-500 font-mono">{{ $mov->comprobante ?? '-' }}</td>
                                        <td class="py-2 px-3 text-slate-600 dark:text-slate-400">{{ $mov->usuario?->name ?? 'Usuario' }}</td>
                                        <td class="py-2 px-3 text-right font-mono font-black {{ $mov->tipo === 'ingreso' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                            {{ ($mov->tipo === 'ingreso' ? '+$ ' : '-$ ') . number_format($mov->monto, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <!-- Observaciones -->
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-600 dark:text-slate-400 mb-1">
                        Observaciones / Justificación de Cierre (Opcional):
                    </label>
                    <textarea name="observaciones" rows="2"
                              class="w-full text-xs py-2 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                              placeholder="Ej: Cierre de jornada sin novedades, cambio retirado..."></textarea>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('cajas.index') }}" 
                   class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs py-2.5 px-5 rounded-xl transition uppercase">
                    Cancelar
                </a>
                <button type="submit" 
                        class="bg-slate-900 hover:bg-black dark:bg-white dark:hover:bg-slate-100 text-white dark:text-slate-900 font-black text-xs py-2.5 px-6 rounded-xl shadow-lg transition uppercase flex items-center gap-2 cursor-pointer">
                    <span>🔒 Confirmar Cierre Z de Caja</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
