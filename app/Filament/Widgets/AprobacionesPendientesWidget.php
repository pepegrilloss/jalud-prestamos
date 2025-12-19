<?php

namespace App\Filament\Widgets;

use App\Models\AprobacionProposicion;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AprobacionesPendientesWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Aprobaciones Pendientes';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AprobacionProposicion::query()
                    ->where('Estado', 'PENDIENTE')
                    ->where('NivelAprobacionID', auth()->user()->getNivelAprobacionActivo()?->NivelAprobacionID)
                    ->with(['proposicion', 'nivel'])
                    ->latest('FechaCreacion')
            )
            ->columns([
                Tables\Columns\TextColumn::make('proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nivel.Nombre')
                    ->label('Nivel')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaCreacion')
                    ->label('Fecha Propuesta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('comentario')
                            ->label('Comentario')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function ($record, array $data) {
                        $record->aprobar(auth()->user(), $data['comentario'] ?? null);
                        $record->proposicion->actualizarEstadoSegunAprobaciones();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Aprobación registrada')
                            ->body('La proposición ha sido aprobada en este nivel')
                            ->send();
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('comentario')
                            ->label('Motivo del rechazo')
                            ->rows(2)
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->rechazar(auth()->user(), $data['comentario']);
                        $record->proposicion->actualizarEstadoSegunAprobaciones();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Rechazo registrado')
                            ->body('La proposición ha sido rechazada')
                            ->send();
                    }),
            ])
            ->defaultSort('FechaCreacion', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function isVisible(): bool
    {
        return auth()->user()->getNivelAprobacionActivo() !== null;
    }
}
