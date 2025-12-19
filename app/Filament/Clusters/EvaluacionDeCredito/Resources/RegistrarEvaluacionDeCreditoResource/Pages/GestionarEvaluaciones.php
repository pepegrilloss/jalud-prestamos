<?php
namespace App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource\Pages;

use App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource;
use App\Models\EvaluacionCredito;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class GestionarEvaluaciones extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = RegistrarEvaluacionDeCreditoResource::class;
    protected static string $view = 'filament.pages.gestionar-evaluaciones';

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Evaluaciones de ' . $this->record->NombresApellidos;
    }

    public function getSubheading(): ?string
    {
        return 'DNI: ' . $this->record->DNI . ' | Monto Máx. Recomendado: S/. ' . number_format($this->record->MontoMaxRecomendado, 2);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('nueva')
                ->label('Nueva Evaluación')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->modalHeading('Nueva Evaluación de Crédito')
                ->modalWidth('3xl')
                ->form([
                    Forms\Components\Textarea::make('Comentario')
                        ->label('Comentario de Evaluación')
                        ->required()
                        ->rows(6)
                        ->maxLength(5000)
                        ->placeholder('Ejemplo: El cliente tiene historial crediticio bajo en central de riesgo...')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    EvaluacionCredito::create([
                        'ClienteID' => $this->record->ClienteID,
                        'Comentario' => $data['Comentario'],
                        'FechaRegistro' => now(),
                        'UsuarioRegistro' => auth()->user()->name ?? 'Sistema',
                    ]);

                    Notification::make()
                        ->title('Evaluación registrada')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('volver')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(RegistrarEvaluacionDeCreditoResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(EvaluacionCredito::query()->where('ClienteID', $this->record->ClienteID))
            ->columns([
                Tables\Columns\TextColumn::make('FechaRegistro')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar')
                    ->iconColor('primary')
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('UsuarioRegistro')
                    ->label('Registrado por')
                    ->icon('heroicon-m-user')
                    ->iconColor('success')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('Comentario')
                    ->label('Evaluación')
                    ->wrap()
                    ->limit(150)
                    ->tooltip(fn ($record) => $record->Comentario)
                    ->searchable(),
            ])
            ->defaultSort('FechaRegistro', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->modalHeading('Detalle de Evaluación')
                    ->modalWidth('3xl')
                    ->modalContent(fn ($record) => view('filament.components.evaluacion-detalle', [
                        'evaluacion' => $record,
                    ])),

                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar Evaluación')
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Textarea::make('Comentario')
                            ->label('Comentario de Evaluación')
                            ->required()
                            ->rows(6)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ]),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading('No hay evaluaciones registradas')
            ->emptyStateDescription('Comienza registrando la primera evaluación de crédito.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Tables\Actions\Action::make('crear')
                    ->label('Crear primera evaluación')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading('Nueva Evaluación de Crédito')
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Textarea::make('Comentario')
                            ->label('Comentario de Evaluación')
                            ->required()
                            ->rows(6)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        EvaluacionCredito::create([
                            'ClienteID' => $this->record->ClienteID,
                            'Comentario' => $data['Comentario'],
                            'FechaRegistro' => now(),
                            'UsuarioRegistro' => auth()->user()->name ?? 'Sistema',
                        ]);

                        Notification::make()
                            ->title('Evaluación registrada')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}