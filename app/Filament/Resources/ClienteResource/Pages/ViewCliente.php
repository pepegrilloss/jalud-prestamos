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
                Components\Section::make('Información Personal')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('DNI')
                                    ->label('Dni'),
                                Components\TextEntry::make('NombresApellidos')
                                    ->label('Nombres y Apellidos')
                                    ->columnSpan(2),
                            ]),
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('Sexo')
                                    ->formatStateUsing(fn($state) => $state === 'M' ? 'Masculino' : 'Femenino'),
                                Components\TextEntry::make('FechaNacimiento')
                                    ->label('Fecha de Nacimiento')
                                    ->date('d/m/Y'),
                                Components\TextEntry::make('Estado')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'NO OBSERVADO' => 'success',
                                        'OBSERVADO' => 'warning',
                                    }),
                            ]),
                    ], ),

                Components\Section::make('Datos del Cónyuge')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('ConyugeDNI')
                                    ->label('DNI del Cónyuge')
                                    ->placeholder('No registrado'),
                                Components\TextEntry::make('ConyugeNombresApellidos')
                                    ->label('Nombres y Apellidos del Cónyuge')
                                    ->placeholder('No registrado'),
                            ]),
                    ])
                    ->collapsible()
                    ->Visible(),

                Components\Section::make('Ubicación')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('ciudad.Nombre')
                                    ->label('Ciudad'),
                                Components\TextEntry::make('zona.Nombre')
                                    ->label('Zona'),
                            ]),
                        Components\TextEntry::make('Domicilio')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Components\Section::make('Información Financiera')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('tasa.Nombre')
                                    ->label('Tasa de Interés')
                                    ->formatStateUsing(
                                        fn($record) =>
                                        $record->tasa ? "{$record->tasa->Nombre} - {$record->tasa->Valor}%" : 'No asignada'
                                    ),
                                Components\TextEntry::make('MontoMaxRecomendado')
                                    ->label('Monto Máximo Recomendado')
                                    ->money('PEN'),
                                Components\TextEntry::make('promotorCobrador.Descripcion')
                                    ->label('Promotor/Cobrador')
                                    ->formatStateUsing(
                                        fn($record) =>
                                        $record->promotorCobrador
                                        ? "{$record->promotorCobrador->Codigo} - {$record->promotorCobrador->Descripcion}"
                                        : 'No asignado'
                                    ),
                            ]),
                    ])
                    ->collapsible(),

                Components\Section::make('Información del Negocio')
                    ->schema([
                        Components\TextEntry::make('negocio.DireccionNegocio')
                            ->label('Dirección del Negocio')
                            ->columnSpanFull(),
                        Components\Grid::make(5)
                            ->schema([
                                Components\TextEntry::make('negocio.Antiguedad')
                                    ->label('Antigüedad')
                                    ->suffix(' años'),
                                Components\TextEntry::make('negocio.giro.Descripcion')
                                    ->label('Giro'),
                                Components\TextEntry::make('negocio.subGiro.Descripcion')
                                    ->label('Sub Giro'),
                                Components\TextEntry::make('negocio.Ubicacion')
                                    ->label('Ubicación')
                                    ->badge()
                                    ->color(fn($state) => match ($state) {
                                        'BUENO' => 'success',
                                        'REGULAR' => 'warning',
                                        'MALO' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                        Components\RepeatableEntry::make('negocio.telefonos')
                            ->label('Teléfonos')
                            ->schema([
                                Components\TextEntry::make('Telefono')
                                    ->label('Teléfono'),
                                Components\TextEntry::make('TipoTelefono')
                                    ->label('Tipo')
                                    ->badge(),
                                Components\TextEntry::make('Observacion')
                                    ->label('Observación'),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible()
                    ->visible(fn($record) => $record->negocio !== null),

                Components\Section::make('Documentación')
                    ->schema([
                        Components\Grid::make([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                // Foto DNI - Usando TextEntry con HTML para mostrar imagen
                                Components\TextEntry::make('foto_dni')
                                    ->label('Foto del DNI')
                                    ->state(function () use ($docDNI) {
                                        if (!$docDNI || !$docDNI->RutaArchivo) {
                                            return 'Sin documento';
                                        }

                                        // Codificar correctamente la URL para manejar espacios y caracteres especiales
                                        $rutaPartes = explode('/', $docDNI->RutaArchivo);
                                        $rutaPartes = array_map('rawurlencode', $rutaPartes);
                                        $rutaCodificada = implode('/', $rutaPartes);
                                        $url = url('storage/' . $rutaCodificada);

                                        return new \Illuminate\Support\HtmlString('
                                            <div class="mt-2">
                                                <img src="' . $url . '" 
                                                     alt="DNI" 
                                                     class="rounded-lg shadow-lg w-full h-auto max-w-md"
                                                     style="max-height: 400px; object-fit: contain;"
                                                     onerror="this.parentElement.innerHTML=\'<p class=text-red-500>Error: No se pudo cargar la imagen.<br>URL: ' . htmlspecialchars($url) . '</p>\'">
                                            </div>
                                        ');
                                    })
                                    ->html()
                                    ->visible(fn() => $docDNI !== null)
                                    ->columnSpan(1),

                                // Recibo - Usando TextEntry con HTML para mostrar imagen
                                Components\TextEntry::make('foto_recibo')
                                    ->label('Recibo de Servicio')
                                    ->state(function () use ($docRecibo) {
                                        if (!$docRecibo || !$docRecibo->RutaArchivo) {
                                            return 'Sin documento';
                                        }

                                        // Codificar correctamente la URL para manejar espacios y caracteres especiales
                                        $rutaPartes = explode('/', $docRecibo->RutaArchivo);
                                        $rutaPartes = array_map('rawurlencode', $rutaPartes);
                                        $rutaCodificada = implode('/', $rutaPartes);
                                        $url = url('storage/' . $rutaCodificada);

                                        return new \Illuminate\Support\HtmlString('
                                            <div class="mt-2">
                                                <img src="' . $url . '" 
                                                     alt="Recibo" 
                                                     class="rounded-lg shadow-lg w-full h-auto max-w-md"
                                                     style="max-height: 400px; object-fit: contain;"
                                                     onerror="this.parentElement.innerHTML=\'<p class=text-red-500>Error: No se pudo cargar la imagen.<br>URL: ' . htmlspecialchars($url) . '</p>\'">
                                            </div>
                                        ');
                                    })
                                    ->html()
                                    ->visible(fn() => $docRecibo !== null)
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsible()
                    ->visible(fn() => $docDNI !== null || $docRecibo !== null),

                Components\Section::make('Garantías y Observaciones')
                    ->schema([
                        Components\TextEntry::make('garante.NombresApellidos')
                            ->label('Garante')
                            ->placeholder('No registrado'),
                        Components\TextEntry::make('Observaciones')
                            ->placeholder('Sin observaciones')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->Visible(),

                Components\Section::make('Información de Auditoría')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('UsuarioRegistro')
                                    ->label('Registrado por'),
                                Components\TextEntry::make('FechaRegistro')
                                    ->label('Fecha de Registro')
                                    ->dateTime('d/m/Y H:i:s'),
                            ]),
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('UsuarioModificacion')
                                    ->label('Modificado por')
                                    ->placeholder('Sin modificaciones'),
                                Components\TextEntry::make('FechaModificacion')
                                    ->label('Fecha de Modificación')
                                    ->dateTime('d/m/Y H:i:s')
                                    ->placeholder('Sin modificaciones'),
                            ]),
                    ])
                    ->collapsible()
                    ->Visible(),
            ]);
    }
}