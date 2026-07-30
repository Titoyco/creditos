<x-app-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-black text-blue-950 uppercase tracking-tighter">Provincias</h1>
        <p class="text-slate-500 font-medium italic text-sm">Configuración regional del sistema</p>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden max-w-2xl">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 text-xs font-black text-slate-500 uppercase italic">ID</th>
                    <th class="px-4 py-2 text-xs font-black text-slate-500 uppercase italic">Nombre</th>
                    <th class="px-4 py-2 text-xs font-black text-slate-500 uppercase italic text-center">Localidades</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($provincias as $provincia)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-2 font-mono text-slate-400 text-sm">#{{ $provincia->id }}</td>
                    <td class="px-4 py-2 font-bold text-blue-950 uppercase">{{ $provincia->nombre }}</td>
                    <td class="px-4 py-2 text-center">
                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-black">
                            {{ $provincia->localidades_count }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>