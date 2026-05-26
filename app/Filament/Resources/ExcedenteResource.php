<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExcedenteResource\Pages;
use App\Models\Excedente;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;

use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class ExcedenteResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Excedente::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Gestión de Pagos';
    protected static ?string $modelLabel = 'Excedente';
    protected static ?string $pluralModelLabel = 'Registro de Excedentes';
    protected static ?int $navigationSort = 9;
    
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Excedente')
                    ->schema([
                        Forms\Components\Select::make('TipoExcedente')
                            ->label('Tipo de Excedente')
                            ->prefixIcon('heroicon-m-tag')
                            ->options([
                                'YAPE_TRANSFERENCIA' => 'Yape / Transferencia',
                                'SOBRANTE_PROMOTOR' => 'Sobrante de Promotor',
                                'SOBRANTE_CAJERO' => 'Registro de Excedentes En Oficina',
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\Select::make('ZonaID')
                            ->label('Zona')
                            ->prefixIcon('heroicon-m-map')
                            ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                            ->searchable()
                            ->native(false)
                            ->visible(fn(Get $get) => $get('TipoExcedente') === 'SOBRANTE_PROMOTOR')
                            ->required(fn(Get $get) => $get('TipoExcedente') === 'SOBRANTE_PROMOTOR'),

                        Forms\Components\TextInput::make('Cuenta')
                            ->label('Cuenta / Destino')
                            ->prefixIcon('heroicon-m-building-library')
                            ->default('Caja Abierta')
                            ->readonly()
                            ->visible(fn(Get $get) => in_array($get('TipoExcedente'), ['YAPE_TRANSFERENCIA', 'SOBRANTE_CAJERO']))
                            ->required(fn(Get $get) => in_array($get('TipoExcedente'), ['YAPE_TRANSFERENCIA', 'SOBRANTE_CAJERO'])),

                        Forms\Components\DatePicker::make('Fecha')
                            ->label('Fecha (del voucher o sobrante)')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->required()
                            ->default(fn() => \App\Services\DateFieldResolver::getFechaAbierta() ?? now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TimePicker::make('Hora')
                            ->label('Hora (del voucher o sobrante)')
                            ->prefixIcon('heroicon-m-clock')
                            ->required()
                            ->default(fn() => \App\Services\DateFieldResolver::getFechaAbierta() ? \App\Services\DateFieldResolver::getFechaAbierta()->copy()->setTime(now()->hour, now()->minute, now()->second) : now()),

                        Forms\Components\TextInput::make('NroOperacion')
                            ->label('Nro. de Operación')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->visible(fn(Get $get) => $get('TipoExcedente') === 'YAPE_TRANSFERENCIA')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('Monto')
                            ->label('Monto (S/)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('S/'),

                        Forms\Components\Textarea::make('Observaciones')
                            ->label('Observaciones')
                            ->columnSpanFull()
                            ->rows(3),

                        Forms\Components\FileUpload::make('VoucherImagen')
                            ->label('Imagen del Voucher')
                            ->image()
                            ->directory('excedentes/vouchers')
                            ->disk('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/*'])
                            ->helperText('Formato: JPG, PNG.')
                            ->columnSpanFull()
                            ->visible(fn(Get $get) => $get('TipoExcedente') === 'YAPE_TRANSFERENCIA'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('TipoExcedente')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'YAPE_TRANSFERENCIA' => 'Yape/Transferencia',
                        'SOBRANTE_PROMOTOR' => 'Sobrante Promotor',
                        'SOBRANTE_CAJERO' => 'Registro de Excedentes En Oficina',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'YAPE_TRANSFERENCIA' => 'info',
                        'SOBRANTE_PROMOTOR' => 'success',
                        'SOBRANTE_CAJERO' => 'warning',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('zona.Nombre')
                    ->label('Zona')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('Cuenta')
                    ->label('Cuenta')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('Hora')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('NroOperacion')
                    ->label('Nro. Operación')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('Monto')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('Observaciones')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('EstadoResolucion')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PENDIENTE' => 'warning',
                        'RESUELTO' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('TipoExcedente')
                    ->label('Tipo')
                    ->options([
                        'YAPE_TRANSFERENCIA' => 'Yape / Transferencia',
                        'SOBRANTE_PROMOTOR' => 'Sobrante de Promotor',
                        'SOBRANTE_CAJERO' => 'Registro de Excedentes En Oficina',
                    ]),
                Tables\Filters\SelectFilter::make('ZonaID')
                    ->label('Zona')
                    ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                    ->searchable(),
                Tables\Filters\Filter::make('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['fecha_desde'],
                                fn(Builder $query, $date): Builder => $query->whereDate('Fecha', '>=', $date),
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn(Builder $query, $date): Builder => $query->whereDate('Fecha', '<=', $date),
                            );
                    }),

            ])
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->EstadoResolucion === 'PENDIENTE'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->EstadoResolucion === 'PENDIENTE'),
            ])
            ->bulkActions([
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Detalle del Excedente')
                    ->description('Información técnica del dinero sobrante o voucher identificado.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                // --- FILA 1: ESTADO Y DINERO ---
                                Infolists\Components\TextEntry::make('MontoOriginal')
                                    ->label('Monto Original')
                                    ->getStateUsing(fn($record) => (float)$record->Monto + (float)$record->resoluciones()->where('Estado', 'APROBADA')->sum('MontoAplicar'))
                                    ->money('PEN')
                                    ->icon('heroicon-m-banknotes')
                                    ->weight('bold')
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('Monto')
                                    ->label('Saldo Restante')
                                    ->money('PEN')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->icon('heroicon-m-currency-dollar')
                                    ->color(fn($state) => $state > 0 ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('EstadoResolucion')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'PENDIENTE' => 'warning',
                                        'RESUELTO' => 'success',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('TipoExcedente')
                                    ->label('Tipo')
                                    ->badge()
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'YAPE_TRANSFERENCIA' => 'Yape/Transferencia',
                                        'SOBRANTE_PROMOTOR' => 'Sobrante Promotor',
                                        'SOBRANTE_CAJERO' => 'Registro Oficina',
                                        default => $state,
                                    }),

                                // --- FILA 2: TIEMPO Y LUGAR ---
                                Infolists\Components\TextEntry::make('Fecha')
                                    ->label('Fecha')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-m-calendar'),

                                Infolists\Components\TextEntry::make('Hora')
                                    ->label('Hora')
                                    ->time('H:i')
                                    ->icon('heroicon-m-clock'),

                                Infolists\Components\TextEntry::make('zona.Nombre')
                                    ->label('Zona / Sede')
                                    ->placeholder('N/A')
                                    ->icon('heroicon-m-map-pin'),

                                Infolists\Components\TextEntry::make('Cuenta')
                                    ->label('Cuenta Destino')
                                    ->placeholder('N/A')
                                    ->icon('heroicon-m-building-library'),

                                // --- FILA 3: IDENTIFICACIÓN ---
                                Infolists\Components\TextEntry::make('NroOperacion')
                                    ->label('Nro. Operación')
                                    ->columnSpan(2)
                                    ->placeholder('N/A')
                                    ->icon('heroicon-m-hashtag'),

                                // --- FILA 4: NOTAS ---
                                Infolists\Components\TextEntry::make('Observaciones')
                                    ->label('Comentarios / Observaciones')
                                    ->columnSpanFull()
                                    ->icon('heroicon-m-chat-bubble-bottom-center-text')
                                    ->placeholder('Sin observaciones registradas'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Historial de Aplicaciones / Resoluciones')
                    ->description('Trazabilidad de cómo se ha utilizado este excedente.')
                    ->icon('heroicon-o-clock')
                    ->collapsed(fn($record) => $record->resoluciones()->count() === 0)
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('resoluciones')
                            ->label('')
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Fecha Aplicación')
                                            ->dateTime('d/m/Y H:i')
                                            ->icon('heroicon-m-clock'),
                                        Infolists\Components\TextEntry::make('clienteDestino.NombresApellidos')
                                            ->label('Cliente Beneficiario')
                                            ->placeholder('N/A')
                                            ->weight('bold')
                                            ->icon('heroicon-m-user'),
                                        Infolists\Components\TextEntry::make('TipoResolucion')
                                            ->label('Tipo de Acción')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('MontoAplicar')
                                            ->label('Monto Usado')
                                            ->money('PEN')
                                            ->color('danger')
                                            ->weight('bold')
                                            ->icon('heroicon-m-minus-circle'),
                                    ])
                            ])
                    ]),

                Infolists\Components\Section::make('Evidencia Digital')
                    ->description('Voucher o comprobante de la operación.')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        Infolists\Components\TextEntry::make('VoucherImagen')
                            ->label('')
                            ->formatStateUsing(fn($state) => $state ? '<div class="flex justify-center"><img src="' . asset('storage/' . $state) . '" style="max-height:500px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 2px solid #f3f4f6;" alt="Voucher"></div>' : '<p class="text-gray-400 italic">No se cargó imagen de evidencia</p>')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(fn($record) => $record->TipoExcedente === 'YAPE_TRANSFERENCIA' || !empty($record->VoucherImagen)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) { return false; }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
            if (request()->routeIs('*create') || request()->isMethod('post')) {
                \Filament\Notifications\Notification::make()
                    ->title('❌ Día Cerrado')
                    ->body('El día de operaciones está cerrado. No se pueden realizar operaciones.')
                    ->danger()
                    ->send();
            }
            return false;
        }
        return true;
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && \App\Models\AperturaCierreDia::estaAbierto() && $record->FechaCierre === null;
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete($record) && \App\Models\AperturaCierreDia::estaAbierto() && $record->FechaCierre === null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExcedentes::route('/'),
            'create' => Pages\CreateExcedente::route('/create'),
            'edit' => Pages\EditExcedente::route('/{record}/edit'),
            'view' => Pages\ViewExcedente::route('/{record}'),
        ];
    }
    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\ExcedenteYapeStatsWidget::class,
            \App\Filament\Widgets\ExcedentePromotorStatsWidget::class,
            \App\Filament\Widgets\ExcedenteOficinaStatsWidget::class,
            \App\Filament\Widgets\ExcedenteTotalStatsWidget::class,
        ];
    }
}
