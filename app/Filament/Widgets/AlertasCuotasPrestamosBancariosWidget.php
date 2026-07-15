<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PrestamoBancarioResource;
use App\Models\CuotaPrestamoBancario;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AlertasCuotasPrestamosBancariosWidget extends BaseWidget
{
    protected static ?string $heading = 'Cuotas bancarias por atender';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return CuotaPrestamoBancario::query()
            ->where('Estado', CuotaPrestamoBancario::ESTADO_PENDIENTE)
            ->whereDate('FechaVencimiento', '<=', now()->startOfDay()->addDays(7))
            ->exists();
    }

    public function table(Table $table): Table
    {
        $hoy = now()->startOfDay();

        return $table
            ->query(
                CuotaPrestamoBancario::query()
                    ->with('prestamo')
                    ->where('Estado', CuotaPrestamoBancario::ESTADO_PENDIENTE)
                    ->whereDate('FechaVencimiento', '<=', $hoy->copy()->addDays(7))
            )
            ->defaultSort('FechaVencimiento')
            ->columns([
                Tables\Columns\TextColumn::make('prestamo.Banco')->label('Banco')->searchable(),
                Tables\Columns\TextColumn::make('prestamo.Cliente')->label('Cliente')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('prestamo.CuentaPrestamo')->label('Cuenta')->searchable(),
                Tables\Columns\TextColumn::make('Numero')->label('Cuota')->alignCenter(),
                Tables\Columns\TextColumn::make('FechaVencimiento')->label('Vencimiento')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('MontoCuota')->label('Importe')->money('PEN')->weight('bold'),
                Tables\Columns\TextColumn::make('alerta')
                    ->label('Estado')
                    ->state(function (CuotaPrestamoBancario $record) use ($hoy): string {
                        $dias = $hoy->diffInDays($record->FechaVencimiento, false);

                        return match (true) {
                            $dias < 0 => 'Vencida hace ' . abs($dias) . ' día(s)',
                            $dias === 0 => 'Vence hoy',
                            $dias === 1 => 'Vence mañana',
                            default => "Vence en {$dias} días",
                        };
                    })
                    ->badge()
                    ->color(fn (CuotaPrestamoBancario $record): string => $record->FechaVencimiento->lt(now()->startOfDay()) ? 'danger' : ($record->FechaVencimiento->lte(now()->startOfDay()->addDays(2)) ? 'warning' : 'info')),
            ])
            ->recordUrl(fn (CuotaPrestamoBancario $record): string => PrestamoBancarioResource::getUrl('view', [
                'record' => $record->PrestamoBancarioID,
            ]))
            ->paginated([5, 10]);
    }
}
