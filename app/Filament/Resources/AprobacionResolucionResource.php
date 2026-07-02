<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AprobacionResolucionResource\Pages;
use App\Models\AprobacionResolucion;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class AprobacionResolucionResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = AprobacionResolucion::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationGroup = 'Gestión de Pagos';
    protected static ?string $modelLabel = 'Aprobación Extorno/Devolución';
    protected static ?string $pluralModelLabel = 'Aprobación Extorno/Devolución';
    protected static ?int $navigationSort = 11;
    protected static ?string $slug = 'aprobacion-extornos-devoluciones';

    public static function getNavigationBadge(): ?string
    {
        $count = AprobacionResolucion::where('Estado', 'PENDIENTE')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'update',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('Estado', 'PENDIENTE');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        // Reutilizamos el infolist del recurso original para no repetir código
        return \App\Filament\Resources\ResolucionExcedenteResource::infolist($infolist);
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

            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),

                Tables\Actions\Action::make('Aprobar')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Extorno/Resolución')
                    ->modalDescription('¿Está seguro de aprobar esta solicitud? Se reflejarán los cambios financieros correspondientes de forma automática y el excedente se marcará como resuelto.')
                    ->visible(fn($record) => $record->Estado === 'PENDIENTE' && $record->FechaCierre === null && \App\Models\AperturaCierreDia::estaAbierto() && auth()->user()?->can('aprobar_extornos'))
                    ->action(function ($record) {
                        app(\App\Services\ResolucionExcedenteService::class)->aprobar($record, auth()->user());

                        Notification::make()
                            ->title('Solicitud Aprobada y Ejecutada')
                            ->success()
                            ->send();

                        try {
                            \App\Models\User::notificarAdmin(
                                'Extorno / Devolución aprobada',
                                'Solicitud #' . $record->SolicitudID . ' — S/ ' . number_format((float) $record->MontoAplicar, 2),
                                'heroicon-o-check-circle',
                                $record->SedeID
                            );
                        } catch (\Exception $e) {
                        }
                    }),

                Tables\Actions\Action::make('Rechazar')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Extorno/Resolución')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('motivo_rechazo')
                            ->label('Motivo del Rechazo')
                            ->required()
                            ->placeholder('Indique la razón por la que rechaza esta solicitud...'),
                    ])
                    ->visible(fn($record) => $record->Estado === 'PENDIENTE' && $record->FechaCierre === null && \App\Models\AperturaCierreDia::estaAbierto() && auth()->user()?->can('aprobar_extornos'))
                    ->action(function ($record, array $data) {
                        $record->update([
                            'Estado' => 'RECHAZADA',
                            'UserAprobadorID' => auth()->id(),
                            'Observaciones' => ($record->Observaciones ? $record->Observaciones . "\n" : '') . "[RECHAZADO] " . ($data['motivo_rechazo'] ?? 'Sin motivo especificado'),
                        ]);
                        Notification::make()
                            ->title('Solicitud Rechazada')
                            ->success()
                            ->send();

                        try {
                            \App\Models\User::notificarAdmin(
                                'Extorno / Devolución rechazada',
                                'Solicitud #' . $record->SolicitudID . ' — S/ ' . number_format((float) $record->MontoAplicar, 2),
                                'heroicon-o-x-circle',
                                $record->SedeID
                            );
                        } catch (\Exception $e) {
                        }
                    }),
            ])
            ->bulkActions([
            ]);
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) { return false; }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
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
            'index' => Pages\ListAprobacionResoluciones::route('/'),
            'view' => Pages\ViewAprobacionResolucion::route('/{record}'),
        ];
    }
}
