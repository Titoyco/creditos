<x-app-layout>
    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-blue-950 uppercase tracking-tighter text-center">Nuevo Operador</h1>
        </div>

        <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-8">
            <form action="{{ route('usuarios.store') }}" method="POST">
                @csrf
                <div class="space-y-6">
                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase mb-2 ml-1 italic">Nombre de Usuario</label>
                        <input type="text" name="name" required class="w-full px-5 py-3.5 rounded-2xl border-slate-200 focus:ring-2 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase mb-2 ml-1 italic">Clave Temporal</label>
                        <input type="password" name="password" required class="w-full px-5 py-3.5 rounded-2xl border-slate-200 focus:ring-2 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase mb-2 ml-1 italic">Sucursal Asignada</label>
                        <select name="id_sucursal" required class="w-full px-5 py-3.5 rounded-2xl border-slate-200 focus:ring-2 focus:ring-blue-600">
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }} ({{ $sucursal->codigo_sucursal }})</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-black py-2 rounded-2xl shadow-lg uppercase tracking-wider mt-4">
                        GUARDAR USUARIO
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>