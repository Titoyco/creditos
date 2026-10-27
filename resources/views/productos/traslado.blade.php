<x-app-layout>
    {{-- Script del Componente Alpine definido ANTES del DOM para garantizar inicialización inmediata --}}
    <script>
        const TRASLADO_CONFIG = {
            sucursalOrigenId: @json((int)$sucursalActivaId),
            sucursalDestinoId: @json(old('sucursal_destino_id', '')),
            comprobante: @json(old('comprobante', $comprobanteSugerido)),
            motivo: @json(old('motivo', 'Traslado inter-sucursal de mercadería')),
            buscarUrl: @json(route('productos.buscar.traslado')),
            verificarStockUrl: @json(route('productos.verificar.stock.traslado')),
            csrfToken: @json(csrf_token()),
            initialItems: @json(old('items', $productoSeleccionado ? [$productoSeleccionado] : []))
        };

        function trasladoLoteComponent() {
            return {
                sucursalOrigenId: TRASLADO_CONFIG.sucursalOrigenId,
                sucursalDestinoId: TRASLADO_CONFIG.sucursalDestinoId,
                comprobante: TRASLADO_CONFIG.comprobante,
                motivo: TRASLADO_CONFIG.motivo,

                scanQuery: '',
                isLoading: false,
                errorMessage: '',
                searchResults: [],
                showResultsDropdown: false,

                modalCantidad: false,
                itemPendiente: null,
                cantidadAEnviar: 1,
                errorModal: '',

                items: (TRASLADO_CONFIG.initialItems || []).map(it => ({
                    ...it,
                    cantidad: parseFloat(it.cantidad) || 1,
                    stock_origen: parseFloat(it.stock_origen) || 0
                })),

                init() {
                    this.focoScanner();
                },

                focoScanner() {
                    this.$nextTick(() => {
                        if (this.$refs.inputScanner) {
                            this.$refs.inputScanner.focus();
                        }
                    });
                },

                // Búsqueda en vivo mientras se escribe (sugerencias)
                async onInputSearch() {
                    const query = this.scanQuery.trim();
                    if (query.length < 2) {
                        this.searchResults = [];
                        this.showResultsDropdown = false;
                        return;
                    }
                    await this.ejecutarBusqueda(query, false);
                },

                // Búsqueda al presionar Enter (pistola barcode o búsqueda forzada)
                async buscarYProcesar() {
                    const query = this.scanQuery.trim();
                    if (!query) return;
                    await this.ejecutarBusqueda(query, true);
                },

                async ejecutarBusqueda(query, esEnter) {
                    this.isLoading = true;
                    this.errorMessage = '';

                    try {
                        const url = `${TRASLADO_CONFIG.buscarUrl}?q=${encodeURIComponent(query)}&sucursal_id=${this.sucursalOrigenId}`;
                        const res = await fetch(url);
                        const data = await res.json();
                        this.isLoading = false;

                        if (!data || data.length === 0) {
                            this.searchResults = [];
                            this.showResultsDropdown = false;
                            if (esEnter) {
                                this.errorMessage = `No se encontró ningún artículo activo con "${query}".`;
                                this.reproducirAlerta();
                                this.focoScanner();
                            }
                            return;
                        }

                        // Si se presionó Enter y es coincidencia exacta de código de barras o código único:
                        if (esEnter) {
                            const exacto = data.find(p => p.exact_match);
                            if (exacto) {
                                this.scanQuery = '';
                                this.showResultsDropdown = false;
                                this.abrirModalCantidad(exacto);
                                return;
                            } else if (data.length === 1) {
                                this.scanQuery = '';
                                this.showResultsDropdown = false;
                                this.abrirModalCantidad(data[0]);
                                return;
                            }
                        }

                        // Mostrar dropdown de sugerencias interactivas
                        this.searchResults = data;
                        this.showResultsDropdown = true;
                    } catch (e) {
                        this.isLoading = false;
                        this.errorMessage = 'Error de comunicación al consultar el catálogo.';
                    }
                },

                seleccionarResultado(prod) {
                    this.showResultsDropdown = false;
                    this.scanQuery = '';
                    this.abrirModalCantidad(prod);
                },

                abrirModalCantidad(prod) {
                    if (prod.stock_origen <= 0) {
                        this.errorMessage = `El artículo [${prod.codigo}] ${prod.descripcion} no posee stock en la sucursal emisora seleccionada (Stock: 0).`;
                        this.reproducirAlerta();
                        this.focoScanner();
                        return;
                    }

                    const itemExistente = this.items.find(i => i.id === prod.id);
                    const yaAsignado = itemExistente ? itemExistente.cantidad : 0;
                    const disponibleRestante = prod.stock_origen - yaAsignado;

                    if (disponibleRestante <= 0) {
                        this.errorMessage = `Ya se cargó todo el stock disponible (${prod.stock_origen} un.) de "${prod.descripcion}" en este lote.`;
                        this.reproducirAlerta();
                        this.focoScanner();
                        return;
                    }

                    this.itemPendiente = prod;
                    this.cantidadAEnviar = 1;
                    this.errorModal = '';
                    this.modalCantidad = true;

                    this.$nextTick(() => {
                        if (this.$refs.inputCantidadModal) {
                            this.$refs.inputCantidadModal.focus();
                            this.$refs.inputCantidadModal.select();
                        }
                    });
                },

                confirmarAgregarAlLote() {
                    if (!this.itemPendiente) return;
                    const cant = parseFloat(this.cantidadAEnviar);

                    if (isNaN(cant) || cant <= 0) {
                        this.errorModal = 'Por favor indique una cantidad mayor a cero.';
                        return;
                    }

                    const itemExistente = this.items.find(i => i.id === this.itemPendiente.id);
                    const yaAsignado = itemExistente ? itemExistente.cantidad : 0;
                    const nuevoTotal = yaAsignado + cant;

                    if (nuevoTotal > this.itemPendiente.stock_origen) {
                        this.errorModal = `La cantidad supera las existencias en origen (Disponible: ${this.itemPendiente.stock_origen}, ya en lote: ${yaAsignado}).`;
                        return;
                    }

                    if (itemExistente) {
                        itemExistente.cantidad = nuevoTotal;
                    } else {
                        this.items.unshift({
                            id: this.itemPendiente.id,
                            codigo: this.itemPendiente.codigo,
                            codigo_barra: this.itemPendiente.codigo_barra,
                            descripcion: this.itemPendiente.descripcion,
                            categoria: this.itemPendiente.categoria,
                            subcategoria: this.itemPendiente.subcategoria,
                            unidad_medida: this.itemPendiente.unidad_medida,
                            stock_origen: this.itemPendiente.stock_origen,
                            cantidad: cant
                        });
                    }

                    this.modalCantidad = false;
                    this.itemPendiente = null;
                    this.focoScanner();
                },

                quitarItem(index) {
                    this.items.splice(index, 1);
                    this.focoScanner();
                },

                modificarCantidad(index, delta) {
                    const item = this.items[index];
                    const nueva = (parseFloat(item.cantidad) || 0) + delta;
                    if (nueva <= 0) {
                        this.quitarItem(index);
                    } else if (nueva > item.stock_origen) {
                        alert(`No es posible superar el stock disponible en origen (${item.stock_origen} un.)`);
                    } else {
                        item.cantidad = nueva;
                    }
                },

                actualizarCantidadManual(index, valor) {
                    const item = this.items[index];
                    const val = parseFloat(valor);
                    if (isNaN(val) || val <= 0) {
                        item.cantidad = 1;
                    } else if (val > item.stock_origen) {
                        alert(`La cantidad no puede superar el stock disponible en origen (${item.stock_origen} un.)`);
                        item.cantidad = item.stock_origen;
                    } else {
                        item.cantidad = val;
                    }
                },

                async cambioSucursalOrigen() {
                    // Si el destino seleccionado coincide con el nuevo origen, reiniciar destino
                    if (this.sucursalDestinoId == this.sucursalOrigenId) {
                        this.sucursalDestinoId = '';
                    }

                    if (this.items.length === 0) return;
                    const ids = this.items.map(i => i.id);
                    try {
                        const res = await fetch(TRASLADO_CONFIG.verificarStockUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': TRASLADO_CONFIG.csrfToken
                            },
                            body: JSON.stringify({ sucursal_id: this.sucursalOrigenId, producto_ids: ids })
                        });
                        const data = await res.json();
                        this.items.forEach(it => {
                            it.stock_origen = parseFloat(data[it.id]) || 0;
                        });
                    } catch (e) {}
                },

                totalArticulos() {
                    return this.items.length;
                },

                totalUnidades() {
                    return this.items.reduce((acc, it) => acc + (parseFloat(it.cantidad) || 0), 0);
                },

                hayStockInvalido() {
                    return this.items.some(it => it.cantidad > it.stock_origen || it.stock_origen <= 0);
                },

                puedeConfirmar() {
                    return this.items.length > 0 && 
                           this.sucursalDestinoId && 
                           this.sucursalDestinoId != this.sucursalOrigenId && 
                           !this.hayStockInvalido();
                },

                confirmarEnvio(e) {
                    if (!this.puedeConfirmar()) {
                        e.preventDefault();
                        return false;
                    }
                    const dest = this.$refs.selectDestino.options[this.$refs.selectDestino.selectedIndex]?.text || 'Sucursal Destino';
                    const msg = `¿Confirma el traslado de ${this.totalArticulos()} artículo(s) (${this.totalUnidades()} unidades) hacia "${dest}"?`;
                    if (!confirm(msg)) {
                        e.preventDefault();
                        return false;
                    }
                },

                reproducirAlerta() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        osc.type = 'sawtooth';
                        osc.frequency.setValueAtTime(220, ctx.currentTime);
                        osc.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.15);
                    } catch (e) {}
                }
            };
        }
    </script>

    <div class="max-w-7xl mx-auto py-2 space-y-5" x-data="trasladoLoteComponent()">

        {{-- Encabezado Principal --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </span>
                    <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">
                        Carga y Traslado de Mercadería por Lote
                    </h1>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Escanee códigos de barras de los artículos con el lector para armar la caja o lote; al finalizar confirme para mover todo el stock a la nueva sucursal.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('stock.movimientos') }}" 
                   class="px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl shadow-2xs transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>Historial de Movimientos</span>
                </a>
                <a href="{{ route('productos.index') }}" 
                   class="px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl shadow-2xs transition-all">
                    &larr; Catálogo
                </a>
            </div>
        </div>

        {{-- Alertas Globales --}}
        @if(session('success'))
            <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2 shadow-2xs">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-xs font-semibold flex items-center gap-2 shadow-2xs">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- FORMULARIO PRINCIPAL --}}
        <form action="{{ route('productos.traslado.store') }}" method="POST" @submit="confirmarEnvio($event)" class="space-y-5">
            @csrf

            {{-- 1. BLOQUE DE ENCABEZADO DEL ENVÍO / REMITO --}}
            <div class="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 flex items-center justify-center text-[11px] font-bold">1</span>
                        Datos del Envío Inter-Sucursal
                    </span>
                    <span class="text-[11px] text-slate-400 font-mono">
                        Configuración de sede emisora y receptora
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    {{-- Sucursal Origen --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Sucursal Origen (Emisora) <span class="text-rose-500">*</span>
                        </label>
                        <select name="sucursal_origen_id" x-model="sucursalOrigenId" @change="cambioSucursalOrigen()" required
                                class="w-full text-xs py-2 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold focus:ring-blue-500 focus:border-blue-500">
                            @foreach($sucursales as $suc)
                                <option value="{{ $suc->id }}">
                                    {{ $suc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Sucursal Destino --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Sucursal Destino (Receptora) <span class="text-rose-500">*</span>
                        </label>
                        <select name="sucursal_destino_id" x-model="sucursalDestinoId" x-ref="selectDestino" required
                                class="w-full text-xs py-2 px-3 rounded-xl border-amber-300 dark:border-amber-600/80 bg-amber-50/30 dark:bg-slate-900 text-slate-900 dark:text-white font-bold focus:ring-amber-500 focus:border-amber-500">
                            <option value="">-- Seleccionar Sucursal Destino --</option>
                            @foreach($sucursales as $suc)
                                <option value="{{ $suc->id }}" :disabled="sucursalOrigenId == {{ $suc->id }}">
                                    {{ $suc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Nro. de Comprobante / Remito --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Nro. de Remito / Guía
                        </label>
                        <input type="text" name="comprobante" x-model="comprobante" required
                               placeholder="Ej: REM-20261017-001"
                               class="w-full text-xs py-2 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-mono font-bold">
                    </div>

                    {{-- Motivo / Observaciones --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Motivo / Observaciones del Lote
                        </label>
                        <input type="text" name="motivo" x-model="motivo"
                               placeholder="Ej: Reposición de stock semanal, Caja N° 1..."
                               class="w-full text-xs py-2 px-3 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                    </div>
                </div>
            </div>

            {{-- 2. BARRA DE ESCANEO RÁPIDO Y BUSCADOR INTELIGENTE --}}
            <div class="bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl p-5 shadow-md text-white relative">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-400 text-blue-950 flex items-center justify-center text-[11px] font-black">2</span>
                        <span class="text-xs font-black uppercase tracking-wider text-blue-100 flex items-center gap-2">
                            Punto de Escaneo de Artículos
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-800 text-blue-200 border border-blue-700">
                                Lector Barcode Activo
                            </span>
                        </span>
                    </div>

                    <div class="text-[11px] text-blue-200 flex items-center gap-3">
                        <span>Escriba para ver sugerencias o escanee con la pistola y presione <kbd class="px-1.5 py-0.5 rounded bg-blue-950 border border-blue-700 font-mono text-[10px]">Enter</kbd>.</span>
                        <button type="button" @click="focoScanner()" class="underline hover:text-white text-xs font-bold">
                            Reenfocar Lector
                        </button>
                    </div>
                </div>

                {{-- Campo de entrada con icono de pistola de código de barras y buscador --}}
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>

                    <input type="text" 
                           x-ref="inputScanner"
                           x-model="scanQuery"
                           @input.debounce.250ms="onInputSearch()"
                           @keydown.enter.prevent="buscarYProcesar()"
                           @keydown.escape="showResultsDropdown = false; scanQuery = ''"
                           placeholder="Escriba código, descripción o pase el lector de código de barras..."
                           class="w-full text-sm py-3 pl-11 pr-28 rounded-xl bg-white/10 dark:bg-black/30 border border-blue-400/40 focus:border-amber-400 focus:bg-white focus:text-slate-900 dark:focus:bg-slate-900 dark:focus:text-white placeholder:text-blue-200/70 transition-all font-mono font-medium shadow-inner">

                    <div class="absolute inset-y-0 right-1.5 flex items-center gap-1.5">
                        <span x-show="isLoading" class="text-xs text-amber-300 animate-pulse font-bold px-2">Buscando...</span>
                        <button type="button" 
                                @click="buscarYProcesar()"
                                class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs px-3 py-1.5 rounded-lg shadow-sm transition-all flex items-center gap-1">
                            <span>BUSCAR</span>
                            <span class="text-[10px]">&crarr;</span>
                        </button>
                    </div>

                    {{-- Dropdown de sugerencias interactivas cuando hay resultados --}}
                    <div x-show="showResultsDropdown" 
                         @click.outside="showResultsDropdown = false"
                         style="display: none;"
                         class="absolute z-50 left-0 right-0 mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl overflow-hidden max-h-72 overflow-y-auto text-slate-900 dark:text-white">
                        <div class="p-2 bg-slate-50 dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between text-xs font-bold text-slate-500">
                            <span>Sugerencias de artículos (<span x-text="searchResults.length"></span>):</span>
                            <span class="text-[10px] text-blue-600 dark:text-blue-400">Haga clic en un artículo para indicar la cantidad</span>
                        </div>
                        <template x-for="item in searchResults" :key="item.id">
                            <button type="button" 
                                    @click="seleccionarResultado(item)"
                                    class="w-full text-left px-3.5 py-2.5 hover:bg-blue-50 dark:hover:bg-slate-800/80 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between transition-colors">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-xs text-blue-600 dark:text-blue-400" x-text="item.codigo"></span>
                                        <span class="text-xs font-bold uppercase" x-text="item.descripcion"></span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-2">
                                        <span x-show="item.codigo_barra" class="font-mono">Barras: <span x-text="item.codigo_barra"></span></span>
                                        <span class="px-1 py-0.2 bg-slate-100 dark:bg-slate-800 rounded text-slate-500" x-text="item.categoria"></span>
                                        <span x-show="item.subcategoria" class="px-1 py-0.2 bg-indigo-50 dark:bg-indigo-950 text-indigo-500 rounded" x-text="item.subcategoria"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block text-xs font-bold font-mono px-2 py-0.5 rounded-full"
                                          :class="item.stock_origen > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'"
                                          x-text="item.stock_origen + ' en origen'"></span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Aviso de error en escaneo --}}
                <div x-show="errorMessage" style="display: none;" 
                     class="mt-2 text-xs text-rose-200 bg-rose-950/80 border border-rose-700/80 px-3 py-1.5 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-text="errorMessage"></span>
                    </div>
                    <button type="button" @click="errorMessage = ''" class="text-rose-300 hover:text-white">&times;</button>
                </div>
            </div>

            {{-- 3. LISTADO DEL LOTE / CAJA DE ENVÍO (TABLA EN TIEMPO REAL) --}}
            <div class="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-2xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 flex items-center justify-center text-[11px] font-bold">3</span>
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                            Artículos en el Lote a Trasladar
                        </h2>
                    </div>

                    <div class="flex items-center gap-4 text-xs font-mono font-bold">
                        <span class="text-slate-500 dark:text-slate-400">
                            Artículos distintos: <strong class="text-slate-900 dark:text-white" x-text="totalArticulos()"></strong>
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-900 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                            Total Unidades a Mover: <strong class="text-sm" x-text="totalUnidades()"></strong>
                        </span>
                    </div>
                </div>

                {{-- Tabla de Artículos --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700 text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-50 dark:bg-slate-800/50">
                                <th class="py-2.5 px-4 w-12 text-center">#</th>
                                <th class="py-2.5 px-4">Código / Barras</th>
                                <th class="py-2.5 px-4">Descripción & Rubro</th>
                                <th class="py-2.5 px-4 text-right">Stock en Origen</th>
                                <th class="py-2.5 px-4 text-center w-48">Cantidad a Mover</th>
                                <th class="py-2.5 px-4 text-right">Remanente Origen</th>
                                <th class="py-2.5 px-4 text-center w-16">Quitar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                            <template x-for="(item, index) in items" :key="item.id">
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors"
                                    :class="item.cantidad > item.stock_origen ? 'bg-rose-50/50 dark:bg-rose-950/30' : ''">
                                    {{-- Nro --}}
                                    <td class="py-3 px-4 text-center font-mono text-slate-400 text-[11px]" x-text="index + 1"></td>

                                    {{-- Código y Barras --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-mono font-bold text-slate-900 dark:text-slate-100" x-text="item.codigo"></div>
                                        <div class="text-[10px] font-mono text-slate-400" x-text="item.codigo_barra || 'Sin barras'"></div>
                                    </td>

                                    {{-- Descripción y Clasificación --}}
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white uppercase tracking-tight" x-text="item.descripcion"></div>
                                        <div class="flex items-center gap-1.5 text-[10px] text-slate-400 mt-0.5">
                                            <span class="px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-700 font-semibold" x-text="item.categoria"></span>
                                            <span x-show="item.subcategoria" class="px-1.5 py-0.2 rounded bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 font-semibold" x-text="item.subcategoria"></span>
                                        </div>
                                    </td>

                                    {{-- Stock actual en origen --}}
                                    <td class="py-3 px-4 text-right whitespace-nowrap font-mono">
                                        <span class="font-bold" x-text="item.stock_origen"></span>
                                        <span class="text-[10px] text-slate-400" x-text="item.unidad_medida"></span>
                                    </td>

                                    {{-- Controles de Cantidad (+/- y número editable) --}}
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center border border-slate-300 dark:border-slate-600 rounded-xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                                            <button type="button" 
                                                    @click="modificarCantidad(index, -1)"
                                                    class="px-2.5 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition font-bold text-sm select-none">
                                                &minus;
                                            </button>
                                            <input type="number" 
                                                   step="1" 
                                                   min="1" 
                                                   :max="item.stock_origen"
                                                   :value="item.cantidad"
                                                   @change="actualizarCantidadManual(index, $event.target.value)"
                                                   class="w-16 py-1 px-1 text-center font-mono font-black text-xs border-0 bg-transparent text-slate-900 dark:text-white focus:ring-0">
                                            <button type="button" 
                                                    @click="modificarCantidad(index, 1)"
                                                    class="px-2.5 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition font-bold text-sm select-none">
                                                &plus;
                                            </button>
                                        </div>
                                    </td>

                                    {{-- Remanente estimado en origen --}}
                                    <td class="py-3 px-4 text-right whitespace-nowrap font-mono font-bold">
                                        <span :class="(item.stock_origen - item.cantidad) < 0 ? 'text-rose-500 font-black' : 'text-slate-600 dark:text-slate-300'"
                                              x-text="(item.stock_origen - item.cantidad)"></span>
                                        <span class="text-[10px] text-slate-400">un.</span>
                                    </td>

                                    {{-- Botón Quitar --}}
                                    <td class="py-3 px-4 text-center">
                                        <button type="button" 
                                                @click="quitarItem(index)"
                                                class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition" 
                                                title="Quitar del lote">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            {{-- Estado Vacío --}}
                            <tr x-show="items.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-400 dark:text-slate-500">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        <p class="font-bold text-xs uppercase tracking-wide">El lote de traslado está vacío</p>
                                        <p class="text-[11px] max-w-sm text-slate-400">
                                            Escriba o use el lector de código de barras arriba para agregar los artículos que colocará en la caja de envío.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Inputs ocultos para enviar el array de items en el formulario HTTP POST --}}
                <template x-for="(it, idx) in items" :key="it.id">
                    <div>
                        <input type="hidden" :name="'items[' + idx + '][producto_id]'" :value="it.id">
                        <input type="hidden" :name="'items[' + idx + '][cantidad]'" :value="it.cantidad">
                    </div>
                </template>
            </div>

            {{-- 4. PIE DE ACCIONES Y CONFIRMACIÓN --}}
            <div class="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    <p class="font-bold text-slate-700 dark:text-slate-200">Resumen de la Operación:</p>
                    <p class="mt-0.5">
                        Al confirmar, se descontarán las unidades en la sede emisora y se acreditarán automáticamente en la sede receptora con auditoría.
                    </p>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                    <a href="{{ route('productos.index') }}" 
                       class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white bg-slate-100 dark:bg-slate-800 rounded-xl transition-all">
                        Cancelar
                    </a>

                    <button type="submit" 
                            :disabled="!puedeConfirmar()"
                            :class="puedeConfirmar() ? 'bg-amber-600 hover:bg-amber-700 text-white shadow-lg cursor-pointer' : 'bg-slate-200 dark:bg-slate-700 text-slate-400 cursor-not-allowed'"
                            class="px-6 py-2.5 text-xs font-black rounded-xl transition-all flex items-center space-x-2 uppercase tracking-wide">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>CONFIRMAR Y MOVER LOTE (<span x-text="totalUnidades()"></span> UN.)</span>
                    </button>
                </div>
            </div>

        </form>

        {{-- MODAL RÁPIDO: PREGUNTAR CANTIDAD AL ESCANEAR O SELECCIONAR --}}
        <div x-show="modalCantidad" style="display: none;" 
             class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
             @keydown.escape.window="modalCantidad = false; focoScanner()">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700"
                 @click.outside="modalCantidad = false; focoScanner()">
                
                {{-- Encabezado Modal --}}
                <div class="flex items-start justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">
                            Artículo Seleccionado
                        </span>
                        <h3 class="text-sm font-black uppercase text-slate-900 dark:text-white tracking-tight mt-0.5"
                            x-text="itemPendiente ? itemPendiente.descripcion : ''"></h3>
                    </div>
                    <button type="button" @click="modalCantidad = false; focoScanner()" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
                </div>

                {{-- Detalles del Producto --}}
                <div class="py-3 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400">Código / SKU:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200" x-text="itemPendiente ? itemPendiente.codigo : ''"></span>
                    </div>
                    <div class="flex items-center justify-between text-xs" x-show="itemPendiente && itemPendiente.codigo_barra">
                        <span class="text-slate-400">Código de Barras:</span>
                        <span class="font-mono text-slate-700 dark:text-slate-300" x-text="itemPendiente ? itemPendiente.codigo_barra : ''"></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400">Stock disponible en Origen:</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm"
                              x-text="itemPendiente ? (itemPendiente.stock_origen + ' ' + itemPendiente.unidad_medida) : ''"></span>
                    </div>

                    {{-- Input de Cantidad --}}
                    <div class="pt-3">
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            ¿Cuántas unidades desea agregar al envío? <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   x-ref="inputCantidadModal"
                                   x-model="cantidadAEnviar"
                                   step="1" 
                                   min="1"
                                   :max="itemPendiente ? itemPendiente.stock_origen : 9999"
                                   @keydown.enter.prevent="confirmarAgregarAlLote()"
                                   class="w-full text-lg py-2.5 px-4 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-mono font-black text-center focus:ring-2 focus:ring-amber-500">
                        </div>

                        {{-- Atajos rápidos de cantidad --}}
                        <div class="flex items-center justify-center gap-2 mt-2.5">
                            <button type="button" @click="cantidadAEnviar = 1" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200">1 un.</button>
                            <button type="button" @click="cantidadAEnviar = 5" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200">+5 un.</button>
                            <button type="button" @click="cantidadAEnviar = 10" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200">+10 un.</button>
                            <button type="button" @click="cantidadAEnviar = itemPendiente ? itemPendiente.stock_origen : 1" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-900 dark:text-amber-300 hover:bg-amber-200">Todo el stock</button>
                        </div>

                        {{-- Error Modal --}}
                        <p x-show="errorModal" class="text-rose-500 text-xs font-bold mt-2 text-center" x-text="errorModal"></p>
                    </div>
                </div>

                {{-- Botones de Acción Modal --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
                    <button type="button" 
                            @click="modalCantidad = false; focoScanner()" 
                            class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700">
                        Cancelar (Esc)
                    </button>
                    <button type="button" 
                            @click="confirmarAgregarAlLote()" 
                            class="px-5 py-2 text-xs font-black text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-md transition-all flex items-center gap-1.5 uppercase">
                        <span>Agregar al Lote</span>
                        <span class="text-[10px] font-mono opacity-80">(Enter)</span>
                    </button>
                </div>

            </div>
        </div>

    </div>
</x-app-layout>
