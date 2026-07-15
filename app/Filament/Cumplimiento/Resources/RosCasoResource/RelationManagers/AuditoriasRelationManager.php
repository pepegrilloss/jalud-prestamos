<?php

namespace App\Filament\Cumplimiento\Resources\RosCasoResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AuditoriasRelationManager extends RelationManager
{
    protected static string $relationship = 'auditorias';
    protected static ?string $title = 'Bitacora reservada';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
                Tables\Columns\TextColumn::make('usuario.name')->label('Usuario')->placeholder('Sistema'),
                Tables\Columns\BadgeColumn::make('Accion')->label('Accion')
                    ->color(fn (string $state) => match ($state) { 'CREAR' => 'success', 'ACTUALIZAR' => 'info', 'ELIMINAR' => 'danger', default => 'gray' }),
                Tables\Columns\TextColumn::make('Modelo')->label('Elemento'),
                Tables\Columns\TextColumn::make('ModeloID')->label('ID'),
                Tables\Columns\TextColumn::make('IpAddress')->label('IP'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
