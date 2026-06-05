<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AprobacionProposicionResource\Pages;
use App\Models\ProposicionCredito;
use App\Models\AprobacionProposicion;
use App\Models\AperturaCierreDia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Sede;
class AprobacionProposicionResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;

    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 6;
    protected static ?string $label = 'Aprobación de Proposición';
    protected static ?string $pluralLabel = 'Aprobaciones de Proposiciones';

    public static function getNavigationBadge(): ?string
    {
        $count = ProposicionCredito::where('Estado', 'PENDIENTE')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Override para usar los permisos de 'aprobacion::proposicion' en lugar de
     * los de ProposicionCreditoPolicy (que apunta a otro recurso).
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_aprobacion::proposicion') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_aprobacion::proposicion') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_aprobacion::proposicion') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_aprobacion::proposicion') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_aprobacion::proposicion') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Proposición')
                    ->schema([
                        Forms\Components\TextInput::make('CodigoCredito')
                            ->label('Código de Crédito')
                            ->disabled(),

                        Forms\Components\TextInput::make('cliente.NombresApellidos')
                            ->label('Cliente')
                            ->disabled(),

                        Forms\Components\TextInput::make('MontoTotal')
                            ->label('Monto solicitado')
                            ->disabled()
                            ->numeric(),

                        Forms\Components\TextInput::make('MMR')
                            ->label('MMR (Monto Máximo Recomendado)')
                            ->state(fn ($record) => $record->cliente?->analisisEconomico?->MontoMaxRecomendado ?? 0)
                            ->disabled()
                            ->numeric()
                            ->helperText('Dato obtenido del último Análisis Económico del cliente.'),

                        Forms\Components\TextInput::make('Estado')
                            ->label('Estado General')
                            ->disabled()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'PENDIENTE' => '⏳ Pendiente',
                                'APROBADO' => '✅ Aprobado',
                                'RECHAZADO' => '❌ Rechazado',
                                default => $state
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detalle de Aprobaciones')
                    ->schema([
                        Forms\Components\ViewField::make('aprobaciones')
                            ->view('filament.components.approval-status')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            ->persistFiltersInSession()
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoTotal')
                    ->label('Monto solicitado')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MMR')
                    ->label('MMR')
                    // OPTIMIZACIÓN N+1: leer del eager load (cliente.analisisEconomico)
                    // añadido en modifyQueryUsing, sin consultas adicionales por fila.
                    ->state(fn ($record) => (float) ($record->cliente?->analisisEconomico?->MontoMaxRecomendado ?? 0))
                    ->money('PEN')
                    ->sortable(
                        query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) => $query
                            ->leftJoin('cliente', 'ProposicionCredito.ClienteID', '=', 'cliente.ClienteID')
                            ->leftJoin('analisiseconomico', 'cliente.ClienteID', '=', 'analisiseconomico.ClienteID')
                            ->where('analisiseconomico.Activo', 1)
                            ->orderBy('analisiseconomico.MontoMaxRecomendado', $direction)
                            ->select('ProposicionCredito.*')
                    )
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('TasaInteres')
                    ->label('Tasa (%)')
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', '') . ' %')
                    ->sortable()
                    ->alignment('center'),


                Tables\Columns\ViewColumn::make('aprobaciones')
                    ->label('Estado de Aprobaciones')
                    ->view('filament.columns.approval-status-column')
                    ->sortable(false),

                Tables\Columns\BadgeColumn::make('Estado')
                    ->label('Estado General')
                    ->colors([
                        'warning' => 'PENDIENTE',
                        'success' => 'APROBADO',
                        'danger' => 'RECHAZADO',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'PENDIENTE',
                        'heroicon-o-check-circle' => 'APROBADO',
                        'heroicon-o-x-circle' => 'RECHAZADO',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaPropuesta')
                    ->label('Fecha Proposición')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('Estado')
                    ->options([
                        'PENDIENTE' => 'Pendiente',
                        'APROBADO' => 'Aprobado',
                        'RECHAZADO' => 'Rechazado',
                    ]),
            ])
            ->modifyQueryUsing(function ($query) {
                // Mostrar proposiciones que aún no tienen crédito generado
                return $query->whereDoesntHave('credito')
                    // OPTIMIZACIÓN N+1: eager load cliente (para columnas DNI/Nombres) y
                    // cliente.analisisEconomico (para columna MMR).
                    ->with(['cliente' => fn ($q) => $q->with('analisisEconomico')]);
            })
            ->actions([
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn($record) =>
                        $record->Estado === 'PENDIENTE'
                        && auth()->user()->getNivelAprobacionActivo()?->NivelAprobacionID !== null
                        && self::puedeAprobarProposicion($record)
                        && AperturaCierreDia::estaAbierto()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Proposición')
                    ->form([
                        Forms\Components\Textarea::make('comentario')
                            ->label('Comentario')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function ($record, array $data) {
                        if (auth()->user()->aprobarProposicion($record, $data['comentario'] ?? null)) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Aprobación Registrada')
                                ->body('La proposición ha sido aprobada en este nivel')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('No se pudo registrar la aprobación')
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn($record) =>
                        $record->Estado === 'PENDIENTE'
                        && auth()->user()->getNivelAprobacionActivo()?->NivelAprobacionID !== null
                        && self::puedeAprobarProposicion($record)
                        && AperturaCierreDia::estaAbierto()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Proposición')
                    ->form([
                        Forms\Components\Textarea::make('comentario')
                            ->label('Motivo del Rechazo')
                            ->rows(3)
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        if (auth()->user()->rechazarProposicion($record, $data['comentario'])) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Rechazo Registrado')
                                ->body('La proposición ha sido rechazada')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('No se pudo registrar el rechazo')
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('FechaPropuesta', 'desc')
            ->paginationPageOptions([10, 25, 50]);
    }

    /**
     * Verifica si el usuario puede aprobar la proposición
     */
    private static function puedeAprobarProposicion(ProposicionCredito $proposicion): bool
    {
        $nivelActivo = auth()->user()->getNivelAprobacionActivo();
        if (!$nivelActivo || !$nivelActivo->NivelAprobacionID) {
            return false;
        }

        $nivelUsuario = $nivelActivo->NivelAprobacionID;

        $aprobacionNivel = $proposicion->aprobaciones()
            ->where('NivelAprobacionID', $nivelUsuario)
            ->first();

        if (!$aprobacionNivel) {
            return false;
        }

        return $proposicion->puedeAprobarEstaNivel($aprobacionNivel);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAprobacionProposicions::route('/'),
            'view' => Pages\ViewAprobacionProposicion::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
