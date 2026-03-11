<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GastoResource\Pages;
use App\Models\Gasto;
use App\Models\TipoComprobanteGasto;
use App\Models\Motivo;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
class GastoResource extends Resource
{
    protected static ?string $model = Gasto::class;

    protected static ?string $navigationGroup = 'Compras y Gastos';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 2003;
    protected static ?string $label = 'Gasto';
    protected static ?string $pluralLabel = 'Gastos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Comprobante')
                    ->schema([
                        Forms\Components\Select::make('TipoComprobanteGastoID')
                            ->label('Tipo de Comprobante')
                            ->options(TipoComprobanteGasto::where('Activo', true)->pluck('Nombre', 'TipoComprobanteGastoID'))
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('Numero')
                            ->label('Número')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\DatePicker::make('FechaEmision')
                            ->label('Fecha Emisión')
                            ->required()
                            ->minDate(now()->startOfMonth())
                            ->maxDate(now()->endOfMonth()),
                    ])->columns(2),

                Forms\Components\Section::make('Datos del Gasto')
                    ->schema([
                        Forms\Components\TextInput::make('NombreProveedor')
                            ->label('Proveedor')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\Select::make('MotivoID')
                            ->label('Motivo')
                            ->options(Motivo::where('Activo', true)->pluck('Nombre', 'MotivoID'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('MetodoGasto')
                            ->label('Método de Gasto')
                            ->options([
                                'CAJA CHICA' => 'CAJA CHICA',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Detalle del Gasto')
                    ->schema([
                        Forms\Components\Repeater::make('detalles')
                            ->label('Líneas de Gasto')
                            ->relationship()
                            ->schema([
                                Forms\Components\Textarea::make('Descripcion')
                                    ->label('Descripción')
                                    ->required()
                                    ->maxLength(500)
                                    ->rows(2)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('Monto')
                                    ->label('Monto')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->prefix('S/. ')
                                    ->live(onBlur: true),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar línea de gasto')
                            ->reorderable(false)
                            ->required()
                            ->minItems(1)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('TotalDisplay')
                            ->label('TOTAL')
                            ->content(function (Get $get): string {
                                $detalles = $get('detalles') ?? [];
                                $total = collect($detalles)->sum(fn($item) => floatval($item['Monto'] ?? 0));
                                return 'S/. ' . number_format($total, 2);
                            })
                            ->extraAttributes(['class' => 'text-xl font-bold']),
                    ]),

                Forms\Components\Section::make('Adicional')
                    ->schema([
                        Forms\Components\Textarea::make('Observaciones')
                            ->label('Observaciones')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->activos()->with('detalles'))
            ->columns([
                Tables\Columns\TextColumn::make('FechaEmision')
                    ->label('Fecha Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipoComprobanteGasto.Nombre')
                    ->label('Tipo Comprobante')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Numero')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('NombreProveedor')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('motivo.Nombre')
                    ->label('Motivo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('MetodoGasto')
                    ->label('Método')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('detalles_resumen')
                    ->label('Descripción')
                    ->getStateUsing(function ($record) {
                        $descripciones = $record->detalles->pluck('Descripcion')->toArray();
                        $resumen = implode(', ', $descripciones);
                        return strlen($resumen) > 50 ? substr($resumen, 0, 50) . '...' : $resumen;
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('Total')
                    ->label('Total')
                    ->numeric(2)
                    ->prefix('S/. ')
                    ->sortable(),
                Tables\Columns\IconColumn::make('Activo')
                    ->label('Estado')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('TipoComprobanteGastoID')
                    ->label('Tipo Comprobante')
                    ->options(TipoComprobanteGasto::where('Activo', true)->pluck('Nombre', 'TipoComprobanteGastoID')),
                Tables\Filters\SelectFilter::make('MotivoID')
                    ->label('Motivo')
                    ->options(Motivo::where('Activo', true)->pluck('Nombre', 'MotivoID')),
                Tables\Filters\SelectFilter::make('MetodoGasto')
                    ->label('Método de Gasto')
                    ->options([
                        'CAJA CHICA' => 'CAJA CHICA',
                        'Tarjeta de crédito' => 'Tarjeta de crédito',
                        'Tarjeta de débito' => 'Tarjeta de débito',
                    ]),
                Tables\Filters\Filter::make('FechaEmision')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when(
                                $data['fecha_desde'],
                                fn($q) => $q->whereDate('FechaEmision', '>=', $data['fecha_desde'])
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn($q) => $q->whereDate('FechaEmision', '<=', $data['fecha_hasta'])
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn() => AperturaCierreDia::estaAbierto()),
                Tables\Actions\Action::make('delete')
                    ->visible(fn() => AperturaCierreDia::estaAbierto())
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Gasto')
                    ->modalDescription('¿Está seguro que desea eliminar este gasto?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->action(fn($record) => $record->update(['Activo' => false]))
                    ->successNotificationTitle('Gasto eliminado correctamente'),
            ])
            ->bulkActions([
            ])
            ->defaultSort('FechaEmision', 'desc')
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate() && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete($record) && \App\Models\AperturaCierreDia::estaAbierto();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGastos::route('/'),
            'create' => Pages\CreateGasto::route('/create'),
            'view' => Pages\ViewGasto::route('/{record}'),
            'edit' => Pages\EditGasto::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
