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

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');
        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='{$url}' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Crear Cliente</span>
            </div>
        ");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['UsuarioRegistro'] = auth()->user()->name ?? 'Sistema';
        
        // Inyectar fecha abierta si no está seteada (con hora actual)
        if (!isset($data['FechaRegistro'])) {
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $data['FechaRegistro'] = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
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
            $negocioData['SedeID'] = $cliente->SedeID;
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
                            'SedeID' => $cliente->SedeID,
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
            // Inyectar fecha abierta
            $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
            $fechaAnalisis = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();
            
            AnalisisEconomico::create([
                'ClienteID' => $clienteID,
                'CapitalManifestado' => $analisis['CapitalManifestado'] ?? 0,
                'CapitalEstimado' => $analisis['CapitalEstimado'] ?? 0,
                'VentaManifestadaMin' => $analisis['VentaManifestadaMin'] ?? 0,
                'VentaManifestadaMax' => $analisis['VentaManifestadaMax'] ?? 0,
                'VentaEstimada' => $analisis['VentaEstimada'] ?? 0,
                'MontoMaxRecomendado' => $analisis['MontoMaxRecomendado'] ?? 0,
                'UsuarioAnalisis' => auth()->user()->name ?? 'Sistema',
                'FechaAnalisis' => $fechaAnalisis,
                'Activo' => 1,
            ]);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cliente registrado exitosamente';
    }
}
