<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use App\Models\Ciudad;
use App\Models\Zona;
use App\Models\Tasa;
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

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    protected static ?string $navigationGroup = 'Clientes';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationGroupSort = 1;
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Banner de Advertencia si no tiene Análisis Económico
                Forms\Components\Placeholder::make('alerta_analisis')
                    ->label('')
                    ->content(
                        fn($record) => $record && !$record->analisisEconomico
                        ? '⚠️ Este cliente NO tiene un Análisis Económico registrado. Es OBLIGATORIO antes de continuar.'
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
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->label('Dni')
                                    ->placeholder('Ingrese DNI')
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

                                                    $curl = curl_init();
                                                    curl_setopt_array($curl, array(
                                                        CURLOPT_URL => 'https://api.apis.net.pe/v1/dni?numero=' . $state,
                                                        CURLOPT_RETURNTRANSFER => true,
                                                        CURLOPT_SSL_VERIFYPEER => 0,
                                                        CURLOPT_ENCODING => '',
                                                        CURLOPT_MAXREDIRS => 2,
                                                        CURLOPT_TIMEOUT => 10,
                                                        CURLOPT_FOLLOWLOCATION => true,
                                                        CURLOPT_CUSTOMREQUEST => 'GET',
                                                        CURLOPT_HTTPHEADER => array(
                                                            'Referer: https://apis.net.pe/consulta-dni-api',
                                                            'Authorization: Bearer ' . $token
                                                        ),
                                                    ));

                                                    $response = curl_exec($curl);
                                                    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                                                    curl_close($curl);

                                                    if ($httpCode == 200) {
                                                        $persona = json_decode($response);

                                                        if (isset($persona->nombres) && isset($persona->apellidoPaterno) && isset($persona->apellidoMaterno)) {
                                                            $nombreCompleto = $persona->nombres . ' ' . $persona->apellidoPaterno . ' ' . $persona->apellidoMaterno;
                                                            $set('NombresApellidos', strtoupper($nombreCompleto));

                                                            \Filament\Notifications\Notification::make()
                                                                ->success()
                                                                ->title('Datos encontrados')
                                                                ->body('Información obtenida de RENIEC correctamente')
                                                                ->send();
                                                        } else {
                                                            throw new \Exception('Datos incompletos');
                                                        }
                                                    } else {
                                                        throw new \Exception('Error en la consulta');
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

                                Forms\Components\TextInput::make('NombresApellidos')
                                    ->required()
                                    ->maxLength(200)
                                    ->label('Nombres y Apellidos')
                                    ->placeholder('Se llenará automáticamente con RENIEC')
                                    ->columnSpan(2),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('Sexo')
                                    ->required()
                                    ->options([
                                        'M' => 'Masculino',
                                        'F' => 'Femenino',
                                    ])
                                    ->native(false),

                                Forms\Components\DatePicker::make('FechaNacimiento')
                                    ->label('Fecha de Nacimiento')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now()),

                                Forms\Components\Select::make('Estado')
                                    ->required()
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

                                                    $curl = curl_init();
                                                    curl_setopt_array($curl, array(
                                                        CURLOPT_URL => 'https://api.apis.net.pe/v1/dni?numero=' . $state,
                                                        CURLOPT_RETURNTRANSFER => true,
                                                        CURLOPT_SSL_VERIFYPEER => 0,
                                                        CURLOPT_ENCODING => '',
                                                        CURLOPT_MAXREDIRS => 2,
                                                        CURLOPT_TIMEOUT => 10,
                                                        CURLOPT_FOLLOWLOCATION => true,
                                                        CURLOPT_CUSTOMREQUEST => 'GET',
                                                        CURLOPT_HTTPHEADER => array(
                                                            'Referer: https://apis.net.pe/consulta-dni-api',
                                                            'Authorization: Bearer ' . $token
                                                        ),
                                                    ));

                                                    $response = curl_exec($curl);
                                                    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                                                    curl_close($curl);

                                                    if ($httpCode == 200) {
                                                        $persona = json_decode($response);

                                                        if (isset($persona->nombres) && isset($persona->apellidoPaterno) && isset($persona->apellidoMaterno)) {
                                                            $nombreCompleto = $persona->nombres . ' ' . $persona->apellidoPaterno . ' ' . $persona->apellidoMaterno;
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
                                                        throw new \Exception('Error en la consulta');
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
                                    ->label('Nombres y Apellidos del Cónyuge')
                                    ->placeholder('Se llenará automáticamente con RENIEC'),
                            ]),
                    ])
                    ->collapsible()
                    ->visible(),

                Forms\Components\Section::make('Domicilio')
                    ->schema([
                        Forms\Components\Textarea::make('Domicilio')
                            ->rows(3)
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
                                    ->options(
                                        Tasa::where('Activo', 1)
                                            ->get()
                                            ->mapWithKeys(fn($tasa) => [
                                                $tasa->TasaID => "{$tasa->Nombre} - {$tasa->Valor}%"
                                            ])
                                    )
                                    ->searchable()
                                    ->native(false),

                                Forms\Components\Select::make('negocio.Calificacion')
                                    ->required()
                                    ->label('Calificación')
                                    ->options([
                                        'MALO' => 'Malo',
                                        'REGULAR' => 'Regular',
                                        'BUENO' => 'Bueno',
                                    ])
                                    ->native(false)
                                    ->placeholder('Seleccione'),
                            ]),

                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\Select::make('PromotorCobradorID')
                                    ->required()
                                    ->label('Promotor/Cobrador')
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
                                    ->options(Ciudad::where('Activo', 1)->pluck('Nombre', 'CiudadID'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set) => $set('negocio.ZonaID', null)),

                                Forms\Components\Select::make('negocio.ZonaID')
                                    ->required()
                                    ->label('Zona')
                                    ->options(
                                        fn(Get $get) =>
                                        Zona::where('CiudadID', $get('negocio.CiudadID'))
                                            ->where('Activo', 1)
                                            ->pluck('Nombre', 'ZonaID')
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn(Get $get) => !$get('negocio.CiudadID')),
                            ]),

                        Forms\Components\Textarea::make('negocio.DireccionNegocio')
                            ->label('Dirección del Negocio')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Dirección completa del negocio'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('negocio.Antiguedad')
                                    ->label('Antigüedad (años)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->suffix('años'),

                                Forms\Components\Select::make('negocio.GiroID')
                                    ->required()
                                    ->label('Giro')
                                    ->options(
                                        Giro::where('Activo', 1)
                                            ->get()
                                            ->mapWithKeys(fn($giro) => [
                                                $giro->GiroID => "{$giro->Codigo} - {$giro->Descripcion}"
                                            ])
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set) => $set('negocio.SubGiroID', null)),

                                Forms\Components\Select::make('negocio.SubGiroID')
                                    ->label('Sub Giro')
                                    ->options(
                                        fn(Get $get) =>
                                        SubGiro::where('GiroID', $get('negocio.GiroID'))
                                            ->where('Activo', 1)
                                            ->pluck('Descripcion', 'SubGiroID')
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn(Get $get) => !$get('negocio.GiroID')),
                            ]),

                        Forms\Components\Repeater::make('negocio.telefonos')
                            ->label('Teléfonos del Negocio')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('Telefono')
                                            ->label('Número de Teléfono')
                                            ->required()
                                            ->tel()
                                            ->maxLength(20)
                                            ->placeholder('Ej: 987654321'),

                                        Forms\Components\Select::make('TipoTelefono')
                                            ->label('Tipo')
                                            ->options([
                                                'PRINCIPAL' => 'Principal',
                                                'SECUNDARIO' => 'Secundario',
                                                'ALTERNATIVO' => 'Alternativo',
                                            ])
                                            ->default('PRINCIPAL')
                                            ->native(false),

                                        Forms\Components\TextInput::make('Observacion')
                                            ->label('Observación')
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
                                    ->label('Capital Manifestado por el Cliente')
                                    ->numeric()
                                    ->placeholder('Ej: 2000.00')
                                    ->helperText('Monto que el cliente indica como capital'),

                                Forms\Components\TextInput::make('analisis_economico.CapitalEstimado')
                                    ->required()
                                    ->label('Capital Estimado por el Jefe de Oficina')
                                    ->numeric()
                                    ->placeholder('Ej: 4000.00')
                                    ->helperText('Estimación del jefe de oficina'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('analisis_economico.VentaManifestadaMin')
                                    ->required()
                                    ->label('Venta Manifestada Mínima')
                                    ->numeric()
                                    ->placeholder('Ej: 500.00')
                                    ->helperText('Venta mínima declarada'),

                                Forms\Components\TextInput::make('analisis_economico.VentaManifestadaMax')
                                    ->required()
                                    ->label('Venta Manifestada Máxima')
                                    ->numeric()
                                    ->placeholder('Ej: 800.00')
                                    ->helperText('Venta máxima declarada'),

                                Forms\Components\TextInput::make('analisis_economico.VentaEstimada')
                                    ->required()
                                    ->label('Venta Estimada por Jefe de Oficina')
                                    ->numeric()
                                    ->placeholder('Ej: 400.00')
                                    ->helperText('Estimación del jefe de oficina'),
                            ]),

                        Forms\Components\TextInput::make('analisis_economico.MontoMaxRecomendado')
                            ->required()
                            ->label('Monto Máximo Recomendado')
                            ->numeric()
                            ->step(0.01)
                            ->placeholder('Ej: 5000.00')
                            ->helperText('Monto máximo que se recomienda prestar a este cliente'),
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
                                                    return $doc ? '📄 ' . $doc->NombreOriginal : '❌ Sin archivo';
                                                }
                                                return '📄 Nuevo archivo';
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
                                                    return $doc ? '📄 ' . $doc->NombreOriginal : '❌ Sin archivo';
                                                }
                                                return '📄 Nuevo archivo';
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
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->icon('heroicon-m-identification') 
                    ->iconColor('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Nombres y Apellidos')
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
                    ->getStateUsing(fn($record) => optional($record->negocio)->telefonos->pluck('Telefono')->implode(', '))
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\Filter::make('NombresApellidos')
                    ->label('Nombres y Apellidos del Cliente')
                    ->form([
                        Forms\Components\TextInput::make('NombresApellidos')
                            ->placeholder('Buscar por nombre o apellido'),
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
                    ->attribute('negocio.CiudadID'),

                Tables\Filters\SelectFilter::make('ZonaID')
                    ->label('Zona')
                    ->options(
                        fn() =>
                        Zona::where('Activo', 1)->pluck('Nombre', 'ZonaID')
                    )
                    ->searchable()
                    ->attribute('negocio.ZonaID'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver')
                        ->visible(fn () => !auth()->user()?->hasRole('Promotor Cobrador')),
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->visible(fn () => \App\Models\AperturaCierreDia::estaAbierto()),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn () => \App\Models\AperturaCierreDia::estaAbierto())
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

    public static function canCreate(): bool
    {
        // Los Promotores Cobradores NO pueden crear clientes
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            return false;
        }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
            \Filament\Notifications\Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden realizar operaciones. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();
            return false;
        }
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Los Promotores Cobradores NO pueden editar clientes
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            return false;
        }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
            \Filament\Notifications\Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden realizar operaciones. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();
            return false;
        }
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Los Promotores Cobradores NO pueden eliminar clientes
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            return false;
        }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
            \Filament\Notifications\Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden eliminar registros. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();
            return false;
        }
        return true;
    }
}