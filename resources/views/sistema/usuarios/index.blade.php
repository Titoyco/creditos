<x-app-layout>
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-blue-950 dark:text-white uppercase tracking-tighter">Operadores del Sistema</h1>
            <p class="text-slate-500 dark:text-slate-400 font-medium italic text-sm">Administración de acceso y sucursales asignadas</p>
        </div>
        
        <a href="{{ route('usuarios.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-2xl font-black shadow-lg transition-all flex items-center space-x-2 active:scale-95">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            <span>NUEVO OPERADOR</span>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($usuarios as $usuario)
        <div class="bg-white dark:bg-slate-800/90 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center space-x-4 mb-4">
                <div class="bg-slate-100 dark:bg-slate-700 p-3 rounded-2xl text-blue-700 dark:text-blue-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-black text-blue-950 dark:text-white uppercase leading-none">{{ $usuario->name }}</h3>
                    <p class="text-slate-400 dark:text-slate-500 text-xs font-bold mt-1 uppercase">{{ $usuario->email }}</p>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-slate-900/60 rounded-2xl p-4 mb-4 flex justify-between items-center border border-transparent dark:border-slate-700">
                <div>
                    <p class="text-[10px] font-black text-blue-400 dark:text-blue-300 uppercase leading-none mb-1">Sucursal</p>
                    <p class="text-blue-800 dark:text-blue-200 font-bold text-sm">{{ $usuario->sucursal->nombre ?? 'CENTRAL' }}</p>
                </div>
                <span class="bg-blue-600 text-white text-[10px] font-black px-2 py-1 rounded-lg">ACTIVO</span>
            </div>

            <div class="flex space-x-2">
                <a href="{{ route('usuarios.edit', $usuario) }}" class="flex-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-center py-2 rounded-xl font-bold text-xs uppercase transition-colors">
                    Editar Perfil
                </a>
                <button class="bg-slate-800 dark:bg-slate-700 hover:bg-red-600 text-white p-2 rounded-xl transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </button>
            </div>
        </div>
        @endforeach
    </div>
</x-app-layout>