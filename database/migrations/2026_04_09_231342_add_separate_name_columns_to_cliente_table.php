<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar las 3 nuevas columnas
        Schema::table('Cliente', function (Blueprint $table) {
            $table->string('ApellidoPaterno', 100)->nullable()->after('NombresApellidos');
            $table->string('ApellidoMaterno', 100)->nullable()->after('ApellidoPaterno');
            $table->string('Nombres', 100)->nullable()->after('ApellidoMaterno');
        });

        // 2. Migrar datos existentes: últimas 2 palabras = apellidos, resto = nombres
        $clientes = DB::table('Cliente')->whereNotNull('NombresApellidos')->get();

        foreach ($clientes as $cliente) {
            $parts = preg_split('/\s+/', trim($cliente->NombresApellidos));
            $totalParts = count($parts);

            if ($totalParts >= 3) {
                // Últimas 2 palabras = apellidos
                $apellidoMaterno = array_pop($parts);
                $apellidoPaterno = array_pop($parts);
                $nombres = implode(' ', $parts);
            } elseif ($totalParts == 2) {
                // Solo 2 palabras: asumimos nombre + apellido
                $nombres = $parts[0];
                $apellidoPaterno = $parts[1];
                $apellidoMaterno = '';
            } else {
                // 1 sola palabra
                $nombres = $parts[0] ?? '';
                $apellidoPaterno = '';
                $apellidoMaterno = '';
            }

            // Actualizar los campos separados Y reescribir NombresApellidos en formato "Apellidos Nombres"
            $nuevoNombreCompleto = trim(
                trim($apellidoPaterno . ' ' . $apellidoMaterno) . ' ' . $nombres
            );

            DB::table('Cliente')
                ->where('ClienteID', $cliente->ClienteID)
                ->update([
                    'ApellidoPaterno' => $apellidoPaterno,
                    'ApellidoMaterno' => $apellidoMaterno,
                    'Nombres' => $nombres,
                    'NombresApellidos' => strtoupper($nuevoNombreCompleto),
                ]);
        }
    }

    public function down(): void
    {
        // Restaurar NombresApellidos al formato original (Nombres Apellidos) antes de eliminar columnas
        $clientes = DB::table('Cliente')
            ->whereNotNull('Nombres')
            ->get();

        foreach ($clientes as $cliente) {
            $original = trim($cliente->Nombres . ' ' . $cliente->ApellidoPaterno . ' ' . $cliente->ApellidoMaterno);
            DB::table('Cliente')
                ->where('ClienteID', $cliente->ClienteID)
                ->update(['NombresApellidos' => strtoupper($original)]);
        }

        Schema::table('Cliente', function (Blueprint $table) {
            $table->dropColumn(['ApellidoPaterno', 'ApellidoMaterno', 'Nombres']);
        });
    }
};
