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
                        Forms\Components\Placeholder::make('cliente_info')
                            ->label('Cliente')
                            ->content(fn($record) => $record?->cuota?->credito?->proposicion?->cliente?->NombresApellidos ?? '-')
                            ->visible(fn() => request()->routeIs('*.view')),

                        Forms\Components\Placeholder::make('zona_info')
                            ->label('Zona')
                            ->content(fn($record) => $record?->cuota?->credito?->proposicion?->cliente?->negocio?->zona?->Nombre ?? '-')
                            ->visible(fn() => request()->routeIs('*.view')),

                        Forms\Components\Placeholder::make('promotor_info')
                            ->label('Promotor Cobrador')
                            ->content(function ($record) {
                                // Obtener la Zona desde Pago -> Cuota -> Credito -> Proposicion -> Zona
                                $zona = $record?->cuota?->credito?->proposicion?->zona;
                                if (!$zona) {
                                    return '-';
                                }

                                // Obtener el Promotor Cobrador asignado a esa Zona
                                $promotor = \App\Models\PromotorCobrador::where('ZonaID', $zona->ZonaID)
                                    ->where('Activo', 1)
                                    ->first();

                                return $promotor?->Descripcion ?? '-';
                            })
                            ->visible(fn() => request()->routeIs('*.view')),

                        Forms\Components\Select::make('ClienteID')
                            ->label('Cliente - DNI')
                            ->options(function () {
                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;

                                // Condiciones que se aplican a las proposiciones
                                $propositionConditions = function ($q) use ($zonaID) {
                                    $q->where('FueRefinanciada', 0)
                                        ->whereHas('credito', function ($sq) {
                                            $sq->where('Activo', 1);
                                        });

                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                };

                                $query = \App\Models\Cliente::whereHas('proposiciones', $propositionConditions);

                                return $query->with(['proposiciones' => $propositionConditions])
                                    ->get()
                                    ->mapWithKeys(function ($cliente) {
                                        return [$cliente->ClienteID => "{$cliente->NombresApellidos} - {$cliente->DNI}"];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->visible(fn() => !request()->routeIs('*.view'))
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('CreditoID', null);
                                $set('TipoCredito', null);
                                $set('CuotaID', null);

                                if ($state) {
                                    $promotorCobrador = auth()->user()?->promotorCobrador;
                                    $zonaID = $promotorCobrador?->ZonaID;

                                    $cliente = \App\Models\Cliente::with([
                                        'proposiciones' => function ($q) use ($zonaID) {
                                            $q->whereHas('credito', function ($sq) {
                                                $sq->where('Activo', 1);
                                            })
                                                // Excluir proposiciones refinanciadas
                                                ->where('FueRefinanciada', 0)
                                                ->with('tipoCredito');

                                            // Filtrar por zona del promotor
                                            if ($zonaID) {
                                                $q->where('ZonaID', $zonaID);
                                            }
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
                                                $fechaInicio = \Carbon\Carbon::parse($proposicion->credito->FechaGeneracion)->format('d/m/Y');
                                                $montoTotal = $proposicion->MontoTotalPagar;
                                                $set('TipoCredito', "{$tipo} - {$proposicion->CodigoCredito} - {$fechaInicio} - {$montoTotal}");

                                                // Auto-seleccionar la primera cuota pendiente (sin restricción de estado)
                                                $primeraCuota = \App\Models\Cuota::where('CreditoID', $creditoID)
                                                    ->where('Activo', 1)
                                                    ->where('NumeroCuota', '>', 0)
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

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;

                                $cliente = \App\Models\Cliente::find($clienteID);

                                // Contar créditos activos en la zona del promotor
                                $creditosActivos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($cliente, $zonaID) {
                                    $q->where('ClienteID', $cliente->ClienteID)
                                        ->where('FueRefinanciada', 0);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                })->where('Activo', 1)->count();

                                // Solo mostrar este select si hay 2+ créditos
                                if ($creditosActivos < 2) {
                                    return [];
                                }

                                return \App\Models\Credito::with('proposicion.tipoCredito')
                                    ->whereHas('proposicion', function ($q) use ($clienteID, $zonaID) {
                                        $q->where('ClienteID', $clienteID)
                                            ->where('FueRefinanciada', 0);
                                        if ($zonaID) {
                                            $q->where('ZonaID', $zonaID);
                                        }
                                    })
                                    ->where('Activo', 1)
                                    ->get()
                                    ->mapWithKeys(function ($credito) {
                                        $tipo = $credito->proposicion->tipoCredito?->Descripcion ?? 'N/A';
                                        $fechaInicio = \Carbon\Carbon::parse($credito->FechaGeneracion)->format('d/m/Y');
                                        $montoTotal = $credito->proposicion->MontoTotalPagar;
                                        return [
                                            $credito->CreditoID => "{$tipo} - {$credito->proposicion->CodigoCredito} - {$fechaInicio} - {$montoTotal}"
                                        ];
                                    });
                            })
                            ->required(function (Forms\Get $get) {
                                // Solo requerido si el select es visible (cuando hay 2+ créditos)
                                $clienteID = $get('ClienteID');
                                if (!$clienteID) {
                                    return false;
                                }

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;

                                $cliente = \App\Models\Cliente::find($clienteID);
                                $creditosActivos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($cliente, $zonaID) {
                                    $q->where('ClienteID', $cliente->ClienteID)
                                        ->where('FueRefinanciada', 0);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                })->where('Activo', 1)->count();

                                return $creditosActivos >= 2;
                            })
                            ->searchable()
                            ->native(false)
                            ->visible(function (Forms\Get $get) {
                                $clienteID = $get('ClienteID');
                                if (!$clienteID) {
                                    return false;
                                }

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;

                                $cliente = \App\Models\Cliente::find($clienteID);
                                $creditosActivos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($cliente, $zonaID) {
                                    $q->where('ClienteID', $cliente->ClienteID)
                                        ->where('FueRefinanciada', 0);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
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
                                // No mostrar en view, solo en edit/create
                                if (request()->routeIs('*.view')) {
                                    return false;
                                }

                                $clienteID = $get('ClienteID');
                                if (!$clienteID)
                                    return true;

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;

                                $cliente = \App\Models\Cliente::find($clienteID);
                                $creditosActivos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($cliente, $zonaID) {
                                    $q->where('ClienteID', $cliente->ClienteID)
                                        ->where('FueRefinanciada', 0);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                })->where('Activo', 1)->count();

                                // Solo visible si tiene 1 crédito. Si tiene 2+, se usa el Select anterior y este se oculta por redundancia.
                                return $creditosActivos < 2;
                            }),

                        Forms\Components\Placeholder::make('tipo_credito_view')
                            ->label('Tipo de Crédito')
                            ->content(fn($record) => $record?->cuota?->credito?->proposicion?->tipoCredito?->Descripcion ?? '-')
                            ->visible(fn() => request()->routeIs('*.view')),

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
                                    ->orderBy('NumeroCuota')
                                    ->get()
                                    ->mapWithKeys(fn($cuota) => [
                                        $cuota->CuotaID => "Cuota #{$cuota->NumeroCuota} - " .
                                            (\Carbon\Carbon::parse($cuota->FechaVencimiento)->format('d/m/Y')) .
                                            " - S/ {$cuota->MontoCuota} (Pagado: S/ {$cuota->MontoPagado})"
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->hidden()
                            ->dehydrated()
                            ->disabled(fn(Forms\Get $get) => !$get('CreditoID'))
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                // Auto-seleccionar la siguiente cuota en secuencia
                                $creditoID = $get('CreditoID');
                                if ($creditoID && !$get('CuotaID')) {
                                    // Obtener el máximo NumeroCuota que ya tiene pagos
                                    $ultimoCuotaConPago = \App\Models\Pago::where('pago.CreditoID', $creditoID)
                                        ->where('pago.Activo', 1)
                                        ->join('cuota', 'pago.CuotaID', '=', 'cuota.CuotaID')
                                        ->max('cuota.NumeroCuota');

                                    // La siguiente cuota es la que viene después
                                    $siguienteCuotaNumber = ($ultimoCuotaConPago ?? 0) + 1;

                                    $siguienteCuota = \App\Models\Cuota::where('CreditoID', $creditoID)
                                        ->where('NumeroCuota', $siguienteCuotaNumber)
                                        ->where('Activo', 1)
                                        ->first();

                                    if ($siguienteCuota) {
                                        $set('CuotaID', $siguienteCuota->CuotaID);
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
                            ->minValue(0.01)
                            ->placeholder('Ingrese el monto del pago'),

                        Forms\Components\DatePicker::make('FechaPago')
                            ->label('Fecha de Pago')
                            ->required()
                            ->default(function () {
                                $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                                return $fechaAbierta ?? now();
                            })
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
                    }),

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
                // Excluir pagos de proposiciones QUE SON refinanciamiento (EsRefinanciamiento = true)
                // Pero SÍ mostrar pagos de créditos que FUERON refinanciados después (FueRefinanciada = 1)
                // IMPORTANTE: Excluir pagos automáticos (EsPagoAutomatico = 1)
                return $query->whereHas('cuota.credito.proposicion', function (Builder $q) {
                    $q->where('EsRefinanciamiento', 0);
                })->where('EsPagoAutomatico', 0);
            })
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn($record) => !auth()->user()?->hasRole('Promotor Cobrador')),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => !auth()->user()?->hasRole('Promotor Cobrador') && self::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => !auth()->user()?->hasRole('Promotor Cobrador') && self::canDelete($record)),
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

    public static function canCreate(): bool
    {
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
        // Si tiene FechaCierre, no se puede editar
        if ($record->FechaCierre !== null) {
            return false;
        }

        // Verificar si el día de PAGO está cerrado (usar FechaPago, no FechaCreacion)
        $fechaPago = $record->FechaPago;
        if (!$fechaPago) {
            return false;
        }
        
        // Convertir a string si es un objeto
        if (is_object($fechaPago)) {
            $fechaPago = $fechaPago->toDateString();
        } else {
            $fechaPago = \Carbon\Carbon::parse($fechaPago)->toDateString();
        }
        
        $fechaHoy = now()->toDateString();
        
        if ($fechaPago !== $fechaHoy) {
            $diaDel = \App\Models\AperturaCierreDia::whereDate('Fecha', $fechaPago)->first();
            if ($diaDel && $diaDel->EstadoDia === 'CERRADO') {
                return false;
            }
        }
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Si tiene FechaCierre, no se puede eliminar
        if ($record->FechaCierre !== null) {
            return false;
        }

        // Verificar si el día de PAGO está cerrado (usar FechaPago, no FechaCreacion)
        $fechaPago = $record->FechaPago;
        if (!$fechaPago) {
            return false;
        }
        
        // Convertir a string si es un objeto
        if (is_object($fechaPago)) {
            $fechaPago = $fechaPago->toDateString();
        } else {
            $fechaPago = \Carbon\Carbon::parse($fechaPago)->toDateString();
        }
        
        $fechaHoy = now()->toDateString();
        
        if ($fechaPago !== $fechaHoy) {
            $diaDel = \App\Models\AperturaCierreDia::whereDate('Fecha', $fechaPago)->first();
            if ($diaDel && $diaDel->EstadoDia === 'CERRADO') {
                return false;
            }
        }
        return true;
    }
}