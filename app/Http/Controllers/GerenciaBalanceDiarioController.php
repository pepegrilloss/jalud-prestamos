<?php

namespace App\Http\Controllers;

use App\Services\GerenciaBalanceDiarioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class GerenciaBalanceDiarioController extends Controller
{
    public function descargar(Request $request, GerenciaBalanceDiarioService $service)
    {
        abort_unless($request->user()?->puedeAccederAGerencia(), 403);

        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $data = $service->generar($validated['fecha']);
        $pdf = Pdf::loadView('reportes.balance-diario-gerencia', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'margin-top' => 20,
                'margin-bottom' => 20,
                'margin-left' => 20,
                'margin-right' => 20,
            ]);

        return $pdf->stream('Balance_Gerencia_' . $data['fecha']->format('d-m-Y') . '.pdf');
    }
}
