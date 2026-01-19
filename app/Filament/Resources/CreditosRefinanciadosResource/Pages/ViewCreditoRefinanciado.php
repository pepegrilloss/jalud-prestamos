<?php

namespace App\Filament\Resources\CreditosRefinanciadosResource\Pages;

use App\Filament\Resources\CreditosRefinanciadosResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewCreditoRefinanciado extends ViewRecord
{
    protected static string $resource = CreditosRefinanciadosResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Información del Crédito Refinanciado')
                    ->schema([
                        Infolists\Components\TextEntry::make('proposicion.CodigoCredito')
                            ->label('Código de Crédito'),
                        
                        Infolists\Components\TextEntry::make('proposicion.cliente.NombresApellidos')
                            ->label('Cliente'),
                        
                        Infolists\Components\TextEntry::make('proposicion.tipoCredito.Descripcion')
                            ->label('Tipo de Crédito'),
                        
                        Infolists\Components\TextEntry::make('proposicion.MontoTotal')
                            ->label('Monto Total')
                            ->money('PEN'),
                        
                        Infolists\Components\TextEntry::make('proposicion.MontoInteres')
                            ->label('Interés Total')
                            ->money('PEN'),
                        
                        Infolists\Components\TextEntry::make('proposicion.Plazo')
                            ->label('Plazo (días)'),
                        
                        Infolists\Components\TextEntry::make('proposicion.NumeroCuotas')
                            ->label('Número de Cuotas'),
                        
                        Infolists\Components\TextEntry::make('FechaGeneracion')
                            ->label('Fecha de Generación')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Pagos Realizados')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('pagos')
                            ->schema([
                                Infolists\Components\TextEntry::make('MontoPagado')
                                    ->label('Monto')
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

    protected function getHeaderActions(): array
    {
        return [];
    }
}
