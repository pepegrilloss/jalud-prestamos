<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Credito;
use App\Models\Sede;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteCreditosController extends Controller
{
    public function descargar(Request $request)
    {
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $sedeIdParam = $request->get('sede_id');

        if (!$fechaDesde || !$fechaHasta) {
            abort(404, 'Debe proporcionar un rango de fechas');
        }

        $fechaDesdeCarbon = Carbon::createFromFormat('Y-m-d', $fechaDesde)->startOfDay();
        $fechaHastaCarbon = Carbon::createFromFormat('Y-m-d', $fechaHasta)->endOfDay();

        $user = auth()->user();
        if ($user && $user->isPrivileged()) {
            if ($sedeIdParam === '0' || $sedeIdParam === 'todas' || $sedeIdParam === '') {
                $sedeId = null;
            } elseif ($sedeIdParam) {
                $sedeId = (int) $sedeIdParam;
            } else {
                $sedeId = $user->getEffectiveSedeId();
            }
        } else {
            $sedeId = $user?->getEffectiveSedeId();
        }

        $sede = $sedeId ? Sede::find($sedeId) : null;
        $sedeNombre = $sede?->Nombre ?? 'TODAS LAS SEDES';

        $creditos = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', 1)
            ->whereBetween('Credito.FechaGeneracion', [$fechaDesdeCarbon, $fechaHastaCarbon])
            ->when($sedeId, fn($q) => $q->where('Credito.SedeID', $sedeId))
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->select(
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
            ->get();

        $totales = [
            'montoTotal' => $creditos->sum('MontoTotal'),
            'interes' => $creditos->sum('MontoInteres'),
            'montoTotalPagar' => $creditos->sum('MontoTotalPagar'),
            'saldo' => $creditos->sum('SaldoPendiente'),
        ];

        $data = compact('creditos', 'fechaDesde', 'fechaHasta', 'sedeNombre', 'totales');

        $fechaDesdeCarbon = Carbon::createFromFormat('Y-m-d', $fechaDesde);
        $fechaHastaCarbon = Carbon::createFromFormat('Y-m-d', $fechaHasta);
        $data['fechaDesde'] = $fechaDesdeCarbon;
        $data['fechaHasta'] = $fechaHastaCarbon;

        $pdf = Pdf::loadView('reportes.reporte-creditos', $data);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream("Reporte_Creditos_{$fechaDesde}_{$fechaHasta}.pdf");
    }
}
