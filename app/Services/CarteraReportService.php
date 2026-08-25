<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Pago;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CarteraReportService
{
    public const TITULOS = [
        'no_vencida' => 'CARTERA NO VENCIDA',
        'vencida' => 'CARTERA VENCIDA (1 - 7 días)',
        'morosa' => 'CARTERA MOROSA (8 - 180 días)',
        'pesada' => 'CARTERA PESADA / PÉRDIDA (181+ días)',
    ];

    public function generar(
        CarbonInterface $fechaCorte,
        ?int $sedeId = null,
        ?int $ciudadId = null,
        ?int $zonaId = null,
        ?CarbonInterface $fechaDesde = null,
    ): array {
        $corte = Carbon::instance($fechaCorte)->startOfDay();

        $creditos = Credito::withoutGlobalScopes()
            ->where('Credito.Activo', 1)
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->leftJoin('Zona', 'ProposicionCredito.ZonaID', '=', 'Zona.ZonaID')
            ->where('ProposicionCredito.SaldoPendiente', '>', 0)
            ->where('ProposicionCredito.FueRefinanciada', 0)
            ->when($sedeId, fn ($query) => $query->where('Credito.SedeID', $sedeId))
            ->when($ciudadId, fn ($query) => $query->where('Zona.CiudadID', $ciudadId))
            ->when($zonaId, fn ($query) => $query->where('ProposicionCredito.ZonaID', $zonaId))
            ->when($fechaDesde, fn ($query) => $query->whereDate('Credito.FechaGeneracion', '>=', $fechaDesde->toDateString()))
            ->whereDate('Credito.FechaGeneracion', '<=', $corte->toDateString())
            ->select(
                'Credito.CreditoID',
                'Credito.FechaGeneracion',
                'Credito.FechaVencimiento',
                'TipoCredito.Descripcion as TipoCreditoDescripcion',
                'Cliente.NombresApellidos',
                'ProposicionCredito.MontoTotal',
                'ProposicionCredito.MontoTotalPagar',
                'ProposicionCredito.CodigoCredito',
                'Zona.Nombre as ZonaNombre',
            )
            ->orderBy('Credito.FechaVencimiento')
            ->get();

        $pagosPorCredito = Pago::withoutGlobalScopes()
            ->whereIn('CreditoID', $creditos->pluck('CreditoID'))
            ->where('Activo', 1)
            ->whereDate('FechaPago', '<=', $corte->toDateString())
            ->selectRaw('CreditoID, SUM(MontoPagado) as total_pagado')
            ->groupBy('CreditoID')
            ->pluck('total_pagado', 'CreditoID');

        $secciones = collect(self::TITULOS)
            ->map(fn (string $titulo) => [
                'titulo' => $titulo,
                'creditos' => [],
                'totalSaldo' => 0.0,
                'porZona' => [],
            ])
            ->all();

        foreach ($creditos as $credito) {
            if (! $credito->FechaVencimiento) {
                continue;
            }

            $fechaVencimiento = Carbon::parse($credito->FechaVencimiento)->startOfDay();
            $pagado = (float) ($pagosPorCredito[$credito->CreditoID] ?? 0);
            $montoTotal = (float) ($credito->MontoTotalPagar ?? 0);
            $saldo = max(0, $montoTotal - $pagado);

            if ($saldo <= 0) {
                continue;
            }

            $tipo = $this->clasificarFechaVencimiento($fechaVencimiento, $corte);
            $diasRaw = (int) $corte->diffInDays($fechaVencimiento, false);
            $zona = $credito->ZonaNombre ?: 'SIN ZONA';

            $secciones[$tipo]['creditos'][] = [
                'codigo' => $credito->CodigoCredito,
                'tipo' => $credito->TipoCreditoDescripcion,
                'cliente' => $credito->NombresApellidos,
                'zona' => $zona,
                'monto_entregado' => (float) ($credito->MontoTotal ?? 0),
                'total' => $montoTotal,
                'pagado' => $pagado,
                'saldo' => $saldo,
                'fecha' => $credito->FechaGeneracion
                    ? Carbon::parse($credito->FechaGeneracion)->format('d/m/Y')
                    : '-',
                'fecha_venc' => $fechaVencimiento->format('d/m/Y'),
                'dias' => abs($diasRaw),
                'dias_raw' => $diasRaw,
            ];
            $secciones[$tipo]['totalSaldo'] += $saldo;
            $secciones[$tipo]['porZona'][$zona] =
                ($secciones[$tipo]['porZona'][$zona] ?? 0) + $saldo;
        }

        return [
            'secciones' => $secciones,
            'totalGeneralSaldo' => array_sum(array_column($secciones, 'totalSaldo')),
        ];
    }

    public function clasificarFechaVencimiento(
        CarbonInterface $fechaVencimiento,
        CarbonInterface $fechaCorte,
    ): string {
        $dias = (int) $fechaCorte->copy()->startOfDay()
            ->diffInDays($fechaVencimiento->copy()->startOfDay(), false);

        return match (true) {
            $dias >= 0 => 'no_vencida',
            abs($dias) <= 7 => 'vencida',
            abs($dias) <= 180 => 'morosa',
            default => 'pesada',
        };
    }
}
