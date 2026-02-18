<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\TelefonoNegocio;
use App\Models\DocumentoCliente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncriptarDatosSensibles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:encrypt-sensitive-data {--force : Ejecutar sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encripta datos sensibles existentes en la BD (DNI, teléfonos, nombres, dominios)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('⚠️ Esta operación encriptará todos los datos sensibles existentes. ¿Deseas continuar?')) {
            $this->info('Operación cancelada.');
            return 0;
        }

        $this->info('🔒 Iniciando encriptación de datos sensibles...');

        // 1. Encriptar datos en tabla Cliente
        $this->encriptarClientesData();

        // 2. Encriptar datos en tabla TelefonoNegocio
        $this->encriptarTelefonosData();

        // 3. Encriptar datos en tabla DocumentoCliente
        $this->encriptarDocumentosData();

        $this->info('✅ Encriptación completada exitosamente');
        $this->info('💾 Los datos sensibles están ahora protegidos en la BD');

        return 0;
    }

    /**
     * Encriptar datos de clientes
     */
    private function encriptarClientesData()
    {
        $this->info('📝 Procesando tabla Cliente...');

        $camposEncriptar = ['DNI', 'NombresApellidos', 'ConyugeDNI', 'ConyugeNombresApellidos', 'Domicilio'];
        
        foreach ($camposEncriptar as $campo) {
            $this->info("  Encriptando {$campo}...");
            
            $clientes = DB::table('Cliente')
                ->whereNotNull($campo)
                ->where(function($q) use ($campo) {
                    // Seleccionar solo registros NO encriptados (no comienzan con base64: o eyJ)
                    $q->whereNot(function($subQ) use ($campo) {
                        $subQ->whereRaw("LEFT({$campo}, 7) = 'base64:'")
                             ->orWhereRaw("LEFT({$campo}, 3) = 'eyJ'");
                    });
                })
                ->get();

            $barra = $this->output->createProgressBar(count($clientes));
            $barra->start();

            foreach ($clientes as $cliente) {
                try {
                    $valor = $cliente->{$campo};
                    $encriptado = Crypt::encryptString($valor);
                    
                    DB::table('Cliente')
                        ->where('ClienteID', $cliente->ClienteID)
                        ->update([$campo => $encriptado]);
                    
                    $barra->advance();
                } catch (\Exception $e) {
                    $this->error("\n    Error encriptando {$campo} en ClienteID {$cliente->ClienteID}: {$e->getMessage()}");
                }
            }

            $barra->finish();
            $this->newLine();
        }

        $this->info('✅ Tabla Cliente encriptada');
    }

    /**
     * Encriptar datos de teléfonos
     */
    private function encriptarTelefonosData()
    {
        $this->info('📱 Procesando tabla TelefonoNegocio...');

        $telefonos = DB::table('TelefonoNegocio')
            ->whereNotNull('Telefono')
            ->where(function($q) {
                // Seleccionar solo registros NO encriptados
                $q->whereNot(function($subQ) {
                    $subQ->whereRaw("LEFT(Telefono, 7) = 'base64:'")
                         ->orWhereRaw("LEFT(Telefono, 3) = 'eyJ'");
                });
            })
            ->get();

        $barra = $this->output->createProgressBar(count($telefonos));
        $barra->start();

        foreach ($telefonos as $telefono) {
            try {
                $encriptado = Crypt::encryptString($telefono->Telefono);
                
                DB::table('TelefonoNegocio')
                    ->where('TelefonoNegocioID', $telefono->TelefonoNegocioID)
                    ->update(['Telefono' => $encriptado]);
                
                $barra->advance();
            } catch (\Exception $e) {
                $this->error("\n    Error encriptando TelefonoNegocioID {$telefono->TelefonoNegocioID}: {$e->getMessage()}");
            }
        }

        $barra->finish();
        $this->newLine();
        $this->info('✅ Tabla TelefonoNegocio encriptada');
    }

    /**
     * Encriptar datos de documentos
     */
    private function encriptarDocumentosData()
    {
        $this->info('📄 Procesando tabla DocumentoCliente...');

        $documentos = DB::table('DocumentoCliente')
            ->whereNotNull('NombreOriginal')
            ->where(function($q) {
                // Seleccionar solo registros NO encriptados
                $q->whereNot(function($subQ) {
                    $subQ->whereRaw("LEFT(NombreOriginal, 7) = 'base64:'")
                         ->orWhereRaw("LEFT(NombreOriginal, 3) = 'eyJ'");
                });
            })
            ->get();

        $barra = $this->output->createProgressBar(count($documentos));
        $barra->start();

        foreach ($documentos as $documento) {
            try {
                $encriptado = Crypt::encryptString($documento->NombreOriginal);
                
                DB::table('DocumentoCliente')
                    ->where('DocumentoClienteID', $documento->DocumentoClienteID)
                    ->update(['NombreOriginal' => $encriptado]);
                
                $barra->advance();
            } catch (\Exception $e) {
                $this->error("\n    Error encriptando DocumentoClienteID {$documento->DocumentoClienteID}: {$e->getMessage()}");
            }
        }

        $barra->finish();
        $this->newLine();
        $this->info('✅ Tabla DocumentoCliente encriptada');
    }
}
