<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use App\Models\AnalisisEconomico;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateCliente extends CreateRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['UsuarioRegistro'] = auth()->user()->name ?? 'Sistema';
        
        // Inyectar fecha abierta si no está seteada
        if (!isset($data['FechaRegistro'])) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $data['FechaRegistro'] = $fechaAbierta ? $fechaAbierta->startOfDay() : now();
        }
        
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Separar datos del negocio, documentos y análisis económico
        $negocioData = $data['negocio'] ?? [];
        $telefonosData = $negocioData['telefonos'] ?? [];
        $documentosData = $data['documentos'] ?? [];
        $analisisData = $data['analisis_economico'] ?? [];
        
        // Remover datos que no pertenecen a la tabla Cliente
        unset($data['negocio'], $data['documentos'], $data['analisis_economico']);
        
        // Crear el cliente
        $cliente = static::getModel()::create($data);
        
        // Crear el negocio si hay datos
        if (!empty($negocioData)) {
            unset($negocioData['telefonos']);
            $negocioData['ClienteID'] = $cliente->ClienteID;
            $negocioData['FechaCreacion'] = now();
            
            $negocio = \App\Models\Negocio::create($negocioData);
            
            // Crear los teléfonos
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
        
        // Guardar documentos
        $this->guardarDocumentos($cliente->ClienteID, $documentosData);
        
        // Guardar análisis económico
        $this->guardarAnalisisEconomico($cliente->ClienteID, $analisisData);
        
        return $cliente;
    }

    protected function guardarDocumentos(int $clienteID, array $documentos): void
    {
        $usuario = auth()->user()->name ?? 'Sistema';
        
        // Guardar DNI
        if (!empty($documentos['dni'])) {
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
        
        // Guardar Recibo de Servicio
        if (!empty($documentos['recibo_servicio'])) {
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

    protected function guardarAnalisisEconomico(int $clienteID, array $analisis): void
    {
        // Guardar análisis económico si hay datos
        if (!empty($analisis['CapitalManifestado']) || !empty($analisis['CapitalEstimado']) || !empty($analisis['MontoMaxRecomendado'])) {
            AnalisisEconomico::create([
                'ClienteID' => $clienteID,
                'CapitalManifestado' => $analisis['CapitalManifestado'] ?? 0,
                'CapitalEstimado' => $analisis['CapitalEstimado'] ?? 0,
                'VentaManifestadaMin' => $analisis['VentaManifestadaMin'] ?? 0,
                'VentaManifestadaMax' => $analisis['VentaManifestadaMax'] ?? 0,
                'VentaEstimada' => $analisis['VentaEstimada'] ?? 0,
                'MontoMaxRecomendado' => $analisis['MontoMaxRecomendado'] ?? 0,
                'UsuarioAnalisis' => auth()->user()->name ?? 'Sistema',
                'FechaAnalisis' => now(),
                'Activo' => 1,
            ]);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cliente registrado exitosamente';
    }
}
