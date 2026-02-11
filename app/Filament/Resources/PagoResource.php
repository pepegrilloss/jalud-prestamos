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
                                        ->where('Activo', true)
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
                                    ->filter(function ($cliente) {
                                        // Solo incluir clientes que tengan al menos un crédito con saldo > 0
                                        foreach ($cliente->proposiciones as $proposicion) {
                                            $saldo = \App\Models\ProposicionCredito::calcularSaldoPendiente($proposicion->ProposicionCreditoID);
                                            if ($saldo > 0) {
                                                return true;
                                            }
                                        }
                                        return false;
                                    })
                                    ->mapWithKeys(function ($cliente) {
                                        return [$cliente->ClienteID => "{$cliente->NombresApellidos} - {$cliente->DNI}"];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->disabled(fn() => request()->routeIs('*.edit'))
                            ->dehydrated()
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
                                                ->where('FueRefinanciada', 0)
                                                ->where('Activo', true)
                                                ->with('tipoCredito');

                                            if ($zonaID) {
                                                $q->where('ZonaID', $zonaID);
                                            }
                                        }
                                    ])->find($state);

                                    if ($cliente) {
                                        // Filtrar solo proposiciones con saldo pendiente > 0
                                        $proposicionesConSaldo = $cliente->proposiciones->filter(function ($prop) {
                                            $saldo = \App\Models\ProposicionCredito::calcularSaldoPendiente($prop->ProposicionCreditoID);
                                            return $saldo > 0;
                                        });

                                        $creditosActivos = $proposicionesConSaldo->count();

                                        // Si el cliente tiene solo 1 crédito con saldo, seleccionarlo
                                        if ($creditosActivos == 1) {
                                            $proposicion = $proposicionesConSaldo->first();
                                            if ($proposicion->credito) {
                                                $creditoID = $proposicion->credito->CreditoID;
                                                $set('CreditoID', $creditoID);
                                                $tipo = $proposicion->tipoCredito?->Descripcion ?? 'N/A';
                                                $fechaInicio = \Carbon\Carbon::parse($proposicion->credito->FechaGeneracion)->format('d/m/Y');
                                                $montoTotal = $proposicion->MontoTotalPagar;
                                                $set('TipoCredito', "{$tipo} - {$proposicion->CodigoCredito} - {$fechaInicio} - Monto total: S/ " . number_format($montoTotal, 2));

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

                                // Obtener créditos y filtrar solo los que tienen saldo > 0
                                $creditos = \App\Models\Credito::with('proposicion.tipoCredito')
                                    ->whereHas('proposicion', function ($q) use ($clienteID, $zonaID) {
                                    $q->where('ClienteID', $clienteID)
                                        ->where('FueRefinanciada', 0)
                                        ->where('Activo', true);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                })
                                    ->where('Activo', 1)
                                    ->get()
                                    ->filter(function ($credito) {
                                    $saldo = \App\Models\ProposicionCredito::calcularSaldoPendiente($credito->proposicion->ProposicionCreditoID);
                                    return $saldo > 0;
                                });

                                // Solo mostrar este select si hay 2+ créditos con saldo
                                if ($creditos->count() < 2) {
                                    return [];
                                }

                                return $creditos->mapWithKeys(function ($credito) {
                                    $tipo = $credito->proposicion->tipoCredito?->Descripcion ?? 'N/A';
                                    $fechaInicio = \Carbon\Carbon::parse($credito->FechaGeneracion)->format('d/m/Y');
                                    $montoTotal = $credito->proposicion->MontoTotalPagar;
                                    return [
                                        $credito->CreditoID => "{$tipo} - {$credito->proposicion->CodigoCredito} - {$fechaInicio} - Monto total: S/ " . number_format($montoTotal, 2)
                                    ];
                                });
                            })
                            ->required(function (Forms\Get $get) {
                                // Solo requerido si el select es visible (cuando hay 2+ créditos con saldo)
                                $clienteID = $get('ClienteID');
                                if (!$clienteID) {
                                    return false;
                                }

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;

                                $creditos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($clienteID, $zonaID) {
                                    $q->where('ClienteID', $clienteID)
                                        ->where('FueRefinanciada', 0)
                                        ->where('Activo', true);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                })->where('Activo', 1)->with('proposicion')->get();

                                // Filtrar solo créditos con saldo > 0
                                $creditosConSaldo = $creditos->filter(function ($credito) {
                                    $saldo = \App\Models\ProposicionCredito::calcularSaldoPendiente($credito->proposicion->ProposicionCreditoID);
                                    return $saldo > 0;
                                });

                                return $creditosConSaldo->count() >= 2;
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

                                $creditos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($clienteID, $zonaID) {
                                    $q->where('ClienteID', $clienteID)
                                        ->where('FueRefinanciada', 0)
                                        ->where('Activo', true);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                })->where('Activo', 1)->with('proposicion')->get();

                                // Filtrar solo créditos con saldo > 0
                                $creditosConSaldo = $creditos->filter(function ($credito) {
                                    $saldo = \App\Models\ProposicionCredito::calcularSaldoPendiente($credito->proposicion->ProposicionCreditoID);
                                    return $saldo > 0;
                                });

                                return $creditosConSaldo->count() >= 2;
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

                                $creditos = \App\Models\Credito::whereHas('proposicion', function ($q) use ($clienteID, $zonaID) {
                                    $q->where('ClienteID', $clienteID)
                                        ->where('FueRefinanciada', 0)
                                        ->where('Activo', true);
                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                })->where('Activo', 1)->with('proposicion')->get();

                                // Filtrar solo créditos con saldo > 0
                                $creditosConSaldo = $creditos->filter(function ($credito) {
                                    $saldo = \App\Models\ProposicionCredito::calcularSaldoPendiente($credito->proposicion->ProposicionCreditoID);
                                    return $saldo > 0;
                                });

                                // Solo visible si tiene 1 crédito con saldo. Si tiene 2+, se usa el Select anterior.
                                return $creditosConSaldo->count() < 2;
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
                Tables\Columns\TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_credito_desc')
                    ->label('Tipo de Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('codigo_credito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('numero_cuota')
                    ->label('Cuota #')
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        return $state ? "Cuota #{$state}" : '-';
                    }),

                Tables\Columns\TextColumn::make('TipoConcepto')
                    ->label('Tipo')
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $tipos = [
                            'C' => 'Cuota Normal',
                            'I' => 'Interés',
                            'M' => 'Mora',
                            'P' => 'Penalidad',
                        ];
                        return $tipos[$record->TipoConcepto] ?? $record->TipoConcepto;
                    }),

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
                Tables\Filters\SelectFilter::make('cliente')
                    ->label('Cliente')
                    ->options(function () {
                        return \App\Models\Cliente::where('Activo', true)
                            ->whereHas('proposiciones.credito')
                            ->pluck('NombresApellidos', 'ClienteID')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            function (Builder $q) use ($data) {
                                return $q->where(
                                    function (Builder $subQ) use ($data) {
                                        $subQ->whereHas(
                                            'cuota.credito.proposicion.cliente', 
                                            fn(Builder $sq) => $sq->where('ClienteID', $data['value'])
                                        )
                                        ->orWhereHas(
                                            'credito.proposicion.cliente',
                                            fn(Builder $sq) => $sq->where('ClienteID', $data['value'])
                                        );
                                    }
                                );
                            }
                        );
                    })
                    ->searchable()
                    ->native(false),

                Tables\Filters\Filter::make('dni')
                    ->label('DNI')
                    ->form([
                        Forms\Components\TextInput::make('dni')
                            ->label('DNI')
                            ->placeholder('Ingrese DNI'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['dni'] ?? null,
                            function (Builder $q) use ($data) {
                                return $q->where(
                                    function (Builder $subQ) use ($data) {
                                        $subQ->whereHas(
                                            'cuota.credito.proposicion.cliente',
                                            fn(Builder $sq) => $sq->where('DNI', 'like', '%' . $data['dni'] . '%')
                                        )
                                        ->orWhereHas(
                                            'credito.proposicion.cliente',
                                            fn(Builder $sq) => $sq->where('DNI', 'like', '%' . $data['dni'] . '%')
                                        );
                                    }
                                );
                            }
                        );
                    }),

                Tables\Filters\SelectFilter::make('zona')
                    ->label('Zona')
                    ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            function (Builder $q) use ($data) {
                                return $q->where(
                                    function (Builder $subQ) use ($data) {
                                        $subQ->whereHas(
                                            'cuota.credito.proposicion.cliente.negocio', 
                                            fn(Builder $sq) => $sq->where('ZonaID', $data['value'])
                                        )
                                        ->orWhereHas(
                                            'credito.proposicion.cliente.negocio',
                                            fn(Builder $sq) => $sq->where('ZonaID', $data['value'])
                                        );
                                    }
                                );
                            }
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
                // Excluir pagos automáticos SOLO si TipoConcepto = 'C' (cuotas normales)
                // Se muestran pagos de TODOS los tipos de crédito incluyendo Refinanciamiento
                // Los pagos automáticos con TipoConcepto diferente a 'C' (descuentos, exoneraciones) SI se muestran
                $query->where(function (Builder $q) {
                    $q->where('EsPagoAutomatico', 0) // Mostrar todos los pagos normales
                        ->orWhere(function (Builder $subQ) {
                            $subQ->where('EsPagoAutomatico', 1) // Y los pagos automáticos
                                ->where('TipoConcepto', '!=', 'C'); // Que NO sean de tipo cuota
                        });
                });
                
                // Join con Credito para obtener datos directamente
                $query->leftJoin('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
                    ->leftJoin('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
                    ->leftJoin('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
                    ->leftJoin('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
                    ->leftJoin('cuota', 'pago.CuotaID', '=', 'cuota.CuotaID')
                    ->select([
                        'pago.*',
                        'Cliente.NombresApellidos as cliente_nombre',
                        'TipoCredito.Descripcion as tipo_credito_desc',
                        'ProposicionCredito.CodigoCredito as codigo_credito',
                        'cuota.NumeroCuota as numero_cuota'
                    ]);
                
                return $query;
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