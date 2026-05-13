<?php

namespace App\Filament\Resources\ReporteClientesAtrasoResource\Pages;

use App\Filament\Resources\ReporteClientesAtrasoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReporteClientesAtraso extends ListRecords
{
    protected static string $resource = ReporteClientesAtrasoResource::class;

    public function getTitle(): string
    {
        return 'Clientes con Días de Atraso';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    $url = route('clientes-atraso.view');
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),
        ];
    }
}
