<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\Localidad;
use App\Models\Provincia;
use App\Models\EstadoBanco;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::with(['localidad.provincia', 'sucursal', 'autorizados', 'titulares']);

        // 1. Buscador por texto
        if ($request->filled('search')) {
            $palabras = array_filter(explode(' ', trim($request->search)));

            $query->where(function ($q) use ($palabras) {
                $q->where(function ($qTitular) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $qTitular->where(function ($subQ) use ($palabra) {
                            $subQ->where('nombre', 'LIKE', "%{$palabra}%")
                                ->orWhere('dni', 'LIKE', "%{$palabra}%");
                        });
                    }
                })
                ->orWhereHas('autorizados', function ($qAutorizado) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $qAutorizado->where(function ($subQ) use ($palabra) {
                            $subQ->where('nombre', 'LIKE', "%{$palabra}%")
                                ->orWhere('dni', 'LIKE', "%{$palabra}%");
                        });
                    }
                });
            });
        }

        // 2. Filtros secundarios
        if ($request->filled('id_sucursal')) {
            $query->where('id_sucursal', $request->id_sucursal);
        }

        if ($request->filled('id_localidad')) {
            $query->where('id_localidad', $request->id_localidad);
        }

        if ($request->filled('id_provincia')) {
            $query->whereHas('localidad', function ($q) use ($request) {
                $q->where('id_provincia', $request->id_provincia);
            });
        }

        if ($request->filled('suspendido') && $request->suspendido !== 'todos') {
            $query->where('suspendido', $request->suspendido);
        }

        // Filtro por la nueva columna estado_banco
        if ($request->filled('estado_banco')) {
            $query->where('estado_banco', $request->estado_banco);
        }
        
        // Saldo > 0
        if ($request->boolean('has_saldo')) {
            $query->where('saldo', '>', 0);
            };

        $clientes = $query->orderBy('nombre', 'asc')
                        ->paginate(15)
                        ->withQueryString();


        

        $sucursales   = Sucursal::orderBy('nombre')->get();
        $localidades  = Localidad::orderBy('nombre')->get();
        $provincias   = Provincia::orderBy('nombre')->get();
        $estadosBanco = EstadoBanco::orderBy('codigo')->get();

        return view('clientes.index', compact('clientes', 'sucursales', 'localidades', 'provincias', 'estadosBanco'));
    }

    public function create()
    {
        $sucursales = Sucursal::all();
        $localidades = Localidad::all();
        return view('clientes.create', compact('sucursales', 'localidades'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dni' => 'required|unique:clientes,dni|integer',
            'nombre' => 'required|string|max:191',
            'id_sucursal' => 'required|exists:sucursales,id',
            'medio_pago_preferido' => 'required|integer|in:0,1',
            
            'cbu' => [
                'required_if:medio_pago_preferido,1',
                'nullable',
                'string',
                'size:22',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !$this->validarCBU($value)) {
                        $fail('El número de CBU ingresado no es válido.');
                    }
                },
            ],
            
            'tipo_haber' => 'nullable|integer',
            'limite' => 'required|numeric|min:0',
            'max_cuotas' => 'required|integer|in:1,2,3,4',
            'direccion' => 'required|string|max:191',
            'id_localidad' => 'required|exists:localidades,id',
            'email' => 'nullable|email|max:191',
            'tel1' => 'required|string|max:50',
            'tel2' => 'nullable|string|max:50',
            
            // Familiar / Referencia
            'familiar' => 'nullable|string|max:191',
            'tipo_familiar' => 'nullable|string|max:50',
            'direccion_familiar' => 'nullable|string|max:191',
            'id_localidad_familiar' => 'nullable|exists:localidades,id',
            'email_familiar' => 'nullable|email|max:191',
            'tel_familiar' => 'nullable|string|max:50',
            
            // Laborales
            'trabajo' => 'nullable|string|max:191',
            'direccion_trabajo' => 'nullable|string|max:191',
            'id_localidad_trabajo' => 'nullable|exists:localidades,id',
            'tel_trabajo' => 'nullable|string|max:50',
            'obs' => 'nullable|string',
        ], [
            'cbu.required_if' => 'El CBU es obligatorio cuando el medio de pago preferido es Débito en CBU.',
        ]);

        $data = $request->all();
        
        // Manejo del checkbox "suspendido"
        $data['suspendido'] = $request->has('suspendido') ? 1 : 0;
        
        $data['estado_banco'] = null;
        $data['fecha_estado_banco'] = null;

        Cliente::create($data);

        return redirect()->route('clientes.index')
                        ->with('success', 'Cliente creado correctamente.');
    }

    public function update(Request $request, Cliente $cliente)
    {
        // Validamos la información (permitiendo que el DNI sea el mismo del cliente actual)
        $validated = $request->validate([
            'dni' => 'required|integer|unique:clientes,dni,' . $cliente->id,
            'nombre' => 'required|string|max:191',
            'id_sucursal' => 'required|exists:sucursales,id',
            'medio_pago_preferido' => 'required|integer|in:0,1',
            
            // --- CBU REQUERIDO SI MEDIO DE PAGO ES 1 (DÉBITO) ---
            'cbu' => [
                'required_if:medio_pago_preferido,1',
                'nullable',
                'string',
                'size:22',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !$this->validarCBU($value)) {
                        $fail('El número de CBU ingresado no es válido.');
                    }
                },
            ],
            
            'tipo_haber' => 'nullable|integer',
            'limite' => 'required|numeric|min:0',
            'max_cuotas' => 'required|integer|in:1,2,3,4', // NOTA: Unifiqué las cuotas permitidas a 1,2,3,4 como en el form
            'direccion' => 'required|string|max:191',
            'id_localidad' => 'required|exists:localidades,id',
            'email' => 'nullable|email|max:191',
            'tel1' => 'required|string|max:50',
            'tel2' => 'nullable|string|max:50',
            
            // Datos del Familiar / Referencia
            'familiar' => 'nullable|string|max:191',
            'tipo_familiar' => 'nullable|string|max:50',
            'direccion_familiar' => 'nullable|string|max:191',
            'id_localidad_familiar' => 'nullable|exists:localidades,id',
            'email_familiar' => 'nullable|email|max:191',
            'tel_familiar' => 'nullable|string|max:50',
            
            // Datos Laborales
            'trabajo' => 'nullable|string|max:191',
            'direccion_trabajo' => 'nullable|string|max:191',
            'id_localidad_trabajo' => 'nullable|exists:localidades,id',
            'tel_trabajo' => 'nullable|string|max:50',
            'obs' => 'nullable|string',
        ], [
            'cbu.required_if' => 'El CBU es obligatorio cuando el medio de pago preferido es Débito en CBU.',
        ]);

        $data = $request->all();

        // Manejo del checkbox de suspensión (0 o 1 estricto)
        $data['suspendido'] = $request->input('suspendido') == 1 ? 1 : 0;

        // Actualizamos el registro del legajo
        $cliente->update($data);

        return redirect()->route('clientes.index')
                        ->with('success', 'Legajo de cliente actualizado correctamente.');
    }
    

    private function validarCBU($cbu)
    {
        // Verificar que sean 22 caracteres numéricos
        if (!preg_match('/^[0-9]{22}$/', $cbu)) {
            return false;
        }

        $bloque1 = substr($cbu, 0, 8);
        $bloque2 = substr($cbu, 8, 14);

        // Validar primer bloque (Banco, Sucursal y Dígito Verificador 1)
        $ponderacion1 = [7, 1, 3, 9, 7, 1, 3];
        $suma1 = 0;
        for ($i = 0; $i < 7; $i++) {
            $suma1 += (int)$bloque1[$i] * $ponderacion1[$i];
        }
        $digitoVerificador1 = (10 - ($suma1 % 10)) % 10;
        if ($digitoVerificador1 !== (int)$bloque1[7]) {
            return false;
        }

        // Validar segundo bloque (Cuenta y Dígito Verificador 2)
        $ponderacion2 = [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3];
        $suma2 = 0;
        for ($i = 0; $i < 13; $i++) {
            $suma2 += (int)$bloque2[$i] * $ponderacion2[$i];
        }
        $digitoVerificador2 = (10 - ($suma2 % 10)) % 10;
        if ($digitoVerificador2 !== (int)$bloque2[13]) {
            return false;
        }

        return true;
    }


    public function edit(Cliente $cliente)
    {
        // Buscamos todas las sucursales y localidades para los menús desplegables del formulario
        $sucursales = Sucursal::all();
        $localidades = Localidad::orderBy('nombre')->get();

        // Retornamos la vista del formulario pasándole el cliente actual
        return view('clientes.edit', compact('cliente', 'sucursales', 'localidades'));
    }

    

    public function destroy(Cliente $cliente)
    {
        // Antes de eliminar, verificamos si el cliente tiene créditos históricos
        if ($cliente->creditos()->exists()) {
            return redirect()->route('clientes.index')
                ->with('error', 'No se puede eliminar el cliente porque tiene historial de créditos.');
        }

        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'El cliente fue eliminado del sistema correctamente.');
    }

    public function estadoCuenta(Cliente $cliente)
    {
        // Cargar créditos con sus cuotas y sucursales
        $cliente->load(['creditos.cuotas', 'creditos.sucursal', 'autorizados']);

        // Filtrar solo los créditos activos / abiertos
        $creditosAbiertos   = $cliente->creditos->whereNull('fecha_cancelacion');
        $creditosCancelados = $cliente->creditos->whereNotNull('fecha_cancelacion');

        // Extraer todas las cuotas asociadas únicamente a créditos activos
        $cuotasActivas = $creditosAbiertos->flatMap->cuotas;

        // Métricas exclusivas de Créditos Activos
        $totalValorBase   = $cuotasActivas->sum('valor_base');    // Total Deuda Base (Original)
        $totalIntereses   = $cuotasActivas->sum('intereses');     // Total Intereses
        $totalEntregado   = $cuotasActivas->sum('entrego');       // Total Entregado (Cobrado)
        $totalAPagar      = $cuotasActivas->sum('saldo_actual');    // Total a Pagar
        $totalRestante    = $totalValorBase + $totalIntereses - $totalEntregado - $totalAPagar;  // Total Restante / Saldo Pendiente

        return view('clientes.estado-cuenta', compact(
            'cliente',
            'creditosAbiertos',
            'creditosCancelados',
            'totalValorBase',
            'totalIntereses',
            'totalEntregado',
            'totalAPagar',
            'totalRestante'
        ));
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['autorizados', 'novedades.user', 'sucursal']); // Cargar relaciones necesarias para la vista de detalle
        return view('clientes.show', compact('cliente'));
    }

}