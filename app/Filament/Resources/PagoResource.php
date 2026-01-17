<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PagoResource\Pages;
use App\Models\Pago;
use App\Models\Credito;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;

class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Gestión de Pagos';
    protected static ?int $navigationGroupSort = 3;
    protected static ?int $navigationSort = 8;
    protected static ?string $modelLabel = 'Pago';
    protected static ?string $pluralModelLabel = 'Pagos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Pago')
                    ->schema([
                        Forms\Components\TextInput::make('cliente_info')
                            ->label('Cliente')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn($record) => $record?->cuota?->credito?->proposicion?->cliente?->NombresApellidos ?? 'N/A'),

                        Forms\Components\TextInput::make('zona_info')
                            ->label('Zona')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn($record) => $record?->cuota?->credito?->proposicion?->cliente?->negocio?->zona?->Nombre ?? 'N/A'),

                        Forms\Components\TextInput::make('promotor_info')
                            ->label('Promotor Cobrador')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn($record) => $record?->cuota?->credito?->proposicion?->cliente?->promotorCobrador?->Usuario ?? 'N/A'),

                        Forms\Components\Select::make('ClienteID')
                            ->label('Cliente - DNI')
                            ->options(function () {
                                $promotorCobradorID = auth()->user()?->PromotorCobradorID;

                                return \App\Models\Cliente::whereHas('proposiciones.credito', function ($query) {
                                    $query->where('Activo', 1);
                                })

                                    ->with([
                                        'proposiciones' => function ($query) {
                                            $query->whereHas('credito', function ($q) {
                                                $q->where('Activo', 1);
                                            })
                                                // Excluir proposiciones refinanciadas
                                                ->where('FueRefinanciada', 0)
                                                ->with('tipoCredito');
                                        }
                                    ])
                                    ->when($promotorCobradorID, function ($query) use ($promotorCobradorID) {
                                        $query->where('PromotorCobradorID', $promotorCobradorID);
                                    })
                                    ->get()
                                    ->mapWithKeys(function ($cliente) {
                                        return [$cliente->ClienteID => "{$cliente->NombresApellidos} - {$cliente->DNI}"];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('CreditoID', null);
                                $set('TipoCredito', null);
                                $set('CuotaID', null);

                                if ($state) {
                                    $cliente = \App\Models\Cliente::with([
                                        'proposiciones' => function ($q) {
                                            $q->whereHas('credito', function ($sq) {
                                                $sq->where('Activo', 1);
                                            })
                                                // Excluir proposiciones refinanciadas
                                                ->where('FueRefinanciada', 0)
                                                ->with('tipoCredito');
                                        }
                                    ])->find($state);

                                    if ($cliente) {
                                        $creditosActivos = $cliente->proposiciones->count();

                                        // Si el cliente tiene solo 1 crédito, seleccionarlo y mostrar el tipo con el código
                                        if ($creditosActivos == 1) {
                                            $proposicion = $cliente->proposiciones->first();
                                            if ($proposicion->credito) {
                                                $creditoID = $proposicion->credito->CreditoID;
                                                $set('CreditoID', $creditoID);
                                                $tipo = $proposicion->tipoCredito?->Descripcion ?? 'N/A';
                                                $set('TipoCredito', "{$tipo} - {$proposicion->CodigoCredito}");

                                                // Auto-seleccionar la primera cuota pendiente
                                                $primeraCuota = \App\Models\Cuota::where('CreditoID', $creditoID)
                                                    ->where('Activo', 1)
                                                    ->where('NumeroCuota', '>', 0)
                                                    ->where('Estado', '!=', \App\Models\Cuota::ESTADO_PAGADA)
                                                    ->orderBy('NumeroCuota')
                                                    ->first();

                                                if ($primeraCuota) {
                                                    $set('CuotaID', $primeraCuota->CuotaID);
                                                }
                                            }
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Select::make('CreditoID')
                            ->label('Seleccionar Crédito')
                            ->options(function (Forms\Get $get) {
                                $clienteID = $get('ClienteID');
                                if (!$clienteID) {
                                    return [];
                                }

                                $cliente = \App\Models\Cliente::find($clienteID);
                                $creditosActivos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($cliente) {
                                    $q->where('ClienteID', $cliente->ClienteID)
                                        ->where('FueRefinanciada', 0);
                                })->where('Activo', 1)->count();

                                // Solo mostrar este select si hay 2+ créditos
                                if ($creditosActivos < 2) {
                                    return [];
                                }

                                return \App\Models\Credito::with('proposicion.tipoCredito')
                                    ->whereHas('proposicion', function ($q) use ($clienteID) {
                                        $q->where('ClienteID', $clienteID)
                                            ->where('FueRefinanciada', 0);
                                    })
                                    ->where('Activo', 1)
                                    ->get()
                                    ->mapWithKeys(fn($credito) => [
                                        $credito->CreditoID => ($credito->proposicion->tipoCredito?->Descripcion ?? 'N/A') . "  {$credito->proposicion->CodigoCredito}"
                                    ]);
                            })
                            ->searchable()
                            ->searchable()
                            ->native(false)
                            ->visible(function (Forms\Get $get) {
                                $clienteID = $get('ClienteID');
                                if (!$clienteID) {
                                    return false;
                                }

                                $cliente = \App\Models\Cliente::find($clienteID);
                                $creditosActivos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($cliente) {
                                    $q->where('ClienteID', $cliente->ClienteID)
                                        ->where('FueRefinanciada', 0);
                                })->where('Activo', 1)->count();

                                return $creditosActivos >= 2;
                            })
                            ->disabled(fn(Forms\Get $get) => !$get('ClienteID'))
                            ->dehydrated()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $set('CuotaID', null);
                            })
                            ->live(),

                        Forms\Components\TextInput::make('TipoCredito')
                            ->label('Tipo de Crédito')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Información del crédito')
                            ->visible(function (Forms\Get $get) {
                                $clienteID = $get('ClienteID');
                                if (!$clienteID)
                                    return true;

                                $cliente = \App\Models\Cliente::find($clienteID);
                                $creditosActivos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($cliente) {
                                    $q->where('ClienteID', $cliente->ClienteID)
                                        ->where('FueRefinanciada', 0);
                                })->where('Activo', 1)->count();

                                // Solo visible si tiene 1 crédito. Si tiene 2+, se usa el Select anterior y este se oculta por redundancia.
                                return $creditosActivos < 2;
                            }),

                        Forms\Components\Select::make('CuotaID')
                            ->label('Cuota - Control de Pagos')
                            ->options(function (Forms\Get $get) {
                                $creditoID = $get('CreditoID');
                                if (!$creditoID) {
                                    return [];
                                }

                                return \App\Models\Cuota::where('CreditoID', $creditoID)
                                    ->where('Activo', 1)
                                    ->where('NumeroCuota', '>', 0)
                                    ->where('Estado', '!=', \App\Models\Cuota::ESTADO_PAGADA)
                                    ->orderBy('NumeroCuota')
                                    ->get()
                                    ->mapWithKeys(fn($cuota) => [
                                        $cuota->CuotaID => "Cuota #{$cuota->NumeroCuota} - " .
                                            (\Carbon\Carbon::parse($cuota->FechaVencimiento)->format('d/m/Y')) .
                                            " - S/ {$cuota->MontoCuota}"
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->hidden()
                            ->dehydrated()
                            ->disabled(fn(Forms\Get $get) => !$get('CreditoID'))
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                // Si es la primera vez que se abre el formulario, auto-seleccionar la primera cuota pendiente
                                $creditoID = $get('CreditoID');
                                if ($creditoID && !$get('CuotaID')) {
                                    $primeraCuota = \App\Models\Cuota::where('CreditoID', $creditoID)
                                        ->where('Activo', 1)
                                        ->where('NumeroCuota', '>', 0)
                                        ->where('Estado', '!=', \App\Models\Cuota::ESTADO_PAGADA)
                                        ->orderBy('NumeroCuota')
                                        ->first();

                                    if ($primeraCuota) {
                                        $set('CuotaID', $primeraCuota->CuotaID);
                                    }
                                }
                            }),
                    ]),

                Forms\Components\Section::make('Detalles del Pago')
                    ->schema([
                        Forms\Components\TextInput::make('MontoPagado')
                            ->label('Monto Pagado')
                            ->numeric()
                            ->required()
                            ->placeholder('Ingrese el monto del pago'),

                        Forms\Components\DatePicker::make('FechaPago')
                            ->label('Fecha de Pago')
                            ->required()
                            ->default(now())
                            ->disabled()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),

                Forms\Components\Section::make('Flags de Pago')
                    ->schema([
                        Forms\Components\Checkbox::make('EsMora')
                            ->label('Es Mora')
                            ->default(false),

                        Forms\Components\Checkbox::make('EsPagoAMayor')
                            ->label('Es A Mayor')
                            ->default(false),

                    ])->columns(3),

                Forms\Components\Section::make('Comentarios')
                    ->schema([
                        Forms\Components\Textarea::make('Comentario')
                            ->label('Comentario')
                            ->rows(3)
                            ->placeholder('Notas adicionales sobre el pago'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('cuota.credito.proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cuota.credito.proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cuota.NumeroCuota')
                    ->label('Cuota #')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoPagado')
                    ->label('Monto Pagado')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\BooleanColumn::make('EsMora')
                    ->label('Es Mora')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaPago')
                    ->label('Fecha de Pago')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('HoraPago')
                    ->label('Hora de Pago')
                    ->getStateUsing(function ($record) {
                        return $record->FechaCreacion ? \Carbon\Carbon::parse($record->FechaCreacion)->format('H:i:s') : 'N/A';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('UsuarioRegistro')
                    ->label('Usuario Registro')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\IconColumn::make('Activo')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zona')
                    ->label('Zona')
                    ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('cuota.credito.proposicion.cliente.negocio', fn(Builder $subQ) => $subQ->where('ZonaID', $data['value']))
                        );
                    })
                    ->native(false),

                Tables\Filters\Filter::make('FechaPago')
                    ->label('Fecha de Pago')
                    ->form([
                        Forms\Components\DatePicker::make('FechaPago_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('FechaPago_to')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['FechaPago_from'],
                                fn(Builder $q) => $q->whereDate('FechaPago', '>=', $data['FechaPago_from']),
                            )
                            ->when(
                                $data['FechaPago_to'],
                                fn(Builder $q) => $q->whereDate('FechaPago', '<=', $data['FechaPago_to']),
                            );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Excluir pagos de créditos que fueron refinanciados
                return $query->whereHas('cuota.credito.proposicion', function (Builder $q) {
                    $q->where('FueRefinanciada', 0);
                });
            })
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn($record) => !auth()->user()?->hasRole('Promotor Cobrador')),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => !auth()->user()?->hasRole('Promotor Cobrador')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => !auth()->user()?->hasRole('Promotor Cobrador')),
            ]);

    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPagos::route('/'),
            'create' => Pages\CreatePago::route('/create'),
            'view' => Pages\ViewPago::route('/{record}'),
            'edit' => Pages\EditPago::route('/{record}/edit'),
        ];
    }
}
