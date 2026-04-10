<?php

namespace App\Filament\Resources\EvaluacionRiesgoResource\Pages;

use App\Filament\Resources\EvaluacionRiesgoResource;
use App\Models\Cliente;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;

class EvaluatedClients extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = EvaluacionRiesgoResource::class;

    protected static string $view = 'filament.resources.evaluacion-riesgo-resource.pages.evaluated-clients';

    protected static ?string $title = 'Clientes con Evaluación de Riesgo';

    public function table(Table $table): Table
    {
        return $table
            ->query(Cliente::query()->whereHas('evaluacionRiesgo'))
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Apellidos y Nombres')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ciudad.Nombre')
                    ->label('Ciudad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('zona.Nombre')
                    ->label('Zona')
                    ->searchable(),
                Tables\Columns\TextColumn::make('evaluacionRiesgo.Referencias')
                    ->label('Referencias')
                    ->badge(),
                Tables\Columns\TextColumn::make('evaluacionRiesgo.Actitud')
                    ->label('Actitud')
                    ->badge(),
            ])
            ->defaultSort('ClienteID', 'desc')
            ->paginated([10, 25, 50])
            ->searchable();
    }
}
