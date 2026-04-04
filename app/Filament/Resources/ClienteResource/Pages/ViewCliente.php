<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Forms;
use Illuminate\Support\Facades\Storage;

class ViewCliente extends ViewRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Botón destacado para Análisis Económico (SOLO LECTURA)
            Actions\Action::make('analisis_economico')
                ->label(fn() => $this->record->analisisEconomico
                    ? 'Ver Análisis Económico'
                    : '⚠️ Sin Análisis Económico (OBLIGATORIO)')
                ->icon(fn() => $this->record->analisisEconomico
                    ? 'heroicon-o-document-chart-bar'
                    : 'heroicon-o-exclamation-triangle')
                ->color(fn() => $this->record->analisisEconomico
                    ? 'info'
                    : 'danger')
                ->modalHeading('Ver Análisis Económico')
                ->modalDescription('Cliente: ' . $this->record->NombresApellidos . ' (DNI: ' . $this->record->DNI . ')')
                ->modalWidth('3xl')
                ->form([
                    Forms\Components\Section::make('Parte Económica del Cliente')
                        ->description('Información económica registrada del cliente')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('CapitalManifestado')
                                        ->label('Capital Manifestado por el Cliente')
                                        ->numeric()
                                        ->step(0.01)
                                        ->disabled(),

                                    Forms\Components\TextInput::make('CapitalEstimado')
                                        ->label('Capital Estimado por el Jefe de Oficina')
                                        ->numeric()
                                        ->step(0.01)
                                        ->disabled(),
                                ]),

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('VentaManifestadaMin')
                                        ->label('Venta Manifestada Mínima por el cliente')
                                        ->numeric()
                                        ->step(0.01)
                                        ->disabled(),

                                    Forms\Components\TextInput::make('VentaManifestadaMax')
                                        ->label('Venta Manifestada Máxima por el cliente')
                                        ->numeric()
                                        ->step(0.01)
                                        ->disabled(),

                                    Forms\Components\TextInput::make('VentaEstimada')
                                        ->label('Venta Estimada por Jefe de Oficina')
                                        ->numeric()
                                        ->step(0.01)
                                        ->disabled(),
                                ]),

                            Forms\Components\TextInput::make('MontoMaxRecomendado')
                                ->label('Monto Máximo Recomendado (MMR)')
                                ->numeric()
                                ->step(0.01)
                                ->disabled()
                                ->columnSpanFull(),

                            // Resumen visual
                            Forms\Components\Placeholder::make('resumen')
                                ->label('📋 Resumen del Análisis')
                                ->content(function (Forms\Get $get) {
                                    $capManifestado = $get('CapitalManifestado') ?? 0;
                                    $capEstimado = $get('CapitalEstimado') ?? 0;
                                    $ventaMin = $get('VentaManifestadaMin') ?? 0;
                                    $ventaMax = $get('VentaManifestadaMax') ?? 0;
                                    $ventaEst = $get('VentaEstimada') ?? 0;

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
                                    return 'Sin análisis económico registrado';
                                })
                                ->columnSpanFull(),
                        ]),
                ])
                ->fillForm(fn() => [
                    'CapitalManifestado' => $this->record->analisisEconomico?->CapitalManifestado ?? 0,
                    'CapitalEstimado' => $this->record->analisisEconomico?->CapitalEstimado ?? 0,
                    'VentaManifestadaMin' => $this->record->analisisEconomico?->VentaManifestadaMin ?? 0,
                    'VentaManifestadaMax' => $this->record->analisisEconomico?->VentaManifestadaMax ?? 0,
                    'VentaEstimada' => $this->record->analisisEconomico?->VentaEstimada ?? 0,
                    'MontoMaxRecomendado' => $this->record->analisisEconomico?->MontoMaxRecomendado ?? 0,
                ])
                ->visible(fn() => $this->record->analisisEconomico !== null)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $docDNI = $this->record->getDocumentoDNI();
        $docRecibo = $this->record->getDocumentoReciboServicio();

        return $infolist
            ->schema([
                Components\Section::make('Perfil del Cliente')
                    ->description('Información personal básica e identificación')
                    ->icon('heroicon-m-user-circle')
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('DNI')
                                    ->label('DNI')
                                    ->icon('heroicon-m-identification')
                                    ->badge()
                                    ->color('primary')
                                    ->columnSpan(1),

                                Components\TextEntry::make('NombresApellidos')
                                    ->label('Nombres y Apellidos')
                                    ->icon('heroicon-m-user')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->columnSpan(2),

                                Components\TextEntry::make('Estado')
                                    ->label('Estado Crediticio')
                                    ->badge()
                                    ->icon(fn($state) => $state === 'NO OBSERVADO' ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle')
                                    ->color(fn(string $state): string => match ($state) {
                                        'NO OBSERVADO' => 'success',
                                        'OBSERVADO' => 'danger',
                                        default => 'warning',
                                    })
                                    ->columnSpan(1),
                            ]),
                            
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('Sexo')
                                    ->icon('heroicon-m-users')
                                    ->formatStateUsing(fn($state) => $state === 'M' ? 'Masculino' : 'Femenino'),
                                    
                                Components\TextEntry::make('FechaNacimiento')
                                    ->label('Fec. Nacimiento')
                                    ->icon('heroicon-m-cake')
                                    ->date('d/m/Y'),
                                    
                                Components\TextEntry::make('Edad') // Computado si es posible, o solo el domicilio
                                    ->label('Domicilio Actual')
                                    ->icon('heroicon-m-home')
                                    ->state(fn($record) => $record->Domicilio ?? 'No registrado')
                                    ->columnSpanFull(),
                            ])->extraAttributes(['class' => 'mt-4']),

                        Components\Fieldset::make('Información Familar Extra')
                            ->schema([
                                Components\TextEntry::make('ConyugeDNI')
                                    ->label('DNI del Cónyuge')
                                    ->icon('heroicon-m-identification')
                                    ->placeholder('No registrado'),
                                Components\TextEntry::make('ConyugeNombresApellidos')
                                    ->label('Nombres del Cónyuge')
                                    ->icon('heroicon-m-heart')
                                    ->placeholder('No registrado'),
                            ])->columns(2)->visible(fn($record) => filled($record->ConyugeDNI) || filled($record->ConyugeNombresApellidos))
                    ]),

                Components\Section::make('Ficha Operativa y Financiera')
                    ->description('Asignaciones internas y detalles de riesgo')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('tasa.Nombre')
                                    ->label('Tasa de Interés Asignada')
                                    ->icon('heroicon-m-receipt-percent')
                                    ->color('info')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn($record) =>
                                        $record->tasa ? "{$record->tasa->Nombre} - {$record->tasa->Valor}%" : 'No asignada'
                                    ),
                                Components\TextEntry::make('promotorCobrador.Descripcion')
                                    ->label('Promotor / Cobrador')
                                    ->icon('heroicon-m-user-group')
                                    ->color('success')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn($record) =>
                                        $record->promotorCobrador
                                        ? "{$record->promotorCobrador->Codigo} - {$record->promotorCobrador->Descripcion}"
                                        : 'No asignado'
                                    ),
                                Components\TextEntry::make('garante.NombresApellidos')
                                    ->label('Aval / Garante')
                                    ->icon('heroicon-m-shield-check')
                                    ->placeholder('Sin garante registrado'),
                            ]),
                    ]),

                Components\Section::make('Unidad de Negocio')
                    ->description('Datos comerciales del negocio del cliente')
                    ->icon('heroicon-m-building-storefront')
                    ->schema([
                        Components\TextEntry::make('negocio.DireccionNegocio')
                            ->label('Ubicación del Negocio')
                            ->icon('heroicon-m-map')
                            ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                            ->columnSpanFull(),

                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('negocio.Antiguedad')
                                    ->label('Tiempo Operando')
                                    ->icon('heroicon-m-calendar-days')
                                    ->badge()
                                    ->color('success')
                                    ->suffix(' años'),
                                Components\TextEntry::make('negocio.giro.Descripcion')
                                    ->label('Rubro / Giro')
                                    ->icon('heroicon-m-shopping-bag'),
                                Components\TextEntry::make('negocio.ciudad.Nombre')
                                    ->label('Ciudad')
                                    ->icon('heroicon-m-map-pin')
                                    ->placeholder('No registrada'),
                                Components\TextEntry::make('negocio.zona.Nombre')
                                    ->label('Zona Comercial')
                                    ->icon('heroicon-m-flag')
                                    ->color('primary')
                                    ->badge()
                                    ->placeholder('No registrada'),
                            ])->extraAttributes(['class' => 'mt-4']),

                        Components\Fieldset::make('Contactos Telefónicos')
                            ->schema([
                                Components\RepeatableEntry::make('negocio.telefonos')
                                    ->label('')
                                    ->schema([
                                        Components\Grid::make(3)
                                            ->schema([
                                                Components\TextEntry::make('Telefono')
                                                    ->label('Número')
                                                    ->icon('heroicon-m-phone')
                                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                                Components\TextEntry::make('TipoTelefono')
                                                    ->label('Categoría')
                                                    ->badge()
                                                    ->color('info'),
                                                Components\TextEntry::make('Observacion')
                                                    ->label('Nota')
                                                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                                                    ->placeholder('-'),
                                            ])
                                    ])->contained(true)->columns(1)
                            ])->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record->negocio !== null),

                Components\Section::make('Gestión Documental')
                    ->description('Archivos adjuntos capturados del cliente')
                    ->icon('heroicon-m-photo')
                    ->collapsed() // Optionally collapse to keep the view clean initially
                    ->schema([
                        Components\Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->schema([
                            Components\TextEntry::make('foto_dni')
                                ->label('Fotografía del DNI')
                                ->icon('heroicon-m-identification')
                                ->state(function () use ($docDNI) {
                                    if (!$docDNI || !$docDNI->RutaArchivo) {
                                        return '<div class="text-gray-400 italic">No se ha subido fotografía</div>';
                                    }
                                    $rutaPartes = explode('/', $docDNI->RutaArchivo);
                                    $rutaPartes = array_map('rawurlencode', $rutaPartes);
                                    $url = url('storage/' . implode('/', $rutaPartes));
                                    return new \Illuminate\Support\HtmlString('
                                        <div class="mt-3 p-2 border rounded-xl bg-gray-50">
                                            <img src="' . $url . '" alt="DNI" class="rounded-lg shadow-sm w-full h-auto max-w-md hover:scale-105 transition-transform duration-300" style="max-height: 250px; object-fit: cover;" onerror="this.parentElement.innerHTML=\'<p class=text-red-500>Error técnico al cargar la imagen.</p>\'">
                                        </div>
                                    ');
                                })->html(),

                            Components\TextEntry::make('foto_recibo')
                                ->label('Fotografía Formato Recibo')
                                ->icon('heroicon-m-document-text')
                                ->state(function () use ($docRecibo) {
                                    if (!$docRecibo || !$docRecibo->RutaArchivo) {
                                        return '<div class="text-gray-400 italic">No se ha subido fotografía</div>';
                                    }
                                    $rutaPartes = explode('/', $docRecibo->RutaArchivo);
                                    $rutaPartes = array_map('rawurlencode', $rutaPartes);
                                    $url = url('storage/' . implode('/', $rutaPartes));
                                    return new \Illuminate\Support\HtmlString('
                                        <div class="mt-3 p-2 border rounded-xl bg-gray-50">
                                            <img src="' . $url . '" alt="Recibo" class="rounded-lg shadow-sm w-full h-auto max-w-md hover:scale-105 transition-transform duration-300" style="max-height: 250px; object-fit: cover;" onerror="this.parentElement.innerHTML=\'<p class=text-red-500>Error técnico al cargar la imagen.</p>\'">
                                        </div>
                                    ');
                                })->html(),
                        ]),
                    ])
                    ->visible(fn() => $docDNI !== null || $docRecibo !== null),

                Components\Section::make('Bitácora y Sistema')
                    ->icon('heroicon-m-server-stack')
                    ->collapsed() // Always collapsed as it is purely administrative
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('Observaciones')
                                    ->label('Anotaciones Extras')
                                    ->icon('heroicon-m-pencil-square')
                                    ->placeholder('Sin observaciones de registro')
                                    ->columnSpanFull(),
                                Components\TextEntry::make('UsuarioRegistro')
                                    ->label('Alta por')
                                    ->icon('heroicon-m-user-plus'),
                                Components\TextEntry::make('FechaRegistro')
                                    ->label('Fec. Alta')
                                    ->icon('heroicon-m-calendar')
                                    ->dateTime('d/m/Y h:i A'),
                                Components\TextEntry::make('UsuarioModificacion')
                                    ->label('Últ. Modif. por')
                                    ->icon('heroicon-m-pencil')
                                    ->placeholder('-'),
                                Components\TextEntry::make('FechaModificacion')
                                    ->label('Fec. Modif.')
                                    ->icon('heroicon-m-calendar')
                                    ->dateTime('d/m/Y h:i A')
                                    ->placeholder('-'),
                            ]),
                    ])
            ]);
    }
}