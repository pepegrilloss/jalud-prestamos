<?php

namespace App\Filament\Widgets;

use App\Models\SolicitudExoneracion;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ExoneracionesPendientesWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SolicitudExoneracion::where('Estado', 'PENDIENTE')
                    ->with('credito.proposicionCredito', 'tipoExoneracion')
                    ->latest('FechaSolicitud')
            )
            ->columns([
                Tables\Columns\TextColumn::make('credito.proposicionCredito.CodigoCredito')
                    ->label('Crédito')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipoExoneracion.Nombre')
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('MontoExonerado')
                    ->label('Monto')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('FechaSolicitud')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->paginated(false);
    }
}
