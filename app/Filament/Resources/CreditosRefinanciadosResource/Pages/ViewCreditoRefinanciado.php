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

                Infolists\Components\Section::make('Historial de Pagos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('cuotas')
                            ->schema([
                                Infolists\Components\TextEntry::make('NumeroCuota')
                                    ->label('Cuota #'),
                                
                                Infolists\Components\TextEntry::make('MontoCuota')
                                    ->label('Monto')
                                    ->money('PEN'),
                                
                                Infolists\Components\TextEntry::make('MontoPagado')
                                    ->label('Pagado')
                                    ->money('PEN'),
                                
                                Infolists\Components\TextEntry::make('SaldoPendiente')
                                    ->label('Saldo')
                                    ->money('PEN'),
                                
                                Infolists\Components\TextEntry::make('Estado')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn(string $state): string => match($state) {
                                        'PAGADA' => 'success',
                                        'PENDIENTE' => 'warning',
                                        'VENCIDA' => 'danger',
                                        'MORA' => 'danger',
                                        default => 'gray',
                                    }),
                                
                                Infolists\Components\TextEntry::make('FechaPago')
                                    ->label('Fecha Pago')
                                    ->dateTime('d/m/Y')
                                    ->placeholder('Sin pago'),
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
