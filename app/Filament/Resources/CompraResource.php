<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompraResource\Pages;
use App\Models\Compra;
use App\Models\TipoComprobante;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $navigationGroup = 'Compras y Gastos';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 2001;
    protected static ?string $label = 'Compra';
    protected static ?string $pluralLabel = 'Compras';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Comprobante')
                    ->schema([
                        Forms\Components\Select::make('TipoComprobanteID')
                            ->label('Tipo de Comprobante')
                            ->options(TipoComprobante::where('Activo', true)->pluck('Nombre', 'TipoComprobanteID'))
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('Serie')
                            ->label('Serie')
                            ->maxLength(20)
                            ->nullable(),
                        Forms\Components\TextInput::make('Numero')
                            ->label('Número')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\DatePicker::make('FechaEmision')
                            ->label('Fecha Emisión')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Proveedor')
                    ->schema([
                        Forms\Components\TextInput::make('NombreProveedor')
                            ->label('Nombre del Proveedor')
                            ->required()
                            ->maxLength(150),
                    ]),

                Forms\Components\Section::make('Detalle de Compra')
                    ->schema([
                        Forms\Components\TextInput::make('ProductoServicio')
                            ->label('Producto o Servicio')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('Cantidad')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->live()
                            ->afterStateUpdated(function ($get, $set) {
                                $cantidad = floatval($get('Cantidad') ?? 0);
                                $precio = floatval($get('PrecioUnitario') ?? 0);
                                $set('Total', $cantidad * $precio);
                            }),
                        Forms\Components\TextInput::make('PrecioUnitario')
                            ->label('Precio Unitario')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->prefix('S/. ')
                            ->live()
                            ->afterStateUpdated(function ($get, $set) {
                                $cantidad = floatval($get('Cantidad') ?? 0);
                                $precio = floatval($get('PrecioUnitario') ?? 0);
                                $set('Total', $cantidad * $precio);
                            }),
                        Forms\Components\TextInput::make('Total')
                            ->label('Total')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->prefix('S/. ')
                            ->readOnly(),
                    ])->columns(2),

                Forms\Components\Section::make('Observaciones')
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
            ->modifyQueryUsing(fn ($query) => $query->activos())
            ->columns([
                Tables\Columns\TextColumn::make('FechaEmision')
                    ->label('Fecha Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipoComprobante.Nombre')
                    ->label('Tipo Comprobante')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Serie')
                    ->label('Serie')
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
                Tables\Columns\TextColumn::make('ProductoServicio')
                    ->label('Producto/Servicio')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('Cantidad')
                    ->label('Cant.')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('PrecioUnitario')
                    ->label('Precio Unit.')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('Total')
                    ->label('Total')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\IconColumn::make('Activo')
                    ->label('Estado')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('TipoComprobanteID')
                    ->label('Tipo Comprobante')
                    ->options(TipoComprobante::where('Activo', true)->pluck('Nombre', 'TipoComprobanteID')),
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
                                fn ($q) => $q->whereDate('FechaEmision', '>=', $data['fecha_desde'])
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn ($q) => $q->whereDate('FechaEmision', '<=', $data['fecha_hasta'])
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => AperturaCierreDia::estaAbierto()),
                Tables\Actions\Action::make('delete')
                    ->visible(fn() => AperturaCierreDia::estaAbierto())
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Compra')
                    ->modalDescription('¿Está seguro que desea eliminar esta compra?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->action(fn($record) => $record->update(['Activo' => false]))
                    ->successNotificationTitle('Compra eliminada correctamente'),
            ])
            ->bulkActions([
            ])
            ->defaultSort('FechaEmision', 'desc')
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return AperturaCierreDia::estaAbierto();
    }

    public static function canEdit($record): bool
    {
        return AperturaCierreDia::estaAbierto();
    }

    public static function canDelete($record): bool
    {
        return AperturaCierreDia::estaAbierto();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompras::route('/'),
            'create' => Pages\CreateCompra::route('/create'),
            'edit' => Pages\EditCompra::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
