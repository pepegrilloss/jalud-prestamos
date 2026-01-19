<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use App\Exports\DescargarPagosCredito;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Actions;
use Filament\Actions\Action;

class ViewCredito extends ViewRecord
{
    protected static string $resource = CreditoResource::class;

    protected static ?string $title = 'Ver Crédito';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargar_pagos')
                ->label('Descargar Pagos (PDF)')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn() => route('descargar-pagos.pdf', $this->record->CreditoID))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Información de la Proposición')
                    ->schema([
                        Infolists\Components\TextEntry::make('proposicion.CodigoCredito')
                            ->label('Código de Crédito'),

                        Infolists\Components\TextEntry::make('proposicion.cliente.NombresApellidos')
                            ->label('Cliente'),

                        Infolists\Components\TextEntry::make('proposicion.cliente.DNI')
                            ->label('DNI'),

                        Infolists\Components\TextEntry::make('proposicion.MontoTotalPagar')
                            ->label('Monto + Interés')
                            ->money('PEN'),

                        Infolists\Components\TextEntry::make('proposicion.TasaInteres')
                            ->label('Tasa (%)'),

                        Infolists\Components\TextEntry::make('proposicion.Plazo')
                            ->label('Plazo (días)'),

                        Infolists\Components\TextEntry::make('proposicion.NumeroCuotas')
                            ->label('Número de Cuotas'),

                        Infolists\Components\TextEntry::make('proposicion.MontoCuota')
                            ->label('Monto por Cuota')
                            ->money('PEN'),

                        Infolists\Components\TextEntry::make('proposicion.MontoInteres')
                            ->label('Monto Total de Interés')
                            ->money('PEN'),

                        Infolists\Components\TextEntry::make('proposicion.TasaMora')
                            ->label('Tasa de Mora (%)'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Información del Crédito Generado')
                    ->schema([
                        Infolists\Components\TextEntry::make('FechaGeneracion')
                            ->label('Fecha de Generación')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('tipoPago.Nombre')
                            ->label('Tipo de Pago'),

                        Infolists\Components\TextEntry::make('ComentarioGeneracion')
                            ->label('Comentario de Generación'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Pagos Realizados')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('pagos')
                            ->schema([
                                Infolists\Components\TextEntry::make('MontoPagado')
                                    ->label('Cuota')
                                    ->money('PEN'),
                                
                                Infolists\Components\TextEntry::make('FechaPago')
                                    ->label('Fecha Pago')
                                    ->dateTime('d/m/Y H:i'),
                                
                                Infolists\Components\TextEntry::make('EsPagoAMayor')
                                    ->label('Tipo')
                                    ->formatStateUsing(fn($state) => $state ? 'Pago a Mayor' : 'Pago Normal'),
                                
                                Infolists\Components\TextEntry::make('EsMora')
                                    ->label('Es Mora')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'danger' : 'success')
                                    ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No'),
                                
                                Infolists\Components\TextEntry::make('UsuarioRegistro')
                                    ->label('Usuario'),
                                
                                Infolists\Components\TextEntry::make('Comentario')
                                    ->label('Comentario')
                                    ->columnSpanFull(),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Este método ahora no es necesario ya que usamos las relaciones directamente
        return $data;
    }
}
