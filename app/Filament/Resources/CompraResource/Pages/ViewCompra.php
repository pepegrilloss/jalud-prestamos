<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewCompra extends ViewRecord
{
    protected static string $resource = CompraResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Información del Comprobante')
                    ->schema([
                        Components\TextEntry::make('tipoComprobante.Nombre')
                            ->label('Tipo de Comprobante'),
                        Components\TextEntry::make('Numero')
                            ->label('Serie / Número'),
                        Components\TextEntry::make('FechaEmision')
                            ->label('Fecha Emisión')
                            ->date('d/m/Y'),
                    ])->columns(3),

                Components\Section::make('Proveedor')
                    ->schema([
                        Components\TextEntry::make('NombreProveedor')
                            ->label('Nombre del Proveedor'),
                    ]),

                Components\Section::make('Detalle de Compra')
                    ->schema([
                        Components\RepeatableEntry::make('detalles')
                            ->label('Productos / Servicios')
                            ->schema([
                                Components\TextEntry::make('ProductoServicio')
                                    ->label('Producto o Servicio'),
                                Components\TextEntry::make('Cantidad')
                                    ->label('Cantidad'),
                                Components\TextEntry::make('PrecioUnitario')
                                    ->label('Precio Unitario')
                                    ->money('PEN'),
                                Components\TextEntry::make('Subtotal')
                                    ->label('Subtotal')
                                    ->money('PEN'),
                            ])
                            ->columns(4),
                    ]),

                Components\Section::make('Totales')
                    ->schema([
                        Components\TextEntry::make('SubtotalBase')
                            ->label('Subtotal Base')
                            ->money('PEN'),
                        Components\TextEntry::make('MontoIGV')
                            ->label('IGV (18%)')
                            ->money('PEN'),
                        Components\TextEntry::make('Total')
                            ->label('Total')
                            ->money('PEN')
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                    ])->columns(3),

                Components\Section::make('Observaciones')
                    ->schema([
                        Components\TextEntry::make('Observaciones')
                            ->label('Observaciones')
                            ->default('Sin observaciones'),
                    ])
                    ->visible(fn($record) => !empty($record->Observaciones)),
            ]);
    }
}
