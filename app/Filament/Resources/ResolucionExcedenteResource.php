<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResolucionExcedenteResource\Pages;
use App\Models\SolicitudResolucionExcedente;
use App\Models\Excedente;
use App\Models\Cliente;
use App\Models\Credito;
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

class ResolucionExcedenteResource extends Resource
{
    protected static ?string $model = SolicitudResolucionExcedente::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Gestión de Pagos';
    protected static ?string $modelLabel = 'Extorno o Devolución';
    protected static ?string $pluralModelLabel = 'Extornos y Devoluciones';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la Solicitud')
                    ->schema([
                        Forms\Components\Select::make('TipoResolucion')
                            ->label('Tipo de Solicitud')
                            ->options([
                                'TRASLADO_DE_PAGO' => 'Traslado de Pago (Error a Cliente A)',
                                'ASIGNACION_POR_RECLAMO' => 'Regularización de Pago por Reclamo (Identificación de Pago)',
                                'DEVOLUCION_EFECTIVO' => 'Devolución en Efectivo a Cliente',
                                'APLICACION_NUEVO_CREDITO' => 'Aplicar como saldo a Nuevo Crédito'
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('ClienteOrigenID', null);
                                $set('ExcedenteID', null);
                            }),

                        Forms\Components\Select::make('ClienteOrigenID')
                            ->label('Seleccionar Cliente A (Origen)')
                            ->options(Cliente::where('Activo', 1)->pluck('NombresApellidos', 'ClienteID'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('ExcedenteID', null))
                            ->visible(fn(Get $get) => in_array($get('TipoResolucion'), ['TRASLADO_DE_PAGO', 'DEVOLUCION_EFECTIVO', 'APLICACION_NUEVO_CREDITO'])),

                        // Para el TRASLADO DE PAGO, buscar Excedentes enlazados a Cliente A
                        Forms\Components\Select::make('ExcedenteID')
                            ->label('Excedente (El Sobrante/Dinero a mover)')
                            ->options(function (Get $get) {
                                $tipo = $get('TipoResolucion');
                                $query = Excedente::where('EstadoResolucion', 'PENDIENTE')->where('Activo', 1);

                                // Mostrar todos los excedentes pendientes
                                $results = $query->get();
                                if ($results->isEmpty()) return [];
                                return $results->mapWithKeys(function ($ex) {
                                    $fecha = \Carbon\Carbon::parse($ex->Fecha)->format('d/m/Y');
                                    $tipoLabel = str_replace('_', ' ', $ex->TipoExcedente);
                                    $op = $ex->NroOperacion ? " - Op: {$ex->NroOperacion}" : "";
                                    return [$ex->ExcedenteID => "S/ {$ex->Monto} - {$tipoLabel}{$op} - {$fecha}"];
                                });
                                return [];
                            })
                            ->required()
                            ->searchable()
                            ->live(),

                        Forms\Components\Select::make('ClienteDestinoID')
                            ->label('Cliente Destino')
                            ->options(Cliente::where('Activo', 1)->pluck('NombresApellidos', 'ClienteID'))
                            ->required(fn(Get $get) => in_array($get('TipoResolucion'), ['TRASLADO_DE_PAGO', 'ASIGNACION_POR_RECLAMO', 'DEVOLUCION_EFECTIVO', 'APLICACION_NUEVO_CREDITO']))
                            ->searchable()
                            ->live()
                            ->visible(fn(Get $get) => $get('TipoResolucion') !== null),

                        Forms\Components\Select::make('CreditoDestinoID')
                            ->label('Crédito Destino')
                            ->options(function (Get $get) {
                                if (!$get('ClienteDestinoID'))
                                    return [];
                                return Credito::whereHas('proposicion', function ($q) use ($get) {
                                    $q->where('ClienteID', $get('ClienteDestinoID'))->where('Activo', 1);
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
                                Infolists\Components\TextEntry::make('excedente.Monto')
                                    ->label('Monto involucrado')
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
                                    ->placeholder('N/A'),
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
                Tables\Columns\TextColumn::make('excedente.Monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('clienteOrigen.NombresApellidos')
                    ->label('Origen')
                    ->searchable()
                    ->default('Sin identificar'),
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
                    ])
                    ->default('PENDIENTE'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->Estado === 'PENDIENTE'),

                Tables\Actions\Action::make('Aprobar')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Extorno/Resolución')
                    ->modalDescription('¿Está seguro de aprobar esta solicitud? Se reflejarán los cambios financieros correspondientes de forma automática y el excedente se marcará como resuelto.')
                    ->visible(fn($record) => $record->Estado === 'PENDIENTE' && (auth()->user()->hasRole('Administrador') || auth()->user()->hasRole('Super Admin') || auth()->user()->esAdmin()))
                    ->action(function ($record) {
                        app(\App\Services\ResolucionExcedenteService::class)->aprobar($record, auth()->user());

                        Notification::make()
                            ->title('Solicitud Aprobada y Ejecutada')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('Rechazar')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->Estado === 'PENDIENTE' && (auth()->user()->hasRole('Administrador') || auth()->user()->hasRole('Super Admin') || auth()->user()->esAdmin()))
                    ->action(function ($record) {
                        $record->update(['Estado' => 'RECHAZADA', 'UserAprobadorID' => auth()->id()]);
                        Notification::make()
                            ->title('Solicitud Rechazada')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
            ]);
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
}
