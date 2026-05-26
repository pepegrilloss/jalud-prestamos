<?php

namespace App\Filament\Resources\TransferenciaSedeResource\Pages;

use App\Filament\Resources\TransferenciaSedeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;

class ViewTransferenciaSede extends ViewRecord
{
    protected static string $resource = TransferenciaSedeResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');
        return new \Illuminate\Support\HtmlString('
            <div class="flex items-center gap-x-3">
                <a href="' . $url . '" class="flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition">
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <span>Ver Transferencia</span>
            </div>
        ');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Información de la Transferencia')
                    ->description('Detalles del envío y estado actual')
                    ->icon('heroicon-m-truck')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('TransferenciaID')
                                    ->label('ID')
                                    ->icon('heroicon-m-hashtag')
                                    ->badge()
                                    ->color('gray'),
                                Components\TextEntry::make('Estado')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'PENDIENTE' => 'warning',
                                        'ACEPTADO' => 'success',
                                        'RECHAZADO' => 'danger',
                                        default => 'gray',
                                    })
                                    ->icon(fn(string $state): string => match ($state) {
                                        'PENDIENTE' => 'heroicon-m-clock',
                                        'ACEPTADO' => 'heroicon-m-check-circle',
                                        'RECHAZADO' => 'heroicon-m-x-circle',
                                        default => 'heroicon-m-question-mark-circle',
                                    }),
                                Components\TextEntry::make('Tipo')
                                    ->label('Tipo')
                                    ->getStateUsing(fn($record): string => match (true) {
                                        $record->EsSolicitudGerencia => 'Solicitud de Gerencia',
                                        $record->EsSolicitudCapital => 'Solicitud de Capital',
                                        default => 'Remesa',
                                    })
                                    ->badge()
                                    ->color(fn($record): string => match (true) {
                                        $record->EsSolicitudGerencia => 'warning',
                                        $record->EsSolicitudCapital => 'info',
                                        default => 'gray',
                                    }),
                            ]),
                        Components\Fieldset::make('Origen y Destino')
                            ->schema([
                                Components\Grid::make(2)
                                    ->schema([
                                        Components\TextEntry::make('sedeOrigen.Nombre')
                                            ->label('Sede Origen')
                                            ->icon('heroicon-m-arrow-up-circle')
                                            ->color('info')
                                            ->weight(FontWeight::SemiBold),
                                        Components\TextEntry::make('sedeDestino.Nombre')
                                            ->label('Sede Destino')
                                            ->icon('heroicon-m-arrow-down-circle')
                                            ->color('success')
                                            ->weight(FontWeight::SemiBold),
                                        Components\TextEntry::make('CuentaOrigen')
                                            ->label('Cuenta Origen')
                                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                                'CAJA_ABIERTA' => 'Caja Abierta',
                                                'CAJA_CHICA' => 'Caja Chica',
                                                default => $state,
                                            })
                                            ->icon('heroicon-m-credit-card'),
                                        Components\TextEntry::make('CuentaDestino')
                                            ->label('Cuenta Destino')
                                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                                'CAJA_ABIERTA' => 'Caja Abierta',
                                                'CAJA_CHICA' => 'Caja Chica',
                                                default => $state,
                                            })
                                            ->icon('heroicon-m-credit-card'),
                                    ]),
                            ]),
                        Components\Fieldset::make('Montos')
                            ->schema([
                                Components\Grid::make(2)
                                    ->schema([
                                        Components\TextEntry::make('Monto')
                                            ->money('PEN')
                                            ->weight(FontWeight::Bold)
                                            ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->icon('heroicon-m-banknotes')
                                            ->color('primary'),
                                        Components\TextEntry::make('MontoAprobado')
                                            ->label('Monto Aprobado')
                                            ->money('PEN')
                                            ->icon('heroicon-m-check-badge')
                                            ->color('success')
                                            ->visible(fn($record): bool => !is_null($record->MontoAprobado)),
                                    ]),
                            ]),
                        Components\Fieldset::make('Tiempos')
                            ->schema([
                                Components\Grid::make(2)
                                    ->schema([
                                        Components\TextEntry::make('FechaTransferencia')
                                            ->label('Fecha de Envío')
                                            ->dateTime()
                                            ->icon('heroicon-m-calendar'),
                                        Components\TextEntry::make('FechaRespuesta')
                                            ->label('Fecha de Respuesta')
                                            ->dateTime()
                                            ->icon('heroicon-m-calendar')
                                            ->visible(fn($record): bool => !is_null($record->FechaRespuesta)),
                                    ]),
                            ]),
                        Components\Fieldset::make('Responsables')
                            ->schema([
                                Components\Grid::make(2)
                                    ->schema([
                                        Components\TextEntry::make('usuarioOrigen.name')
                                            ->label('Enviado por')
                                            ->icon('heroicon-m-user'),
                                        Components\TextEntry::make('usuarioResponde.name')
                                            ->label('Respondido por')
                                            ->icon('heroicon-m-user')
                                            ->visible(fn($record): bool => !is_null($record->usuarioResponde)),
                                    ]),
                            ]),
                        Components\TextEntry::make('Observacion')
                            ->label('Observación')
                            ->icon('heroicon-m-document-text')
                            ->columnSpanFull()
                            ->placeholder('Sin observaciones'),
                    ]),

                Components\Section::make('Voucher del Depósito')
                    ->description('Comprobante de la transferencia')
                    ->icon('heroicon-m-photo')
                    ->visible(fn($record): bool => !empty($record->VoucherImagen))
                    ->schema([
                        Components\ImageEntry::make('VoucherImagen')
                            ->label('')
                            ->disk('public')
                            ->width('100%')
                            ->alignCenter()
                            ->extraImgAttributes([
                                'class' => 'rounded-lg border border-gray-200 dark:border-gray-700 mx-auto',
                                'style' => 'width: 100%; max-width: 900px; height: auto;',
                            ]),
                    ]),
            ]);
    }
}
