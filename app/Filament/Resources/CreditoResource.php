<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditoResource\Pages;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\TipoPago;
use App\Models\Zona;
use App\Models\TipoCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class CreditoResource extends Resource
{
    protected static ?string $model = Credito::class;

    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?int $navigationSort = 8;
    protected static ?string $label = 'Créditos Generados';
    protected static ?string $pluralLabel = 'Créditos Generados';

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\Credito::where('Activo', true)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    protected static ?string $recordTitleAttribute = 'CreditoID';

    public static function getGloballySearchableAttributes(): array
    {
        return ['proposicion.CodigoCredito', 'proposicion.cliente.NombresApellidos', 'proposicion.cliente.DNI'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return 'Crédito: ' . ($record->proposicion?->CodigoCredito ?? '#' . $record->CreditoID) . ' (' . ($record->proposicion?->cliente?->NombresApellidos ?? 'Sin cliente') . ')';
    }


    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_credito') ?? false;
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Proposición')
                    ->schema([
                        Forms\Components\TextInput::make('proposicion_codigocredito')
                            ->label('Código de Crédito')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cliente_nombre')
                            ->label('Cliente')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cliente_dni')
                            ->label('DNI')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_monto')
                            ->label('Monto Total')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_tasa')
                            ->label('Tasa (%)')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_plazo')
                            ->label('Plazo (días)')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cuotas')
                            ->label('Número de Cuotas')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_monto_cuota')
                            ->label('Monto por Cuota')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_interes')
                            ->label('Monto Total de Interés')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_mora')
                            ->label('Tasa de Mora (%)')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Información del Crédito Generado')
                    ->schema([
                        Forms\Components\TextInput::make('FechaGeneracion')
                            ->label('Fecha de Generación')
                            ->disabled(),

                        Forms\Components\Select::make('TipoPagoID')
                            ->label('Tipo de Pago')
                            ->relationship('tipoPago', 'Nombre')
                            ->disabled(),

                        Forms\Components\Textarea::make('ComentarioGeneracion')
                            ->label('Comentario de Generación')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.tipoCredito.Descripcion')
                    ->label('Tipo de Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.ZonaID')
                    ->label('Zona')
                    ->getStateUsing(function ($record) {
                        return $record->proposicion?->zona?->Nombre ?? '-';
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.TasaInteres')
                    ->label('Tasa (%)')
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', '') . ' %')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoInteres')
                    ->label('Interés')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotalPagar')
                    ->label('Monto + Interés')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.SaldoPendiente')
                    ->label('Saldo Pendiente')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaVencimiento')
                    ->label('Fecha Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->FechaVencimiento)
                            return 'gray';

                        if ($record->FechaVencimiento < today()) {
                            if (($record->proposicion?->SaldoPendiente ?? 0) <= 0) {
                                return 'success';
                            }
                            return 'danger'; // Vencido (fecha pasada) = Rojo
                        }

                        $diasFaltantes = today()->diffInDays($record->FechaVencimiento);
                        if ($diasFaltantes <= 5) {
                            return 'warning'; // Próximo a vencer (0-5 días) = Amarillo
                        }

                        return 'success'; // Al día (más de 5 días) = Verde
                    }),

                Tables\Columns\TextColumn::make('mora_acumulada')
                    ->label('Mora Acumulada')
                    ->money('PEN')
                    ->getStateUsing(function ($record) {
                        return $record->moras()?->latest('FechaMora')?->first()?->MoraAcumulada ?? 0;
                    })
                    ->color(function ($state) {
                        return $state > 0 ? 'danger' : 'success';
                    })
                    ->sortable(false),

                Tables\Columns\TextColumn::make('FechaGeneracion')
                    ->label('Fecha Generación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([

                Tables\Filters\Filter::make('filtros_dinamicos')
                    ->form([
                        Forms\Components\Select::make('campos_activos')
                            ->label('Seleccionar Filtros a Aplicar')
                            ->placeholder('Haz clic para elegir filtros...')
                            ->multiple()
                            ->options([
                                'sede' => 'Sede',
                                'tipo_pago' => 'Tipo de Pago',
                                'zona' => 'Zona',
                                'cliente' => 'Cliente',
                                'tipo_credito' => 'Tipo de Crédito',
                            ])
                            ->live()
                            ->columnSpanFull()
                            ->native(false),

                        Forms\Components\Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 4,
                        ])
                            ->schema([
                                Forms\Components\Select::make('SedeID')
                                    ->label('Sede')
                                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => auth()->user()->esAdmin() && in_array('sede', $get('campos_activos') ?? []))
                                    ->live(),

                                Forms\Components\Select::make('TipoPagoID')
                                    ->label('Tipo de Pago')
                                    ->options(\App\Models\TipoPago::where('Activo', true)->pluck('Nombre', 'TipoPagoID'))
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => in_array('tipo_pago', $get('campos_activos') ?? []))
                                    ->live(),

                                Forms\Components\Select::make('zona')
                                    ->label('Zona')
                                    ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID')->toArray())
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => in_array('zona', $get('campos_activos') ?? []))
                                    ->live(),

                                Forms\Components\Select::make('cliente')
                                    ->label('Cliente')
                                    ->options(function () {
                                        return \App\Models\Cliente::where('Activo', true)
                                            ->whereHas('proposiciones.credito')
                                            ->pluck('NombresApellidos', 'ClienteID')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => in_array('cliente', $get('campos_activos') ?? []))
                                    ->live(),

                                Forms\Components\Select::make('tipoCredito')
                                    ->label('Tipo de Crédito')
                                    ->options(TipoCredito::where('Activo', true)->pluck('Descripcion', 'TipoCreditoID')->toArray())
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => in_array('tipo_credito', $get('campos_activos') ?? []))
                                    ->live(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $activos = $data['campos_activos'] ?? [];
                        return $query
                            ->when(
                                in_array('sede', $activos) && !empty($data['SedeID']),
                                fn(Builder $q) => $q->where('SedeID', $data['SedeID'])
                            )
                            ->when(
                                in_array('tipo_pago', $activos) && !empty($data['TipoPagoID']),
                                fn(Builder $q) => $q->where('TipoPagoID', $data['TipoPagoID'])
                            )
                            ->when(
                                in_array('zona', $activos) && !empty($data['zona']),
                                fn(Builder $q) => $q->whereHas('proposicion', fn(Builder $subQ) => $subQ->where('ZonaID', $data['zona']))
                            )
                            ->when(
                                in_array('cliente', $activos) && !empty($data['cliente']),
                                fn(Builder $q) => $q->whereHas('proposicion.cliente', fn(Builder $subQ) => $subQ->where('ClienteID', $data['cliente']))
                            )
                            ->when(
                                in_array('tipo_credito', $activos) && !empty($data['tipoCredito']),
                                fn(Builder $q) => $q->whereHas('proposicion', fn(Builder $subQ) => $subQ->where('TipoCreditoID', $data['tipoCredito']))
                            );
                    }),
            ])
            ->filtersFormColumns(1)
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->modifyQueryUsing(function (Builder $query, \Livewire\Component $livewire) {
                $query->with([
                    'proposicion' => fn($q) => $q->with(['cliente', 'zona', 'tipoCredito']),
                    'tipoPago',
                    'pagos' => fn($q) => $q->where('Activo', 1),
                ])
                    // Excluir créditos de proposiciones refinanciadas
                    ->whereHas('proposicion', function (Builder $q) {
                        $q->where('FueRefinanciada', 0);
                    });

                if (property_exists($livewire, 'fechaFiltro') && !empty($livewire->fechaFiltro)) {
                    $query->whereDate('FechaGeneracion', $livewire->fechaFiltro);
                }

                return $query;
            })
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('descargar_libreta')
                    ->label('Excel')
                    ->tooltip('Descargar Libreta de Pagos (Excel)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn($record) => route('libreta-pagos.descargar', $record->CreditoID))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('descargar_libreta_html')
                    ->label('Imprimir')
                    ->tooltip('Ver Libreta de Pagos para Imprimir')
                    ->icon('heroicon-o-printer')
                    ->url(fn($record) => route('libreta-pagos.html', $record->CreditoID))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('descargar_ticket')
                    ->label('Descargar Ticket')
                    ->icon('heroicon-o-ticket')
                    ->color('danger')
                    ->url(fn($record) => route('ticket.descargar', $record->CreditoID))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->defaultSort('FechaGeneracion', 'desc')
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditos::route('/'),
            'view' => Pages\ViewCredito::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\CreditoGeneradoTotalWidget::class,
            \App\Filament\Widgets\CreditoGeneradoCantidadWidget::class,
        ];
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) {
            return false;
        }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
            \Filament\Notifications\Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden realizar operaciones. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();
            return false;
        }
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (!parent::canEdit($record)) {
            return false;
        }

        // Si tiene FechaCierre, no se puede editar
        if ($record->FechaCierre !== null) {
            return false;
        }

        // Verificar si el día de generación está cerrado
        $fechaGeneracion = $record->FechaGeneracion->toDateString();
        $fechaHoy = now()->toDateString();

        if ($fechaGeneracion !== $fechaHoy) {
            $diaDel = \App\Models\AperturaCierreDia::whereDate('Fecha', $fechaGeneracion)->first();
            if ($diaDel && $diaDel->EstadoDia === 'CERRADO') {
                return false;
            }
        }
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (!parent::canDelete($record)) {
            return false;
        }

        // Si tiene FechaCierre, no se puede eliminar
        if ($record->FechaCierre !== null) {
            return false;
        }

        // Verificar si el día de generación está cerrado
        $fechaGeneracion = $record->FechaGeneracion->toDateString();
        $fechaHoy = now()->toDateString();

        if ($fechaGeneracion !== $fechaHoy) {
            $diaDel = \App\Models\AperturaCierreDia::whereDate('Fecha', $fechaGeneracion)->first();
            if ($diaDel && $diaDel->EstadoDia === 'CERRADO') {
                return false;
            }
        }
        return true;
    }

    public static function getInfolistSchema(): array
    {
        return [
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

                            Infolists\Components\TextEntry::make('SaldoActual')
                                ->label('Saldo Actual')
                                ->money('PEN')
                                ->getStateUsing(fn($record) => $record->proposicion?->SaldoPendiente ?? 0)
                                ->color('danger')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold),

                            Infolists\Components\TextEntry::make('PagosRealizados')
                                ->label('N° Pagos')
                                ->getStateUsing(fn($record) => $record->relationLoaded('pagos') ? $record->pagos->count() : $record->pagos()->where('Activo', 1)->count())
                                ->icon('heroicon-m-check-circle')
                                ->badge()
                                ->color('success'),

                            Infolists\Components\TextEntry::make('FechaGeneracion')
                                ->label('Fecha de Generación')
                                ->icon('heroicon-m-calendar')
                                ->dateTime('d/m/Y')
                                ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                            Infolists\Components\TextEntry::make('FechaVencimiento')
                                ->label('Fecha de Vencimiento')
                                ->date('d/m/Y')
                                ->icon('heroicon-m-calendar')
                                ->color(function ($record) {
                                    if (!$record->FechaVencimiento?->isPast()) {
                                        return 'success';
                                    }
                                    return ($record->proposicion?->SaldoPendiente ?? 0) > 0 ? 'danger' : 'success';
                                }),

                            Infolists\Components\TextEntry::make('DiasVencimiento')
                                ->label('Días Vencido')
                                ->getStateUsing(function ($record) {
                                    if (!$record->FechaVencimiento || !$record->FechaVencimiento->isPast()) {
                                        return null;
                                    }
                                    if (($record->proposicion?->SaldoPendiente ?? 0) <= 0) {
                                        return null;
                                    }
                                    $dias = $record->FechaVencimiento->diffInDays(today());
                                    return $dias . ' día' . ($dias !== 1 ? 's' : '');
                                })
                                ->icon('heroicon-m-exclamation-circle')
                                ->color('danger')
                                ->visible(fn($record) => ($record->FechaVencimiento?->isPast() ?? false) && ($record->proposicion?->SaldoPendiente ?? 0) > 0)
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
                    Infolists\Components\ViewEntry::make('pagos_table')
                        ->label('')
                        ->view('components.pagos-table')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Moras Acumuladas')
                ->icon('heroicon-m-clock')
                ->collapsed()
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
        ];
    }
}
