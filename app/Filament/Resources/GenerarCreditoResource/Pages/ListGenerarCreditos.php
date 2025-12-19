<?php

namespace App\Filament\Resources\GenerarCreditoResource\Pages;

use App\Filament\Resources\GenerarCreditoResource;
use App\Models\ProposicionCredito;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;

class ListGenerarCreditos extends ListRecords
{
    protected static string $resource = GenerarCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargar_acta')
                ->label('Descargar PDF Acta de Créditos')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    return $this->descargarActaCreditos();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GenerarCreditoResource\Widgets\ProposicionCreditoStats::class,
        ];
    }

    public function descargarActaCreditos()
    {
        $proposiciones = ProposicionCredito::with(['cliente', 'zona', 'tipoCredito', 'tasa'])
            ->where('Activo', true)
            ->orderBy('CodigoCredito')
            ->get();

        $pdf = Pdf::loadView('pdf.acta-creditos', [
            'proposiciones' => $proposiciones,
            'fecha' => now()->format('d/m/Y'),
        ]);

        $pdf->setPaper('a3', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Acta_Creditos_' . now()->format('Y-m-d_His') . '.pdf');
    }
}