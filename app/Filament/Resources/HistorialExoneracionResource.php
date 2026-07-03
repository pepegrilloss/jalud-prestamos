<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HistorialExoneracionResource\Pages;
use App\Models\HistorialExoneracion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class HistorialExoneracionResource extends Resource
{
    protected static ?string $model = HistorialExoneracion::class;

    protected static ?string $navigationGroup = 'Exoneraciones';
    protected static ?int $navigationGroupSort = 100;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?int $navigationSort = 11;
    protected static ?string $label = 'Historial de Exoneración';
    protected static ?string $pluralLabel = 'Historial de Exoneraciones';

    public static function shouldRegisterNavigation(): bool
    {
        if (!parent::shouldRegisterNavigation()) { return false; }

        $user = auth()->user();
        if ($user && $user->PromotorCobradorID) {
            return false;
        }
        return true;
    }

        public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_historial::exoneracion') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('cliente_nombre')
                ->label('Cliente')
                ->disabled(),
            Forms\Components\TextInput::make('credito_codigo')
                ->label('Crédito')
                ->disabled(),
            Forms\Components\Select::make('TipoExoneracion')
                ->label('Tipo')
                ->options([
                    'P' => 'Pronto Pago',
                    'I' => 'Interés',
                    'M' => 'Mora',
                ])
                ->disabled(),
            Forms\Components\TextInput::make('MontoExonerado')
                ->label('Monto')
                ->disabled(),
            Forms\Components\TextInput::make('UsuarioAprobador')
                ->label('Usuario Aprobador')
                ->disabled(),
            Forms\Components\Textarea::make('Comentario')
                ->label('Comentario')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('credito.proposicion.CodigoCredito')
                    ->label('Crédito')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('TipoExoneracion')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => match($state) {
                        'P' => 'Pronto Pago',
                        'I' => 'Interés',
                        'M' => 'Mora',
                        default => $state
                    })
                    ->colors([
                        'success' => 'P',
                        'info' => 'I',
                        'warning' => 'M',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('MontoExonerado')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('UsuarioAprobador')
                    ->label('Aprobador')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaExoneracion')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('TipoExoneracion')
                    ->label('Tipo')
                    ->options([
                        'P' => 'Pronto Pago',
                        'I' => 'Interés',
                        'M' => 'Mora',
                    ]),
                Tables\Filters\Filter::make('FechaExoneracion')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q) => $q->whereDate('FechaExoneracion', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('FechaExoneracion', '<=', $data['hasta']));
                    }),
                Tables\Filters\Filter::make('cliente')
                    ->label('Cliente / Crédito')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('busqueda')
                            ->label('Buscar por cliente o código de crédito')
                            ->placeholder('Nombre, DNI o código...'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['busqueda'], function ($q, $busqueda) {
                            $q->where(function ($sub) use ($busqueda) {
                                $sub->whereHas('cliente', fn($cq) => $cq->where('NombresApellidos', 'like', "%{$busqueda}%"))
                                    ->orWhereHas('cliente', fn($cq) => $cq->where('DNI', 'like', "%{$busqueda}%"))
                                    ->orWhereHas('credito.proposicion', fn($cq) => $cq->where('CodigoCredito', 'like', "%{$busqueda}%"));
                            });
                        });
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['cliente', 'credito.proposicion'])
                    ->orderBy('FechaExoneracion', 'desc');
            })
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) { return false; }
        return false;
    }

    public static function canEdit($record): bool
    {
        if (!parent::canEdit($record)) { return false; }
        return false;
    }

    public static function canDelete($record): bool
    {
        if (!parent::canDelete($record)) { return false; }
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHistorialExoneraciones::route('/'),
            'view' => Pages\ViewHistorialExoneracion::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getInfolistSchema(): array
    {
        return [
            Infolists\Components\Section::make('Información de la Exoneración')
                ->icon('heroicon-o-information-circle')
                ->description('Detalle del descuento o exoneración aplicada')
                ->schema([
                    Infolists\Components\Grid::make(3)
                        ->schema([
                            Infolists\Components\TextEntry::make('cliente.NombresApellidos')
                                ->label('Cliente')
                                ->icon('heroicon-o-user')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold),
                            Infolists\Components\TextEntry::make('credito.proposicion.CodigoCredito')
                                ->label('Crédito')
                                ->icon('heroicon-o-document-text')
                                ->badge()
                                ->color('primary'),
                            Infolists\Components\TextEntry::make('sede.Nombre')
                                ->label('Sede')
                                ->icon('heroicon-o-building-office'),
                        ]),
                    Infolists\Components\Grid::make(3)
                        ->schema([
                            Infolists\Components\TextEntry::make('TipoExoneracion')
                                ->label('Tipo de Exoneración')
                                ->icon('heroicon-o-tag')
                                ->badge()
                                ->formatStateUsing(fn($state) => match($state) {
                                    'P' => 'Pronto Pago',
                                    'I' => 'Interés',
                                    'M' => 'Mora',
                                    default => $state
                                })
                                ->color(fn($state) => match($state) {
                                    'P' => 'success',
                                    'I' => 'info',
                                    'M' => 'warning',
                                    default => 'gray'
                                }),
                            Infolists\Components\TextEntry::make('MontoExonerado')
                                ->label('Monto Exonerado')
                                ->icon('heroicon-o-currency-dollar')
                                ->money('PEN')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->color('success'),
                            Infolists\Components\TextEntry::make('FechaExoneracion')
                                ->label('Fecha de Exoneración')
                                ->icon('heroicon-o-calendar')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                    Infolists\Components\Grid::make(2)
                        ->schema([
                            Infolists\Components\TextEntry::make('UsuarioAprobador')
                                ->label('Aprobado por')
                                ->icon('heroicon-o-check-badge'),
                            Infolists\Components\TextEntry::make('Comentario')
                                ->label('Comentario')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->markdown(),
                        ]),
                ]),

            Infolists\Components\Section::make('Crédito Asociado')
                ->icon('heroicon-o-banknotes')
                ->description('Estado actual del crédito al que se aplicó la exoneración')
                ->collapsed()
                ->schema([
                    Infolists\Components\Grid::make(4)
                        ->schema([
                            Infolists\Components\TextEntry::make('credito.proposicion.MontoTotal')
                                ->label('Capital')
                                ->money('PEN'),
                            Infolists\Components\TextEntry::make('credito.proposicion.SaldoPendiente')
                                ->label('Saldo Actual')
                                ->money('PEN')
                                ->color(fn($state) => (float)$state <= 0 ? 'success' : 'danger')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold),
                            Infolists\Components\TextEntry::make('credito.EstatusCreditoFinal')
                                ->label('Estado del Crédito')
                                ->badge()
                                ->color(fn($state) => match($state) {
                                    'SALDADO' => 'success',
                                    'ACTIVO' => 'warning',
                                    default => 'gray'
                                }),
                            Infolists\Components\TextEntry::make('credito.FechaSaldamiento')
                                ->label('Fecha de Saldamiento')
                                ->dateTime('d/m/Y')
                                ->visible(fn($record) => $record->credito?->EstatusCreditoFinal === 'SALDADO'),
                        ]),
                ]),

            Infolists\Components\Section::make('Pago Generado')
                ->icon('heroicon-o-receipt-percent')
                ->description('Pago automático creado al aprobar la exoneración')
                ->collapsed()
                ->visible(fn($record) => $record->solicitud?->pagoGenerado !== null)
                ->schema([
                    Infolists\Components\Grid::make(4)
                        ->schema([
                            Infolists\Components\TextEntry::make('solicitud.pagoGenerado.PagoID')
                                ->label('Pago ID')
                                ->badge()
                                ->color('info'),
                            Infolists\Components\TextEntry::make('solicitud.pagoGenerado.MontoPagado')
                                ->label('Monto')
                                ->money('PEN'),
                            Infolists\Components\TextEntry::make('solicitud.pagoGenerado.TipoConcepto')
                                ->label('Concepto')
                                ->formatStateUsing(fn($state) => match($state) {
                                    'M' => 'Mora',
                                    'I' => 'Interés',
                                    'P' => 'Pronto Pago',
                                    default => $state
                                }),
                            Infolists\Components\TextEntry::make('solicitud.pagoGenerado.FechaPago')
                                ->label('Fecha de Pago')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ]),
        ];
    }
}
