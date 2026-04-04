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
use Filament\Tables\Enums\FiltersLayout;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
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
                Forms\Components\Section::make('Método de Pago')
                    ->schema([
                        Forms\Components\Select::make('TipoPago')
                            ->label('Seleccionar Método de Pago')
                            ->prefixIcon('heroicon-m-credit-card')
                            ->options([
                                'EFECTIVO' => 'Efectivo',
                                'YAPE_PLIN' => 'Yape o Plin',
                                'TRANSFERENCIA_BANCARIA' => 'Transferencia Bancaria',
                            ])
                            ->required()
                            ->live()
                            ->native(false)
                            ->hidden(fn(Get $get) => filled($get('TipoPago'))),

                        Forms\Components\Placeholder::make('metodo_seleccionado_display')
                            ->label('Método de Pago Seleccionado')
                            ->content(fn(Get $get) => match ($get('TipoPago')) {
                                'EFECTIVO' => 'EFECTIVO',
                                'YAPE_PLIN' => 'YAPE O PLIN',
                                'TRANSFERENCIA_BANCARIA' => 'TRANSFERENCIA BANCARIA',
                                default => 'Ninguno',
                            })
                            ->visible(fn(Get $get) => filled($get('TipoPago'))),
                    ]),

                Forms\Components\Section::make('Información del Pago')
                    ->hidden(fn(Get $get) => !$get('TipoPago'))
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
                            ->prefixIcon('heroicon-m-user')
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
                            ->prefixIcon('heroicon-m-identification')
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
                            ->prefixIcon('heroicon-m-clipboard-document-list')
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
                    ->hidden(fn(Get $get) => !$get('TipoPago'))
                    ->schema([
                        Forms\Components\TextInput::make('MontoPagado')
                            ->label('Monto Pagado')
                            ->prefixIcon('heroicon-m-banknotes')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->placeholder('Ingrese el monto del pago'),

                        Forms\Components\DatePicker::make('FechaPago')
                            ->label('Fecha de Pago')
                            ->prefixIcon('heroicon-m-calendar')
                            ->required()
                            ->default(function () {
                                $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                                return $fechaAbierta ?? now();
                            })
                            ->disabled()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),

                Forms\Components\Section::make('Comentarios')
                    ->hidden(fn(Get $get) => !$get('TipoPago'))
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

                Tables\Columns\IconColumn::make('EsPagoInicial')
                    ->label('Inicial')
                    ->boolean()
                    ->sortable()
                    ->tooltip('Pago realizado el día de generación del crédito'),

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
                Tables\Filters\Filter::make('filtros_dinamicos')
                    ->form([
                        Forms\Components\Select::make('campos_activos')
                            ->label('Seleccionar Filtros a Aplicar')
                            ->placeholder('Haz clic para elegir filtros...')
                            ->multiple()
                            ->options([
                                'sede' => 'Sede/Sucursal',
                                'cliente' => 'Cliente (Nombre/ID)',
                                'dni' => 'DNI del Cliente',
                                'zona' => 'Zona/Sector',
                                'fecha' => 'Rango de Fechas',
                            ])
                            ->live()
                            ->columnSpanFull()
                            ->native(false),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Select::make('SedeID')
                                    ->label('Sede')
                                    ->options(\App\Models\Sede::all()->pluck('Nombre', 'SedeID')->toArray())
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn(Get $get) => in_array('sede', $get('campos_activos') ?? [])),

                                Forms\Components\Select::make('ClienteID')
                                    ->label('Cliente')
                                    ->options(\App\Models\Cliente::all()->pluck('NombresApellidos', 'ClienteID')->toArray())
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn(Get $get) => in_array('cliente', $get('campos_activos') ?? [])),

                                Forms\Components\TextInput::make('DNI')
                                    ->label('DNI')
                                    ->placeholder('Ingrese DNI')
                                    ->visible(fn(Get $get) => in_array('dni', $get('campos_activos') ?? [])),

                                Forms\Components\Select::make('ZonaID')
                                    ->label('Zona')
                                    ->options(\App\Models\Zona::all()->pluck('Nombre', 'ZonaID')->toArray())
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn(Get $get) => in_array('zona', $get('campos_activos') ?? [])),

                                Forms\Components\DatePicker::make('FechaDesde')
                                    ->label('Desde')
                                    ->native(false)
                                    ->visible(fn(Get $get) => in_array('fecha', $get('campos_activos') ?? [])),

                                Forms\Components\DatePicker::make('FechaHasta')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->visible(fn(Get $get) => in_array('fecha', $get('campos_activos') ?? [])),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                isset($data['SedeID']) && $data['SedeID'],
                                fn(Builder $q) => $q->where('pago.SedeID', $data['SedeID'])
                            )
                            ->when(
                                isset($data['ClienteID']) && $data['ClienteID'],
                                fn(Builder $q) => $q->where('Cliente.ClienteID', $data['ClienteID'])
                            )
                            ->when(
                                isset($data['DNI']) && $data['DNI'],
                                fn(Builder $q) => $q->where('Cliente.DNI', 'like', "%{$data['DNI']}%")
                            )
                            ->when(
                                isset($data['ZonaID']) && $data['ZonaID'],
                                fn(Builder $q) => $q->whereHas('cuota.credito.proposicion.cliente.negocio', function ($subQ) use ($data) {
                                    $subQ->where('ZonaID', $data['ZonaID']);
                                })
                            )
                            ->when(
                                isset($data['FechaDesde']) && $data['FechaDesde'],
                                fn(Builder $q) => $q->whereRaw('DATE(`pago`.`FechaPago`) >= ?', [$data['FechaDesde']])
                            )
                            ->when(
                                isset($data['FechaHasta']) && $data['FechaHasta'],
                                fn(Builder $q) => $q->whereRaw('DATE(`pago`.`FechaPago`) <= ?', [$data['FechaHasta']])
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->modifyQueryUsing(function (Builder $query) {
                // Excluir pagos automáticos SOLO si TipoConcepto = 'C' (cuotas normales)
                // Se muestran pagos de TODOS los tipos de crédito incluyendo Refinanciamiento
                // Los pagos automáticos con TipoConcepto diferente a 'C' (descuentos, exoneraciones) SI se muestran
                $query->where(function (Builder $q) {
                    $q->whereRaw('`pago`.`EsPagoAutomatico` = 0') // Mostrar todos los pagos normales
                        ->orWhere(function (Builder $subQ) {
                            $subQ->whereRaw('`pago`.`EsPagoAutomatico` = 1') // Y los pagos automáticos
                                ->whereRaw('`pago`.`TipoConcepto` != ?', ['C']); // Que NO sean de tipo cuota
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
        if (!parent::canCreate()) { return false; }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
            \Filament\Notifications\Notification::make()
                ->title('Día Cerrado')
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
        if (!parent::canEdit($record)) { return false; }

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
        if (!parent::canDelete($record)) { return false; }

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