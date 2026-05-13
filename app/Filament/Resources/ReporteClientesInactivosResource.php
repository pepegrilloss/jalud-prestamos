<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteClientesInactivosResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReporteClientesInactivosResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationGroupSort = 10;
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'Clientes Inactivos';
    protected static ?string $pluralLabel = 'Clientes Inactivos';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('negocio.zona.Nombre')
                    ->label('Zona')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ultimo_codigo')
                    ->label('Último Crédito')
                    ->getStateUsing(fn ($record) => $record->ultimo_codigo ?? '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ultimo_monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->getStateUsing(fn ($record) => (float) ($record->ultimo_monto ?? 0)),

                Tables\Columns\TextColumn::make('ultimo_monto_total')
                    ->label('Monto + Interés')
                    ->money('PEN')
                    ->getStateUsing(fn ($record) => (float) ($record->ultimo_monto_total ?? 0)),

                Tables\Columns\TextColumn::make('fecha_saldado')
                    ->label('Fecha Saldado')
                    ->getStateUsing(fn ($record) => $record->fecha_saldado
                        ? \Carbon\Carbon::parse($record->fecha_saldado)->format('d/m/Y')
                        : '-'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias_inactivo')
                    ->label('Días Inactivo')
                    ->getStateUsing(fn ($record) => $record->fecha_saldado
                        ? \App\Services\DiasHabilesCalculator::contarDiasHabiles(
                            \Carbon\Carbon::parse($record->fecha_saldado)->addDay(),
                            now()
                        )
                        : 0
                    )
                    ->sortable()
                    ->color('gray')
                    ->badge()
                    ->icon('heroicon-m-clock'),
            ])
            ->filters([
                Tables\Filters\Filter::make('cliente_filtro')
                    ->label('Cliente')
                    ->form([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre o DNI')
                            ->placeholder('Buscar por nombre o DNI...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['nombre'] ?? null,
                            fn(Builder $q, $nombre) => $q->where(function ($sq) use ($nombre) {
                                $sq->where('Cliente.NombresApellidos', 'like', "%{$nombre}%")
                                   ->orWhere('Cliente.DNI', 'like', "%{$nombre}%");
                            })
                        );
                    }),

                Tables\Filters\Filter::make('fecha')
                    ->label('Fecha Saldado')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['fecha_desde'] ?? null,
                                fn(Builder $q, $date) => $q->havingRaw('MAX(Credito.FechaSaldamiento) >= ?', [$date])
                            )
                            ->when(
                                $data['fecha_hasta'] ?? null,
                                fn(Builder $q, $date) => $q->havingRaw('MAX(Credito.FechaSaldamiento) <= ?', [$date])
                            );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->select('Cliente.*')
                    ->selectRaw("MAX(Credito.FechaSaldamiento) as fecha_saldado")
                    ->selectRaw("DATEDIFF(NOW(), MAX(Credito.FechaSaldamiento)) as dias_inactivo")
                    ->selectRaw("(SELECT pc.CodigoCredito FROM ProposicionCredito pc 
                        JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID 
                        WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO' 
                        ORDER BY c.FechaSaldamiento DESC LIMIT 1) as ultimo_codigo")
                    ->selectRaw("(SELECT pc.MontoTotal FROM ProposicionCredito pc 
                        JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID 
                        WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO' 
                        ORDER BY c.FechaSaldamiento DESC LIMIT 1) as ultimo_monto")
                    ->selectRaw("(SELECT pc.MontoTotalPagar FROM ProposicionCredito pc 
                        JOIN Credito c ON c.ProposicionCreditoID = pc.ProposicionCreditoID 
                        WHERE pc.ClienteID = Cliente.ClienteID AND c.EstatusCreditoFinal = 'SALDADO' 
                        ORDER BY c.FechaSaldamiento DESC LIMIT 1) as ultimo_monto_total")
                    ->join('ProposicionCredito as prop', 'prop.ClienteID', '=', 'Cliente.ClienteID')
                    ->join('Credito', function ($join) {
                        $join->on('Credito.ProposicionCreditoID', '=', 'prop.ProposicionCreditoID')
                             ->where('Credito.EstatusCreditoFinal', '=', 'SALDADO');
                    })
                    ->where('Cliente.Activo', true)
                    ->whereDoesntHave('proposiciones', function ($q) {
                        $q->where('Activo', true)
                          ->whereHas('credito', function ($sq) {
                              $sq->where('Activo', true)
                                 ->where('EstatusCreditoFinal', '!=', 'SALDADO');
                          });
                    })
                    ->groupBy('Cliente.ClienteID')
                    ->havingRaw('dias_inactivo >= 1')
                    ->orderByRaw('dias_inactivo DESC');
            })
            ->actions([])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_reporte::clientes::inactivos');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteClientesInactivos::route('/'),
        ];
    }
}
