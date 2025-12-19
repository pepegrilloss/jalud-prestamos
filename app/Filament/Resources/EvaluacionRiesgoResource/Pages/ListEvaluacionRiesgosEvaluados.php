<?php

namespace App\Filament\Resources\EvaluacionRiesgoResource\Pages;

use App\Filament\Resources\EvaluacionRiesgoResource;
use App\Models\Cliente;
use App\Models\EvaluacionRiesgo;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;

class ListEvaluacionRiesgosEvaluados extends ListRecords
{
    protected static string $resource = EvaluacionRiesgoResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->query(
                Cliente::query()->whereHas('evaluacionRiesgo')
            )
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Nombres y Apellidos')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ciudad.Nombre')
                    ->label('Ciudad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('zona.Nombre')
                    ->label('Zona')
                    ->searchable(),
                Tables\Columns\TextColumn::make('evaluacionRiesgo.MMR')
                    ->label('MMR')
                    ->numeric(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('verEvaluacion')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => route('filament.resources.evaluacion-riesgo.edit', [
                        'record' => EvaluacionRiesgo::where('ClienteID', $record->ClienteID)->value('EvaluacionRiesgoID')
                    ])),
                Tables\Actions\Action::make('editarEvaluacion')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil')
                    ->url(fn($record) => route('filament.resources.evaluacion-riesgo.edit', [
                        'record' => EvaluacionRiesgo::where('ClienteID', $record->ClienteID)->value('EvaluacionRiesgoID')
                    ])),
            ])
            ->defaultSort('ClienteID', 'desc')
            ->paginated([10, 25, 50])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->searchable()
            ->persistSortInSession()
            ->searchDebounce(500);
    }
}
