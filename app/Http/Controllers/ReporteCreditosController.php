<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Credito;
use App\Models\Sede;
use App\Services\SedeAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteCreditosController extends Controller
{
    public function descargar(Request $request)
    {
        abort_unless(
            $request->user()?->puedeAccederAGerencia()
                || $request->user()?->can('reporte_creditos'),
            403
        );

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $sedeIdParam = $request->get('sede_id');

        if (!$fechaDesde || !$fechaHasta) {
            abort(404, 'Debe proporcionar un rango de fechas');
        }

        $fechaDesdeCarbon = Carbon::createFromFormat('Y-m-d', $fechaDesde)->startOfDay();
        $fechaHastaCarbon = Carbon::createFromFormat('Y-m-d', $fechaHasta)->endOfDay();

        if ($fechaDesdeCarbon->diffInDays($fechaHastaCarbon) > 365) {
            abort(400, 'El rango maximo permitido es de 1 ano (365 dias).');
        }

        $user = auth()->user();
        $sedeId = app(SedeAccessService::class)
            ->resolveReportSedeId($user, $sedeIdParam);

        $sede = $sedeId ? Sede::find($sedeId) : null;
        $sedeNombre = $sede?->Nombre ?? 'TODAS LAS SEDES';

        $baseQuery = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', 1)
            ->whereBetween('Credito.FechaGeneracion', [$fechaDesdeCarbon, $fechaHastaCarbon])
            ->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID');

        $totalRegistros = (clone $baseQuery)->count();
        $LIMITE = 2000;
        $limitado = false;

        if ($totalRegistros > $LIMITE) {
            $limitado = true;
        }

        $creditos = $baseQuery->select(
                'Credito.*',
                'ProposicionCredito.MontoTotal',
                'ProposicionCredito.MontoInteres',
                'ProposicionCredito.MontoTotalPagar',
                'ProposicionCredito.SaldoPendiente',
                'ProposicionCredito.MontoCuota',
                'ProposicionCredito.Plazo',
                'Cliente.DNI',
                'Cliente.NombresApellidos',
                'TipoCredito.Descripcion as TipoCreditoDescripcion'
            )
            ->orderBy('Credito.FechaGeneracion')
            ->limit($LIMITE)
            ->get();

        $totales = [
            'montoTotal' => $creditos->sum('MontoTotal'),
            'interes' => $creditos->sum('MontoInteres'),
            'montoTotalPagar' => $creditos->sum('MontoTotalPagar'),
            'saldo' => $creditos->sum('SaldoPendiente'),
        ];

        $data = compact('creditos', 'fechaDesde', 'fechaHasta', 'sedeNombre', 'totales', 'limitado', 'totalRegistros', 'LIMITE');

        $fechaDesdeCarbon = Carbon::createFromFormat('Y-m-d', $fechaDesde);
        $fechaHastaCarbon = Carbon::createFromFormat('Y-m-d', $fechaHasta);
        $data['fechaDesde'] = $fechaDesdeCarbon;
        $data['fechaHasta'] = $fechaHastaCarbon;

        $pdf = Pdf::loadView('reportes.reporte-creditos', $data);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream("Reporte_Creditos_{$fechaDesde}_{$fechaHasta}.pdf");
    }
}
