<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use App\Models\Ciudad;
use App\Models\Zona;
use App\Models\Tasa;
use App\Models\TasaMora;
use App\Models\PromotorCobrador;
use App\Models\Giro;
use App\Models\SubGiro;
use App\Models\AnalisisEconomico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;

use App\Models\Sede;
class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    protected static ?string $navigationGroup = 'Clientes';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationGroupSort = 1;
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'NombresApellidos';

    public static function getGloballySearchableAttributes(): array
    {
        return ['NombresApellidos', 'DNI'];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Banner de Advertencia si no tiene Análisis Económico
                Forms\Components\Placeholder::make('alerta_analisis')
                    ->label('')
                    ->content(
                        fn($record) => $record && !$record->analisisEconomico
                        ? 'Este cliente NO tiene un Análisis Económico registrado. Es OBLIGATORIO antes de continuar.'
                        : ''
                    )
                    ->visible(fn($record) => $record && !$record->analisisEconomico)
                    ->extraAttributes([
                        'class' => 'text-danger-600 font-bold text-center p-4 bg-danger-50 rounded-lg border-2 border-danger-600'
                    ]),

                Forms\Components\Section::make('Información Personal')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('DNI')
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        $sedeId = auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID;
                                        return $rule->where('SedeID', $sedeId);
                                    })
                                    ->maxLength(20)
                                    ->label('Dni')
                                    ->placeholder('Ingrese DNI')
                                    ->prefixIcon('heroicon-m-identification')
                                    ->live(onBlur: true)
                                    ->suffixAction(
                                        Forms\Components\Actions\Action::make('buscarReniec')
                                            ->icon('heroicon-o-magnifying-glass')
                                            ->action(function (Set $set, $state) {
                                                if (empty($state) || strlen($state) != 8) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('DNI inválido')
                                                        ->body('Ingrese un DNI de 8 dígitos')
                                                        ->send();
                                                    return;
                                                }

                                                try {
                                                    $token = 'apis-token-1.aTSI1U7KEuT-6bbbCguH-4Y8TI6KS73N';

                                                    $response = Http::withHeaders([
                                                        'Referer' => 'https://apis.net.pe/consulta-dni-api',
                                                        'Authorization' => 'Bearer ' . $token
                                                    ])->get('https://api.apis.net.pe/v1/dni?numero=' . $state);

                                                    if ($response->successful()) {
                                                        $persona = $response->json();

                                                        if (isset($persona['nombres']) && isset($persona['apellidoPaterno']) && isset($persona['apellidoMaterno'])) {
                                                            $set('ApellidoPaterno', strtoupper($persona['apellidoPaterno']));
                                                            $set('ApellidoMaterno', strtoupper($persona['apellidoMaterno']));
                                                            $set('Nombres', strtoupper($persona['nombres']));

                                                            \Filament\Notifications\Notification::make()
                                                                ->success()
                                                                ->title('Datos encontrados')
                                                                ->body('Información obtenida de RENIEC correctamente')
                                                                ->send();
                                                        } else {
                                                            throw new \Exception('Datos incompletos');
                                                        }
                                                    } else {
                                                        throw new \Exception('Error en la consulta: ' . $response->status());
                                                    }
                                                } catch (\Exception $e) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('RENIEC no ha devuelto datos')
                                                        ->body('Por favor, ingrese los datos manualmente')
                                                        ->persistent()
                                                        ->send();
                                                }
                                            })
                                    )
                                    ->validationMessages([
                                        'unique' => 'Este DNI ya está registrado en el sistema.',
                                    ]),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('ApellidoPaterno')
                                    ->required()
                                    ->maxLength(100)
                                    ->label('Apellido Paterno')
                                    ->placeholder('Se llenará con RENIEC')
                                    ->prefixIcon('heroicon-m-user'),

                                Forms\Components\TextInput::make('ApellidoMaterno')
                                    ->required()
                                    ->maxLength(100)
                                    ->label('Apellido Materno')
                                    ->placeholder('Se llenará con RENIEC')
                                    ->prefixIcon('heroicon-m-user'),

                                Forms\Components\TextInput::make('Nombres')
                                    ->required()
                                    ->maxLength(100)
                                    ->label('Nombres')
                                    ->placeholder('Se llenará con RENIEC')
                                    ->prefixIcon('heroicon-m-user'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('Sexo')
                                    ->required()
                                    ->prefixIcon('heroicon-m-users')
                                    ->options([
                                        'M' => 'Masculino',
                                        'F' => 'Femenino',
                                    ])
                                    ->native(false),

                                Forms\Components\DatePicker::make('FechaNacimiento')
                                    ->label('Fecha de Nacimiento')
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now()),

                                Forms\Components\Select::make('Estado')
                                    ->required()
                                    ->prefixIcon('heroicon-m-check-badge')
                                    ->options([
                                        'NO OBSERVADO' => 'No Observado',
                                        'OBSERVADO' => 'Observado',
                                    ])
                                    ->default('NO OBSERVADO')
                                    ->native(false),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Datos del Cónyuge')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('ConyugeDNI')
                                    ->maxLength(20)
                                    ->label('DNI del Cónyuge')
                                    ->placeholder('Ingrese DNI del cónyuge')
                                    ->prefixIcon('heroicon-m-identification')
                                    ->live(onBlur: true)
                                    ->suffixAction(
                                        Forms\Components\Actions\Action::make('buscarConyugeReniec')
                                            ->icon('heroicon-o-magnifying-glass')
                                            ->action(function (Set $set, $state) {
                                                if (empty($state) || strlen($state) != 8) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('DNI inválido')
                                                        ->body('Ingrese un DNI de 8 dígitos')
                                                        ->send();
                                                    return;
                                                }

                                                try {
                                                    $token = 'apis-token-1.aTSI1U7KEuT-6bbbCguH-4Y8TI6KS73N';

                                                    $response = Http::withHeaders([
                                                        'Referer' => 'https://apis.net.pe/consulta-dni-api',
                                                        'Authorization' => 'Bearer ' . $token
                                                    ])->get('https://api.apis.net.pe/v1/dni?numero=' . $state);

                                                    if ($response->successful()) {
                                                        $persona = $response->json();

                                                        if (isset($persona['nombres']) && isset($persona['apellidoPaterno']) && isset($persona['apellidoMaterno'])) {
                                                            $nombreCompleto = $persona['apellidoPaterno'] . ' ' . $persona['apellidoMaterno'] . ' ' . $persona['nombres'];
                                                            $set('ConyugeNombresApellidos', strtoupper($nombreCompleto));

                                                            \Filament\Notifications\Notification::make()
                                                                ->success()
                                                                ->title('Datos del cónyuge encontrados')
                                                                ->body('Información obtenida de RENIEC correctamente')
                                                                ->send();
                                                        } else {
                                                            throw new \Exception('Datos incompletos');
                                                        }
                                                    } else {
                                                        throw new \Exception('Error en la consulta: ' . $response->status());
                                                    }
                                                } catch (\Exception $e) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('RENIEC no ha devuelto datos del cónyuge')
                                                        ->body('Por favor, ingrese los datos manualmente')
                                                        ->persistent()
                                                        ->send();
                                                }
                                            })
                                    ),

                                Forms\Components\TextInput::make('ConyugeNombresApellidos')
                                    ->maxLength(200)
                                    ->label('Apellidos y Nombres del Cónyuge')
                                    ->prefixIcon('heroicon-m-user')
                                    ->placeholder('Se llenará automáticamente con RENIEC'),
                            ]),
                    ])
                    ->collapsible()
                    ->visible(),

                Forms\Components\Section::make('Domicilio')
                    ->schema([
                        Forms\Components\TextInput::make('Domicilio')
                            ->prefixIcon('heroicon-m-map-pin')
                            ->maxLength(500)
                            ->placeholder('Dirección completa del domicilio del cliente'),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Información Financiera')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('TasaID')
                                    ->required()
                                    ->label('Tasa de Interés')
                                    ->prefixIcon('heroicon-m-receipt-percent')
                                    ->options(
                                        Tasa::where('Activo', 1)
                                            ->get()
                                            ->mapWithKeys(fn($tasa) => [
                                                $tasa->TasaID => "{$tasa->Nombre} - {$tasa->Valor}%"
                                            ])
                                    )
                                    ->searchable()
                                    ->native(false),

                                Forms\Components\Select::make('TasaMoraID')
                                    ->required()
                                    ->label('Tasa de Mora')
                                    ->prefixIcon('heroicon-m-exclamation-triangle')
                                    ->options(
                                        TasaMora::where('Activo', 1)
                                            ->get()
                                            ->mapWithKeys(fn($tasaMora) => [
                                                $tasaMora->TasaMoraID => "{$tasaMora->Nombre} - {$tasaMora->Porcentaje}%"
                                            ])
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder('Seleccione una tasa de mora'),

                                Forms\Components\Select::make('PromotorCobradorID')
                                    ->label('Promotor/Cobrador')
                                    ->prefixIcon('heroicon-m-briefcase')
                                    ->options(
                                        PromotorCobrador::where('Activo', 1)
                                            ->get()
                                            ->mapWithKeys(fn($pc) => [
                                                $pc->PromotorCobradorID => "{$pc->Codigo} - {$pc->Descripcion}"
                                            ])
                                    )
                                    ->searchable()
                                    ->native(false),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Información del Negocio')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('negocio.CiudadID')
                                    ->required()
                                    ->label('Ciudad')
                                    ->prefixIcon('heroicon-m-building-office')
                                    ->options(Ciudad::where('Activo', 1)->pluck('Nombre', 'CiudadID'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set) => $set('negocio.ZonaID', null)),

                                Forms\Components\Select::make('negocio.ZonaID')
                                    ->label('Zona')
                                    ->prefixIcon('heroicon-m-map')
                                    ->options(
                                        fn(Get $get) =>
                                        Zona::where('CiudadID', $get('negocio.CiudadID'))
                                            ->where('Activo', 1)
                                            ->pluck('Nombre', 'ZonaID')
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn(Get $get) => !$get('negocio.CiudadID')),
                                    
                                Forms\Components\TextInput::make('negocio.DireccionNegocio')
                                    ->label('Dirección del Negocio')
                                    ->required()
                                    ->maxLength(500)
                                    ->prefixIcon('heroicon-m-map-pin')
                                    ->placeholder('Dirección completa del negocio')
                                    ->columnSpan(2),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('negocio.Antiguedad')
                                    ->label('Antigüedad (años)')
                                    ->prefixIcon('heroicon-m-clock')
                                    ->numeric()
                                    ->step(0.1)
                                    ->suffix('años'),

                                Forms\Components\Select::make('negocio.GiroID')
                                    ->required()
                                    ->label('Giro')
                                    ->prefixIcon('heroicon-m-briefcase')
                                    ->options(
                                        fn() => Giro::where('Activo', 1)
                                            ->get()
                                            ->mapWithKeys(fn($giro) => [
                                                $giro->GiroID => "{$giro->Codigo} - {$giro->Descripcion}"
                                            ])
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('Codigo')
                                            ->label('Código')
                                            ->required()
                                            ->maxLength(10)
                                            ->placeholder('Ej: G001'),
                                        Forms\Components\TextInput::make('Descripcion')
                                            ->label('Descripción')
                                            ->required()
                                            ->maxLength(200),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $giro = \App\Models\Giro::create($data);
                                        return $giro->GiroID;
                                    })
                                    ->afterStateUpdated(fn(Set $set) => $set('negocio.SubGiroID', null)),

                                Forms\Components\Select::make('negocio.SubGiroID')
                                    ->label('Sub Giro')
                                    ->prefixIcon('heroicon-m-tag')
                                    ->options(
                                        fn(Get $get) =>
                                        SubGiro::where('GiroID', $get('negocio.GiroID'))
                                            ->where('Activo', 1)
                                            ->pluck('Descripcion', 'SubGiroID')
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn(Get $get) => !$get('negocio.GiroID'))
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('Descripcion')
                                            ->label('Descripción del Sub Giro')
                                            ->required()
                                            ->maxLength(200),
                                    ])
                                    ->createOptionUsing(function (array $data, Get $get) {
                                        $data['GiroID'] = $get('negocio.GiroID');
                                        $subGiro = \App\Models\SubGiro::create($data);
                                        return $subGiro->SubGiroID;
                                    }),

                                Forms\Components\TextInput::make('negocio.ObservacionGiro')
                                    ->label('Detalle u Observación del Giro')
                                    ->prefixIcon('heroicon-m-document-text')
                                    ->maxLength(255)
                                    ->placeholder('Ej: Sientos de motos, venta de abarrotes, etc.'),
                            ]),

                        Forms\Components\Repeater::make('negocio.telefonos')
                            ->label('Teléfonos del Negocio')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('Telefono')
                                            ->label('Número de Teléfono')
                                            ->required()
                                            ->prefixIcon('heroicon-m-phone')
                                            ->tel()
                                            ->maxLength(20)
                                            ->placeholder('Ej: 987654321'),

                                        Forms\Components\Select::make('TipoTelefono')
                                            ->label('Tipo')
                                            ->prefixIcon('heroicon-m-device-phone-mobile')
                                            ->options([
                                                'PRINCIPAL' => 'Principal',
                                                'SECUNDARIO' => 'Secundario',
                                                'ALTERNATIVO' => 'Alternativo',
                                            ])
                                            ->default('PRINCIPAL')
                                            ->native(false),

                                        Forms\Components\TextInput::make('Observacion')
                                            ->label('Observación')
                                            ->prefixIcon('heroicon-m-chat-bubble-bottom-center-text')
                                            ->maxLength(200)
                                            ->placeholder('Ej: WhatsApp, Personal, etc.'),
                                    ]),
                            ])
                            ->defaultItems(1)
                            ->maxItems(3)
                            ->minItems(1)
                            ->addActionLabel('Agregar Teléfono')
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['Telefono'] ?? null
                            ),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Análisis Económico')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('analisis_economico.CapitalManifestado')
                                    ->required()
                                    ->label('Capital Manifestado')
                                    ->prefixIcon('heroicon-m-banknotes')
                                    ->numeric()
                                    ->placeholder('Ej: 2000.00')
                                    ->helperText('Capital que el cliente indica'),

                                Forms\Components\TextInput::make('analisis_economico.CapitalEstimado')
                                    ->required()
                                    ->label('Capital Estimado')
                                    ->prefixIcon('heroicon-m-currency-dollar')
                                    ->numeric()
                                    ->placeholder('Ej: 4000.00')
                                    ->helperText('Estimación del jefe de oficina'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('analisis_economico.VentaManifestadaMin')
                                    ->required()
                                    ->label('Venta Min. Manifestada')
                                    ->prefixIcon('heroicon-m-presentation-chart-line')
                                    ->numeric()
                                    ->placeholder('Ej: 500.00')
                                    ->helperText('Venta mínima declarada'),

                                Forms\Components\TextInput::make('analisis_economico.VentaManifestadaMax')
                                    ->required()
                                    ->label('Venta Max. Manifestada')
                                    ->prefixIcon('heroicon-m-presentation-chart-line')
                                    ->numeric()
                                    ->placeholder('Ej: 800.00')
                                    ->helperText('Venta máxima declarada'),

                                Forms\Components\TextInput::make('analisis_economico.VentaEstimada')
                                    ->required()
                                    ->label('Venta Estimada')
                                    ->prefixIcon('heroicon-m-chart-bar')
                                    ->numeric()
                                    ->placeholder('Ej: 400.00')
                                    ->helperText('Estimación de oficina'),
                            ]),

                        Forms\Components\TextInput::make('analisis_economico.MontoMaxRecomendado')
                            ->required()
                            ->label('Monto Máximo Recomendado')
                            ->prefixIcon('heroicon-m-shield-check')
                            ->numeric()
                            ->step(0.01)
                            ->placeholder('Ej: 5000.00')
                            ->helperText('Monto recomendado para préstamo'),
                    ])
                    ->collapsible()
                    ->visible(fn($livewire) => $livewire instanceof \App\Filament\Resources\ClienteResource\Pages\CreateCliente),

                Forms\Components\Section::make('Documentación')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('dni_info')
                                            ->label('Archivo Actual')
                                            ->content(function () {
                                                if (request()->route('record')) {
                                                    $record = \App\Models\Cliente::find(request()->route('record'));
                                                    $doc = $record->getDocumentoDNI();
                                                    return $doc ? $doc->NombreOriginal : 'Sin archivo';
                                                }
                                                return 'Nuevo archivo';
                                            })
                                            ->visible(fn($livewire) => !($livewire instanceof \App\Filament\Resources\ClienteResource\Pages\CreateCliente)),

                                        Forms\Components\FileUpload::make('documentos.dni')
                                            ->label('Foto del DNI')
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                null,
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ])
                                            ->directory('documentos/dni')
                                            ->maxSize(5120)
                                            ->acceptedFileTypes(['image/*'])
                                            ->helperText('Formato: JPG, PNG. Tamaño máximo: 5MB')
                                            ->preserveFilenames()
                                            ->visibility('public')
                                            ->dehydrated(fn($state) => filled($state)),
                                    ]),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('recibo_info')
                                            ->label('Archivo Actual')
                                            ->content(function () {
                                                if (request()->route('record')) {
                                                    $record = \App\Models\Cliente::find(request()->route('record'));
                                                    $doc = $record->getDocumentoReciboServicio();
                                                    return $doc ? $doc->NombreOriginal : 'Sin archivo';
                                                }
                                                return 'Nuevo archivo';
                                            })
                                            ->visible(fn($livewire) => !($livewire instanceof \App\Filament\Resources\ClienteResource\Pages\CreateCliente)),

                                        Forms\Components\FileUpload::make('documentos.recibo_servicio')
                                            ->label('Recibo de Servicio (Luz/Agua)')
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                null,
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ])
                                            ->directory('documentos/recibos')
                                            ->maxSize(5120)
                                            ->acceptedFileTypes(['image/*'])
                                            ->helperText('Formato: JPG, PNG. Tamaño máximo: 5MB')
                                            ->preserveFilenames()
                                            ->visibility('public')
                                            ->dehydrated(fn($state) => filled($state)),
                                    ]),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Garantías y Observaciones')
                    ->schema([
                        Forms\Components\Select::make('GaranteID')
                            ->label('Garante')
                            ->prefixIcon('heroicon-m-user-plus')
                            ->options(function ($record) {
                                // Obtener todos los clientes activos excepto el cliente actual
                                return Cliente::where('Activo', 1)
                                    ->when($record, fn($query) => $query->where('ClienteID', '!=', $record->ClienteID))
                                    ->orderBy('NombresApellidos')
                                    ->get()
                                    ->mapWithKeys(fn($cliente) => [
                                        $cliente->ClienteID => "{$cliente->DNI} - {$cliente->NombresApellidos}"
                                    ]);
                            })
                            ->searchable()
                            ->native(false)
                            ->placeholder('Seleccione un cliente como garante')
                            ->helperText('El garante debe ser un cliente registrado en el sistema'),

                        Forms\Components\Textarea::make('Observaciones')
                            ->rows(4)
                            ->maxLength(65535)
                            ->placeholder('Observaciones adicionales sobre el cliente o préstamo'),
                    ])
                    ->collapsible()
                    ->visible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->icon('heroicon-m-identification') 
                    ->iconColor('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Apellidos y Nombres')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('negocio.ciudad.Nombre')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\TextColumn::make('negocio.zona.Nombre')
                    ->label('Zona')
                    ->searchable()
                    ->toggleable()
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\TextColumn::make('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'NO OBSERVADO' => 'success',
                        'OBSERVADO' => 'warning',
                    })
                    ->sortable()
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\TextColumn::make('promotorCobrador.Descripcion')
                    ->label('Promotor/Cobrador')
                    ->searchable()
                    ->toggleable()
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\TextColumn::make('garante.NombresApellidos')
                    ->label('Garante')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('Sin garante')
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\IconColumn::make('Activo')
                    ->boolean()
                    ->sortable()
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                Tables\Columns\TextColumn::make('FechaRegistro')
                    ->label('Fecha Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn() => !auth()->user()?->hasRole('Promotor Cobrador')),

                // Direcciones y teléfonos: visibles para todos (promotor verá solo estas junto al nombre)
                Tables\Columns\TextColumn::make('Domicilio')
                    ->label('Domicilio')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->Domicilio),

                Tables\Columns\TextColumn::make('negocio.DireccionNegocio')
                    ->label('Dirección Negocio')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->negocio?->DireccionNegocio),

                Tables\Columns\TextColumn::make('telefonos')
                    ->label('Teléfonos')
                    ->getStateUsing(fn($record) => $record->negocio?->telefonos?->pluck('Telefono')?->implode(', ') ?? '-')
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn () => auth()->user()->esAdmin()),
                Tables\Filters\Filter::make('NombresApellidos')
                    ->label('Apellidos y Nombres del Cliente')
                    ->form([
                        Forms\Components\TextInput::make('NombresApellidos')
                            ->placeholder('Buscar por apellido o nombre'),
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        $query->when(
                            $data['NombresApellidos'] ?? null,
                            fn(Builder $query, $value) => $query->where('NombresApellidos', 'like', "%{$value}%")
                        )
                    ),

                Tables\Filters\SelectFilter::make('CiudadID')
                    ->label('Ciudad')
                    ->options(Ciudad::where('Activo', 1)->pluck('Nombre', 'CiudadID'))
                    ->searchable()
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('negocio', fn(Builder $subQ) => $subQ->where('CiudadID', $data['value']))
                        );
                    }),

                Tables\Filters\SelectFilter::make('ZonaID')
                    ->label('Zona')
                    ->options(
                        fn() =>
                        Zona::where('Activo', 1)->pluck('Nombre', 'ZonaID')
                    )
                    ->searchable()
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('negocio', fn(Builder $subQ) => $subQ->where('ZonaID', $data['value']))
                        );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Solo mostrar clientes activos
                return $query->where('Activo', true);
            })
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver')
                        ->visible(fn () => !auth()->user()?->hasRole('Promotor Cobrador')),
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->visible(fn (Cliente $record) => self::canEdit($record)),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Cliente $record) => self::canDelete($record))
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Cliente')
                        ->modalDescription('¿Está seguro de que desea eliminar este cliente? Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->action(function (Cliente $record) {
                            $record->update([
                                'Activo' => false,
                                'UsuarioModificacion' => auth()->user()->name ?? 'Sistema',
                                'FechaModificacion' => now(),
                            ]);

                            // Optional: Also deactivate related records for data consistency
                            $record->negocio()->update(['Activo' => false]);
                            $record->analisisEconomico()->update(['Activo' => false]);
                        }),
                ]),
            ])
            ->defaultSort('FechaRegistro', 'desc');
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
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
            'view' => Pages\ViewCliente::route('/{record}'),
        ];
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (!parent::canView($record)) { return false; }

        return true;
    }

    public static function canCreate(): bool
    {
        // Respetar Permisos de Rol (Shield)
        if (!parent::canCreate()) {
            return false;
        }

        // Los Promotores Cobradores NO pueden crear clientes
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            return false;
        }

        // Si hay cualquier día abierto, permitir crear
        return \App\Models\AperturaCierreDia::where('EstadoDia', 'ABIERTO')->exists();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Respetar Permisos de Rol (Shield)
        if (!parent::canEdit($record)) {
            return false;
        }

        // Los Promotores Cobradores NO pueden editar clientes
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            return false;
        }

        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Respetar Permisos de Rol (Shield)
        if (!parent::canDelete($record)) {
            return false;
        }

        // Los Promotores Cobradores NO pueden eliminar clientes
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            return false;
        }

        // Si hay cualquier día abierto, permitir eliminar
        return \App\Models\AperturaCierreDia::where('EstadoDia', 'ABIERTO')->exists();
    }
}