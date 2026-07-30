<x-app-layout>
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-blue-950 uppercase tracking-tighter">Localidades</h1>
            <p class="text-slate-500 font-medium italic text-sm">Base de datos de ciudades y códigos postales</p>
        </div>
        
        <button class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-3 rounded-2xl font-bold shadow-lg transition-all text-sm uppercase">
            + Agregar Localidad
        </button>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 text-xs font-black text-slate-500 uppercase italic">CP</th>
                    <th class="px-4 py-2 text-xs font-black text-slate-500 uppercase italic">Ciudad</th>
                    <th class="px-4 py-2 text-xs font-black text-slate-500 uppercase italic">Provincia</th>
                    <th class="px-4 py-2 text-xs font-black text-slate-500 uppercase italic text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($localidades as $localidad)
                <tr class="hover:bg-blue-50/30 transition-colors text-sm">
                    <td class="px-4 py-2 font-mono font-bold text-blue-700 tracking-tighter italic">
                        {{ $localidad->codigo_postal ?? '---' }}
                    </td>
                    <td class="px-4 py-2 font-bold text-slate-700 uppercase">{{ $localidad->nombre }}</td>
                    <td class="px-4 py-2">
                        <span class="text-slate-500 font-semibold uppercase text-xs">
                            {{ $localidad->provincia->nombre }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-center">
                        <button class="text-slate-400 hover:text-blue-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="px-4 py-2 bg-slate-50 border-t border-slate-100">
            {{ $localidades->links() }}
        </div>
    </div>
</x-app-layout>