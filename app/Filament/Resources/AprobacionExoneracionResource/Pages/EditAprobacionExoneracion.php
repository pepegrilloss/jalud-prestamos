<?php

namespace App\Filament\Resources\AprobacionExoneracionResource\Pages;

use App\Filament\Resources\AprobacionExoneracionResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;

class EditAprobacionExoneracion extends ViewRecord
{
    protected static string $resource = AprobacionExoneracionResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Card::make()
                ->schema([
                    Forms\Components\Section::make('Información del Crédito')
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('codigoCredito')
                                ->label('Código Crédito')
                                ->disabled()
                                ->formatStateUsing(fn($record) => $record?->credito?->proposicion?->CodigoCredito),
                            Forms\Components\TextInput::make('cliente')
                                ->label('Cliente')
                                ->disabled()
                                ->formatStateUsing(fn($record) => $record?->credito?->proposicion?->cliente?->NombresApellidos),
                            Forms\Components\TextInput::make('monto')
                                ->label('Monto')
                                ->disabled()
                                ->formatStateUsing(fn($record) => $record?->credito?->proposicion?->MontoTotal),
                            Forms\Components\TextInput::make('saldo')
                                ->label('Saldo Pendiente')
                                ->disabled()
                                ->formatStateUsing(fn($record) => $record?->credito?->proposicion?->SaldoPendiente),
                        ]),
                    Forms\Components\Section::make('Información de Exoneración')
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('tipoNombre')
                                ->label('Tipo de Exoneración')
                                ->disabled()
                                ->formatStateUsing(fn($record) => $record?->tipoExoneracion?->Nombre),
                            Forms\Components\TextInput::make('MontoDisponible')
                                ->label('Monto Disponible')
                                ->disabled(),
                            Forms\Components\TextInput::make('MontoExonerado')
                                ->label('Monto Exonerado')
                                ->disabled(),
                        ]),
                    Forms\Components\Section::make('Detalles')
                        ->columns(1)
                        ->schema([
                            Forms\Components\Textarea::make('Comentario')
                                ->label('Observaciones')
                                ->disabled(),
                        ]),
                ]),
        ]);
    }
}
