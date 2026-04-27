<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AperturaCierreDia;
use App\Models\Pago;
use App\Models\Credito;
use App\Models\Sede;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteDiarioController extends Controller
{
    /**
     * Genera el reporte general del día (cierre de caja) en PDF.
     *
     * Incluye:
     *   - AMORTIZACIONES: todos los pagos activos registrados en esa fecha
     *   - CREDITOS EMITIDOS: todos los créditos generados en esa fecha
     */
    public function descargar(Request $request)
    {
        $fecha = $request->get('fecha');
        $aperturaCierreDiaId = $request->get('id');

        if (!$fecha) {
            abort(404, 'Fecha no proporcionada');
        }

        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);

        // Obtener el registro de apertura/cierre para la sede
        $aperturaCierre = null;
        if ($aperturaCierreDiaId) {
            $aperturaCierre = AperturaCierreDia::withoutGlobalScopes()
                ->find($aperturaCierreDiaId);
        }

        $sedeId = $aperturaCierre?->SedeID;
        $sede = $sedeId ? Sede::find($sedeId) : null;
        $sedeNombre = $sede?->Nombre ?? 'CHICLAYO';

        // ─── AMORTIZACIONES ───
        // Pagos activos cuya FechaPago cae en la fecha del día cerrado
        $pagosQuery = Pago::withoutGlobalScopes()
            ->where('pago.Activo', true)
            ->whereDate('pago.FechaPago', $fecha);

        if ($sedeId) {
            $pagosQuery->where('pago.SedeID', $sedeId);
        }

        $pagos = $pagosQuery
            ->join('credito', 'pago.CreditoID', '=', 'credito.CreditoID')
            ->join('proposicioncredito', 'credito.ProposicionCreditoID', '=', 'proposicioncredito.ProposicionCreditoID')
            ->join('cliente', 'proposicioncredito.ClienteID', '=', 'cliente.ClienteID')
            ->join('tipocredito', 'proposicioncredito.TipoCreditoID', '=', 'tipocredito.TipoCreditoID')
            ->select(
                'pago.PagoID',
                'proposicioncredito.CodigoCredito',
                'tipocredito.Codigo as TipoCreditoCodigo',
                'cliente.NombresApellidos',
                'pago.MontoPagado'
            )
            ->orderBy('pago.PagoID', 'asc')
            ->get();

        $totalAmortizaciones = $pagos->sum('MontoPagado');

        // ─── CREDITOS EMITIDOS ───
        // Créditos generados en la fecha del día cerrado
        $creditosQuery = Credito::withoutGlobalScopes()
            ->where('credito.Activo', true)
            ->whereDate('credito.FechaGeneracion', $fecha);

        if ($sedeId) {
            $creditosQuery->where('credito.SedeID', $sedeId);
        }

        $creditos = $creditosQuery
            ->join('proposicioncredito', 'credito.ProposicionCreditoID', '=', 'proposicioncredito.ProposicionCreditoID')
            ->join('cliente', 'proposicioncredito.ClienteID', '=', 'cliente.ClienteID')
            ->join('tipocredito', 'proposicioncredito.TipoCreditoID', '=', 'tipocredito.TipoCreditoID')
            ->select(
                'proposicioncredito.CodigoCredito',
                'tipocredito.Codigo as TipoCreditoCodigo',
                'cliente.NombresApellidos',
                'proposicioncredito.MontoTotal',
                'proposicioncredito.MontoInteres',
                'proposicioncredito.MontoTotalPagar',
                'proposicioncredito.NumeroCuotas',
                'proposicioncredito.MontoCuota'
            )
            ->orderBy('credito.CreditoID', 'asc')
            ->get();

        $totalCreditosEmitidos = $creditos->sum('MontoTotal');

        // Calcular paginación (aprox. 40 líneas por página)
        $ahora = Carbon::now();

        $data = [
            'fecha'                => $fechaCarbon,
            'sedeNombre'           => strtoupper($sedeNombre),
            'emision'              => $ahora,
            'pagos'                => $pagos,
            'totalAmortizaciones'  => $totalAmortizaciones,
            'creditos'             => $creditos,
            'totalCreditosEmitidos'=> $totalCreditosEmitidos,
        ];

        $pdf = Pdf::loadView('reportes.reporte-diario', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('Reporte_Diario_' . $fechaCarbon->format('d-m-Y') . '.pdf');
    }
}
