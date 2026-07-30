<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'LaMarina Créditos') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="min-h-screen">
        <nav class="bg-blue-800 border-b border-blue-900 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-4 lg:px-8">
                <div class="flex justify-between h-16 items-center relative"> 
                    <div class="inline-flex items-center bg-white rounded-2xl hover:bg-slate-50 transition-all px-4 py-2 shadow-sm border border-slate-100">
                        <a href="{{ url('/') }}" class="flex items-center text-blue-950 hover:text-blue-700 font-black text-xl tracking-tight transition-colors">
                            <img src="{{ asset('img/logo.png') }}" alt="Logo" class="h-9 w-auto mr-3 object-contain"> 
                        </a>
                    </div>

    

                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <span class="text-blue-200 text-lg font-black italic tracking-widest uppercase">
                            SISTEMA DE CRÉDITOS
                        </span>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ms-6 text-white text-right z-10">
                        <div class="mr-4 border-r border-blue-700 pr-4">
                            <p class="text-[10px] uppercase text-blue-300 font-black leading-none mb-1 tracking-tighter">Usuario</p>
                            <p class="text-sm font-bold italic">{{ Auth::user()->name }}</p>
                        </div>



                        <div class="mr-4 border-r border-blue-700 pr-4">
                            <p class="text-[10px] uppercase text-blue-300 font-black leading-none mb-1 tracking-tighter">Sucursal Activa</p>

                            <form action="{{ route('sucursal.update.global') }}" method="POST" id="formCambioSucursal" autocomplete="off">
                                @csrf
                                <select name="id_sucursal" onchange="this.form.submit()" 
                                    class="bg-blue-800 text-white text-sm font-bold border-none p-0 focus:ring-0 cursor-pointer hover:text-blue-200 transition-colors">
                                    
                                    @foreach(\App\Models\Sucursal::all() as $suc)
                                            <option value="{{ $suc->id }}" 
                                                {{ (int)config('app.sucursal_activa.id') === (int)$suc->id ? 'selected' : '' }}>
                                                {{ $suc->nombre }}
                                            </option>
                                        @endforeach
                                </select>
                            </form>
                        </div>
                        
                        <form method="POST" action="{{ route('logout') }}" class="ml-6">
                            @csrf
                            <button type="submit" class="text-blue-200 hover:text-white transition p-2 hover:bg-blue-800 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-2 max-w-[90%] mx-auto px-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            {{ $slot }}
            </div>
        </main>
    </div>

    <x-notification-modal />

</body>
</html>