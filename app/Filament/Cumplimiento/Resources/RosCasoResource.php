<?php

namespace App\Filament\Cumplimiento\Resources;

use App\Filament\Cumplimiento\Resources\RosCasoResource\Pages;
use App\Filament\Cumplimiento\Resources\RosCasoResource\RelationManagers;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\RosCaso;
use App\Models\Sede;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RosCasoResource extends Resource
{
    protected static ?string $model = RosCaso::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup = 'Cumplimiento';
    protected static ?string $navigationLabel = 'Casos ROS';
    protected static ?string $modelLabel = 'Caso ROS';
    protected static ?string $pluralModelLabel = 'Casos ROS';
    protected static ?string $recordTitleAttribute = 'CodigoInterno';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->puedeAccederACumplimientoSbs() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->can('ver_todos_los_casos_sbs')) {
            $query->withoutGlobalScope('sede');
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Control del caso')
                ->schema([
                    Forms\Components\TextInput::make('CodigoInterno')
                        ->label('Codigo interno')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn ($record) => $record !== null),
                    Forms\Components\Select::make('Estado')
                        ->options(RosCaso::estados())
                        ->required()
                        ->default(RosCaso::ESTADO_BORRADOR),
                    Forms\Components\Select::make('ClaseReporte')
                        ->label('Clase de reporte')
                        ->options([
                            'INICIAL' => 'Reporte inicial',
                            'CORRECCION' => 'Correccion del anterior',
                            'AMPLIACION' => 'Ampliacion del anterior',
                        ])
                        ->required()
                        ->default('INICIAL')
                        ->live(),
                    Forms\Components\Select::make('SedeID')
                        ->label('Sede')
                        ->options(fn () => Sede::where('Activo', true)->orderBy('Nombre')->pluck('Nombre', 'SedeID'))
                        ->default(fn () => auth()->user()?->getEffectiveSedeId())
                        ->required()
                        ->visible(fn () => auth()->user()?->can('ver_todos_los_casos_sbs') ?? false),
                    Forms\Components\Placeholder::make('sede_asignada')
                        ->label('Sede')
                        ->content(fn () => auth()->user()?->sede?->Nombre ?? 'Sede asignada al usuario')
                        ->visible(fn () => !(auth()->user()?->can('ver_todos_los_casos_sbs') ?? false)),
                    Forms\Components\TextInput::make('NumeroReporteAnterior')
                        ->label('Numero de reporte anterior')
                        ->visible(fn (Forms\Get $get) => in_array($get('ClaseReporte'), ['CORRECCION', 'AMPLIACION'], true)),
                    Forms\Components\DatePicker::make('FechaReporteAnterior')
                        ->label('Fecha de reporte anterior')
                        ->visible(fn (Forms\Get $get) => in_array($get('ClaseReporte'), ['CORRECCION', 'AMPLIACION'], true)),
                    Forms\Components\Toggle::make('EsDatosPrueba')
                        ->label('Datos de prueba')
                        ->helperText('No usar para comunicaciones reales.')
                        ->default(false),
                ])->columns(3),

            Forms\Components\Section::make('Operacion y vinculacion con JALUD')
                ->schema([
                    Forms\Components\Select::make('ZonaID')
                        ->label('Zona')
                        ->relationship('zona', 'Nombre')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('ClienteID')
                        ->label('Cliente principal')
                        ->relationship('cliente', 'NombresApellidos')
                        ->searchable(['NombresApellidos', 'DNI'])
                        ->preload(),
                    Forms\Components\Select::make('CreditoID')
                        ->label('Credito relacionado')
                        ->options(fn () => Credito::query()->with('proposicion:ProposicionCreditoID,CodigoCredito')->limit(100)->get()
                            ->mapWithKeys(fn (Credito $credito) => [$credito->CreditoID => $credito->proposicion?->CodigoCredito ?? "Credito #{$credito->CreditoID}"]))
                        ->searchable(),
                    Forms\Components\Select::make('PagoID')
                        ->label('Pago relacionado')
                        ->options(fn () => Pago::query()->latest('PagoID')->limit(100)->pluck('PagoID', 'PagoID'))
                        ->searchable(),
                    Forms\Components\TextInput::make('MontoTotal')
                        ->label('Monto total involucrado')
                        ->numeric()
                        ->prefix('S/'),
                    Forms\Components\Select::make('Moneda')
                        ->options(['PEN' => 'Soles', 'USD' => 'Dolares', 'EUR' => 'Euros', 'OTRA' => 'Otra'])
                        ->default('PEN')
                        ->required(),
                    Forms\Components\DatePicker::make('FechaDeteccion')->label('Fecha de deteccion'),
                    Forms\Components\DatePicker::make('FechaOperacionDesde')->label('Operacion desde'),
                    Forms\Components\DatePicker::make('FechaOperacionHasta')->label('Operacion hasta'),
                    Forms\Components\Select::make('Alcance')
                        ->options(['NACIONAL' => 'Nacional', 'INTERNACIONAL' => 'Internacional'])
                        ->default('NACIONAL')
                        ->required(),
                    Forms\Components\TextInput::make('PaisesRelacionados')
                        ->label('Paises relacionados')
                        ->visible(fn (Forms\Get $get) => $get('Alcance') === 'INTERNACIONAL'),
                    Forms\Components\TextInput::make('DelitoPrecedente')->label('Presunto delito precedente'),
                ])->columns(3),

            Forms\Components\Section::make('Personas relacionadas')
                ->schema([
                    Forms\Components\Repeater::make('personas')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('TipoPersona')->options(['NATURAL' => 'Persona natural', 'JURIDICA' => 'Persona juridica'])->required(),
                            Forms\Components\Select::make('ClienteID')->label('Cliente existente')->options(fn () => Cliente::query()->limit(100)->pluck('NombresApellidos', 'ClienteID'))->searchable(),
                            Forms\Components\TextInput::make('ApellidoPaterno')->label('Apellido paterno'),
                            Forms\Components\TextInput::make('ApellidoMaterno')->label('Apellido materno'),
                            Forms\Components\TextInput::make('Nombres'),
                            Forms\Components\TextInput::make('RazonSocial')->label('Razon social'),
                            Forms\Components\TextInput::make('TipoDocumento')->label('Tipo documento'),
                            Forms\Components\TextInput::make('NumeroDocumento')->label('Numero documento'),
                            Forms\Components\Select::make('CondicionParticipacion')->options(['TITULAR' => 'Titular', 'APODERADO' => 'Apoderado', 'BENEFICIARIO' => 'Beneficiario', 'GARANTE' => 'Garante', 'OTRO' => 'Otro']),
                            Forms\Components\Toggle::make('EsPep')->label('PEP'),
                            Forms\Components\TextInput::make('ProfesionOcupacion')->label('Profesion u ocupacion'),
                            Forms\Components\TextInput::make('ActividadEconomica')->label('Actividad economica'),
                            Forms\Components\TextInput::make('Domicilio'),
                            Forms\Components\TextInput::make('Telefono'),
                            Forms\Components\TextInput::make('Correo')->email(),
                            Forms\Components\TextInput::make('IngresoMensual')->numeric()->prefix('S/'),
                        ])->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel('Agregar persona relacionada')
                        ->collapsible(),
                ]),

            Forms\Components\Section::make('Operaciones, alertas y sustento')
                ->schema([
                    Forms\Components\Repeater::make('operaciones')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('ProductoServicio')->label('Producto o servicio')->required(),
                            Forms\Components\TextInput::make('CodigoProducto')->label('Codigo ROS'),
                            Forms\Components\TextInput::make('NumeroOperacion')->label('Numero o codigo'),
                            Forms\Components\TextInput::make('Monto')->numeric()->prefix('S/'),
                            Forms\Components\Select::make('Moneda')->options(['PEN' => 'Soles', 'USD' => 'Dolares', 'EUR' => 'Euros'])->default('PEN'),
                            Forms\Components\DatePicker::make('FechaOperacion'),
                            Forms\Components\Textarea::make('Detalle')->columnSpanFull(),
                        ])->columns(3)
                        ->addActionLabel('Agregar operacion')
                        ->collapsible(),
                    Forms\Components\Repeater::make('senalesAlerta')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('Tipo')->options(['REPORTADO' => 'Del reportado', 'OPERACION' => 'De la operacion'])->required(),
                            Forms\Components\TextInput::make('Codigo')->placeholder('Ej. I-1'),
                            Forms\Components\Textarea::make('Descripcion')->required()->columnSpanFull(),
                        ])->columns(2)
                        ->addActionLabel('Agregar senal de alerta')
                        ->collapsible(),
                    Forms\Components\Repeater::make('tipologias')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('Codigo'),
                            Forms\Components\Textarea::make('Descripcion')->required(),
                        ])->columns(2)
                        ->addActionLabel('Agregar tipologia')
                        ->collapsible(),
                    Forms\Components\Repeater::make('adjuntos')
                        ->relationship()
                        ->schema([
                            Forms\Components\FileUpload::make('RutaArchivo')
                                ->label('Archivo de sustento')
                                ->disk('local')
                                ->directory('cumplimiento-sbs')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                                ->maxSize(10240)
                                ->downloadable(),
                            Forms\Components\TextInput::make('Descripcion')->label('Descripcion'),
                        ])->columns(2)
                        ->addActionLabel('Adjuntar sustento')
                        ->collapsible(),
                ]),

            Forms\Components\Section::make('Analisis reservado')
                ->schema([
                    Forms\Components\TextInput::make('SectorEconomico')->label('Sector economico'),
                    Forms\Components\TextInput::make('ActividadEconomica')->label('Actividad economica'),
                    Forms\Components\Textarea::make('DescripcionHechos')
                        ->label('Descripcion cronologica de los hechos')
                        ->rows(8)
                        ->required(),
                    Forms\Components\Textarea::make('ConclusionEvaluacion')
                        ->label('Conclusion de la evaluacion')
                        ->rows(5),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sede', 'zona', 'cliente', 'credito.proposicion']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('CodigoInterno')->label('Caso')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('Estado')->formatStateUsing(fn (string $state) => RosCaso::estados()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'REPORTADO' => 'success', 'DESCARTADO' => 'gray', 'APROBADO_PARA_ROS' => 'warning', 'EN_EVALUACION' => 'info', default => 'primary' }),
                Tables\Columns\TextColumn::make('cliente.NombresApellidos')->label('Cliente')->searchable()->placeholder('Sin cliente'),
                Tables\Columns\TextColumn::make('credito.proposicion.CodigoCredito')->label('Credito')->placeholder('Sin credito'),
                Tables\Columns\TextColumn::make('zona.Nombre')->label('Zona')->placeholder('Sin zona'),
                Tables\Columns\TextColumn::make('sede.Nombre')->label('Sede')->visible(fn () => auth()->user()?->can('ver_todos_los_casos_sbs') ?? false),
                Tables\Columns\TextColumn::make('MontoTotal')->label('Monto')->money('PEN')->sortable(),
                Tables\Columns\TextColumn::make('FechaDeteccion')->label('Detectado')->date('d/m/Y')->sortable(),
                Tables\Columns\IconColumn::make('EsDatosPrueba')->label('Prueba')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('Estado')->options(RosCaso::estados()),
                Tables\Filters\SelectFilter::make('ZonaID')->label('Zona')->options(Zona::where('Activo', true)->orderBy('Nombre')->pluck('Nombre', 'ZonaID')),
                Tables\Filters\SelectFilter::make('SedeID')->label('Sede')->options(Sede::where('Activo', true)->orderBy('Nombre')->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()?->can('ver_todos_los_casos_sbs') ?? false),
                Tables\Filters\TernaryFilter::make('EsDatosPrueba')->label('Datos de prueba'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRosCasos::route('/'),
            'create' => Pages\CreateRosCaso::route('/create'),
            'view' => Pages\ViewRosCaso::route('/{record}'),
            'edit' => Pages\EditRosCaso::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AuditoriasRelationManager::class,
        ];
    }
}
