<?php

namespace App\Filament\Resources\ReporteCreditosVencidosResource\Pages;

use App\Filament\Resources\ReporteCreditosVencidosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

class ListReporteCreditosVencidos extends ListRecords
{
    protected static string $resource = ReporteCreditosVencidosResource::class;

    public function getTitle(): string
    {
        return 'Créditos Vencidos';
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
        $creditos = \App\Models\Credito::where('Activo', 1)
            ->whereDate('FechaVencimiento', '<=', Carbon::today())
            ->whereHas('proposicion', function ($q) {
                $q->where('SaldoPendiente', '>', 0);
            })
            ->with(['proposicion.cliente', 'proposicion.tipoCredito'])
            ->orderBy('FechaVencimiento', 'asc')
            ->get();

        $data = [
            'fecha' => Carbon::now()->format('d/m/Y H:i'),
            'creditos' => $creditos,
        ];

        $pdf = Pdf::loadView('reportes.creditos-vencidos', $data)
            ->setPaper('a4', 'landscape');

        $filename = 'creditos_vencidos_' . Carbon::now()->format('d-m-Y_H-i-s') . '.pdf';
        return response()->streamDownload(
            fn() => print($pdf->output()),
            $filename
        );
    }
}
