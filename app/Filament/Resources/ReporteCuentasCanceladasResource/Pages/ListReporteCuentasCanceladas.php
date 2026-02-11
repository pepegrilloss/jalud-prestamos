<?php

namespace App\Filament\Resources\ReporteCuentasCanceladasResource\Pages;

use App\Filament\Resources\ReporteCuentasCanceladasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

class ListReporteCuentasCanceladas extends ListRecords
{
    protected static string $resource = ReporteCuentasCanceladasResource::class;

    public function getTitle(): string
    {
        return 'Cuentas Canceladas en el Día';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn() => $this->descargarPdf())
                ->color('danger'),
        ];
    }

    public function descargarPdf()
    {
        $proposiciones = \App\Models\ProposicionCredito::where('SaldoPendiente', 0)
            ->with(['cliente', 'credito'])
            ->orderByDesc('FechaModificacion')
            ->get();

        $data = [
            'fecha' => Carbon::now()->format('d/m/Y H:i'),
            'proposiciones' => $proposiciones,
        ];

        $pdf = Pdf::loadView('reportes.cuentas-canceladas', $data)
            ->setPaper('a4', 'landscape');

        $filename = 'cuentas_canceladas_' . Carbon::now()->format('d-m-Y_H-i-s') . '.pdf';
        return response()->streamDownload(
            fn() => print($pdf->output()),
            $filename
        );
    }
}
