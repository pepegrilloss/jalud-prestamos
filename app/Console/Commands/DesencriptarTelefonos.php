<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Exception;

class DesencriptarTelefonos extends Command
{
    protected $signature = 'security:desencriptar-telefonos {--force : Ejecutar sin confirmación}';
    protected $description = 'Desencripta los teléfonos en TelefonoNegocio y revierte la columna a VARCHAR(20)';

    public function handle()
    {
        if (!$this->option('force')) {
            $this->warn('⚠️  ADVERTENCIA: Este comando desencriptará todos los teléfonos.');
            if (!$this->confirm('¿Deseas continuar?')) {
                $this->info('Operación cancelada.');
                return;
            }
        }

        $this->info('🔍 Analizando datos en TelefonoNegocio...');

        try {
            // Obtener todos los teléfonos
            $telefonos = DB::table('TelefonoNegocio')->select('TelefonoNegocioID', 'Telefono')->get();

            if ($telefonos->isEmpty()) {
                $this->info('✅ No hay registros para desencriptar.');
                return;
            }

            $this->info("Encontrados {$telefonos->count()} registros para procesar.");

            $desencriptados = 0;
            $errores = 0;
            $fallidos = [];

            foreach ($telefonos as $registro) {
                try {
                    $telefonoEncriptado = $registro->Telefono;

                    // Intentar desencriptar
                    $telefonoDesencriptado = null;
                    
                    try {
                        // Si ya está desencriptado (solo números), dejarlo como está
                        if (preg_match('/^\d+$/', $telefonoEncriptado)) {
                            $telefonoDesencriptado = $telefonoEncriptado;
                        } else {
                            // Intentar desencriptar
                            $telefonoDesencriptado = Crypt::decryptString($telefonoEncriptado);
                        }
                    } catch (Exception $e) {
                        // Si no se puede desencriptar, intentar limpiar solo números
                        $numeros = preg_replace('/[^0-9]/', '', $telefonoEncriptado);
                        if (!empty($numeros)) {
                            $telefonoDesencriptado = $numeros;
                        } else {
                            throw new Exception("No se pudo extraer número: " . substr($telefonoEncriptado, 0, 50));
                        }
                    }

                    // Validar que el teléfono desencriptado tenga longitud válida
                    if (strlen($telefonoDesencriptado) > 20) {
                        throw new Exception("Teléfono demasiado largo: " . $telefonoDesencriptado);
                    }

                    // Actualizar en BD
                    DB::table('TelefonoNegocio')
                        ->where('TelefonoNegocioID', $registro->TelefonoNegocioID)
                        ->update(['Telefono' => $telefonoDesencriptado]);

                    $desencriptados++;
                    $this->line("✓ ID {$registro->TelefonoNegocioID}: {$telefonoDesencriptado}");

                } catch (Exception $e) {
                    $errores++;
                    $fallidos[] = [
                        'id' => $registro->TelefonoNegocioID,
                        'error' => $e->getMessage()
                    ];
                    $this->error("✗ ID {$registro->TelefonoNegocioID}: " . $e->getMessage());
                }
            }

            $this->newLine();
            $this->info("📊 Resultados:");
            $this->info("  ✅ Desencriptados: {$desencriptados}");
            $this->error("  ❌ Errores: {$errores}");

            if (!empty($fallidos)) {
                $this->newLine();
                $this->warn("Registros fallidos:");
                foreach ($fallidos as $fallo) {
                    $this->line("  - ID {$fallo['id']}: {$fallo['error']}");
                }
            }

            // Revertir la columna a VARCHAR(20)
            $this->info("\n🔄 Revertiendo estructura de columna...");
            
            DB::statement('ALTER TABLE `TelefonoNegocio` MODIFY COLUMN `Telefono` VARCHAR(20) NOT NULL');
            
            $this->info("✅ Columna Telefono revertida a VARCHAR(20)");
            $this->info("\n✨ Proceso completado exitosamente.");

        } catch (Exception $e) {
            $this->error("❌ Error crítico: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
