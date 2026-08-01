<x-app-layout>
    <div class="max-w-5xl mx-auto py-12">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-black text-blue-950 uppercase tracking-tighter">Panel Principal</h1>
            <p class="text-slate-500 font-bold italic">Seleccione un módulo para operar</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <a href="{{ route('clientes.index') }}" class="group bg-white p-8 rounded-3xl shadow-xl border border-slate-100 hover:bg-blue-600 transition-all duration-300 text-center">
                <div class="bg-blue-100 w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-blue-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-700 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-blue-950 group-hover:text-white uppercase tracking-tighter">Clientes</h2>
                <p class="text-slate-400 text-xs font-bold mt-2 group-hover:text-blue-100 uppercase">Gestión y Legajos</p>
            </a>

            <a href="{{ route('creditos.index') }}" class="group bg-white p-8 rounded-3xl shadow-xl border border-slate-100 hover:bg-blue-600 transition-all duration-300 text-center">
                <div class="bg-green-100 w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-green-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-700 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-blue-950 group-hover:text-white uppercase tracking-tighter">Créditos</h2>
                <p class="text-slate-400 text-xs font-bold mt-2 group-hover:text-blue-100 uppercase">Cobranzas y Planes</p>
            </a>

            <a href="{{ route('sistema.index') }}" class="group bg-white p-8 rounded-3xl shadow-xl border border-slate-100 hover:bg-blue-600 transition-all duration-300 text-center">
                <div class="bg-slate-100 w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-slate-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-700 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-blue-950 group-hover:text-white uppercase tracking-tighter">Sistema</h2>
                <p class="text-slate-400 text-xs font-bold mt-2 group-hover:text-blue-100 uppercase">Configuración y Lotes</p>
            </a>
        </div>
    </div>
</x-app-layout>