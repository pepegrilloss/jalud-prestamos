<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Services\CarteraReportService;
use App\Services\SedeAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReporteCarteraController extends Controller
{
    public function descargar(Request $request, CarteraReportService $carteraReportService)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        abort_unless(
            $request->user()?->puedeAccederAGerencia()
                || $request->user()?->can('reporte_cartera'),
            403
        );

        $fecha = $request->get('fecha');
        $tipos = $request->get('tipos', ''); // comma-separated: no_vencida,vencida,morosa,pesada

        if (! $fecha) {
            abort(400, 'Debe especificar una fecha.');
        }

        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $tiposArray = array_filter(explode(',', $tipos));

        if (empty($tiposArray)) {
            abort(400, 'Debe seleccionar al menos un tipo de cartera.');
        }

        $user = auth()->user();
        $sedeId = app(SedeAccessService::class)
            ->resolveReportSedeId($user, request()->get('sede_id'));

        $sede = $sedeId ? Sede::find($sedeId) : null;
        $sedeNombre = $sede?->Nombre ?? 'SEDE NO ESPECIFICADA';

        $resultado = $carteraReportService->generar($fechaCarbon, $sedeId);
        $secciones = array_intersect_key($resultado['secciones'], array_flip($tiposArray));
        $totalGeneralSaldo = array_sum(array_column($secciones, 'totalSaldo'));

        $rangoFechas = $fechaCarbon->format('d/m/Y');

        $data = [
            'secciones' => $secciones,
            'totalGeneralSaldo' => $totalGeneralSaldo,
            'sedeNombre' => strtoupper($sedeNombre),
            'rangoFechas' => $rangoFechas,
            'fechaEmision' => now(),
        ];

        $pdf = Pdf::loadView('reportes.reporte-cartera', $data);
        $pdf->setPaper('a4', 'landscape');

        $nombreArchivo = 'Reporte_Cartera_'.$fechaCarbon->format('d-m-Y');

        return $pdf->stream($nombreArchivo.'.pdf');
    }
}
