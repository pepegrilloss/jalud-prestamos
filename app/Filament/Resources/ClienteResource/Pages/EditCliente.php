<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Filament\Forms;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Botón destacado para Análisis Económico
            Actions\Action::make('analisis_economico')
                ->label(fn () => $this->record->analisisEconomico 
                    ? 'Ver/Actualizar Análisis Económico' 
                    : '⚠️ Registrar Análisis Económico (OBLIGATORIO)')
                ->icon(fn () => $this->record->analisisEconomico 
                    ? 'heroicon-o-document-chart-bar' 
                    : 'heroicon-o-exclamation-triangle')
                ->color(fn () => $this->record->analisisEconomico 
                    ? 'info' 
                    : 'danger')
                ->modalHeading(fn () => $this->record->analisisEconomico 
                    ? 'Ver/Actualizar Análisis Económico' 
                    : 'Registrar Análisis Económico')
                ->modalDescription('Cliente: ' . $this->record->NombresApellidos . ' (DNI: ' . $this->record->DNI . ')')
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
                                        ->live(),
                                    
                                    Forms\Components\TextInput::make('CapitalEstimado')
                                        ->label('Capital Estimado por el Jefe de Oficina')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->placeholder('Ej: 4000.00')
                                        ->helperText('Estimación del jefe de oficina')
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
                                        ->live(),
                                    
                                    Forms\Components\TextInput::make('VentaManifestadaMax')
                                        ->label('Venta Manifestada Máxima  por el cliente')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->placeholder('Ej: 800.00')
                                        ->live()
                                        ->rules([
                                            fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
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
                                ]),

                            Forms\Components\TextInput::make('MontoMaxRecomendado')
                                ->label('Monto Máximo Recomendado')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->placeholder('Ej: 5000.00')
                                ->helperText('Monto máximo que se recomienda prestar a este cliente')
                                ->live(),

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
                                    return 'Complete los campos para ver el resumen';
                                })
                                ->columnSpanFull(),
                        ]),
                ])
                ->fillForm(fn () => [
                    'CapitalManifestado' => $this->record->analisisEconomico?->CapitalManifestado ?? 0,
                    'CapitalEstimado' => $this->record->analisisEconomico?->CapitalEstimado ?? 0,
                    'VentaManifestadaMin' => $this->record->analisisEconomico?->VentaManifestadaMin ?? 0,
                    'VentaManifestadaMax' => $this->record->analisisEconomico?->VentaManifestadaMax ?? 0,
                    'VentaEstimada' => $this->record->analisisEconomico?->VentaEstimada ?? 0,
                    'MontoMaxRecomendado' => $this->record->analisisEconomico?->MontoMaxRecomendado ?? 0,
                ])
                ->action(function (array $data) {
                    try {
                        // Desactivar análisis anteriores
                        \App\Models\AnalisisEconomico::where('ClienteID', $this->record->ClienteID)
                            ->where('Activo', 1)
                            ->update([
                                'Activo' => 0,
                                'FechaModificacion' => now(),
                                'UsuarioModificacion' => auth()->user()->name ?? 'Sistema',
                            ]);

                        // Crear nuevo análisis
                        \App\Models\AnalisisEconomico::create([
                            'ClienteID' => $this->record->ClienteID,
                            'CapitalManifestado' => $data['CapitalManifestado'],
                            'CapitalEstimado' => $data['CapitalEstimado'],
                            'VentaManifestadaMin' => $data['VentaManifestadaMin'],
                            'VentaManifestadaMax' => $data['VentaManifestadaMax'],
                            'VentaEstimada' => $data['VentaEstimada'],
                            'MontoMaxRecomendado' => $data['MontoMaxRecomendado'],
                            'UsuarioAnalisis' => auth()->user()->name ?? 'Sistema',
                            'FechaAnalisis' => now(),
                            'Activo' => 1,
                        ]);
 
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('✅ Análisis Económico Registrado')
                            ->body('El análisis económico ha sido guardado correctamente.')
                            ->duration(5000)
                            ->send();

                        // Refrescar la página
                        redirect()->to($this->getResource()::getUrl('edit', ['record' => $this->record]));

                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('❌ Error al guardar')
                            ->body('Ocurrió un error: ' . $e->getMessage())
                            ->persistent()
                            ->send();
                    }
                })
                ->modalSubmitActionLabel('💾 Guardar Análisis')
                ->modalCancelActionLabel('Cancelar'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar datos del negocio y teléfonos
        $negocio = $this->record->negocio;
        
        if ($negocio) {
            $data['negocio'] = [
                'DireccionNegocio' => $negocio->DireccionNegocio,
                'Antiguedad' => $negocio->Antiguedad,
                'GiroID' => $negocio->GiroID,
                'SubGiroID' => $negocio->SubGiroID,
                'Ubicacion' => $negocio->Ubicacion,
                'CiudadID' => $negocio->CiudadID,
                'ZonaID' => $negocio->ZonaID,
                'Calificacion' => $negocio->Calificacion,
                'telefonos' => $negocio->telefonos->map(function ($tel) {
                    return [
                        'Telefono' => $tel->Telefono,
                        'TipoTelefono' => $tel->TipoTelefono,
                        'Observacion' => $tel->Observacion,
                    ];
                })->toArray(),
            ];
        }
        
        // Cargar documentos existentes - Mostrar solo el nombre del archivo, no la ruta
        $docDNI = $this->record->getDocumentoDNI();
        $docRecibo = $this->record->getDocumentoReciboServicio();
        
        // No cargar la ruta, dejar vacío para que el usuario pueda cambiar
        $data['documentos'] = [
            'dni' => null,
            'recibo_servicio' => null,
        ];
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['UsuarioModificacion'] = auth()->user()->name ?? 'Sistema';
        $data['FechaModificacion'] = now();
        
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Separar datos del negocio y documentos
        $negocioData = $data['negocio'] ?? [];
        $telefonosData = $negocioData['telefonos'] ?? [];
        $documentosData = $data['documentos'] ?? [];
        
        // Remover datos que no pertenecen a la tabla Cliente
        unset($data['negocio'], $data['documentos']);
        
        // Asegurar que se guarden los datos de auditoría
        $data['UsuarioModificacion'] = auth()->user()->name ?? 'Sistema';
        $data['FechaModificacion'] = now();
        
        // Actualizar el cliente
        $record->update($data);
        
        // Actualizar o crear el negocio
        if (!empty($negocioData)) {
            unset($negocioData['telefonos']);
            $negocioData['FechaModificacion'] = now();
            
            $negocio = $record->negocio;
            
            if ($negocio) {
                $negocio->update($negocioData);
            } else {
                $negocioData['ClienteID'] = $record->ClienteID;
                $negocioData['FechaCreacion'] = now();
                $negocio = \App\Models\Negocio::create($negocioData);
            }
            
            // Eliminar teléfonos existentes y crear los nuevos
            \App\Models\TelefonoNegocio::where('NegocioID', $negocio->NegocioID)->delete();
            
            if (!empty($telefonosData)) {
                foreach ($telefonosData as $telefono) {
                    if (!empty($telefono['Telefono'])) {
                        \App\Models\TelefonoNegocio::create([
                            'NegocioID' => $negocio->NegocioID,
                            'Telefono' => $telefono['Telefono'],
                            'TipoTelefono' => $telefono['TipoTelefono'] ?? 'PRINCIPAL',
                            'Observacion' => $telefono['Observacion'] ?? null,
                            'FechaCreacion' => now(),
                        ]);
                    }
                }
            }
        }
        
        // Actualizar documentos
        $this->actualizarDocumentos($record->ClienteID, $documentosData);
        
        return $record;
    }

    protected function actualizarDocumentos(int $clienteID, array $documentos): void
    {
        $usuario = auth()->user()->name ?? 'Sistema';
        
        // Actualizar DNI
        if (!empty($documentos['dni'])) {
            $docExistente = \App\Models\DocumentoCliente::where('ClienteID', $clienteID)
                ->where('TipoDocumento', 'DNI')
                ->first();
            
            // Si cambió el archivo
            if (!$docExistente || $docExistente->RutaArchivo !== $documentos['dni']) {
                // Eliminar documento anterior si existe
                if ($docExistente) {
                    if (Storage::disk('public')->exists($docExistente->RutaArchivo)) {
                        Storage::disk('public')->delete($docExistente->RutaArchivo);
                    }
                    $docExistente->delete();
                }
                
                // Crear nuevo documento
                $rutaArchivo = $documentos['dni'];
                $tamanio = Storage::disk('public')->exists($rutaArchivo) 
                    ? Storage::disk('public')->size($rutaArchivo) 
                    : null;
                
                \App\Models\DocumentoCliente::create([
                    'ClienteID' => $clienteID,
                    'TipoDocumento' => 'DNI',
                    'RutaArchivo' => $rutaArchivo,
                    'NombreOriginal' => basename($rutaArchivo),
                    'TamanioArchivo' => $tamanio,
                    'Extension' => pathinfo($rutaArchivo, PATHINFO_EXTENSION),
                    'UsuarioRegistro' => $usuario,
                    'FechaCreacion' => now(),
                ]);
            }
        }
        
        // Actualizar Recibo de Servicio
        if (!empty($documentos['recibo_servicio'])) {
            $docExistente = \App\Models\DocumentoCliente::where('ClienteID', $clienteID)
                ->where('TipoDocumento', 'RECIBO_SERVICIO')
                ->first();
            
            // Si cambió el archivo
            if (!$docExistente || $docExistente->RutaArchivo !== $documentos['recibo_servicio']) {
                // Eliminar documento anterior si existe
                if ($docExistente) {
                    if (Storage::disk('public')->exists($docExistente->RutaArchivo)) {
                        Storage::disk('public')->delete($docExistente->RutaArchivo);
                    }
                    $docExistente->delete();
                }
                
                // Crear nuevo documento
                $rutaArchivo = $documentos['recibo_servicio'];
                $tamanio = Storage::disk('public')->exists($rutaArchivo) 
                    ? Storage::disk('public')->size($rutaArchivo) 
                    : null;
                
                \App\Models\DocumentoCliente::create([
                    'ClienteID' => $clienteID,
                    'TipoDocumento' => 'RECIBO_SERVICIO',
                    'RutaArchivo' => $rutaArchivo,
                    'NombreOriginal' => basename($rutaArchivo),
                    'TamanioArchivo' => $tamanio,
                    'Extension' => pathinfo($rutaArchivo, PATHINFO_EXTENSION),
                    'UsuarioRegistro' => $usuario,
                    'FechaCreacion' => now(),
                ]);
            }
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Cliente actualizado exitosamente';
    }
}