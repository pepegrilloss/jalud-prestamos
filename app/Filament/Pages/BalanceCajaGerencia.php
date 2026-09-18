<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MovimientoTesoreriaResource;
use App\Models\FondoSede;
use App\Models\MovimientoTesoreria;
use App\Services\TesoreriaGerenciaService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class BalanceCajaGerencia extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationLabel = 'Balance Caja Gerencia';

    protected static ?string $title = 'Balance Caja Gerencia';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.balance-caja-gerencia';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        if (filament()->getCurrentPanel()?->getId() !== 'gerencia') {
            return false;
        }

        return auth()->user()?->puedeAccederAGerencia() ?? false;
    }

    public function getSaldoActual(): float
    {
        $fondo = FondoSede::withoutGlobalScope('sede')
            ->whereHas('sede', fn ($query) => $query->where('Nombre', 'like', '%Gerencia%'))
            ->first();

        return round((float) ($fondo?->Saldo ?? 0), 2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MovimientoTesoreria::query()
                    ->with('usuario')
                    ->where(function (Builder $query): void {
                        $query->where('OrigenTipo', MovimientoTesoreria::CAJA_GERENCIA)
                            ->orWhere('DestinoTipo', MovimientoTesoreria::CAJA_GERENCIA);
                    })
            )
            ->defaultSort('FechaMovimiento', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('FechaContable')
                    ->label('Fecha contable')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaMovimiento')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Movimiento')
                    ->badge()
                    ->getStateUsing(fn (MovimientoTesoreria $record): string => $record->OrigenTipo === MovimientoTesoreria::CAJA_GERENCIA
                        ? 'Salida'
                        : 'Entrada')
                    ->color(fn (string $state) => $state === 'Salida' ? 'danger' : 'success'),
                Tables\Columns\BadgeColumn::make('Tipo')->label('Tipo'),
                Tables\Columns\TextColumn::make('contraparte')
                    ->label('Contraparte')
                    ->getStateUsing(fn (MovimientoTesoreria $record): string => $record->OrigenTipo === MovimientoTesoreria::CAJA_GERENCIA
                        ? $record->CuentaDestinoNombre
                        : $record->CuentaOrigenNombre)
                    ->wrap(),
                Tables\Columns\TextColumn::make('Monto')
                    ->money('PEN')
                    ->weight('bold')
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total filtrado')
                            ->money('PEN')
                    ),
                Tables\Columns\TextColumn::make('saldo_resultante')
                    ->label('Saldo')
                    ->getStateUsing(fn (MovimientoTesoreria $record) => $record->OrigenTipo === MovimientoTesoreria::CAJA_GERENCIA
                        ? $record->SaldoNuevoOrigen
                        : $record->SaldoNuevoDestino)
                    ->money('PEN')
                    ->weight('bold')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('Concepto')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('usuario.name')->label('Usuario'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direccion')
                    ->label('Movimiento')
                    ->options([
                        'ENTRADA' => 'Entrada',
                        'SALIDA' => 'Salida',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'ENTRADA' => $query->where('DestinoTipo', MovimientoTesoreria::CAJA_GERENCIA),
                        'SALIDA' => $query->where('OrigenTipo', MovimientoTesoreria::CAJA_GERENCIA),
                        default => $query,
                    }),
                Tables\Filters\SelectFilter::make('Tipo')
                    ->multiple()
                    ->options(fn () => MovimientoTesoreria::query()
                        ->where(function (Builder $query): void {
                            $query->where('OrigenTipo', MovimientoTesoreria::CAJA_GERENCIA)
                                ->orWhere('DestinoTipo', MovimientoTesoreria::CAJA_GERENCIA);
                        })
                        ->distinct()
                        ->orderBy('Tipo')
                        ->pluck('Tipo', 'Tipo')),
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('FechaContable', '>=', $fecha))
                        ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('FechaContable', '<=', $fecha))),
            ])
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn (MovimientoTesoreria $record) => MovimientoTesoreriaResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}
