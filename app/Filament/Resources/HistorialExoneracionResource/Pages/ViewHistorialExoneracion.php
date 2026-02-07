<?php

namespace App\Filament\Resources\HistorialExoneracionResource\Pages;

use App\Filament\Resources\HistorialExoneracionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewHistorialExoneracion extends ViewRecord
{
    protected static string $resource = HistorialExoneracionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\TextEntry::make('cliente.NombresApellidos')
                    ->label('Cliente'),
                Infolists\Components\TextEntry::make('credito.proposicion.CodigoCredito')
                    ->label('Crédito'),
                Infolists\Components\TextEntry::make('TipoExoneracion')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => match($state) {
                        'P' => 'Pronto Pago',
                        'I' => 'Interés',
                        'M' => 'Mora',
                        default => $state
                    }),
                Infolists\Components\TextEntry::make('MontoExonerado')
                    ->label('Monto')
                    ->money('PEN'),
                Infolists\Components\TextEntry::make('UsuarioAprobador')
                    ->label('Usuario Aprobador'),
                Infolists\Components\TextEntry::make('Comentario')
                    ->label('Comentario'),
                Infolists\Components\TextEntry::make('FechaExoneracion')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}
