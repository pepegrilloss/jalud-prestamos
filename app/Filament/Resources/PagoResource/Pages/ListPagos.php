<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use App\Models\AperturaCierreDia;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Illuminate\Database\Eloquent\Builder;

class ListPagos extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PagoResource::class;

    public function getTitle(): string
    {
        $title = 'Pagos';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        $user = auth()->user();
        $sedeId = $user?->getEffectiveSedeId();
        $promotorCobrador = $user?->promotorCobrador;
        $zonaId = $promotorCobrador?->ZonaID ?? null;
        $promotorId = $user?->PromotorCobradorID ?? null;

        $bloqueado = $user?->hasRole('promotor_cobrador')
            && \App\Models\PagoBloqueoPromotor::estaBloqueado($sedeId, $zonaId, $promotorId);

        if (AperturaCierreDia::estaAbierto() && !$bloqueado) {
            $actions[] = Actions\CreateAction::make()
                ->label('Registrar Pago')
                ->visible(function () use ($sedeId, $zonaId, $promotorId) {
                    if (auth()->user()?->hasRole('promotor_cobrador')) {
                        $aunBloqueado = \App\Models\PagoBloqueoPromotor::estaBloqueado($sedeId, $zonaId, $promotorId);
                        return !$aunBloqueado;
                    }
                    return true;
                });
        }

        // Botón Bloquear Pago Promotor
        $actions[] = Actions\Action::make('bloquearPagoPromotor')
            ->label('Bloquear Pago Promotor')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->visible(fn() => $user?->can('bloquear_pago_promotor'))
            ->form([
                \Filament\Forms\Components\Select::make('tipo_bloqueo')
                    ->label('Bloquear por')
                    ->options([
                        'zona' => 'Zona (de la proposición)',
                        'promotor' => 'Promotor Cobrador',
                    ])
                    ->required()
                    ->live()
                    ->native(false),

                \Filament\Forms\Components\Select::make('ZonaID')
                    ->label('Zona')
                    ->options(fn() => \App\Models\Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                    ->searchable()
                    ->native(false)
                    ->visible(fn(\Filament\Forms\Get $get) => $get('tipo_bloqueo') === 'zona')
                    ->required(fn(\Filament\Forms\Get $get) => $get('tipo_bloqueo') === 'zona'),

                \Filament\Forms\Components\Select::make('PromotorCobradorID')
                    ->label('Promotor Cobrador')
                    ->options(fn() => \App\Models\PromotorCobrador::where('Activo', true)->pluck('Descripcion', 'PromotorCobradorID'))
                    ->searchable()
                    ->native(false)
                    ->visible(fn(\Filament\Forms\Get $get) => $get('tipo_bloqueo') === 'promotor')
                    ->required(fn(\Filament\Forms\Get $get) => $get('tipo_bloqueo') === 'promotor'),
            ])
            ->action(function (array $data) use ($sedeId, $user) {
                $zonaID = $data['tipo_bloqueo'] === 'zona' ? ($data['ZonaID'] ?? null) : null;
                $promotorID = $data['tipo_bloqueo'] === 'promotor' ? ($data['PromotorCobradorID'] ?? null) : null;

                // Validar duplicado activo
                $yaExiste = \App\Models\PagoBloqueoPromotor::where('SedeID', $sedeId)
                    ->where('Activo', true)
                    ->where(function ($q) use ($zonaID, $promotorID) {
                        if ($zonaID) {
                            $q->where('ZonaID', $zonaID);
                        }
                        if ($promotorID) {
                            $q->where('PromotorCobradorID', $promotorID);
                        }
                    })
                    ->exists();

                if ($yaExiste) {
                    \Filament\Notifications\Notification::make()
                        ->title('Bloqueo duplicado')
                        ->body('Ya existe un bloqueo activo para la zona o promotor seleccionado.')
                        ->warning()
                        ->send();
                    return;
                }

                $record = new \App\Models\PagoBloqueoPromotor();
                $record->SedeID = $sedeId;
                $record->ZonaID = $zonaID;
                $record->PromotorCobradorID = $promotorID;
                $record->UsuarioBloqueoID = $user?->id;
                $record->Activo = true;
                $record->save();

                \Filament\Notifications\Notification::make()
                    ->title('Bloqueo registrado')
                    ->body('Se ha bloqueado el registro de pagos para la zona o promotor seleccionado.')
                    ->success()
                    ->send();
            });

        // Botón Desbloquear Pago Promotor (siempre visible para quien tenga permiso)
        $actions[] = Actions\Action::make('desbloquearPagoPromotor')
            ->label('Desbloquear Pago Promotor')
            ->icon('heroicon-o-lock-open')
            ->color('success')
            ->visible(fn() => $user?->can('bloquear_pago_promotor'))
            ->form([
                \Filament\Forms\Components\Select::make('bloqueo_id')
                    ->label('Bloqueo activo a desbloquear')
                    ->options(fn() => \App\Models\PagoBloqueoPromotor::bloqueosActivos($sedeId))
                    ->required()
                    ->native(false)
                    ->helperText(function () use ($sedeId) {
                        $bloqueos = \App\Models\PagoBloqueoPromotor::bloqueosActivos($sedeId);
                        if (empty($bloqueos)) {
                            return 'No hay bloqueos activos actualmente.';
                        }
                        return count($bloqueos) . ' bloqueo(s) activo(s). Seleccione uno para desbloquear.';
                    }),
            ])
            ->action(function (array $data) use ($user) {
                $bloqueo = \App\Models\PagoBloqueoPromotor::find($data['bloqueo_id']);
                if ($bloqueo) {
                    $bloqueo->update([
                        'Activo' => false,
                        'UsuarioDesbloqueoID' => $user?->id,
                    ]);
                    \Filament\Notifications\Notification::make()
                        ->title('Desbloqueo realizado')
                        ->body('El bloqueo ha sido desactivado exitosamente.')
                        ->success()
                        ->send();
                }
            });

        return $actions;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\PagosCantidadStatsWidget::class,
            \App\Filament\Widgets\PagosMontoStatsWidget::class,
            \App\Filament\Widgets\PagosMontoMesStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 3,
            'lg' => 3,
        ];
    }
    
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            $promotorCobrador = auth()->user()->promotorCobrador;
            
            if ($promotorCobrador && $promotorCobrador->ZonaID) {
                return $query->whereHas('cuota.credito.proposicion', function (Builder $q) use ($promotorCobrador) {
                    $q->where('ZonaID', $promotorCobrador->ZonaID);
                });
            }

            return $query->whereRaw('1 = 0');
        }

        // Si NO tiene el permiso "ver_todos_los_pagos", filtrar SOLO pagos del día abierto
        if (!auth()->user()?->can('ver_todos_los_pagos')) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            if ($fechaAbierta) {
                $query->whereDate('pago.FechaPago', $fechaAbierta->toDateString());
            }
        }

        return $query;
    }
    
}