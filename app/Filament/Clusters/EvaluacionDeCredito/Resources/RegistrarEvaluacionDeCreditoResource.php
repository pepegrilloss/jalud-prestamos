<?php

namespace App\Filament\Clusters\EvaluacionDeCredito\Resources;

use App\Filament\Clusters\EvaluacionDeCredito;
use App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource\Pages;
use App\Models\RegistrarEvaluacionDeCredito;
use App\Models\EvaluacionCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;

class RegistrarEvaluacionDeCreditoResource extends Resource
{
    protected static ?string $model = RegistrarEvaluacionDeCredito::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $cluster = EvaluacionDeCredito::class;
    protected static ?string $navigationLabel = 'Evaluación de Crédito';
    protected static ?string $modelLabel = 'Evaluación de Crédito';
    protected static ?string $pluralModelLabel = 'Evaluaciones de Crédito';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon('heroicon-m-identification')
                    ->copyable()
                    ->copyMessage('DNI copiado')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Nombres y Apellidos')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('MontoMaxRecomendado')
                    ->label('Monto Máx. Recomendado')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('evaluacionesCredito_count')
                    ->label('Evaluaciones')
                    ->counts('evaluacionesCredito')
                    ->alignCenter()
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    ->icon(fn($state) => $state > 0 ? 'heroicon-m-document-check' : 'heroicon-m-document')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('verEvaluaciones')
                    ->label('Ver Evaluaciones')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Evaluaciones de ' . $record->NombresApellidos)
                    ->modalDescription(fn($record) => 'DNI: ' . $record->DNI)
                    ->modalWidth('4xl')
                    ->modalContent(fn($record) => view('filament.components.evaluaciones-list', [
                        'evaluaciones' => $record->evaluacionesCredito()->orderBy('FechaRegistro', 'desc')->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->visible(fn($record) => $record->evaluacionesCredito()->count() > 0),

                Tables\Actions\Action::make('registrarEvaluacion')
                    ->label('Registrar Evaluación')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading(fn($record) => 'Nueva Evaluación de Crédito')
                    ->modalDescription(fn($record) => $record->NombresApellidos . ' - DNI: ' . $record->DNI)
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Textarea::make('Comentario')
                                    ->label('Comentario de Evaluación')
                                    ->required()
                                    ->rows(6)
                                    ->maxLength(5000)
                                    ->placeholder('Ejemplo: El cliente tiene historial crediticio bajo en central de riesgo...')
                                    ->helperText('Describe la evaluación del cliente, su historial crediticio, capacidad de pago, etc.')
                                    ->columnSpanFull(),
                            ])
                    ])
                    ->action(function ($record, array $data) {
                        EvaluacionCredito::create([
                            'ClienteID' => $record->ClienteID,
                            'Comentario' => $data['Comentario'],
                            'FechaRegistro' => now(),
                            'UsuarioRegistro' => auth()->user()->name ?? 'Sistema',
                        ]);

                        Notification::make()
                            ->title('Evaluación registrada exitosamente')
                            ->success()
                            ->body('La evaluación ha sido guardada correctamente.')
                            ->send();
                    })
                    ->successNotificationTitle('Evaluación registrada'),
            ])
            ->bulkActions([
                //
            ])
            ->emptyStateHeading('No hay clientes registrados')
            ->emptyStateDescription('Comienza registrando clientes para poder evaluar sus créditos.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrarEvaluacionDeCreditos::route('/'),
            'evaluaciones' => Pages\GestionarEvaluaciones::route('/{record}/evaluaciones'),
        ];
    }
}