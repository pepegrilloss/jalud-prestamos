<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResolucionExcedenteResource\Pages;
use App\Models\SolicitudResolucionExcedente;
use App\Models\Excedente;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Sede;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;

use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class ResolucionExcedenteResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = SolicitudResolucionExcedente::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Gestión de Pagos';
    protected static ?string $modelLabel = 'Registro de Extorno/Devolución';
    protected static ?string $pluralModelLabel = 'Registro de Extornos y Devoluciones';
    protected static ?int $navigationSort = 10;

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
                Forms\Components\Section::make('Datos de la Solicitud')
                    ->schema([
                        Forms\Components\Select::make('TipoResolucion')
                            ->label('Tipo de Solicitud')
                            ->prefixIcon('heroicon-m-arrows-right-left')
                            ->options([
                                'TRASLADO_DE_PAGO' => 'Traslado de Pago (Error a Cliente A)',
                                'ASIGNACION_POR_RECLAMO' => 'Regularización de Pago por Reclamo (Identificación de Pago)',
                                'DEVOLUCION_EFECTIVO' => 'Devolución en Efectivo a Cliente',
                                'APLICACION_NUEVO_CREDITO' => 'Aplicar como saldo a Nuevo Crédito'
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('ClienteOrigenID', null);
                                $set('ExcedenteID', null);
                                $set('CreditoOrigenID', null);
                                $set('PagoOrigenID', null);
                                $set('MontoAplicar', null);
                            }),

                        Forms\Components\Select::make('ClienteOrigenID')
                            ->label('Seleccionar Cliente A (Origen)')
                            ->prefixIcon('heroicon-m-user-minus')
                            ->options(Cliente::where('Activo', 1)->pluck('NombresApellidos', 'ClienteID'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('ExcedenteID', null);
                                $set('CreditoOrigenID', null);
                                $set('PagoOrigenID', null);
                                $set('MontoAplicar', null);
                            })
                            ->visible(fn(Get $get) => in_array($get('TipoResolucion'), ['TRASLADO_DE_PAGO', 'DEVOLUCION_EFECTIVO', 'APLICACION_NUEVO_CREDITO'])),

                        // ========== FLUJO TRASLADO DE PAGO ==========
                        // Seleccionar crédito del Cliente A
                        Forms\Components\Select::make('CreditoOrigenID')
                            ->label('Crédito del Cliente Origen')
                            ->prefixIcon('heroicon-m-document-text')
                            ->options(function (Get $get) {
                                $clienteID = $get('ClienteOrigenID');
                                if (!$clienteID)
                                    return [];
                                return Credito::whereHas('proposicion', function ($q) use ($clienteID) {
                                    $q->where('ClienteID', $clienteID)->where('Activo', 1);
                                })->where('Activo', 1)->with('proposicion')->get()->mapWithKeys(function ($cr) {
                                    return [$cr->CreditoID => "{$cr->proposicion->CodigoCredito} - Saldo: S/ " . number_format($cr->proposicion->SaldoPendiente, 2)];
                                });
                            })
                            ->required(fn(Get $get) => $get('TipoResolucion') === 'TRASLADO_DE_PAGO')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('PagoOrigenID', null))
                            ->visible(fn(Get $get) => $get('TipoResolucion') === 'TRASLADO_DE_PAGO' && $get('ClienteOrigenID')),

                        // Seleccionar pago del crédito del Cliente A
                        Forms\Components\Select::make('PagoOrigenID')
                            ->label('Pago a Trasladar')
                            ->prefixIcon('heroicon-m-banknotes')
                            ->options(function (Get $get) {
                                $creditoID = $get('CreditoOrigenID');
                                if (!$creditoID)
                                    return [];

                                $pagos = Pago::where('CreditoID', $creditoID)
                                    ->where('Activo', 1)
                                    ->orderBy('FechaPago', 'asc')
                                    ->orderBy('PagoID', 'asc')
                                    ->get();

                                $opciones = [];
                                $correlativo = 1;

                                foreach ($pagos as $pago) {
                                    // Solo mostrar en las opciones los que no han sido trasladados
                                    if ($pago->EstadoTraslado !== 'TRASLADADO') {
                                        $fecha = \Carbon\Carbon::parse($pago->FechaPago)->format('d/m/Y');
                                        $opciones[$pago->PagoID] = "Pago #{$correlativo} - S/ " . number_format($pago->MontoPagado, 2) . " - {$fecha} - {$pago->TipoPago}";
                                    }
                                    $correlativo++;
                                }

                                // Devolver invertido para ver los más recientes primero
                                return array_reverse($opciones, true);
                            })
                            ->required(fn(Get $get) => $get('TipoResolucion') === 'TRASLADO_DE_PAGO')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $pagoID = $get('PagoOrigenID');
                                if ($pagoID) {
                                    $pago = Pago::find($pagoID);
                                    if ($pago) {
                                        $set('MontoAplicar', $pago->MontoPagado);
                                    }
                                } else {
                                    $set('MontoAplicar', null);
                                }
                            })
                            ->helperText('Seleccione el pago que se trasladará al cliente destino.')
                            ->visible(fn(Get $get) => $get('TipoResolucion') === 'TRASLADO_DE_PAGO' && $get('CreditoOrigenID')),

                        // ========== FLUJO EXCEDENTE (otros tipos) ==========
                        Forms\Components\DatePicker::make('FiltroFechaExcedente')
                            ->label('Fecha del Excedente (Filtro)')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->required(fn(Get $get) => $get('TipoResolucion') !== null && $get('TipoResolucion') !== 'TRASLADO_DE_PAGO')
                            ->visible(fn(Get $get) => $get('TipoResolucion') !== null && $get('TipoResolucion') !== 'TRASLADO_DE_PAGO')
                            ->live()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->dehydrated(false)
                            ->afterStateUpdated(function (Set $set) {
                                $set('ExcedenteID', null);
                                $set('MontoAplicar', null);
                            }),

                        Forms\Components\Select::make('ExcedenteID')
                            ->label('Excedente (El Sobrante/Dinero a mover)')
                            ->prefixIcon('heroicon-m-banknotes')
                            ->options(function (Get $get) {
                                $fechaFiltro = $get('FiltroFechaExcedente');
                                if (!$fechaFiltro)
                                    return []; // Si no hay fecha, retorna vacío
                    
                                $query = Excedente::where('EstadoResolucion', 'PENDIENTE')
                                    ->where('Activo', 1)
                                    ->whereDate('Fecha', $fechaFiltro);

                                $results = $query->get();
                                if ($results->isEmpty())
                                    return [];
                                return $results->mapWithKeys(function ($ex) {
                                    $fecha = \Carbon\Carbon::parse($ex->Fecha)->format('d/m/Y');
                                    $tipoLabel = str_replace('_', ' ', $ex->TipoExcedente);
                                    $op = $ex->NroOperacion ? " - Op: {$ex->NroOperacion}" : "";
                                    return [$ex->ExcedenteID => "S/ {$ex->Monto} - {$tipoLabel}{$op} - {$fecha}"];
                                });
                            })
                            ->required(fn(Get $get) => $get('TipoResolucion') !== 'TRASLADO_DE_PAGO')
                            ->disabled(fn(Get $get) => !$get('FiltroFechaExcedente'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $excedenteID = $get('ExcedenteID');
                                if ($excedenteID) {
                                    $excedente = Excedente::find($excedenteID);
                                    if ($excedente) {
                                        $set('MontoAplicar', $excedente->Monto);
                                    }
                                } else {
                                    $set('MontoAplicar', null);
                                }
                            })
                            ->visible(fn(Get $get) => $get('TipoResolucion') !== null && $get('TipoResolucion') !== 'TRASLADO_DE_PAGO'),

                        // Monto del pago seleccionado (solo informativo para traslado)
                        Forms\Components\TextInput::make('MontoAplicar')
                            ->label(fn(Get $get) => $get('TipoResolucion') === 'TRASLADO_DE_PAGO' ? 'Monto del Pago a Trasladar (S/)' : 'Monto a Aplicar (S/)')
                            ->prefixIcon('heroicon-m-currency-dollar')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('S/')
                            ->readOnly(fn(Get $get) => $get('TipoResolucion') === 'TRASLADO_DE_PAGO')
                            ->helperText(function (Get $get) {
                                if ($get('TipoResolucion') === 'TRASLADO_DE_PAGO') {
                                    return 'El monto se toma automáticamente del pago seleccionado.';
                                }
                                $excedenteID = $get('ExcedenteID');
                                if ($excedenteID) {
                                    $excedente = Excedente::find($excedenteID);
                                    if ($excedente) {
                                        return "Monto disponible del excedente: S/ " . number_format($excedente->Monto, 2);
                                    }
                                }
                                return 'Seleccione un excedente primero.';
                            })
                            ->rules([
                                fn(Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('TipoResolucion') === 'TRASLADO_DE_PAGO')
                                        return; // No validar contra excedente
                                    $excedenteID = $get('ExcedenteID');
                                    if ($excedenteID) {
                                        $excedente = Excedente::find($excedenteID);
                                        if ($excedente && $value > $excedente->Monto) {
                                            $fail("El monto no puede exceder S/ " . number_format($excedente->Monto, 2) . " (disponible del excedente).");
                                        }
                                    }
                                },
                            ])
                            ->live(debounce: 500)
                            ->visible(fn(Get $get) => $get('TipoResolucion') === 'TRASLADO_DE_PAGO' ? $get('PagoOrigenID') !== null : true),

                        // ========== CAMPOS COMUNES ==========
                        Forms\Components\Select::make('ClienteDestinoID')
                            ->label('Cliente Destino')
                            ->prefixIcon('heroicon-m-user-plus')
                            ->options(function (Get $get) {
                                $origenID = $get('ClienteOrigenID');
                                $tipo = $get('TipoResolucion');

                                return Cliente::where('Activo', 1)
                                    ->when($tipo === 'TRASLADO_DE_PAGO' && $origenID, function ($query) use ($origenID) {
                                        return $query->where('ClienteID', '!=', $origenID);
                                    })
                                    ->pluck('NombresApellidos', 'ClienteID');
                            })
                            ->required(fn(Get $get) => in_array($get('TipoResolucion'), ['TRASLADO_DE_PAGO', 'ASIGNACION_POR_RECLAMO', 'DEVOLUCION_EFECTIVO', 'APLICACION_NUEVO_CREDITO']))
                            ->searchable()
                            ->live()
                            ->rules([
                                fn(Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('TipoResolucion') === 'TRASLADO_DE_PAGO' && $value == $get('ClienteOrigenID')) {
                                        $fail('El cliente destino no puede ser el mismo que el origen en un traslado de pago.');
                                    }
                                },
                            ])
                            ->visible(fn(Get $get) => $get('TipoResolucion') !== null),

                        Forms\Components\Select::make('CreditoDestinoID')
                            ->label('Crédito Destino')
                            ->prefixIcon('heroicon-m-document-text')
                            ->options(function (Get $get) {
                                if (!$get('ClienteDestinoID'))
                                    return [];
                                return Credito::whereHas('proposicion', function ($q) use ($get) {
                                    $q->where('ClienteID', $get('ClienteDestinoID'));
                                })->where('Activo', 1)->with('proposicion.tipoCredito')->get()->mapWithKeys(function ($cr) {
                                    return [$cr->CreditoID => "{$cr->proposicion->CodigoCredito} - Vigente"];
                                });
                            })
                            ->required(fn(Get $get) => in_array($get('TipoResolucion'), ['TRASLADO_DE_PAGO', 'ASIGNACION_POR_RECLAMO', 'APLICACION_NUEVO_CREDITO']))
                            ->searchable()
                            ->visible(fn(Get $get) => in_array($get('TipoResolucion'), ['TRASLADO_DE_PAGO', 'ASIGNACION_POR_RECLAMO', 'APLICACION_NUEVO_CREDITO'])),

                        Forms\Components\Textarea::make('DatosValeCaja')
                            ->label('Datos de Vale de Egreso (Nro, Detalles)')
                            ->required(fn(Get $get) => $get('TipoResolucion') === 'DEVOLUCION_EFECTIVO')
                            ->visible(fn(Get $get) => $get('TipoResolucion') === 'DEVOLUCION_EFECTIVO')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('Observaciones')
                            ->label('Observaciones Adicionales')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Resumen de la Solicitud')
                    ->description('Estado actual y tipo de operación financiera solicitada.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('TipoResolucion')
                                    ->label('Tipo de Operación')
                                    ->badge()
                                    ->icon('heroicon-m-arrows-right-left')
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'TRASLADO_DE_PAGO' => 'Traslado de Pago',
                                        'ASIGNACION_POR_RECLAMO' => 'Regularización Reclamo',
                                        'DEVOLUCION_EFECTIVO' => 'Devolución Efectivo',
                                        'APLICACION_NUEVO_CREDITO' => 'Saldo Nuevo Crédito',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('MontoAplicar')
                                    ->label('Monto Aplicado')
                                    ->money('PEN')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->icon('heroicon-m-currency-dollar')
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('Estado')
                                    ->label('Estado de Gestión')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'PENDIENTE' => 'warning',
                                        'APROBADA' => 'success',
                                        'RECHAZADA' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Infolists\Components\Section::make('Participantes del Movimiento')
                    ->description('Detalle del flujo de dinero entre clientes.')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('clienteOrigen.NombresApellidos')
                                    ->label('Cliente Origen (A)')
                                    ->placeholder('Sin cliente origen (Dinero Flotante)')
                                    ->icon('heroicon-m-user-minus')
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('clienteDestino.NombresApellidos')
                                    ->label('Cliente Destino (B)')
                                    ->icon('heroicon-m-user-plus')
                                    ->color('info')
                                    ->weight('bold'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Detalle Técnico y Ejecución')
                    ->description('Información sobre el crédito destino, excedente y documentos de caja.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('excedente.NroOperacion')
                                    ->label('Voucher / Operación')
                                    ->icon('heroicon-m-hashtag')
                                    ->placeholder('N/A')
                                    ->visible(fn($record) => $record->TipoResolucion !== 'TRASLADO_DE_PAGO'),
                                Infolists\Components\TextEntry::make('pagoOrigen.MontoPagado')
                                    ->label('Pago Original')
                                    ->icon('heroicon-m-banknotes')
                                    ->money('PEN')
                                    ->helperText(fn($record) => $record->pagoOrigen ? "{$record->pagoOrigen->TipoPago} - " . \Carbon\Carbon::parse($record->pagoOrigen->FechaPago)->format('d/m/Y') : '')
                                    ->visible(fn($record) => $record->TipoResolucion === 'TRASLADO_DE_PAGO' && $record->pagoOrigen),
                                Infolists\Components\TextEntry::make('creditoDestino.proposicion.CodigoCredito')
                                    ->label('Crédito Aplicado')
                                    ->badge()
                                    ->icon('heroicon-m-credit-card')
                                    ->placeholder('Sin crédito (Devolución)'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Fecha Solicitud')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon('heroicon-m-calendar-days'),
                            ]),

                        Infolists\Components\TextEntry::make('DatosValeCaja')
                            ->label('Vale de Caja / Egreso')
                            ->placeholder('No aplica para esta operación')
                            ->icon('heroicon-m-document-check')
                            ->visible(fn($record) => $record->TipoResolucion === 'DEVOLUCION_EFECTIVO'),

                        Infolists\Components\TextEntry::make('Observaciones')
                            ->label('Observaciones de la Solicitud')
                            ->columnSpanFull()
                            ->icon('heroicon-m-chat-bubble-left-right')
                            ->placeholder('Sin comentarios adicionales'),
                    ]),

                Infolists\Components\Section::make('Auditoría')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('solicitante.name')
                                    ->label('Registrado por')
                                    ->icon('heroicon-m-user-circle'),
                                Infolists\Components\TextEntry::make('aprobador.name')
                                    ->label('Aprobado/Rechazado por')
                                    ->placeholder('Pendiente de procesar')
                                    ->icon('heroicon-m-check-badge')
                                    ->color(fn($record) => $record->Estado === 'APROBADA' ? 'success' : ($record->Estado === 'RECHAZADA' ? 'danger' : 'gray')),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('SolicitudID')->sortable(),
                Tables\Columns\TextColumn::make('TipoResolucion')
                    ->label('Tipo de Solicitud')
                    ->badge(),
                Tables\Columns\TextColumn::make('MontoAplicar')
                    ->label('Monto Aplicado')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('clienteOrigen.NombresApellidos')
                    ->label('Origen')
                    ->searchable()
                    ->default('Excedente'),
                Tables\Columns\TextColumn::make('clienteDestino.NombresApellidos')
                    ->label('Destino')
                    ->searchable(),
                Tables\Columns\TextColumn::make('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PENDIENTE' => 'warning',
                        'APROBADA' => 'success',
                        'RECHAZADA' => 'danger',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('solicitante.name')
                    ->label('Solicitante'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('Estado')
                    ->options([
                        'PENDIENTE' => 'Pendientes',
                        'APROBADA' => 'Aprobadas',
                        'RECHAZADA' => 'Rechazadas',
                    ]),

            Tables\Filters\SelectFilter::make('SedeID')
                ->label('Sede')
                ->options(fn() => \App\Models\Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                ->visible(fn() => auth()->user()->isPrivileged() && !session('sede_activa')),
            
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->Estado === 'PENDIENTE'),
            ])
            ->bulkActions([
            ]);
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) {
            return false;
        }

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
            'index' => Pages\ListResolucionExcedentes::route('/'),
            'create' => Pages\CreateResolucionExcedente::route('/create'),
            'edit' => Pages\EditResolucionExcedente::route('/{record}/edit'),
            'view' => Pages\ViewResolucionExcedente::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('view_any_resolucion::excedente');
    }

}
