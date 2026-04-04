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
                Infolists\Components\Section::make('Información Principal del Crédito')
                    ->description('Detalles generales sobre el cliente y los montos del crédito.')
                    ->icon('heroicon-m-information-circle')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('proposicion.CodigoCredito')
                                    ->label('Código de Crédito')
                                    ->icon('heroicon-m-hashtag')
                                    ->badge()
                                    ->color('primary')
                                    ->columnSpan(1),

                                Infolists\Components\TextEntry::make('proposicion.cliente.NombresApellidos')
                                    ->label('Cliente')
                                    ->icon('heroicon-m-user')
                                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('proposicion.cliente.DNI')
                                    ->label('DNI')
                                    ->icon('heroicon-m-identification')
                                    ->columnSpan(1),
                            ]),

                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('proposicion.zona.Nombre')
                                    ->label('Zona')
                                    ->icon('heroicon-m-map-pin'),

                                Infolists\Components\TextEntry::make('proposicion.TasaInteres')
                                    ->label('Tasa de Interés')
                                    ->icon('heroicon-m-receipt-percent')
                                    ->suffix('%'),

                                Infolists\Components\TextEntry::make('proposicion.Plazo')
                                    ->label('Plazo')
                                    ->icon('heroicon-m-calendar-days')
                                    ->suffix(' días'),

                                Infolists\Components\TextEntry::make('proposicion.NumeroCuotas')
                                    ->label('Total Cuotas')
                                    ->icon('heroicon-m-numbered-list'),
                            ])->extraAttributes(['class' => 'mt-4']),

                        Infolists\Components\Fieldset::make('Despliegue Financiero')
                            ->schema([
                                Infolists\Components\TextEntry::make('proposicion.MontoTotal')
                                    ->label('Capital Solicitado')
                                    ->money('PEN')
                                    ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                                Infolists\Components\TextEntry::make('proposicion.MontoInteres')
                                    ->label('Interés Generado')
                                    ->money('PEN')
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('proposicion.MontoTotalPagar')
                                    ->label('Monto Final a Pagar')
                                    ->money('PEN')
                                    ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('proposicion.MontoCuota')
                                    ->label('Monto por Cuota')
                                    ->money('PEN')
                                    ->color('info')
                                    ->badge(),
                            ])->columns(4)
                    ]),

                Infolists\Components\Section::make('Datos de Aprobación y Generación')
                    ->icon('heroicon-m-check-badge')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('FechaGeneracion')
                            ->label('Fecha de Generación')
                            ->icon('heroicon-m-clock')
                            ->dateTime('d/m/Y H:i A'),

                        Infolists\Components\TextEntry::make('tipoPago.Nombre')
                            ->label('Tipo de Desembolso')
                            ->badge()
                            ->icon('heroicon-m-banknotes'),

                        Infolists\Components\TextEntry::make('proposicion.TasaMora')
                            ->label('Infracción de Mora')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->color('danger')
                            ->suffix('% Diario'),

                        Infolists\Components\TextEntry::make('ComentarioGeneracion')
                            ->label('Comentarios Administrativos')
                            ->columnSpanFull(),
                    ])->columns(3),

                Infolists\Components\Section::make('Registro de Pagos e Historial')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('pagos')
                            ->label('')
                            ->schema([
                                Infolists\Components\Grid::make(5)
                                ->schema([
                                    Infolists\Components\TextEntry::make('MontoPagado')
                                        ->label('Pagado')
                                        ->money('PEN')
                                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                        ->color('success')
                                        ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large),

                                    Infolists\Components\TextEntry::make('FechaPago')
                                        ->label('Realizado el')
                                        ->dateTime('d/m/Y h:i A')
                                        ->icon('heroicon-m-calendar'),

                                    Infolists\Components\TextEntry::make('EsPagoAMayor')
                                        ->label('Método')
                                        ->badge()
                                        ->color(fn($state) => $state ? 'info' : 'primary')
                                        ->formatStateUsing(fn($state) => $state ? 'Pago a Mayor' : 'Pago Normal'),

                                    Infolists\Components\TextEntry::make('EsMora')
                                        ->label('Contiene Mora')
                                        ->badge()
                                        ->color(fn($state) => $state ? 'danger' : 'success')
                                        ->formatStateUsing(fn($state) => $state ? 'Sí Contiene' : 'Libre de Mora')
                                        ->icon(fn($state) => $state ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle'),

                                    Infolists\Components\TextEntry::make('UsuarioRegistro')
                                        ->label('Recibido por')
                                        ->icon('heroicon-m-user-circle'),
                                ]),
                                Infolists\Components\TextEntry::make('Comentario')
                                    ->label('Nota Adicional')
                                    ->icon('heroicon-m-chat-bubble-bottom-center-text')
                                    ->visible(fn($state) => filled($state))
                                    ->columnSpanFull(),
                            ])
                            ->contained(true)
                    ]),

                Infolists\Components\Section::make('Moras Acumuladas')
                    ->icon('heroicon-m-clock')
                    ->collapsed() // Moras is less common to view, keep it collapsed by default
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('moras')
                            ->label('')
                            ->schema([
                                Infolists\Components\Grid::make(5)
                                ->schema([
                                    Infolists\Components\TextEntry::make('FechaMora')
                                        ->label('Fecha')
                                        ->date('d/m/Y')
                                        ->icon('heroicon-m-calendar-days'),

                                    Infolists\Components\TextEntry::make('SaldoPendiente')
                                        ->label('Saldo Base')
                                        ->money('PEN'),

                                    Infolists\Components\TextEntry::make('PorcentajeMora')
                                        ->label('Penalidad')
                                        ->suffix('%'),

                                    Infolists\Components\TextEntry::make('MontoMora')
                                        ->label('Mora Calculada')
                                        ->money('PEN')
                                        ->color('warning')
                                        ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                                    Infolists\Components\TextEntry::make('MoraAcumulada')
                                        ->label('Deuda Histórica Acumulada')
                                        ->money('PEN')
                                        ->color('danger')
                                        ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                ])
                            ])
                            ->contained(true)
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Este método ahora no es necesario ya que usamos las relaciones directamente
        return $data;
    }
}
