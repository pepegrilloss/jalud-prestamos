<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewGasto extends ViewRecord
{
    protected static string $resource = GastoResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Información del Comprobante')
                    ->schema([
                        Components\TextEntry::make('tipoComprobanteGasto.Nombre')
                            ->label('Tipo de Comprobante'),
                        Components\TextEntry::make('Numero')
                            ->label('Número'),
                        Components\TextEntry::make('FechaEmision')
                            ->label('Fecha Emisión')
                            ->date('d/m/Y'),
                    ])->columns(3),

                Components\Section::make('Datos del Gasto')
                    ->schema([
                        Components\TextEntry::make('proveedor.Nombre')
                            ->label('Proveedor'),
                        Components\TextEntry::make('motivo.Nombre')
                            ->label('Motivo'),
                        Components\IconEntry::make('EsGasto')
                            ->label('¿Es gasto?')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-badge')
                            ->falseIcon('heroicon-o-x-circle'),
                        Components\TextEntry::make('FuenteTesoreria')
                            ->label('Origen del dinero')
                            ->visible(fn ($record) => filled($record->OrigenTesoreriaTipo)),
                    ])->columns(3),

                Components\Section::make('Detalle del Gasto')
                    ->schema([
                        Components\RepeatableEntry::make('detalles')
                            ->label('Líneas de Gasto')
                            ->schema([
                                Components\TextEntry::make('Descripcion')
                                    ->label('Descripción'),
                                Components\TextEntry::make('Monto')
                                    ->label('Monto')
                                    ->money('PEN'),
                            ])
                            ->columns(2),
                    ]),

                Components\Section::make('Total')
                    ->schema([
                        Components\TextEntry::make('Total')
                            ->label('Total')
                            ->money('PEN')
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                    ]),

                Components\Section::make('Observaciones')
                    ->schema([
                        Components\TextEntry::make('Observaciones')
                            ->label('Observaciones')
                            ->default('Sin observaciones'),
                    ])
                    ->visible(fn ($record) => ! empty($record->Observaciones)),
            ]);
    }
}
