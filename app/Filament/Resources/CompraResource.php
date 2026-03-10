<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompraResource\Pages;
use App\Models\Compra;
use App\Models\TipoComprobante;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                            ->searchable()
                            ->live(),
                        Forms\Components\TextInput::make('Numero')
                            ->label('Serie / Número')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\DatePicker::make('FechaEmision')
                            ->label('Fecha Emisión')
                            ->required()
                            ->minDate(now()->startOfMonth())
                            ->maxDate(now()->endOfMonth()),
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
                        Forms\Components\Repeater::make('detalles')
                            ->label('Productos / Servicios')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('ProductoServicio')
                                    ->label('Producto o Servicio')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('Cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $cantidad = floatval($get('Cantidad') ?? 0);
                                        $precio = floatval($get('PrecioUnitario') ?? 0);
                                        $set('Subtotal', number_format($cantidad * $precio, 2, '.', ''));
                                    }),
                                Forms\Components\TextInput::make('PrecioUnitario')
                                    ->label('Precio Unitario')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->prefix('S/. ')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $cantidad = floatval($get('Cantidad') ?? 0);
                                        $precio = floatval($get('PrecioUnitario') ?? 0);
                                        $set('Subtotal', number_format($cantidad * $precio, 2, '.', ''));
                                    }),
                                Forms\Components\TextInput::make('Subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('S/. ')
                                    ->readOnly()
                                    ->default(0),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->required()
                            ->minItems(1)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('TotalesDisplay')
                            ->label('Desglose de Totales')
                            ->content(function (Get $get): \Illuminate\Support\HtmlString {
                                $detalles = $get('detalles') ?? [];
                                $subtotalBase = collect($detalles)->sum(fn($item) => floatval($item['Subtotal'] ?? 0));

                                $tipoComprobanteId = $get('TipoComprobanteID');
                                $aplicaIgv = false;

                                if ($tipoComprobanteId) {
                                    $comprobante = \App\Models\TipoComprobante::find($tipoComprobanteId);
                                    if ($comprobante && in_array($comprobante->Nombre, ['FACTURA ELECTRÓNICA', 'BOLETA DE VENTA ELECTRÓNICA', 'SERVICIOS PÚBLICOS'])) {
                                        $aplicaIgv = true;
                                    }
                                }

                                $igv = $aplicaIgv ? $subtotalBase * 0.18 : 0;
                                $totalFinal = $subtotalBase + $igv;

                                return new \Illuminate\Support\HtmlString("
                                    <div class='flex flex-col gap-1 text-sm'>
                                        <div class='flex justify-between w-48'>
                                            <span class='text-gray-500'>Subtotal Base:</span>
                                            <span class='font-medium'>S/. " . number_format($subtotalBase, 2) . "</span>
                                        </div>
                                        <div class='flex justify-between w-48'>
                                            <span class='text-gray-500'>IGV (18%):</span>
                                            <span class='font-medium'>S/. " . number_format($igv, 2) . "</span>
                                        </div>
                                        <div class='flex justify-between w-48 pt-2 mt-2 border-t border-gray-200 dark:border-gray-700'>
                                            <span class='font-bold'>TOTAL:</span>
                                            <span class='text-xl font-bold text-primary-600'>S/. " . number_format($totalFinal, 2) . "</span>
                                        </div>
                                    </div>
                                ");
                            })
                    ]),

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
            ->modifyQueryUsing(fn($query) => $query->activos()->with('detalles'))
            ->columns([
                Tables\Columns\TextColumn::make('FechaEmision')
                    ->label('Fecha Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipoComprobante.Nombre')
                    ->label('Tipo Comprobante')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('Numero')
                    ->label('Serie / Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('NombreProveedor')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('detalles_resumen')
                    ->label('Productos')
                    ->getStateUsing(function ($record) {
                        $nombres = $record->detalles->pluck('ProductoServicio')->toArray();
                        $resumen = implode(', ', $nombres);
                        return strlen($resumen) > 50 ? substr($resumen, 0, 50) . '...' : $resumen;
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('detalles_count')
                    ->label('Ítems')
                    ->getStateUsing(fn($record) => $record->detalles->count())
                    ->alignCenter(),
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
            'index' => Pages\ListCompras::route('/'),
            'create' => Pages\CreateCompra::route('/create'),
            'view' => Pages\ViewCompra::route('/{record}'),
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
