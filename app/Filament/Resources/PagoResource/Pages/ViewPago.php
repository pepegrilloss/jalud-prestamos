<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;

class ViewPago extends ViewRecord
{
    protected static string $resource = PagoResource::class;

    public function mount(int|string $record): void
    {
        if (auth()->user()?->esPromotorCobrador()) {
            abort(403);
        }

        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');
        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='{$url}' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Ver Pago</span>
            </div>
        ");
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Detalle del Pago')
                    ->description('Informacion principal del pago registrado')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('TipoPago')
                                    ->label('Metodo de Pago')
                                    ->icon('heroicon-m-credit-card')
                                    ->badge()
                                    ->color(fn($state) => match ($state) {
                                        'EFECTIVO' => 'success',
                                        'YAPE_PLIN' => 'info',
                                        'TRANSFERENCIA_BANCARIA' => 'warning',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'EFECTIVO' => 'EFECTIVO',
                                        'YAPE_PLIN' => 'YAPE O PLIN',
                                        'TRANSFERENCIA_BANCARIA' => 'TRANSFERENCIA BANCARIA',
                                        default => $state,
                                    }),

                                Components\TextEntry::make('MontoPagado')
                                    ->label('Monto Pagado')
                                    ->icon('heroicon-m-currency-dollar')
                                    ->money('PEN')
                                    ->size(Components\TextEntry\TextEntrySize::Large)
                                    ->weight(FontWeight::Bold),

                                Components\TextEntry::make('TipoConcepto')
                                    ->label('Concepto')
                                    ->icon('heroicon-m-tag')
                                    ->badge()
                                    ->color(fn($state) => match ($state) {
                                        'C' => 'success',
                                        'I' => 'info',
                                        'M' => 'warning',
                                        'P' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'C' => 'Cuota (Capital)',
                                        'I' => 'Interes',
                                        'M' => 'Mora',
                                        'P' => 'Penalidad',
                                        default => $state,
                                    }),

                                Components\TextEntry::make('FechaPago')
                                    ->label('Fecha de Pago')
                                    ->icon('heroicon-m-calendar')
                                    ->date('d/m/Y'),
                            ]),

                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('estado_pago')
                                    ->label('Estado')
                                    ->icon('heroicon-m-check-circle')
                                    ->state(fn($record) => $record->Activo ? 'ACTIVO' : 'ANULADO')
                                    ->badge()
                                    ->color(fn($record) => $record->Activo ? 'success' : 'danger'),

                                Components\TextEntry::make('origen_pago')
                                    ->label('Origen')
                                    ->icon('heroicon-m-arrow-path')
                                    ->state(function ($record) {
                                        if ($record->PagoOrigenID) return 'Traslado de Pago';
                                        if ($record->solicitudResolucion) return 'Excedente';
                                        if ($record->EsPagoAutomatico && $record->TipoConcepto === 'C') return 'Refinanciamiento';
                                        if ($record->EsPagoAutomatico) return 'Automatico';
                                        return 'Normal';
                                    })
                                    ->badge()
                                    ->color(fn($record) => match (true) {
                                        (bool) $record->PagoOrigenID => 'danger',
                                        (bool) $record->solicitudResolucion => 'success',
                                        $record->EsPagoAutomatico && $record->TipoConcepto === 'C' => 'info',
                                        $record->EsPagoAutomatico => 'warning',
                                        default => 'gray',
                                    }),

                                Components\TextEntry::make('EsMora')
                                    ->label('Mora')
                                    ->icon('heroicon-m-exclamation-triangle')
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn($record) => $record->EsMora),

                                Components\TextEntry::make('EsPagoAMayor')
                                    ->label('Pago a Mayor')
                                    ->icon('heroicon-m-plus-circle')
                                    ->badge()
                                    ->color('info')
                                    ->state(fn($record) => $record->SolicitudResolucionID ? 'EXTORNO (a mayor)' : 'SI')
                                    ->visible(fn($record) => $record->EsPagoAMayor),

                                Components\TextEntry::make('EsPagoAMayorPorMora')
                                    ->label('A Mayor x Mora')
                                    ->icon('heroicon-m-shield-exclamation')
                                    ->badge()
                                    ->color('danger')
                                    ->visible(fn($record) => $record->EsPagoAMayorPorMora),

                                Components\TextEntry::make('EsPagoInicial')
                                    ->label('Pago Inicial')
                                    ->icon('heroicon-m-star')
                                    ->badge()
                                    ->color('primary')
                                    ->visible(fn($record) => $record->EsPagoInicial),

                                Components\TextEntry::make('EsPagoForzado')
                                    ->label('Forzado')
                                    ->icon('heroicon-m-bolt')
                                    ->badge()
                                    ->color('danger')
                                    ->visible(fn($record) => $record->EsPagoForzado),

                                Components\TextEntry::make('EstadoTraslado')
                                    ->label('Estado Traslado')
                                    ->icon('heroicon-m-arrows-right-left')
                                    ->badge()
                                    ->color(fn($state) => match ($state) {
                                        'COMPLETADO' => 'success',
                                        'PENDIENTE' => 'warning',
                                        default => 'gray',
                                    })
                                    ->visible(fn($record) => $record->PagoOrigenID !== null),
                            ])->extraAttributes(['class' => 'mt-4']),
                    ]),

                Components\Section::make('Cliente y Credito')
                    ->description('Informacion del cliente y credito asociado')
                    ->icon('heroicon-m-user-group')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('cliente_nombre')
                                    ->label('Cliente')
                                    ->icon('heroicon-m-user')
                                    ->weight(FontWeight::SemiBold)
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->cliente?->NombresApellidos
                                            ?? $record->cuota?->credito?->proposicion?->cliente?->NombresApellidos
                                            ?? '-';
                                    }),

                                Components\TextEntry::make('cliente_dni')
                                    ->label('DNI')
                                    ->icon('heroicon-m-identification')
                                    ->badge()
                                    ->color('primary')
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->cliente?->DNI
                                            ?? $record->cuota?->credito?->proposicion?->cliente?->DNI
                                            ?? '-';
                                    }),

                                Components\TextEntry::make('cliente_estado_crediticio')
                                    ->label('Estado Crediticio')
                                    ->icon('heroicon-m-shield-check')
                                    ->badge()
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->cliente?->Estado
                                            ?? $record->cuota?->credito?->proposicion?->cliente?->Estado
                                            ?? '-';
                                    })
                                    ->color(fn($state) => match ($state) {
                                        'NO OBSERVADO' => 'success',
                                        'OBSERVADO' => 'danger',
                                        default => 'warning',
                                    }),
                            ]),

                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('codigo_credito')
                                    ->label('Codigo Credito')
                                    ->icon('heroicon-m-hashtag')
                                    ->badge()
                                    ->color('primary')
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->CodigoCredito
                                            ?? $record->cuota?->credito?->proposicion?->CodigoCredito
                                            ?? '-';
                                    }),

                                Components\TextEntry::make('tipo_credito')
                                    ->label('Tipo de Credito')
                                    ->icon('heroicon-m-clipboard-document-list')
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->tipoCredito?->Descripcion
                                            ?? $record->cuota?->credito?->proposicion?->tipoCredito?->Descripcion
                                            ?? '-';
                                    }),

                                Components\TextEntry::make('zona_nombre')
                                    ->label('Zona')
                                    ->icon('heroicon-m-map-pin')
                                    ->badge()
                                    ->color('info')
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->zona?->Nombre
                                            ?? $record->cuota?->credito?->proposicion?->zona?->Nombre
                                            ?? $record->credito?->proposicion?->cliente?->negocio?->zona?->Nombre
                                            ?? '-';
                                    }),

                                Components\TextEntry::make('promotor_nombre')
                                    ->label('Promotor Cobrador')
                                    ->icon('heroicon-m-user-group')
                                    ->badge()
                                    ->color('success')
                                    ->state(function ($record) {
                                        $zona = $record->cuota?->credito?->proposicion?->zona
                                            ?? $record->credito?->proposicion?->zona;
                                        if (!$zona) return '-';
                                        $promotor = \App\Models\PromotorCobrador::where('ZonaID', $zona->ZonaID)
                                            ->where('Activo', 1)
                                            ->first();
                                        return $promotor?->Descripcion ?? '-';
                                    }),
                            ])->extraAttributes(['class' => 'mt-4']),

                        Components\Fieldset::make('Detalle del Credito')
                            ->schema([
                                Components\TextEntry::make('monto_total_credito')
                                    ->label('Monto Total')
                                    ->icon('heroicon-m-banknotes')
                                    ->money('PEN')
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->MontoTotal
                                            ?? $record->cuota?->credito?->proposicion?->MontoTotal
                                            ?? 0;
                                    })
                                    ->weight(FontWeight::Bold),

                                Components\TextEntry::make('saldo_pendiente')
                                    ->label('Saldo Pendiente')
                                    ->icon('heroicon-m-chart-bar')
                                    ->money('PEN')
                                    ->color('danger')
                                    ->weight(FontWeight::Bold)
                                    ->state(function ($record) {
                                        return $record->credito?->proposicion?->SaldoPendiente
                                            ?? $record->cuota?->credito?->proposicion?->SaldoPendiente
                                            ?? 0;
                                    }),

                                Components\TextEntry::make('fecha_credito')
                                    ->label('Fecha del Credito')
                                    ->icon('heroicon-m-calendar')
                                    ->date('d/m/Y')
                                    ->state(function ($record) {
                                        return $record->credito?->FechaInicio
                                            ?? $record->cuota?->credito?->FechaInicio;
                                    })
                                    ->placeholder('-'),
                            ])->columns(3),
                    ]),

                Components\Section::make('Comentarios y Notas')
                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                    ->schema([
                        Components\TextEntry::make('Comentario')
                            ->label('Comentario del Pago')
                            ->icon('heroicon-m-document-text')
                            ->placeholder('Sin comentarios')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => filled($record->Comentario)),

                Components\Section::make('Bitacora y Sistema')
                    ->icon('heroicon-m-server-stack')
                    ->collapsed()
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('UsuarioRegistro')
                                    ->label('Registrado por')
                                    ->icon('heroicon-m-user-plus'),

                                Components\TextEntry::make('FechaCreacion')
                                    ->label('Fecha de Registro')
                                    ->icon('heroicon-m-calendar')
                                    ->dateTime('d/m/Y h:i A'),

                                Components\TextEntry::make('UserModificacionID')
                                    ->label('Ult. Modif. por (ID)')
                                    ->icon('heroicon-m-pencil')
                                    ->placeholder('-'),

                                Components\TextEntry::make('FechaModificacion')
                                    ->label('Fec. Modificacion')
                                    ->icon('heroicon-m-calendar')
                                    ->dateTime('d/m/Y h:i A')
                                    ->placeholder('-'),
                            ]),

                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('Activo')
                                    ->label('Registro Activo')
                                    ->icon('heroicon-m-check-circle')
                                    ->badge()
                                    ->state(fn($record) => $record->Activo ? 'SI' : 'NO')
                                    ->color(fn($record) => $record->Activo ? 'success' : 'danger'),

                                Components\TextEntry::make('SedeID')
                                    ->label('Sede')
                                    ->icon('heroicon-m-building-office')
                                    ->state(function ($record) {
                                        return \App\Models\Sede::find($record->SedeID)?->Nombre ?? 'Sede #' . $record->SedeID;
                                    }),
                            ])->extraAttributes(['class' => 'mt-4']),
                    ]),
            ]);
    }
}
