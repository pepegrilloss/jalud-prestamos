<?php

namespace App\Filament\Resources\ClienteProposicionResource\Pages;

use App\Filament\Resources\ClienteProposicionResource;
use App\Filament\Resources\ClienteProposicionResource\Widgets\ClienteProposicionStats;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;

class ListClienteProposicions extends ListRecords
{
    protected static string $resource = ClienteProposicionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargar_acta')
                ->label('Descargar PDF Acta de Créditos')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('acta-creditos.view'))
                ->openUrlInNewTab(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClienteProposicionStats::class,
        ];
    }
}