<?php

namespace App\Filament\Clusters\EvaluacionDeCredito\Resources;

use App\Filament\Clusters\EvaluacionDeCredito\Resources\RegistrarEvaluacionDeCreditoResource\Pages;
use App\Models\RegistrarEvaluacionDeCredito;
use App\Models\EvaluacionCredito;
use App\Models\AperturaCierreDia;
use App\Models\AnalisisEconomico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use App\Filament\Resources\CreditoResource;

class RegistrarEvaluacionDeCreditoResource extends Resource
{
    protected static ?string $model = RegistrarEvaluacionDeCredito::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Evaluación de Crédito';
    protected static ?string $modelLabel = 'Evaluación de Crédito';
    protected static ?string $pluralModelLabel = 'Evaluaciones de Crédito';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = 'Créditos';

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
            ->modifyQueryUsing(fn ($query) => $query->where('SedeID', auth()->user()->getEffectiveSedeId())->where('Activo', true))
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon('heroicon-m-identification')
                    ->iconColor('primary')
                    ->action(
                        Tables\Actions\Action::make('verCreditos')
                            ->modalHeading(fn ($record, array $arguments) => 'Créditos de ' . (($record?->NombresApellidos) ?? (\App\Models\Cliente::find($arguments['cliente'] ?? null)?->NombresApellidos ?? 'Cliente')))
                            ->modalWidth('7xl')
                            ->modalContent(function ($record, array $arguments) {
                                $cliente = $record ?? \App\Models\Cliente::with(['proposiciones' => fn($q) => $q->where('Estado', 'APROBADO')->has('credito')->with(['tipoCredito', 'credito', 'zona']), 'negocio.zona'])->find($arguments['cliente'] ?? null);
                                return view('filament.components.client-credits-evaluacion-table', ['cliente' => $cliente]);
                            })
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                    ),

                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Apellidos y Nombres')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('Estado')
                    ->label('Observado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'OBSERVADO' ? 'Si' : 'No')
                    ->color(fn (?string $state) => $state === 'OBSERVADO' ? 'danger' : 'success')
                    ->icon(fn (?string $state) => $state === 'OBSERVADO'
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-check-circle')
                    ->sortable(),

                Tables\Columns\TextColumn::make('negocio.ciudad.Nombre')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('negocio.zona.Nombre')
                    ->label('Zona')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('analisisEconomico.MontoMaxRecomendado')
                    ->label('Monto Máx. Recomendado')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

            ])
            ->filters([
                Tables\Filters\Filter::make('filtros_dinamicos')
                    ->form([
                        Forms\Components\Select::make('campos_activos')
                            ->label('Seleccionar Filtros a Aplicar')
                            ->placeholder('Haz clic para elegir filtros...')
                            ->multiple()
                            ->options([
                                'cliente' => 'Cliente',
                                'fecha' => 'Rango de Fechas (Registro)',
                            ])
                            ->live()
                            ->columnSpanFull()
                            ->native(false),

                        Forms\Components\Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Forms\Components\Select::make('cliente')
                                    ->label('Cliente')
                                    ->options(function () {
                                        return \App\Models\Cliente::where('Activo', true)
                                            ->pluck('NombresApellidos', 'ClienteID')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => in_array('cliente', $get('campos_activos') ?? []))
                                    ->live(),

                                Forms\Components\DatePicker::make('fecha_desde')
                                    ->label('Desde')
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => in_array('fecha', $get('campos_activos') ?? []))
                                    ->live(),

                                Forms\Components\DatePicker::make('fecha_hasta')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->visible(fn(\Filament\Forms\Get $get) => in_array('fecha', $get('campos_activos') ?? []))
                                    ->live(),
                            ]),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $activos = $data['campos_activos'] ?? [];
                        return $query
                            ->when(
                                in_array('cliente', $activos) && !empty($data['cliente']),
                                fn(\Illuminate\Database\Eloquent\Builder $q) => $q->where('ClienteID', $data['cliente'])
                            )
                            ->when(
                                in_array('fecha', $activos) && !empty($data['fecha_desde']),
                                fn(\Illuminate\Database\Eloquent\Builder $q) => $q->whereDate('FechaRegistro', '>=', $data['fecha_desde'])
                            )
                            ->when(
                                in_array('fecha', $activos) && !empty($data['fecha_hasta']),
                                fn(\Illuminate\Database\Eloquent\Builder $q) => $q->whereDate('FechaRegistro', '<=', $data['fecha_hasta'])
                            );
                    }),
            ])
            ->filtersFormColumns(1)
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)

            ->actions([
                Tables\Actions\ActionGroup::make([
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
                        ->visible(fn() => AperturaCierreDia::estaAbierto())
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
                            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                            $fechaRegistro = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

                            EvaluacionCredito::create([
                                'ClienteID' => $record->ClienteID,
                                'Comentario' => $data['Comentario'],
                                'FechaRegistro' => $fechaRegistro,
                                'UsuarioRegistro' => auth()->user()->name ?? 'Sistema',
                            ]);

                            Notification::make()
                                ->title('Evaluación registrada exitosamente')
                                ->success()
                                ->body('La evaluación ha sido guardada correctamente.')
                                ->send();
                        })
                        ->successNotificationTitle('Evaluación registrada'),

                    Tables\Actions\Action::make('analisisEconomico')
                        ->label(fn($record) => $record->analisisEconomico
                            ? 'Ver Análisis Económico'
                            : '⚠️ Análisis Económico')
                        ->icon(fn($record) => $record->analisisEconomico
                            ? 'heroicon-o-document-chart-bar'
                            : 'heroicon-o-exclamation-triangle')
                        ->color(fn($record) => $record->analisisEconomico
                            ? 'info'
                            : 'warning')
                        ->disabled(fn() => !AperturaCierreDia::estaAbierto())
                        ->modalHeading(fn($record) => $record->analisisEconomico
                            ? 'Ver/Actualizar Análisis Económico'
                            : 'Registrar Análisis Económico')
                        ->modalDescription(fn($record) => 'Cliente: ' . $record->NombresApellidos . ' (DNI: ' . $record->DNI . ')')
                        ->modalWidth('3xl')
                        ->form([
                            Forms\Components\Section::make('Parte Económica del Cliente')
                                ->description('Complete la información económica manifestada por el cliente y la estimación del jefe de oficina')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('CapitalManifestado')
                                                ->label('Capital Manifestado por el Cliente')
                                                ->required()
                                                ->numeric()
                                                ->minValue(0)
                                                ->placeholder('Ej: 2000.00')
                                                ->helperText('Monto que el cliente indica como capital')
                                                ->disabled(fn() => !AperturaCierreDia::estaAbierto())
                                                ->live(),

                                            Forms\Components\TextInput::make('CapitalEstimado')
                                                ->label('Capital Estimado por el Jefe de Oficina')
                                                ->required()
                                                ->numeric()
                                                ->minValue(0)
                                                ->placeholder('Ej: 4000.00')
                                                ->helperText('Estimación del jefe de oficina')
                                                ->disabled(fn() => !AperturaCierreDia::estaAbierto())
                                                ->live(),
                                        ]),

                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('VentaManifestadaMin')
                                                ->label('Venta Manifestada Mínima por el cliente')
                                                ->required()
                                                ->numeric()
                                                ->minValue(0)
                                                ->placeholder('Ej: 500.00')
                                                ->disabled(fn() => !AperturaCierreDia::estaAbierto())
                                                ->live(),

                                            Forms\Components\TextInput::make('VentaManifestadaMax')
                                                ->label('Venta Manifestada Máxima  por el cliente')
                                                ->required()
                                                ->numeric()
                                                ->minValue(0)
                                                ->placeholder('Ej: 800.00')
                                                ->disabled(fn() => !AperturaCierreDia::estaAbierto())
                                                ->live()
                                                ->rules([
                                                    fn(Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        if ($value < $get('VentaManifestadaMin')) {
                                                            $fail('La venta máxima debe ser mayor o igual a la venta mínima.');
                                                        }
                                                    },
                                                ]),

                                            Forms\Components\TextInput::make('VentaEstimada')
                                                ->label('Venta Estimada por Jefe de Oficina')
                                                ->required()
                                                ->numeric()
                                                ->minValue(0)
                                                ->placeholder('Ej: 400.00')
                                                ->disabled(fn() => !AperturaCierreDia::estaAbierto())
                                        ]),

                                    Forms\Components\TextInput::make('MontoMaxRecomendado')
                                        ->label('Monto Máximo Recomendado')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->placeholder('Ej: 5000.00')
                                        ->helperText('Monto máximo que se recomienda prestar a este cliente')
                                        ->disabled(fn() => !AperturaCierreDia::estaAbierto())
                                        ->live(),

                                    // Resumen visual
                                    Forms\Components\Placeholder::make('resumen')
                                        ->label('📋 Resumen del Análisis')
                                        ->content(function (Forms\Get $get) {
                                            $capManifestado = (float) ($get('CapitalManifestado') ?? 0);
                                            $capEstimado = (float) ($get('CapitalEstimado') ?? 0);
                                            $ventaMin = (float) ($get('VentaManifestadaMin') ?? 0);
                                            $ventaMax = (float) ($get('VentaManifestadaMax') ?? 0);
                                            $ventaEst = (float) ($get('VentaEstimada') ?? 0);

                                            if ($capManifestado > 0 || $capEstimado > 0) {
                                                return new \Illuminate\Support\HtmlString('
                                                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                    <div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">Capital Manifestado vs Estimado</div>
                                                        <div class="text-lg font-semibold">S/ ' . number_format($capManifestado, 2) . ' → S/ ' . number_format($capEstimado, 2) . '</div>
                                                        <div class="text-xs text-gray-500">Diferencia: S/ ' . number_format($capEstimado - $capManifestado, 2) . '</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">Rango de Ventas Manifestadas</div>
                                                        <div class="text-lg font-semibold">S/ ' . number_format($ventaMin, 2) . ' - S/ ' . number_format($ventaMax, 2) . '</div>
                                                        <div class="text-xs text-gray-500">Venta Estimada: S/ ' . number_format($ventaEst, 2) . '</div>
                                                    </div>
                                                </div>
                                            ');
                                            }
                                            return 'Complete los campos para ver el resumen';
                                        })
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->fillForm(fn($record) => [
                            'CapitalManifestado' => $record->analisisEconomico?->CapitalManifestado ?? 0,
                            'CapitalEstimado' => $record->analisisEconomico?->CapitalEstimado ?? 0,
                            'VentaManifestadaMin' => $record->analisisEconomico?->VentaManifestadaMin ?? 0,
                            'VentaManifestadaMax' => $record->analisisEconomico?->VentaManifestadaMax ?? 0,
                            'VentaEstimada' => $record->analisisEconomico?->VentaEstimada ?? 0,
                            'MontoMaxRecomendado' => $record->analisisEconomico?->MontoMaxRecomendado ?? 0,
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                // Desactivar análisis anteriores
                                \App\Models\AnalisisEconomico::where('ClienteID', $record->ClienteID)
                                    ->where('Activo', 1)
                                    ->update([
                                        'Activo' => 0,
                                        'FechaModificacion' => now(),
                                        'UsuarioModificacion' => auth()->user()->name ?? 'Sistema',
                                    ]);

                                // Crear nuevo análisis con fecha abierta
                                $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                                $fechaAnalisis = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

                                \App\Models\AnalisisEconomico::create([
                                    'ClienteID' => $record->ClienteID,
                                    'CapitalManifestado' => $data['CapitalManifestado'],
                                    'CapitalEstimado' => $data['CapitalEstimado'],
                                    'VentaManifestadaMin' => $data['VentaManifestadaMin'],
                                    'VentaManifestadaMax' => $data['VentaManifestadaMax'],
                                    'VentaEstimada' => $data['VentaEstimada'],
                                    'MontoMaxRecomendado' => $data['MontoMaxRecomendado'],
                                    'UsuarioAnalisis' => auth()->user()->name ?? 'Sistema',
                                    'FechaAnalisis' => $fechaAnalisis,
                                    'Activo' => 1,
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('✅ Análisis Económico Registrado')
                                    ->body('El análisis económico ha sido guardado correctamente.')
                                    ->duration(5000)
                                    ->send();

                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('❌ Error al guardar')
                                    ->body('Ocurrió un error: ' . $e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        })
                        ->modalSubmitActionLabel('💾 Guardar Análisis')
                        ->modalCancelActionLabel('Cancelar'),
                ]),
            ]);

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

    public static function getVerDetalleCreditoAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('verDetalleCredito')
            ->label('Ver Detalle de Crédito')
            ->modalHeading('Detalle del Crédito')
            ->modalDescription(function (?\App\Models\Credito $record, array $arguments): ?\Illuminate\Support\HtmlString {
                $clienteNombre = 'Cliente';
                $clienteId = null;
                if ($record && $record->proposicion?->cliente) {
                    $clienteNombre = $record->proposicion->cliente->NombresApellidos;
                    $clienteId = $record->proposicion->ClienteID;
                } else {
                    $clienteId = $arguments['cliente'] ?? null;
                    if ($clienteId) {
                        $clienteNombre = \App\Models\Cliente::find($clienteId)?->NombresApellidos ?? 'Cliente';
                    }
                }
                if (!$clienteId) return null;
                $clienteNombre = e($clienteNombre);
                return new \Illuminate\Support\HtmlString("
                    <button type=\"button\"
                        x-on:click=\"close(); \$wire.mountTableAction('verCreditos', '{$clienteId}')\"
                        class=\"inline-flex items-center gap-x-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300\">
                        <svg class=\"w-4 h-4\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"2\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18\"/></svg>
                        Regresar a créditos de {$clienteNombre}
                    </button>
                ");
            })
            ->modalWidth('7xl')
            ->infolist(function (\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist {
                return $infolist
                    ->schema(array_merge(
                        [
                            \Filament\Infolists\Components\ViewEntry::make('back_button')
                                ->view('filament.components.back-button-credits')
                                ->columnSpanFull()
                        ],
                        \App\Filament\Resources\CreditoResource::getInfolistSchema()
                    ));
            })
            ->record(function (array $arguments) {
                return \App\Models\Credito::with([
                    'proposicion.cliente',
                    'proposicion.zona',
                    'proposicion.tipoCredito',
                    'tipoPago',
                    'moras',
                    'pagos' => fn($q) => $q->where('Activo', 1)->with(['solicitudResolucion.excedente'])->orderBy('FechaPago', 'asc'),
                ])->find($arguments['credito'] ?? null);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }
}
