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
use Illuminate\Support\Facades\DB;

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

    public static function puedeSeleccionarCreditosSaldados($user = null): bool
    {
        $user ??= auth()->user();

        return (bool) (
            $user?->can('registrar_pagos_a_mayor')
            || $user?->can('registrar_pagos_a_mayor_por_mora')
        );
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('solicitudResolucion');
        $user = auth()->user();
        if ($user->isPrivileged()) {
            $sedeId = session('sede_activa');
            return $sedeId ? $query->where('pago.SedeID', $sedeId) : $query->whereRaw('1 = 0');
        }
        return $query->where('pago.SedeID', $user->SedeID);
    }

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
                            ->hidden(fn() => request()->routeIs('*.view')),

                        Forms\Components\Placeholder::make('metodo_seleccionado_display')
                            ->label('Método de Pago Seleccionado')
                            ->content(fn(Get $get) => match ($get('TipoPago')) {
                                'EFECTIVO' => 'EFECTIVO',
                                'YAPE_PLIN' => 'YAPE O PLIN',
                                'TRANSFERENCIA_BANCARIA' => 'TRANSFERENCIA BANCARIA',
                                default => 'Ninguno',
                            })
                            ->visible(fn() => request()->routeIs('*.view')),
                    ]),

                Forms\Components\Section::make('Información del Pago')
                    ->hidden(fn(Get $get) => !$get('TipoPago'))
                    ->schema([
                        Forms\Components\Placeholder::make('cliente_info')
                            ->label('Cliente')
                            ->content(fn($record) =>
                                $record?->cuota?->credito?->proposicion?->cliente?->NombresApellidos
                                ?? $record?->credito?->proposicion?->cliente?->NombresApellidos
                                ?? '-')
                            ->visible(fn() => request()->routeIs('*.view')),

                        Forms\Components\Placeholder::make('zona_info')
                            ->label('Zona')
                            ->content(fn($record) =>
                                $record?->cuota?->credito?->proposicion?->cliente?->negocio?->zona?->Nombre
                                ?? $record?->credito?->proposicion?->cliente?->negocio?->zona?->Nombre
                                ?? '-')
                            ->visible(fn() => request()->routeIs('*.view')),

                        Forms\Components\Placeholder::make('promotor_info')
                            ->label('Promotor Cobrador')
                            ->content(function ($record) {
                                // Obtener la Zona desde Pago -> Cuota -> Credito -> Proposicion -> Zona (fallback: via Credito directo)
                                $zona = $record?->cuota?->credito?->proposicion?->zona
                                    ?? $record?->credito?->proposicion?->zona;
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
                                $user = auth()->user();
                                $puedePagarMayor = static::puedeSeleccionarCreditosSaldados($user);

                                // OPTIMIZADO: Filtrar por columna SaldoPendiente en SQL
                                $query = \App\Models\Cliente::whereHas('proposiciones', function ($q) use ($zonaID, $puedePagarMayor) {
                                    if (!$puedePagarMayor) {
                                        $q->where('FueRefinanciada', 0)->where('Activo', true);
                                    } else {
                                        $q->where(function($sub) {
                                            $sub->where('Activo', true)->orWhere('FueRefinanciada', 1);
                                        });
                                    }

                                    // Filtrar por saldo > 0 O créditos saldados si tiene permiso
                                    $q->where(function ($saldoQ) use ($puedePagarMayor) {
                                        $saldoQ->where('SaldoPendiente', '>', 0);
                                        if ($puedePagarMayor) {
                                            $saldoQ->orWhereHas('credito', fn($cq) => $cq->whereIn('EstatusCreditoFinal', ['SALDADO', 'REFINANCIADO']));
                                        }
                                    });

                                    $q->whereHas('credito', function ($sq) use ($puedePagarMayor) {
                                        $sq->where('Activo', 1);
                                        if (!$puedePagarMayor) {
                                            $sq->whereNotIn('EstatusCreditoFinal', ['SALDADO', 'REFINANCIADO']);
                                        }
                                    });

                                    if ($zonaID) {
                                        $q->where('ZonaID', $zonaID);
                                    }
                                });

                                return $query
                                    ->select('ClienteID', 'NombresApellidos', 'DNI')
                                    ->orderBy('NombresApellidos')
                                    ->get()
                                    ->mapWithKeys(fn($cliente) => [
                                        $cliente->ClienteID => "{$cliente->NombresApellidos} - {$cliente->DNI}",
                                    ]);
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
                                            $user = auth()->user();
                                            $puedePagarMayor = static::puedeSeleccionarCreditosSaldados($user);

                                            if (!$puedePagarMayor) {
                                                $q->where('FueRefinanciada', 0)
                                                  ->where('Activo', true);
                                            } else {
                                                $q->where(function($sub) {
                                                    $sub->where('Activo', true)
                                                        ->orWhere('FueRefinanciada', 1);
                                                });
                                            }

                                            $q->whereHas('credito', function ($sq) use ($user, $puedePagarMayor) {
                                                $sq->where('Activo', 1);
                                                if (!$puedePagarMayor) {
                                                    $sq->whereNotIn('EstatusCreditoFinal', ['SALDADO', 'REFINANCIADO']);
                                                }
                                            })
                                                ->with('tipoCredito');

                                            if ($zonaID) {
                                                $q->where('ZonaID', $zonaID);
                                            }
                                        }
                                    ])->find($state);

                                    if ($cliente) {
                                        // Filtrar proposiciones con saldo pendiente > 0 o saldados si tiene permiso
                                        $user = auth()->user();
                                        $puedePagarMayor = static::puedeSeleccionarCreditosSaldados($user);

                                        $proposicionesConSaldo = $cliente->proposiciones->filter(function ($prop) use ($puedePagarMayor) {
                                            // OPTIMIZADO: Leer de columna SaldoPendiente (ya cargada con el modelo)
                                            $saldo = (float) ($prop->SaldoPendiente ?? 0);
                                            return $saldo > 0 || ($puedePagarMayor && in_array($prop->credito?->EstatusCreditoFinal, ['SALDADO', 'REFINANCIADO']));
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

                                                // Buscar la cuota del día (cuota = día)
                                                $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                                                $fechaHoy = $fechaAbierta ? $fechaAbierta->toDateString() : now()->toDateString();

                                                $cuotaDelDia = \App\Models\Cuota::where('CreditoID', $creditoID)
                                                    ->where('Activo', 1)
                                                    ->where('NumeroCuota', '>', 0)
                                                    ->whereDate('FechaVencimiento', $fechaHoy)
                                                    ->first();

                                                if ($cuotaDelDia) {
                                                    $set('CuotaID', $cuotaDelDia->CuotaID);
                                                } else {
                                                    // Fallback: primera cuota pendiente
                                                    $cuotaPendiente = \App\Models\Cuota::where('CreditoID', $creditoID)
                                                        ->where('Activo', 1)
                                                        ->where('NumeroCuota', '>', 0)
                                                        ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
                                                        ->orderBy('NumeroCuota')
                                                        ->first();
                                                    if ($cuotaPendiente) {
                                                        $set('CuotaID', $cuotaPendiente->CuotaID);
                                                    }
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
                                if (!$clienteID) { return []; }

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;
                                $user = auth()->user();
                                $puedePagarMayor = static::puedeSeleccionarCreditosSaldados($user);

                                // OPTIMIZADO: Usa servicio centralizado (lee columna SaldoPendiente)
                                $creditos = \App\Services\SaldoPendienteService::obtenerCreditosConSaldoParaCliente($clienteID, $zonaID, $puedePagarMayor);

                                if ($creditos->count() < 2) { return []; }

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
                                // OPTIMIZACIÓN: usar el mismo cache key de Cache::remember
                                // (mismo TTL) para evitar 3 invocaciones idénticas por render.
                                $clienteID = $get('ClienteID');
                                if (!$clienteID) { return false; }

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;
                                $user = auth()->user();
                                $puedePagarMayor = static::puedeSeleccionarCreditosSaldados($user);

                                $count = \Illuminate\Support\Facades\Cache::remember(
                                    "pago_saldo_count_{$clienteID}_{$zonaID}_" . ($puedePagarMayor ? '1' : '0'),
                                    5,
                                    fn () => \App\Services\SaldoPendienteService::obtenerCreditosConSaldoParaCliente($clienteID, $zonaID, $puedePagarMayor)->count()
                                );

                                return $count >= 2;
                            })
                            ->searchable()
                            ->native(false)
                            ->visible(function (Forms\Get $get) {
                                // OPTIMIZACIÓN: reutilizar el conteo cacheado (5s TTL).
                                $clienteID = $get('ClienteID');
                                if (!$clienteID) { return false; }

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;
                                $user = auth()->user();
                                $puedePagarMayor = static::puedeSeleccionarCreditosSaldados($user);

                                $count = \Illuminate\Support\Facades\Cache::remember(
                                    "pago_saldo_count_{$clienteID}_{$zonaID}_" . ($puedePagarMayor ? '1' : '0'),
                                    5,
                                    fn () => \App\Services\SaldoPendienteService::obtenerCreditosConSaldoParaCliente($clienteID, $zonaID, $puedePagarMayor)->count()
                                );

                                return $count >= 2;
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
                                if (request()->routeIs('*.view')) { return false; }

                                $clienteID = $get('ClienteID');
                                if (!$clienteID) return true;

                                $promotorCobrador = auth()->user()?->promotorCobrador;
                                $zonaID = $promotorCobrador?->ZonaID;
                                $user = auth()->user();
                                $puedePagarMayor = static::puedeSeleccionarCreditosSaldados($user);

                                // OPTIMIZACIÓN: reutilizar el conteo cacheado.
                                $count = \Illuminate\Support\Facades\Cache::remember(
                                    "pago_saldo_count_{$clienteID}_{$zonaID}_" . ($puedePagarMayor ? '1' : '0'),
                                    5,
                                    fn () => \App\Services\SaldoPendienteService::obtenerCreditosConSaldoParaCliente($clienteID, $zonaID, $puedePagarMayor)->count()
                                );

                                return $count < 2;
                            }),

                        Forms\Components\Placeholder::make('tipo_credito_view')
                            ->label('Tipo de Crédito')
                            ->content(fn($record) =>
                                $record?->cuota?->credito?->proposicion?->tipoCredito?->Descripcion
                                ?? $record?->credito?->proposicion?->tipoCredito?->Descripcion
                                ?? '-')
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
                                    ->with(['pagos' => fn($q) => $q->where('Activo', 1)])
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
                                // Auto-seleccionar la cuota del día (cuota = día)
                                $creditoID = $get('CreditoID');
                                if ($creditoID && !$get('CuotaID')) {
                                    $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                                    $fechaHoy = $fechaAbierta ? $fechaAbierta->toDateString() : now()->toDateString();

                                    $cuotaDelDia = \App\Models\Cuota::where('CreditoID', $creditoID)
                                        ->where('Activo', 1)
                                        ->where('NumeroCuota', '>', 0)
                                        ->whereDate('FechaVencimiento', $fechaHoy)
                                        ->first();

                                    if ($cuotaDelDia) {
                                        $set('CuotaID', $cuotaDelDia->CuotaID);
                                    } else {
                                        // Fallback: primera cuota pendiente
                                        $cuotaPendiente = \App\Models\Cuota::where('CreditoID', $creditoID)
                                            ->where('Activo', 1)
                                            ->where('NumeroCuota', '>', 0)
                                            ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
                                            ->orderBy('NumeroCuota')
                                            ->first();
                                        if ($cuotaPendiente) {
                                            $set('CuotaID', $cuotaPendiente->CuotaID);
                                        } else {
                                            // Fallback final: cualquier cuota como referencia
                                            $cuotaRef = \App\Models\Cuota::where('CreditoID', $creditoID)
                                                ->where('NumeroCuota', '>', 0)
                                                ->orderBy('NumeroCuota')
                                                ->first();
                                            if ($cuotaRef) {
                                                $set('CuotaID', $cuotaRef->CuotaID);
                                            }
                                        }
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
                            ->placeholder('Ingrese el monto del pago')
                            ->extraAttributes(['onwheel' => 'return false;']),

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

                        Forms\Components\Toggle::make('EsMora')
                            ->label('Es Pago de Mora')
                            ->default(false)
                            ->live()
                            ->visible(fn () => auth()->user()?->can('registrar_pago_mora') ?? false)
                            ->helperText('Marcar si este pago corresponde a mora. No afecta el saldo pendiente del crédito.'),
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
            ->persistFiltersInSession()
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhere('Cliente.NombresApellidos', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('Cliente.NombresApellidos', $direction)),

                Tables\Columns\TextColumn::make('tipo_credito_desc')
                    ->label('Tipo de Crédito')
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhere('TipoCredito.Descripcion', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('TipoCredito.Descripcion', $direction)),

                Tables\Columns\TextColumn::make('zona_nombre')
                    ->label('Zona')
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhere('Zona.Nombre', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('Zona.Nombre', $direction)),

                Tables\Columns\TextColumn::make('codigo_credito')
                    ->label('Código Crédito')
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhere('ProposicionCredito.CodigoCredito', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('ProposicionCredito.CodigoCredito', $direction)),

                Tables\Columns\TextColumn::make('numero_pago')
                    ->label('Pago #')
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->EsPagoAMayorPorMora) return 'A MAYOR X MORA';
                        if ($record->EsPagoAMayor && $record->SolicitudResolucionID) return 'EXTORNO';
                        if ($record->EsPagoAMayor) return 'A MAYOR';
                        if ($record->EsMora) return 'MORA';
                        if ($record->EsPagoAutomatico) return 'AUTO';
                        if ($state) return "Pago #{$state}";
                        return '-';
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

                Tables\Columns\TextColumn::make('EsPagoAutomatico')
                    ->label('Origen')
                    ->sortable()
                    ->formatStateUsing(fn($state, $record) => match (true) {
                        $record->PagoOrigenID !== null => 'Traslado',
                        $record->solicitudResolucion !== null => 'Excedente',
                        $record->EsPagoAutomatico && $record->TipoConcepto === 'C' => 'Refinanciamiento',
                        $record->EsPagoAutomatico => 'Automático',
                        default => 'Normal',
                    })
                    ->badge()
                    ->color(fn($state, $record) => match (true) {
                        $record->PagoOrigenID !== null => 'danger',
                        $record->solicitudResolucion !== null => 'success',
                        $record->EsPagoAutomatico && $record->TipoConcepto === 'C' => 'info',
                        $record->EsPagoAutomatico => 'warning',
                        default => 'gray',
                    }),

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
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhere('pago.UsuarioRegistro', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('pago.UsuarioRegistro', $direction))
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
                                'tipo_pago' => 'Método de Pago',
                                'origen_pago' => 'Origen del Pago (Normal/Automático)',
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
                                    ->options(fn() => \App\Models\Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => in_array('sede', $get('campos_activos') ?? [])),

                                Forms\Components\Select::make('ClienteID')
                                    ->label('Cliente')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search) => \App\Models\Cliente::where('NombresApellidos', 'like', "%{$search}%")->orWhere('DNI', 'like', "%{$search}%")->limit(50)->pluck('NombresApellidos', 'ClienteID'))
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => in_array('cliente', $get('campos_activos') ?? [])),

                                Forms\Components\TextInput::make('DNI')
                                    ->label('DNI')
                                    ->placeholder('Ingrese DNI')
                                    ->live(debounce: 500)
                                    ->visible(fn(Get $get) => in_array('dni', $get('campos_activos') ?? [])),

                                Forms\Components\Select::make('ZonaID')
                                    ->label('Zona')
                                    ->options(fn() => \App\Models\Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => in_array('zona', $get('campos_activos') ?? [])),

                                Forms\Components\DatePicker::make('FechaDesde')
                                    ->label('Desde')
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => in_array('fecha', $get('campos_activos') ?? [])),

                                Forms\Components\DatePicker::make('FechaHasta')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => in_array('fecha', $get('campos_activos') ?? [])),

                                Forms\Components\CheckboxList::make('TipoPago')
                                    ->label('Método de Pago')
                                    ->options([
                                        'EFECTIVO' => 'Efectivo',
                                        'YAPE_PLIN' => 'Yape o Plin',
                                        'TRANSFERENCIA_BANCARIA' => 'Transferencia Bancaria',
                                    ])
                                    ->columns(1)
                                    ->live()
                                    ->visible(fn(Get $get) => in_array('tipo_pago', $get('campos_activos') ?? [])),

                                Forms\Components\Select::make('EsPagoAutomatico')
                                    ->label('Origen del Pago')
                                    ->options([
                                        '0' => 'Normal',
                                        '1' => 'Automáticos',
                                    ])
                                    ->placeholder('Todos')
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => in_array('origen_pago', $get('campos_activos') ?? [])),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $activos = $data['campos_activos'] ?? [];
                        return $query
                            ->when(
                                in_array('sede', $activos) && isset($data['SedeID']) && $data['SedeID'],
                                fn(Builder $q) => $q->where('pago.SedeID', $data['SedeID'])
                            )
                            ->when(
                                in_array('cliente', $activos) && isset($data['ClienteID']) && $data['ClienteID'],
                                fn(Builder $q) => $q->where('Cliente.ClienteID', $data['ClienteID'])
                            )
                            ->when(
                                in_array('dni', $activos) && isset($data['DNI']) && $data['DNI'],
                                fn(Builder $q) => $q->where('Cliente.DNI', 'like', "%{$data['DNI']}%")
                            )
                            ->when(
                                in_array('zona', $activos) && isset($data['ZonaID']) && $data['ZonaID'],
                                fn(Builder $q) => $q->whereHas('credito.proposicion', function ($subQ) use ($data) {
                                    $subQ->where('ZonaID', $data['ZonaID']);
                                })
                            )
                            ->when(
                                in_array('fecha', $activos) && isset($data['FechaDesde']) && $data['FechaDesde'],
                                fn(Builder $q) => $q->whereDate('pago.FechaPago', '>=', $data['FechaDesde'])
                            )
                            ->when(
                                in_array('fecha', $activos) && isset($data['FechaHasta']) && $data['FechaHasta'],
                                fn(Builder $q) => $q->whereDate('pago.FechaPago', '<=', $data['FechaHasta'])
                            )
                            ->when(
                                in_array('tipo_pago', $activos) && !empty($data['TipoPago']),
                                fn(Builder $q) => $q->whereIn('pago.TipoPago', $data['TipoPago'])
                            )
                            ->when(
                                in_array('origen_pago', $activos) && isset($data['EsPagoAutomatico']) && $data['EsPagoAutomatico'] !== '',
                                fn(Builder $q) => $q->where('pago.EsPagoAutomatico', $data['EsPagoAutomatico'])
                            );
                    }),

            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->modifyQueryUsing(function (Builder $query) {
                
                // Join con Credito para obtener datos directamente
                $query->leftJoin('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
                    ->leftJoin('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
                    ->leftJoin('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
                    ->leftJoin('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
                    ->leftJoin('Zona', 'ProposicionCredito.ZonaID', '=', 'Zona.ZonaID')
                    ->select([
                        'pago.*',
                        'Cliente.NombresApellidos as cliente_nombre',
                        'TipoCredito.Descripcion as tipo_credito_desc',
                        'Zona.Nombre as zona_nombre',
                        'ProposicionCredito.CodigoCredito as codigo_credito',
                        DB::raw("(
                            SELECT COUNT(*)
                            FROM pago p2
                            WHERE p2.CreditoID = pago.CreditoID
                              AND (
                                  p2.FechaPago < pago.FechaPago
                                  OR (p2.FechaPago = pago.FechaPago AND p2.PagoID <= pago.PagoID)
                              )
                        ) as numero_pago")
                    ]);
                
                return $query;
            })
            ->defaultSort('pago.FechaPago', 'desc')
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

        // Si el usuario es promotor y hay bloqueo activo en su sede, zona o promotor, bloquear
        $user = auth()->user();
        if ($user?->hasRole('promotor_cobrador') && $user->SedeID) {
            $sedeId = $user->getEffectiveSedeId();
            $promotorCobrador = $user->promotorCobrador;
            $zonaId = $promotorCobrador?->ZonaID ?? null;
            $promotorId = $user->PromotorCobradorID ?? null;

            $bloqueado = \App\Models\PagoBloqueoPromotor::estaBloqueado($sedeId, $zonaId, $promotorId);
            if ($bloqueado) {
                \Filament\Notifications\Notification::make()
                    ->title('Pagos Bloqueados')
                    ->body('El registro de pagos para promotores está deshabilitado por administración.')
                    ->danger()
                    ->persistent()
                    ->send();
                return false;
            }
        }

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
    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\PagosCantidadStatsWidget::class,
            \App\Filament\Widgets\PagosMontoStatsWidget::class,
            \App\Filament\Widgets\PagosMontoMesStatsWidget::class,
        ];
    }
}
