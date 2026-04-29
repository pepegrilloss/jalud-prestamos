<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GenerarCreditoResource\Pages;
use App\Filament\Resources\GenerarCreditoResource\Widgets;
use App\Models\ProposicionCredito;
use App\Models\Cliente;
use App\Models\TipoCredito;
use App\Models\Tasa;
use App\Models\Zona;
use App\Models\Credito;
use App\Models\TipoPago;
use App\Models\Pago;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class GenerarCreditoResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;
    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Generar Crédito';
    protected static ?string $modelLabel = 'Generar Crédito';
    protected static ?string $pluralModelLabel = 'Generar Crédito';

    /**
     * Override para usar los permisos de 'generar::credito' en lugar de ProposicionCreditoPolicy.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_generar::credito') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_generar::credito') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!(auth()->user()?->can('update_generar::credito') ?? false)) { return false; }

        if ($record->FechaCierre !== null) {
            return false;
        }
        return true;
    }

    public static function canDelete($record): bool
    {
        if (!(auth()->user()?->can('delete_generar::credito') ?? false)) { return false; }

        if ($record->FechaCierre !== null) {
            return false;
        }
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Crédito')
                    ->schema([
                        Forms\Components\Select::make('ClienteID')
                            ->label('Cliente')
                            ->options(
                                Cliente::where('Activo', true)
                                    ->orderBy('NombresApellidos')
                                    ->get()
                                    ->mapWithKeys(fn($cliente) => [
                                        $cliente->ClienteID => "{$cliente->NombresApellidos} (DNI: {$cliente->DNI})"
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->columnSpanFull()
                            ->default(function () {
                                try {
                                    if ($encrypted = request()->query('cliente')) {
                                        session()->put('cliente_predefinido', true);
                                        return Crypt::decrypt($encrypted);
                                    }
                                } catch (\Exception $e) {
                                    return null;
                                }
                            })
                            ->disabled(fn() => session()->has('cliente_predefinido'))
                            ->dehydrated()
                            ->live(debounce: 0)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $cliente = Cliente::find($state);
                                    if ($cliente) {
                                        $set('CodigoCliente', $cliente->DNI);
                                        $set('ZonaID', $cliente->ZonaID);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('CodigoCredito')
                            ->label('Código de Crédito')
                            ->disabled()
                            ->dehydrated()
                            ->default(fn() => ProposicionCredito::generarCodigoCredito())
                            ->columnSpanFull(),

                        Forms\Components\Select::make('TipoCreditoID')
                            ->label('Tipo de Crédito')
                            ->options(TipoCredito::where('Activo', true)->pluck('Descripcion', 'TipoCreditoID'))
                            ->required()
                            ->searchable()
                            ->native(false),

                        Forms\Components\TextInput::make('MontoTotal')
                            ->label('Monto Total')
                            ->required()
                            ->numeric()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get, $state) => static::calcularTotales($set, $get, $state)),

                        Forms\Components\Select::make('TasaID')
                            ->label('Tasa de Interés')
                            ->options(Tasa::where('Activo', true)->get()->mapWithKeys(fn($t) => [$t->TasaID => "{$t->Nombre} - {$t->Valor}%"]))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                if ($tasa = Tasa::find($state)) {
                                    $set('TasaInteres', $tasa->Valor);
                                    $set('Plazo', $tasa->Dias);
                                    $set('NumeroCuotas', $tasa->Cuotas);
                                    static::calcularTotales($set, $get, $get('MontoTotal'));
                                }
                            }),

                        Forms\Components\TextInput::make('TasaInteres')
                            ->label('Tasa (%)')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => static::calcularTotales($set, $get, $get('MontoTotal'))),
                        Forms\Components\TextInput::make('Plazo')->label('Plazo (días)')->required()->numeric(),
                        Forms\Components\TextInput::make('NumeroCuotas')->label('N° Cuotas')->required()->numeric()
                            ->live(onBlur: true)->afterStateUpdated(fn(Set $set, Get $get) => static::calcularTotales($set, $get, $get('MontoTotal'))),

                        Forms\Components\TextInput::make('MontoCuota')->label('Monto por Cuota')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoInteres')->label('Monto Total Interés')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('MontoTotalPagar')->label('Monto Total a Pagar')->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('TasaMora')->label('Mora (S/)')->required()->numeric()->default(0.50),
                    ])->columns(3),

                Forms\Components\Section::make('Información Adicional')
                    ->schema([
                        Forms\Components\Select::make('ZonaID')
                            ->label('Zona')
                            ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                            ->searchable(),
                        Forms\Components\Textarea::make('Observaciones')->rows(3)->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    protected static function calcularTotales(Set $set, Get $get, $monto): void
    {
        $montoVal = (float) $monto;
        $tasaVal = (float) $get('TasaInteres');
        $cuotasVal = (int) $get('NumeroCuotas');

        if ($montoVal > 0 && $tasaVal > 0 && $cuotasVal > 0) {
            $interes = $montoVal * ($tasaVal / 100);
            $total = $montoVal + $interes;
            $set('MontoInteres', round($interes, 2));
            $set('MontoTotalPagar', round($total, 2));
            $set('MontoCuota', round($total / $cuotasVal, 2));
        }
    }

    protected static function recalcularTotales(Set $set, Get $get): void
    {
        $montoVal = (float) ($get('MontoTotal') ?? 0);
        $tasaVal = (float) ($get('TasaInteres') ?? 0);
        $cuotasVal = (int) ($get('NumeroCuotas') ?? 0);

        if ($montoVal > 0 && $tasaVal > 0 && $cuotasVal > 0) {
            $interes = $montoVal * ($tasaVal / 100);
            $total = $montoVal + $interes;
            $set('MontoInteres', round($interes, 2));
            $set('MontoTotalPagar', round($total, 2));
            $set('MontoCuota', round($total / $cuotasVal, 2));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('CodigoCredito')->label('Código')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cliente.NombresApellidos')->label('Cliente')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('MontoTotal')->label('Monto')->money('PEN')->sortable(),
                Tables\Columns\TextColumn::make('TasaInteres')->label('Tasa (%)')->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', '') . ' %')->sortable(),
                Tables\Columns\TextColumn::make('MontoInteres')
                    ->label('Intereses')
                    ->sortable()
                    ->getStateUsing(fn($record) => (float) (($record->MontoTotal ?? 0) * (($record->TasaInteres ?? 0) / 100)))
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format((float) $state, 2, '.', '')),

                Tables\Columns\TextColumn::make('MontoTotalPagar')
                    ->label('Monto Total')
                    ->sortable()
                    ->getStateUsing(fn($record) => (float) (($record->MontoTotal ?? 0) + (($record->MontoTotal ?? 0) * (($record->TasaInteres ?? 0) / 100))))
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format((float) $state, 2, '.', '')),
                Tables\Columns\TextColumn::make('NumeroCuotas')->label('Cuotas')->sortable(),
                Tables\Columns\TextColumn::make('Plazo')->label('Días')->sortable(),
                Tables\Columns\TextColumn::make('Estado')->badge()->color(fn(string $state): string => match ($state) {
                    'APROBADO' => 'success',
                    default => 'gray',
                })->sortable(),
            ])
            // CORRECCIÓN: Se cambió $q por $query para evitar el error de resolución
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('Estado', 'APROBADO')
                    ->whereDoesntHave('credito');
            })
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),

            ])

            ->actions([
                Tables\Actions\Action::make('ver_comentarios')
                    ->label('Comentarios')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->modalHeading('Historial de Evaluación')
                    ->modalSubmitAction(false)
                    ->form([
                        Forms\Components\ViewField::make('evaluaciones')
                            ->view('filament.components.evaluaciones-credito')
                    ]),

                Action::make('editar_datos')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Editar Datos del Crédito')
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\Section::make('Valores del Crédito')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('MontoTotal')
                                            ->label('Monto')
                                            ->numeric()
                                            ->step(0.01)
                                            ->required()
                                            ->live(debounce: 300)
                                            ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcularTotales($set, $get)),

                                        Forms\Components\Select::make('TasaID')
                                            ->label('Tasa de Interés')
                                            ->options(Tasa::where('Activo', true)->get()->mapWithKeys(fn($t) => [$t->TasaID => "{$t->Nombre} - {$t->Valor}%"]))
                                            ->required()
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                if ($tasa = Tasa::find($state)) {
                                                    $set('TasaInteres', $tasa->Valor);
                                                    $set('Plazo', $tasa->Dias);
                                                    $set('NumeroCuotas', $tasa->Cuotas);
                                                    static::recalcularTotales($set, $get);
                                                }
                                            }),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('TasaInteres')
                                            ->label('Tasa %')
                                            ->numeric()
                                            ->step(0.01)
                                            ->required()
                                            ->live(debounce: 300)
                                            ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcularTotales($set, $get)),

                                        Forms\Components\TextInput::make('Plazo')
                                            ->label('Plazo (días)')
                                            ->numeric()
                                            ->required(),

                                        Forms\Components\TextInput::make('NumeroCuotas')
                                            ->label('N° cuotas')
                                            ->numeric()
                                            ->required()
                                            ->live(debounce: 300)
                                            ->afterStateUpdated(fn(Set $set, Get $get) => static::recalcularTotales($set, $get)),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('MontoInteres')
                                            ->label('Total Interés')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(),
                                        Forms\Components\TextInput::make('MontoTotalPagar')
                                            ->label('Total a Pagar')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(),
                                        Forms\Components\TextInput::make('MontoCuota')
                                            ->label('Monto por cuota')
                                            ->numeric()
                                            ->step(0.01)
                                            ->required(),
                                    ]),
                            ]),
                    ])
                    ->fillForm(fn(ProposicionCredito $record) => [
                        'MontoTotal' => $record->MontoTotal,
                        'TasaID' => Tasa::where('Valor', $record->TasaInteres)->where('Activo', true)->first()?->TasaID,
                        'TasaInteres' => $record->TasaInteres,
                        'Plazo' => $record->Plazo,
                        'NumeroCuotas' => $record->NumeroCuotas,
                        'MontoInteres' => $record->MontoInteres,
                        'MontoTotalPagar' => $record->MontoTotalPagar,
                        'MontoCuota' => $record->MontoCuota,
                    ])
                    ->action(function (ProposicionCredito $record, array $data) {
                        $monto = (float) ($data['MontoTotal'] ?? $record->MontoTotal);
                        $tasa = (float) ($data['TasaInteres'] ?? $record->TasaInteres);
                        $interesCalculado = $monto * ($tasa / 100);
                        $totalCalculado = $monto + $interesCalculado;

                        $record->update([
                            'MontoTotal' => $data['MontoTotal'] ?? $record->MontoTotal,
                            'TasaID' => $data['TasaID'] ?? $record->TasaID,
                            'TasaInteres' => $data['TasaInteres'] ?? $record->TasaInteres,
                            'Plazo' => $data['Plazo'] ?? $record->Plazo,
                            'NumeroCuotas' => $data['NumeroCuotas'] ?? $record->NumeroCuotas,
                            'MontoInteres' => $data['MontoInteres'] ?? $interesCalculado,
                            'MontoTotalPagar' => $data['MontoTotalPagar'] ?? $totalCalculado,
                            'MontoCuota' => $data['MontoCuota'] ?? $record->MontoCuota,
                        ]);

                        Notification::make()->title('Datos actualizados')->success()->send();
                    }),

                Action::make('generar_credito')
                    ->label('Generar Crédito')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->modalHeading('Confirmar Formalización')
                    ->modalWidth('4xl')
                    ->form([
                        Forms\Components\ViewField::make('resumen_moderno')
                            ->columnSpanFull()
                            ->view('filament.components.resumen-credito-moderno'),

                        Forms\Components\Section::make('Datos de Formalización')
                            ->schema([
                                Forms\Components\Select::make('TipoPagoID')
                                    ->label('Frecuencia de Pago')
                                    ->options(TipoPago::where('Activo', true)->pluck('Nombre', 'TipoPagoID'))
                                    ->required()
                                    ->native(false)
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('ComentarioGeneracion')
                                    ->label('Notas')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                    ])
                    ->action(function (ProposicionCredito $record, array $data) {
                        // Crear el crédito
                        // Usar la fecha abierta en lugar de now() (con hora actual)
                        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                        $fechaGeneracion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
                        
                        $credito = Credito::create([
                            'ProposicionCreditoID' => $record->ProposicionCreditoID,
                            'TipoPagoID' => $data['TipoPagoID'],
                            'ComentarioGeneracion' => $data['ComentarioGeneracion'],
                            'FechaGeneracion' => $fechaGeneracion,
                            'UserGeneracionID' => auth()->id(),
                            'Activo' => true,
                        ]);

                        // Calcular fechas usando la misma lógica de cuotas
                        // (omitiendo domingos y feriados)
                        $feriadosData = [];
                        try {
                            $fechaInicio = \Carbon\Carbon::parse($credito->FechaGeneracion);
                            $fechaFin = $fechaInicio->copy()->addDays($record->NumeroCuotas * 2); // Buffer de seguridad
                            $annoInicio = $fechaInicio->year;
                            $annoFin = $fechaFin->year;
                            
                            for ($anno = $annoInicio; $anno <= $annoFin; $anno++) {
                                try {
                                    $response = file_get_contents("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                                    $feriados = json_decode($response, true);
                                    foreach ($feriados as $feriado) {
                                        $feriadosData[$feriado['date']] = $feriado['localName'];
                                    }
                                } catch (\Exception $e) {
                                    // Continuar sin feriados si falla la API
                                }
                            }
                        } catch (\Exception $e) {
                            // Continuar sin feriados
                        }

                        // Calcular FechaInicio (primera cuota real, omitiendo domingos y feriados)
                        $fechaActual = \Carbon\Carbon::parse($credito->FechaGeneracion)->addDay();
                        $cuotasContadas = 0;
                        $fechaInicio = null;
                        $fechaVencimiento = null;

                        while ($cuotasContadas < $record->NumeroCuotas) {
                            $esDomingo = $fechaActual->dayOfWeek == 0;
                            $esFeriado = isset($feriadosData[$fechaActual->format('Y-m-d')]);

                            // Si NO es domingo ni feriado, es una cuota real
                            if (!$esDomingo && !$esFeriado) {
                                if ($fechaInicio === null) {
                                    $fechaInicio = $fechaActual->clone();
                                }
                                $fechaVencimiento = $fechaActual->clone();
                                $cuotasContadas++;
                            }

                            $fechaActual->addDay();
                        }

                        // Actualizar crédito con las fechas calculadas
                        if ($fechaInicio && $fechaVencimiento) {
                            $credito->update([
                                'FechaInicio' => $fechaInicio->format('Y-m-d'),
                                'FechaVencimiento' => $fechaVencimiento->format('Y-m-d'),
                            ]);
                        }

                        // Las cuotas se crean automáticamente en el Observer

                        // PAGO AUTOMÁTICO SI ES REFINANCIAMIENTO
                        if ($record->EsRefinanciamiento && $record->ProposicionCreditoAnteriorID) {
                            self::crearPagoAutomaticoRefinanciamiento($record, $credito);
                        }

                        \Filament\Notifications\Notification::make()->title('Crédito Generado')->success()->send();
                    }),
            ]);
    }

    protected static function crearPagoAutomaticoRefinanciamiento(ProposicionCredito $record, Credito $creditoNuevo): void
    {
        try {
            $proposicionAnterior = ProposicionCredito::find($record->ProposicionCreditoAnteriorID);

            if (!$proposicionAnterior) {
                return;
            }

            // Obtener la fecha de apertura (con hora actual)
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $fechaPago = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

            // Obtener el crédito de la proposición anterior
            $creditoAnterior = Credito::where('ProposicionCreditoID', $proposicionAnterior->ProposicionCreditoID)
                ->where('Activo', true)
                ->first();

            if (!$creditoAnterior) {
                return;
            }

            // Obtener todas las cuotas del crédito anterior
            $allCuotas = $creditoAnterior->cuotas()->where('Activo', true)->get();

            // Filtrar cuotas pendientes
            $cuotasPendientes = $allCuotas->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA', 'NORMAL']);

            if ($cuotasPendientes->isEmpty()) {
                return;
            }

            // Calcular el saldo total pendiente desde la proposición anterior (ya calculado correctamente)
            $saldoTotalPendiente = (float) $proposicionAnterior->SaldoPendiente;

            $montoRefinanciamiento = (float) $record->MontoTotal;

            // Obtener el promotor cobrador del cliente
            $cliente = Cliente::find($record->ClienteID);
            $promotorCobradorID = $cliente?->PromotorCobradorID;

            // Fallback: Si el cliente no tiene promotor, intentar usar el del usuario actual
            if (!$promotorCobradorID) {
                $promotorCobradorID = Auth::user()?->PromotorCobradorID;
            }

            // PAGO 1: Crear pago por el saldo pendiente del crédito anterior
            $pago1 = Pago::create([
                'CreditoID' => $creditoAnterior->CreditoID,
                'CuotaID' => $cuotasPendientes->first()->CuotaID,
                'PromotorCobradorID' => $promotorCobradorID,
                'MontoPagado' => $saldoTotalPendiente,
                'FechaPago' => $fechaPago,
                'EsMora' => false,
                'EsPagoAMayor' => false,
                'EsPagoForzado' => false,
                'EsPagoAutomatico' => 1,
                'Comentario' => "Pago automático por refinanciamiento. Proposición #{$record->ProposicionCreditoID}. Saldo total: S/ " . number_format($saldoTotalPendiente, 2),
                'UsuarioRegistro' => Auth::user()?->name ?? 'Sistema',
                'Activo' => true,
            ]);

            // Si el monto refinanciado es mayor al saldo pendiente, crear PAGO 2 como pago a mayor
            if ($montoRefinanciamiento > $saldoTotalPendiente) {
                $montoAMayor = $montoRefinanciamiento - $saldoTotalPendiente;

                Pago::create([
                    'CreditoID' => $creditoAnterior->CreditoID,
                    'CuotaID' => $cuotasPendientes->first()->CuotaID,
                    'PromotorCobradorID' => $promotorCobradorID,
                    'MontoPagado' => $montoAMayor,
                    'FechaPago' => $fechaPago,
                    'EsMora' => false,
                    'EsPagoAMayor' => true,
                    'EsPagoForzado' => false,
                    'EsPagoAutomatico' => 1,
                    'Comentario' => "Pago a mayor automático por refinanciamiento. Proposición #{$record->ProposicionCreditoID}. Monto adicional: S/ " . number_format($montoAMayor, 2),
                    'UsuarioRegistro' => Auth::user()?->name ?? 'Sistema',
                    'Activo' => true,
                ]);
            }

            // Actualizar todas las cuotas pendientes como pagadas
            foreach ($cuotasPendientes as $cuota) {
                $cuota->update([
                    'Estado' => 'PAGADO',
                    'FechaPago' => $fechaPago,
                ]);
            }

            // Marcar la proposición anterior como refinanciada
            $proposicionAnterior->update([
                'FueRefinanciada' => 1
            ]);

            $mensaje = "Pago automático de S/ " . number_format($saldoTotalPendiente, 2) . " para cerrar el crédito anterior.";
            if ($montoRefinanciamiento > $saldoTotalPendiente) {
                $montoAMayor = $montoRefinanciamiento - $saldoTotalPendiente;
                $mensaje .= " + Pago a mayor de S/ " . number_format($montoAMayor, 2);
            }

            Notification::make()
                ->success()
                ->title('✓ Pago Automático')
                ->body($mensaje)
                ->persistent()
                ->send();

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generando pago automático refinanciamiento en GenerarCredito: ' . $e->getMessage(), [
                'exception' => $e,
                'proposicionID' => $record->ProposicionCreditoID
            ]);

            Notification::make()
                ->warning()
                ->title('⚠️ Aviso')
                ->body("El crédito se generó correctamente, pero hubo un error al crear el pago automático: {$e->getMessage()}")
                ->persistent()
                ->send();
        }
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\GenerarCreditoTotalPorGenerarStats::class,
            \App\Filament\Widgets\GenerarCreditoCantidadCreditosStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGenerarCreditos::route('/'),
            'view' => Pages\ViewGenerarCredito::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        if (!(auth()->user()?->can('create_generar::credito') ?? false)) { return false; }

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
}