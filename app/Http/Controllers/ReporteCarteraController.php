<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Sede;
use App\Services\SedeAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteCarteraController extends Controller
{
    public function descargar(Request $request)
    {
        abort_unless(
            $request->user()?->puedeAccederAGerencia()
                || $request->user()?->can('reporte_cartera'),
            403
        );

        $fecha = $request->get('fecha');
        $tipos = $request->get('tipos', ''); // comma-separated: no_vencida,vencida,morosa,pesada

        if (!$fecha) {
            abort(400, 'Debe especificar una fecha.');
        }

        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $hoy = Carbon::today();

        $tiposArray = array_filter(explode(',', $tipos));

        if (empty($tiposArray)) {
            abort(400, 'Debe seleccionar al menos un tipo de cartera.');
        }

        $user = auth()->user();
        $sedeId = app(SedeAccessService::class)
            ->resolveReportSedeId($user, request()->get('sede_id'));

        $sede = $sedeId ? Sede::find($sedeId) : null;
        $sedeNombre = $sede?->Nombre ?? 'SEDE NO ESPECIFICADA';

        // Obtener créditos activos con saldo pendiente
        $query = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', 1)
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->leftJoin('Zona', 'ProposicionCredito.ZonaID', '=', 'Zona.ZonaID')
            ->where('ProposicionCredito.SaldoPendiente', '>', 0)
            ->where('ProposicionCredito.FueRefinanciada', 0);

        if ($sedeId) {
            $query->where('Credito.SedeID', $sedeId);
        }

        // Filtrar por fecha de generación del crédito
        $query->whereDate('Credito.FechaGeneracion', '<=', $fechaCarbon->toDateString());

        $creditos = $query->select(
            'Credito.CreditoID',
            'Credito.FechaGeneracion',
            'Credito.FechaVencimiento',
            'Credito.ProposicionCreditoID',
            'TipoCredito.Descripcion as TipoCreditoDescripcion',
            'Cliente.NombresApellidos',
            'ProposicionCredito.MontoTotalPagar',
            'ProposicionCredito.SaldoPendiente',
            'ProposicionCredito.CodigoCredito',
            'Zona.Nombre as ZonaNombre'
        )
        ->orderBy('Credito.FechaVencimiento', 'asc')
        ->get();

        // Clasificar cada crédito según su categoría
        $secciones = [];
        $titulos = [
            'no_vencida' => 'CARTERA NO VENCIDA',
            'vencida'    => 'CARTERA VENCIDA (1 - 7 días)',
            'morosa'     => 'CARTERA MOROSA (8 - 180 días)',
            'pesada'     => 'CARTERA PESADA / PÉRDIDA (181+ días)',
        ];

        foreach ($tiposArray as $tipo) {
            $secciones[$tipo] = [
                'titulo'   => $titulos[$tipo] ?? strtoupper($tipo),
                'creditos' => [],
                'totalSaldo' => 0,
            ];
        }

        // Pre-agregar todos los pagos en UNA sola query (evita N+1)
        $creditoIds = $creditos->pluck('CreditoID')->toArray();
        $pagosSums = Pago::withoutGlobalScopes()
            ->whereIn('CreditoID', $creditoIds)
            ->where('Activo', 1)
            ->selectRaw('CreditoID, SUM(MontoPagado) as total_pagado')
            ->groupBy('CreditoID')
            ->pluck('total_pagado', 'CreditoID');

        foreach ($creditos as $credito) {
            $fechaVenc = $credito->FechaVencimiento ? Carbon::parse($credito->FechaVencimiento) : null;

            if (!$fechaVenc) {
                continue;
            }

            // Calcular días de vencimiento
            $diasVencimiento = $hoy->diffInDays($fechaVenc, false); // negativo si ya venció

            // Pagado pre-agregado (sin N+1)
            $pagado = $pagosSums[$credito->CreditoID] ?? 0;

            $total = (float) $credito->MontoTotalPagar;
            $saldo = max(0, $total - $pagado);

            if ($saldo <= 0) {
                continue;
            }

            $item = [
                'tipo'         => $credito->TipoCreditoDescripcion,
                'cliente'      => $credito->NombresApellidos,
                'zona'         => $credito->ZonaNombre ?? '-',
                'total'        => $total,
                'pagado'       => $pagado,
                'saldo'        => $saldo,
                'fecha'        => $credito->FechaGeneracion ? Carbon::parse($credito->FechaGeneracion)->format('d/m/Y') : '-',
                'fecha_venc'   => $fechaVenc->format('d/m/Y'),
                'dias'         => abs(intval($hoy->diffInDays($fechaVenc, false))),
                'dias_raw'     => intval($hoy->diffInDays($fechaVenc, false)),
            ];

            if ($diasVencimiento >= 0) {
                // No ha vencido aún (hoy o futuro)
                if (in_array('no_vencida', $tiposArray)) {
                    $secciones['no_vencida']['creditos'][] = $item;
                    $secciones['no_vencida']['totalSaldo'] += $saldo;
                }
            } elseif (abs($diasVencimiento) <= 7) {
                // Vencido entre 1 y 7 días
                if (in_array('vencida', $tiposArray)) {
                    $secciones['vencida']['creditos'][] = $item;
                    $secciones['vencida']['totalSaldo'] += $saldo;
                }
            } elseif (abs($diasVencimiento) <= 180) {
                // Vencido entre 8 y 180 días
                if (in_array('morosa', $tiposArray)) {
                    $secciones['morosa']['creditos'][] = $item;
                    $secciones['morosa']['totalSaldo'] += $saldo;
                }
            } else {
                // Vencido 181+ días
                if (in_array('pesada', $tiposArray)) {
                    $secciones['pesada']['creditos'][] = $item;
                    $secciones['pesada']['totalSaldo'] += $saldo;
                }
            }
        }

        // Calcular total general
        $totalGeneralSaldo = 0;
        foreach ($secciones as $seccion) {
            $totalGeneralSaldo += $seccion['totalSaldo'];
        }

        $rangoFechas = $fechaCarbon->format('d/m/Y');

        $data = [
            'secciones'         => $secciones,
            'totalGeneralSaldo' => $totalGeneralSaldo,
            'sedeNombre'        => strtoupper($sedeNombre),
            'rangoFechas'       => $rangoFechas,
            'fechaEmision'      => now(),
        ];

        $pdf = Pdf::loadView('reportes.reporte-cartera', $data);
        $pdf->setPaper('a4', 'landscape');

        $nombreArchivo = 'Reporte_Cartera_' . $fechaCarbon->format('d-m-Y');

        return $pdf->stream($nombreArchivo . '.pdf');
    }
}
