<?php

namespace App\Http\Controllers;

use App\Models\Presentacion;
use App\Models\Credito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;




class PresentacionController extends Controller
{
    public function index()
    {
        // traemos el usuario autenticado
        $usuarios = User::with('sucursal')->get();
        // Traemos las presentaciones de la más nueva a la más vieja
        $presentaciones = Presentacion::orderBy('numero', 'desc')->paginate(15);
        $actual = Presentacion::orderBy('numero', 'desc')->first();

        return view('sistema.presentaciones.index', compact('presentaciones', 'actual', 'usuarios'));
    }

    public function cambiar(Request $request)
    {
        $request->validate([
            'interes_credito' => 'required|numeric|min:0',
            'interes_mora'    => 'required|numeric|min:0',
        ]);

        $presentacionActual = Presentacion::whereNull('fecha_fin')->orderBy('numero', 'desc')->first();

        if (!$presentacionActual) {
            return back()->with('error', 'No hay una presentación abierta actualmente.');
        }

        // 1. Validar regla de los 7 días (días transcurridos desde que se abrió esta presentación)
        $diasTranscurridos = Carbon::parse($presentacionActual->fecha_inicio)->diffInDays(now());
        if ($diasTranscurridos < 7) {
            return back()->with('error', "No se puede cerrar. Han pasado solo {$diasTranscurridos} días desde la última apertura. (Mínimo 7 días obligatorios).");
        }

        try {
            DB::beginTransaction();

            $ahora = now();
            $tasaMora = $presentacionActual->interes_mora; // O usamos el nuevo si preferís: $request->interes_mora

            // 2. Cerrar la presentación actual
            $presentacionActual->update(['fecha_fin' => $ahora]);

            // 3. Crear la nueva presentación
            $nuevaPresentacion = Presentacion::create([
                'numero'          => $presentacionActual->numero + 1,
                'fecha_inicio'    => $ahora,
                'fecha_fin'       => null,
                'interes_credito' => $request->interes_credito,
                'interes_mora'    => $request->interes_mora,
            ]);

            // 4. Actualizar las cuotas IMPAGAS (saldo_actual > 0)
            // Se calcula el interés y se suma al campo intereses y al saldo_actual.
            // Ecuación: saldo_actual = saldo_actual + (saldo_actual * interes_mora)
            DB::table('cuotas')
                ->where('saldo_actual', '>', 0)
                ->update([
                    'intereses'    => DB::raw("intereses + (saldo_actual * {$tasaMora})"),
                    'saldo_actual' => DB::raw("saldo_actual + (saldo_actual * {$tasaMora})")
                ]);

            // 5. Volver exigibles las cuotas del nuevo ciclo (saldo_actual = valor_base)
            // Lógica: credito.presentacion_origen + cuota.num_cuota == nuevaPresentacion.numero
            // Y que actualmente su saldo sea 0.
            $numeroNuevo = $nuevaPresentacion->numero;

            DB::table('cuotas')
                ->join('creditos', 'cuotas.id_credito', '=', 'creditos.id')
                ->whereRaw('(creditos.presentacion_origen + cuotas.num_cuota) = ?', [$numeroNuevo])
                ->where('cuotas.saldo_actual', '=', 0) // Que no haya sido adelantada/exigida
                ->update([
                    'cuotas.saldo_actual' => DB::raw('cuotas.valor_base')
                ]);

            DB::commit();

            return redirect()->route('presentaciones.index')->with('success', 'Presentación #'.$numeroNuevo.' abierta. Intereses y saldos actualizados correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ocurrió un error grave al procesar el cierre: ' . $e->getMessage());
        }
    }
}