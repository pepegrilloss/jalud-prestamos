<?php

namespace App\Filament\Resources\AperturaCierreDiaResource\Pages;

use App\Filament\Resources\AperturaCierreDiaResource;
use App\Models\AperturaCierreDia;
use App\Models\CalendarioNoMoroso;
use App\Services\AperturaCierreDiaLogger;
use App\Events\DiaAbierto;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;

class GestionarAperturaCierre extends ManageRecords
{
    protected static string $resource = AperturaCierreDiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data): Model {
                    $logger = new AperturaCierreDiaLogger();

                    // Validar contra Calendario No Moroso
                    $sedeId = auth()->user()->esAdmin() ? session('sede_activa') : auth()->user()->SedeID;
                    $fechaNoMorosa = CalendarioNoMoroso::where('Fecha', $data['Fecha'])
                        ->where('SedeID', $sedeId)
                        ->where('Activo', true)
                        ->first();

                    if ($fechaNoMorosa) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Fecha bloqueada')
                            ->body("No se puede registrar esta fecha: {$fechaNoMorosa->Descripcion}")
                            ->persistent()
                            ->send();
                        $this->halt();
                    }
                    
                    try {
                        $logger->info('[APERTURA_CIERRE] Creando nuevo registro', [
                            'fecha' => $data['Fecha'],
                            'estado' => $data['EstadoDia'],
                        ]);
                        
                        return DB::transaction(function () use ($data, $logger) {
                            // Si se intenta crear con estado ABIERTO
                            if ($data['EstadoDia'] === 'ABIERTO') {
                                // Verificar con lock que no haya otro día abierto
                                $diaAbierto = AperturaCierreDia::lockForUpdate()
                                    ->where('EstadoDia', 'ABIERTO')
                                    ->first();
                                
                                if ($diaAbierto) {
                                    $logger->error('[APERTURA_CIERRE] Bloqueado en CREATE: Otro día abierto', [
                                        'dia_abierto' => $diaAbierto->Fecha->format('d/m/Y'),
                                    ]);
                                    
                                    throw new \Exception("Ya hay un día abierto: {$diaAbierto->Fecha->format('d/m/Y')}");
                                }
                                
                                $data['FechaApertura'] = now();
                                $data['UsuarioAperturaID'] = auth()->id();
                            }
                            
                            if ($data['EstadoDia'] === 'CERRADO') {
                                $data['FechaCierre'] = now();
                                $data['UsuarioCierreID'] = auth()->id();
                            }
                            
                            $record = AperturaCierreDia::create($data);
                            
                            // Disparar evento si se crea como ABIERTO
                            if ($record->EstadoDia === 'ABIERTO') {
                                DiaAbierto::dispatch($record);
                            }
                            
                            $logger->success('[APERTURA_CIERRE] Registro creado exitosamente', [
                                'id' => $record->AperturaCierreDiaID,
                            ]);
                            
                            return $record;
                        });
                        
                    } catch (UniqueConstraintViolationException $e) {
                        $logger->error('[APERTURA_CIERRE] Constraint violation en CREATE');
                        
                        $diaAbierto = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->first();
                        
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('🔒 Error de integridad')
                            ->body($diaAbierto 
                                ? "Ya existe un día abierto: {$diaAbierto->Fecha->format('d/m/Y')}. Ciérrelo antes de abrir otro."
                                : "No se puede abrir múltiples días simultáneamente.")
                            ->persistent()
                            ->send();
                        
                        // Detener la ejecución sin lanzar excepción
                        $this->halt();
                        
                    } catch (\Exception $e) {
                        $logger->error('[APERTURA_CIERRE] Error en CREATE', [
                            'error' => $e->getMessage(),
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('❌ Error al crear')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                        
                        // Detener la ejecución sin lanzar excepción
                        $this->halt();
                    }
                }),
        ];
    }
    
    /**
     * Sobrescribir el método que maneja las actualizaciones de la tabla
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $logger = new AperturaCierreDiaLogger();
        
        try {
            $logger->info('[APERTURA_CIERRE] Actualizando desde tabla inline', [
                'record_id' => $record->getKey(),
                'estado_anterior' => $record->EstadoDia,
                'estado_nuevo' => $data['EstadoDia'] ?? null,
            ]);
            
            return DB::transaction(function () use ($record, $data, $logger) {
                // Obtener el registro con lock
                $recordLocked = AperturaCierreDia::lockForUpdate()
                    ->find($record->getKey());
                
                // Si se intenta cambiar a ABIERTO
                if (isset($data['EstadoDia']) && $data['EstadoDia'] === 'ABIERTO' && $recordLocked->EstadoDia !== 'ABIERTO') {
                    // Verificar que no haya otro día abierto
                    $diaAbierto = AperturaCierreDia::lockForUpdate()
                        ->where('EstadoDia', 'ABIERTO')
                        ->where('AperturaCierreDiaID', '!=', $record->getKey())
                        ->first();
                    
                    if ($diaAbierto) {
                        $logger->error('[APERTURA_CIERRE] Bloqueado en UPDATE: Otro día abierto', [
                            'dia_abierto' => $diaAbierto->Fecha->format('d/m/Y'),
                        ]);
                        
                        throw new \Exception("Ya hay un día abierto: {$diaAbierto->Fecha->format('d/m/Y')}");
                    }
                    
                    $data['FechaApertura'] = now();
                    $data['UsuarioAperturaID'] = auth()->id();
                    $data['FechaCierre'] = null;
                    $data['UsuarioCierreID'] = null;
                }
                
                // Si se cambia de ABIERTO a CERRADO
                if (isset($data['EstadoDia']) && $recordLocked->EstadoDia === 'ABIERTO' && $data['EstadoDia'] === 'CERRADO') {
                    $data['FechaCierre'] = now();
                    $data['UsuarioCierreID'] = auth()->id();
                }
                
                $recordLocked->update($data);
                
                $logger->success('[APERTURA_CIERRE] Registro actualizado exitosamente', [
                    'id' => $record->getKey(),
                ]);
                
                return $recordLocked->fresh();
            });
            
        } catch (UniqueConstraintViolationException $e) {
            $logger->error('[APERTURA_CIERRE] Constraint violation en UPDATE inline');
            
            $diaAbierto = AperturaCierreDia::where('EstadoDia', 'ABIERTO')
                ->where('AperturaCierreDiaID', '!=', $record->getKey())
                ->first();
            
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('🔒 No se puede abrir')
                ->body($diaAbierto 
                    ? "Ya existe un día abierto: {$diaAbierto->Fecha->format('d/m/Y')}. Ciérrelo antes de abrir otro."
                    : "No se puede abrir múltiples días simultáneamente.")
                ->persistent()
                ->send();
            
            // Retornar el registro sin cambios
            return $record->fresh();
            
        } catch (\Exception $e) {
            $logger->error('[APERTURA_CIERRE] Error en UPDATE inline', [
                'error' => $e->getMessage(),
            ]);
            
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('❌ Error al actualizar')
                ->body($e->getMessage())
                ->persistent()
                ->send();
            
            // Retornar el registro sin cambios
            return $record->fresh();
        }
    }
}