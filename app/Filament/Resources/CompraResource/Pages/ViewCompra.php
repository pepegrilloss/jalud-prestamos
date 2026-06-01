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
                        Components\TextEntry::make('proveedor.Nombre')
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
                        Components\TextEntry::make('TipoIGV')
                            ->label('Tipo IGV')
                            ->badge()
                            ->color(fn(string $state): string => $state === 'EXONERADO' ? 'success' : 'warning')
                            ->formatStateUsing(function (string $state): string {
                                $tipo = \App\Models\TipoIgv::where('Codigo', $state)->first();
                                return $tipo ? $tipo->Nombre . ' (' . number_format($tipo->Porcentaje, 1) . '%)' : $state;
                            }),
                        Components\TextEntry::make('MontoIGV')
                            ->label('IGV')
                            ->money('PEN'),
                        Components\TextEntry::make('Total')
                            ->label('Total')
                            ->money('PEN')
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                        Components\TextEntry::make('TipoCompra')
                            ->label('Tipo Compra')
                            ->badge()
                            ->color(fn(string $state): string => $state === 'CREDITO' ? 'info' : 'gray')
                            ->formatStateUsing(fn(string $state): string => $state === 'CREDITO' ? 'Crédito' : 'Contado'),
                        Components\TextEntry::make('EstadoPago')
                            ->label('Estado Pago')
                            ->badge()
                            ->color(fn(string $state): string => $state === 'PENDIENTE' ? 'danger' : 'success')
                            ->formatStateUsing(fn(string $state): string => $state === 'PENDIENTE' ? 'Pendiente' : 'Pagado'),
                        Components\TextEntry::make('FechaPago')
                            ->label('Fecha Pago')
                            ->date('d/m/Y H:i')
                            ->visible(fn($record): bool => filled($record->FechaPago)),
                    ])->columns(4),

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
