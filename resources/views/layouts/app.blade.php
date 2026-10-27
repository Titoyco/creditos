<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'LaMarina Créditos') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/favicon.ico') }}">
    
    {{-- Prevención de FOUC para Modo Oscuro --}}
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Helper global para filas con Menú Contextual interactivo
        function contextMenuRow() {
            return {
                open: false,
                x: 0,
                y: 0,
                openMenu(e) {
                    if (e.target.closest('a') || e.target.closest('button') || e.target.closest('input') || e.target.closest('select')) {
                        return;
                    }
                    window.dispatchEvent(new CustomEvent('close-context-menus'));
                    const menuWidth = 240;
                    const menuHeight = 320;
                    const winW = window.innerWidth;
                    const winH = window.innerHeight;
                    this.x = (e.clientX + menuWidth > winW - 10) ? Math.max(10, winW - menuWidth - 10) : e.clientX;
                    this.y = (e.clientY + menuHeight > winH - 10) ? Math.max(10, e.clientY - menuHeight) : e.clientY;
                    this.open = true;
                },
                closeMenu() {
                    this.open = false;
                }
            };
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-sans antialiased transition-colors duration-200">
    <div class="min-h-screen">
        <nav class="bg-blue-800 dark:bg-slate-950 border-b border-blue-900 dark:border-slate-800 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-4 lg:px-8">
                <div class="flex justify-between h-16 items-center"> 
                    {{-- Logo y Nombre del Sistema --}}
                    <div class="flex items-center gap-3 shrink-0">
                        <a href="{{ url('/') }}" class="inline-flex items-center bg-white dark:bg-slate-800 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-700/80 transition-all px-3.5 py-1.5 shadow-sm border border-slate-100 dark:border-slate-700">
                            <img src="{{ asset('img/logo.png') }}" alt="Logo" class="h-8 w-auto object-contain"> 
                        </a>

                        <div class="hidden md:flex items-center">
                            <div class="h-6 w-px bg-blue-700/80 dark:bg-slate-700 mr-3"></div>
                            <div class="flex flex-col">
                                <span class="text-blue-100 dark:text-slate-200 text-xs sm:text-sm font-black italic tracking-widest uppercase leading-none drop-shadow-xs">
                                    SISTEMA DE CRÉDITOS
                                </span>
                                <span class="text-blue-300 dark:text-slate-400 text-[10px] font-semibold tracking-wider uppercase mt-0.5">
                                    Panel de Gestión
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Panel Operativo Derecho --}}
                    <div class="flex items-center text-white text-right z-10 shrink-0">
                        {{-- ⏱ Reloj Operativo --}}
                        <div class="hidden sm:flex flex-col items-end pr-3 border-r border-blue-700/60 dark:border-slate-800 leading-tight select-none mr-3"
                             x-data="{
                                 hora: '',
                                 fecha: '',
                                 actualizar() {
                                     const now = new Date();
                                     this.hora = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                                     const opcionesFecha = { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' };
                                     this.fecha = now.toLocaleDateString('es-AR', opcionesFecha).replace('.', '');
                                 },
                                 init() {
                                     this.actualizar();
                                     setInterval(() => this.actualizar(), 1000);
                                 }
                             }">
                            <span class="text-[10px] text-blue-200 dark:text-slate-400 font-bold uppercase tracking-wider font-mono" x-text="fecha">
                                {{ now()->locale('es')->isoFormat('ddd, DD/MM/YYYY') }}
                            </span>
                            <span class="text-xs font-mono font-bold tabular-nums text-white" x-text="hora">
                                {{ now()->format('H:i:s') }}
                            </span>
                        </div>

                        <div class="mr-4 border-r border-blue-700 dark:border-slate-800 pr-4">
                            <p class="text-[10px] uppercase text-blue-300 dark:text-slate-400 font-black leading-none mb-1 tracking-tighter">Usuario</p>
                            <p class="text-sm font-bold italic">{{ Auth::user()->name }}</p>
                        </div>

                        @if(Auth::user()->is_admin)
                        <div class="mr-4 border-r border-blue-700 dark:border-slate-800 pr-4">
                            <p class="text-[10px] uppercase text-amber-300 font-black leading-none mb-1 tracking-tighter flex items-center gap-1">
                                <svg class="w-3 h-3 text-amber-400 inline" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                Sucursal Activa (Admin)
                            </p>

                            <form action="{{ route('sucursal.update.global') }}" method="POST" id="formCambioSucursal" autocomplete="off">
                                @csrf
                                <select name="id_sucursal" onchange="this.form.submit()" 
                                    class="bg-blue-900/90 dark:bg-slate-800 hover:bg-blue-900 dark:hover:bg-slate-700 text-white text-xs font-black border border-blue-500 dark:border-slate-600 rounded-lg py-1 px-2.5 focus:ring-2 focus:ring-amber-400 cursor-pointer transition-all shadow-sm">
                                    @foreach(\App\Models\Sucursal::all() as $suc)
                                        <option value="{{ $suc->id }}" 
                                            {{ (int)config('app.sucursal_activa.id') === (int)$suc->id ? 'selected' : '' }}>
                                            {{ $suc->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                        @else
                        <div class="mr-4 border-r border-blue-700 dark:border-slate-800 pr-4">
                            <p class="text-[10px] uppercase text-blue-300 dark:text-slate-400 font-black leading-none mb-1 tracking-tighter">Sucursal Asignada</p>
                            <div class="flex items-center gap-1.5 text-xs font-black text-white bg-blue-900/70 dark:bg-slate-800 border border-blue-600 dark:border-slate-700 rounded-lg py-1 px-2.5 shadow-sm">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span>{{ config('app.sucursal_activa.nombre') ?? Auth::user()->sucursal?->nombre ?? 'Sin sucursal' }}</span>
                            </div>
                        </div>
                        @endif
                        
                        {{-- 🌓 Conmutador de Modo Claro / Oscuro --}}
                        <div x-data="{
                                isDark: document.documentElement.classList.contains('dark'),
                                toggle() {
                                    this.isDark = !this.isDark;
                                    if (this.isDark) {
                                        document.documentElement.classList.add('dark');
                                        localStorage.setItem('color-theme', 'dark');
                                    } else {
                                        document.documentElement.classList.remove('dark');
                                        localStorage.setItem('color-theme', 'light');
                                    }
                                }
                            }" 
                            class="flex items-center mr-2">
                            <button @click="toggle()" 
                                    type="button" 
                                    class="p-1.5 rounded-lg text-blue-200 dark:text-amber-300 hover:text-white hover:bg-blue-700/60 dark:hover:bg-slate-800 border border-blue-700/50 dark:border-slate-700 transition-all focus:outline-none"
                                    :title="isDark ? 'Cambiar a Modo Claro' : 'Cambiar a Modo Oscuro'">
                                {{-- Icono Sol (Modo Claro) --}}
                                <svg x-show="isDark" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9h-1m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                {{-- Icono Luna (Modo Oscuro) --}}
                                <svg x-show="!isDark" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('logout') }}" class="ml-2">
                            @csrf
                            <button type="submit" title="Cerrar Sesión" class="text-blue-200 hover:text-white transition p-2 hover:bg-blue-800 dark:hover:bg-slate-800 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-2 max-w-[92%] mx-auto px-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            {{ $slot }}
            </div>
        </main>
    </div>

    <x-notification-modal />

</body>
</html>