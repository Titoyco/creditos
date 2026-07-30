
<!-- 1. DATOS PERSONALES Y SUCURSAL -->
<div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-3">
    <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
        <h3 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">1. Datos Personales y Sucursal</h3>
    </div>
    <div class="p-3 grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-1">
            <label for="dni" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">DNI (Sin puntos)</label>
            <input type="number" id="dni" name="dni" value="{{ old('dni', $cliente->dni ?? '') }}" required 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm font-mono" />
            <x-input-error :messages="$errors->get('dni')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-2">
            <label for="nombre" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Nombre y Apellido Completo</label>
            <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}" required 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm uppercase" />
            <x-input-error :messages="$errors->get('nombre')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-1">
            <label for="id_sucursal" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Sucursal de Alta</label>
            <select name="id_sucursal" id="id_sucursal" required class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                @foreach($sucursales as $sucursal)
                    <option value="{{ $sucursal->id }}" {{ old('id_sucursal', $cliente->id_sucursal ?? '') == $sucursal->id ? 'selected' : '' }}>
                        {{ $sucursal->nombre }} ({{ $sucursal->codigo_sucursal }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('id_sucursal')" class="mt-1 text-[10px]" />
        </div>
    </div>
</div>

<!-- 2. UBICACIÓN Y CONTACTO -->
<div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-3">
    <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
        <h3 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">2. Ubicación y Contacto</h3>
    </div>
    <div class="p-3 grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-1">
            <label for="id_localidad" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Localidad</label>
            <select name="id_localidad" id="id_localidad" required class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                <option value="">Seleccione...</option>
                @foreach($localidades as $localidad)
                    <option value="{{ $localidad->id }}" {{ old('id_localidad', $cliente->id_localidad ?? '') == $localidad->id ? 'selected' : '' }}>
                        {{ $localidad->nombre }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('id_localidad')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-3">
            <label for="direccion" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Dirección Particular</label>
            <input type="text" id="direccion" name="direccion" value="{{ old('direccion', $cliente->direccion ?? '') }}" required 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" />
            <x-input-error :messages="$errors->get('direccion')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-1">
            <label for="tel1" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Tel. Principal (Celular)</label>
            <input type="text" id="tel1" name="tel1" value="{{ old('tel1', $cliente->tel1 ?? '') }}" required 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm font-mono" />
            <x-input-error :messages="$errors->get('tel1')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-1">
            <label for="tel2" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Tel. Alternativo</label>
            <input type="text" id="tel2" name="tel2" value="{{ old('tel2', $cliente->tel2 ?? '') }}" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm font-mono" />
            <x-input-error :messages="$errors->get('tel2')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-2">
            <label for="email" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Email (Opcional)</label>
            <input type="email" id="email" name="email" value="{{ old('email', $cliente->email ?? '') }}" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" />
            <x-input-error :messages="$errors->get('email')" class="mt-1 text-[10px]" />
        </div>
    </div>
</div>

<!-- 3. INFORMACIÓN LABORAL Y BANCARIA -->
<div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden mb-3">
    <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
        <h3 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">3. Laboral y Bancaria</h3>
    </div>
    <div class="p-3 grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <label for="trabajo" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Lugar de Trabajo / Empleador</label>
            <input type="text" id="trabajo" name="trabajo" value="{{ old('trabajo', $cliente->trabajo ?? '') }}" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" />
            <x-input-error :messages="$errors->get('trabajo')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-2">
            <label for="direccion_trabajo" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Dirección Laboral</label>
            <input type="text" id="direccion_trabajo" name="direccion_trabajo" value="{{ old('direccion_trabajo', $cliente->direccion_trabajo ?? '') }}" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" />
            <x-input-error :messages="$errors->get('direccion_trabajo')" class="mt-1 text-[10px]" />
        </div>
        <div class="md:col-span-1">
            <label for="id_localidad_trabajo" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Loc. Laboral</label>
            <select name="id_localidad_trabajo" id="id_localidad_trabajo" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                <option value="">Seleccione...</option>
                @foreach($localidades as $localidad)
                    <option value="{{ $localidad->id }}" {{ old('id_localidad_trabajo', $cliente->id_localidad_trabajo ?? '') == $localidad->id ? 'selected' : '' }}>
                        {{ $localidad->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-1">
            <label for="tel_trabajo" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Tel. Laboral</label>
            <input type="text" id="tel_trabajo" name="tel_trabajo" value="{{ old('tel_trabajo', $cliente->tel_trabajo ?? '') }}" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm font-mono" />
        </div>
        <div class="md:col-span-1">
            <label for="tipo_haber" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Tipo Haber (ID)</label>
            <input type="number" id="tipo_haber" name="tipo_haber" value="{{ old('tipo_haber', $cliente->tipo_haber ?? '') }}" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" />
        </div>
        <div class="md:col-span-1">
            <label for="limite" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Límite Otorgado ($)</label>
            <input type="number" step="0.01" id="limite" name="limite" value="{{ old('limite', $cliente->limite ?? '800000') }}" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm font-bold text-blue-800 bg-blue-50" />
        </div>
        <div class="md:col-span-2">
            <label for="cbu" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">CBU / CVU / Alias</label>
            <input type="text" id="cbu" name="cbu" value="{{ old('cbu', $cliente->cbu ?? '') }}" placeholder="22 dígitos" 
                   class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm font-mono" />
        </div>
        <div class="md:col-span-1">
            <label for="medio_pago_preferido" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Medio de Pago</label>
            <select name="medio_pago_preferido" id="medio_pago_preferido" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                <option value="1" {{ old('medio_pago_preferido', $cliente->medio_pago_preferido ?? 1) == 1 ? 'selected' : '' }}>Débito CBU</option>
                <option value="0" {{ old('medio_pago_preferido', $cliente->medio_pago_preferido ?? 0) == 0 ? 'selected' : '' }}>Sucursal</option>
            </select>
        </div>
        <div class="md:col-span-1">
            <label for="max_cuotas" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Máx. Cuotas</label>
            <select name="max_cuotas" id="max_cuotas" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                @for($i = 1; $i <= 4; $i++)
                    <option value="{{ $i }}" {{ old('max_cuotas', $cliente->max_cuotas ?? 4) == $i ? 'selected' : '' }}>{{ $i }} Cuotas</option>
                @endfor
            </select>
        </div>
    </div>
</div>

<!-- 4. FAMILIAR Y ESTADO DEL CLIENTE -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
    
    <!-- Familiar -->
    <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden">
        <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
            <h3 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">4. Referencia Familiar</h3>
        </div>
        <div class="p-3 grid grid-cols-2 gap-3">
            <div class="col-span-2 flex space-x-2">
                <div class="flex-grow">
                    <label for="familiar" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Nombre Familiar</label>
                    <input type="text" id="familiar" name="familiar" value="{{ old('familiar', $cliente->familiar ?? '') }}" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 shadow-sm" />
                </div>
                <div class="w-24">
                    <label for="tipo_familiar" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Parentesco</label>
                    <input type="text" id="tipo_familiar" name="tipo_familiar" value="{{ old('tipo_familiar', $cliente->tipo_familiar ?? '') }}" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 shadow-sm" />
                </div>
            </div>
            <div>
                <label for="id_localidad_familiar" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Localidad</label>
                <select name="id_localidad_familiar" id="id_localidad_familiar" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 shadow-sm">
                    <option value="">Seleccione...</option>
                    @foreach($localidades as $localidad)
                        <option value="{{ $localidad->id }}" {{ old('id_localidad_familiar', $cliente->id_localidad_familiar ?? '') == $localidad->id ? 'selected' : '' }}>{{ $localidad->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tel_familiar" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Teléfono</label>
                <input type="text" id="tel_familiar" name="tel_familiar" value="{{ old('tel_familiar', $cliente->tel_familiar ?? '') }}" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 shadow-sm font-mono" />
            </div>
            <div class="col-span-2">
                <label for="direccion_familiar" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Dirección</label>
                <input type="text" id="direccion_familiar" name="direccion_familiar" value="{{ old('direccion_familiar', $cliente->direccion_familiar ?? '') }}" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 shadow-sm" />
            </div>
        </div>
    </div>

    <!-- Observaciones y Estado -->
    <div class="bg-white rounded-md border border-slate-300 shadow-sm overflow-hidden flex flex-col">
        <div class="bg-slate-100 border-b border-slate-300 px-3 py-1.5">
            <h3 class="font-bold text-[10px] text-slate-700 uppercase tracking-wider">5. Observaciones y Estado</h3>
        </div>
        <div class="p-3 flex-grow flex flex-col justify-between">
            <div>
                <label for="obs" class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Observaciones Generales</label>
                <textarea id="obs" name="obs" rows="3" class="block w-full text-xs py-1.5 px-2 rounded border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm resize-none">{{ old('obs', $cliente->obs ?? '') }}</textarea>
            </div>
            
            <div class="mt-4 pt-3 border-t border-slate-100">
                <label for="suspendido" class="flex items-center cursor-pointer p-2 bg-slate-50 rounded border border-slate-200 hover:bg-slate-100 transition-colors">
                    <input type="hidden" name="suspendido" value="0">
                    <input type="checkbox" id="suspendido" name="suspendido" value="1" 
                        class="rounded border-slate-300 text-rose-600 focus:ring-rose-500 h-4 w-4"
                        {{ old('suspendido', $cliente->suspendido ?? 0) == 1 ? 'checked' : '' }}>
                    <span class="ml-2 text-[11px] font-bold text-slate-700 uppercase">Marcar como Suspendido / Adicional</span>
                </label>
            </div>
        </div>
    </div>

</div>