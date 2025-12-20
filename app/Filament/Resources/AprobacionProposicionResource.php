<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AprobacionProposicionResource\Pages;
use App\Models\ProposicionCredito;
use App\Models\AprobacionProposicion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AprobacionProposicionResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;

    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 6;
    protected static ?string $label = 'Aprobación de Proposición';
    protected static ?string $pluralLabel = 'Aprobaciones de Proposiciones';

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
                            ->label('Monto Total')
                            ->disabled()
                            ->prefix('S/.'),

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
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

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
                Tables\Filters\SelectFilter::make('Estado')
                    ->options([
                        'PENDIENTE' => 'Pendiente',
                        'APROBADO' => 'Aprobado',
                        'RECHAZADO' => 'Rechazado',
                    ]),
            ])
            ->modifyQueryUsing(function ($query) {
                // Solo mostrar proposiciones PENDIENTES (que necesitan aprobación)
                return $query->where('Estado', 'PENDIENTE');
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
     * Verifica si el usuario puede aprobar la proposición (logística secuencial)
     */
    private static function puedeAprobarProposicion(ProposicionCredito $proposicion): bool
    {
        $nivelUsuario = auth()->user()->getNivelAprobacionActivo()?->NivelAprobacionID;
        if (!$nivelUsuario) {
            return false;
        }

        // Obtener la aprobación de este nivel
        $aprobacionNivel = $proposicion->aprobaciones()
            ->where('NivelAprobacionID', $nivelUsuario)
            ->first();

        if (!$aprobacionNivel) {
            return false;
        }

        // Verificar que es la siguiente en la secuencia
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
