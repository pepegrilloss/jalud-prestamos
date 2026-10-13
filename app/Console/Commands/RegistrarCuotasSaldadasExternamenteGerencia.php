<?php

namespace App\Console\Commands;

use App\Models\Log;
use App\Models\PrestamoBancario;
use App\Services\PrestamoBancarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RegistrarCuotasSaldadasExternamenteGerencia extends Command
{
    protected $signature = 'gerencia:registrar-cuotas-saldadas-externamente
        {--prestamo=* : ID del préstamo y última cuota a saldar, formato ID:CUOTA; puede repetirse}
        {--usuario-id= : Usuario responsable, obligatorio para aplicar}
        {--aplicar : Registra las cuotas como saldadas fuera del sistema}
        {--confirmar= : Confirmación literal REGISTRAR-CUOTAS-GERENCIA-2026}';

    protected $description = 'Marca cuotas ya pagadas fuera del sistema sin generar movimientos ni cambiar saldos de Gerencia';

    private const CONFIRMACION = 'REGISTRAR-CUOTAS-GERENCIA-2026';

    public function handle(PrestamoBancarioService $service): int
    {
        try {
            $selecciones = $this->leerSelecciones();
            $sedeId = $this->sedeGerenciaId();
            $usuarioId = $this->option('aplicar') ? $this->validarAplicacion() : null;

            $resultado = $this->option('aplicar')
                ? DB::transaction(fn (): array => $this->aplicar($selecciones, $service, $sedeId, $usuarioId))
                : $this->previsualizar($selecciones, $service);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Préstamo ID', 'Cliente', 'Prestamista', 'Monto', 'Desembolso', 'Cuotas', 'Importe referencial', 'Resultado'],
            $resultado['filas'],
        );

        $this->line('No se crean pagos ni movimientos. No se modifican Caja Gerencia ni saldos bancarios.');
        $this->line('No se inventan ni se modifican fechas reales de pago.');

        if (! $this->option('aplicar')) {
            $this->warn('Vista previa solamente. Revise que cada préstamo corresponda al solicitado.');
            $this->line('Para aplicar: agregue --aplicar --usuario-id=ID --confirmar='.self::CONFIRMACION);

            return self::SUCCESS;
        }

        $this->info('Registro histórico completado. Cuotas modificadas: '.$resultado['actualizadas'].'.');

        return self::SUCCESS;
    }

    /** @return array<int, array{prestamo_id: int, hasta_cuota: int}> */
    private function leerSelecciones(): array
    {
        $opciones = $this->option('prestamo');
        if (! is_array($opciones) || $opciones === []) {
            throw new RuntimeException('Indique al menos un --prestamo=ID:CUOTA.');
        }

        $selecciones = [];
        foreach ($opciones as $opcion) {
            if (! preg_match('/^(\d+):(\d+)$/', (string) $opcion, $coincidencias)) {
                throw new RuntimeException("Formato inválido '{$opcion}'. Use --prestamo=ID:CUOTA.");
            }

            $prestamoId = (int) $coincidencias[1];
            $hastaCuota = (int) $coincidencias[2];
            if ($prestamoId <= 0 || $hastaCuota <= 0 || isset($selecciones[$prestamoId])) {
                throw new RuntimeException('Los IDs deben ser positivos y no se puede repetir un préstamo.');
            }

            $selecciones[$prestamoId] = ['prestamo_id' => $prestamoId, 'hasta_cuota' => $hastaCuota];
        }

        return array_values($selecciones);
    }

    private function sedeGerenciaId(): int
    {
        $sedeId = DB::table('Sede')
            ->whereRaw('LOWER(Nombre) LIKE ?', ['%gerencia%'])
            ->orderBy('SedeID')
            ->value('SedeID');

        if (! $sedeId) {
            throw new RuntimeException('No se encontró la sede Gerencia para auditar la operación.');
        }

        return (int) $sedeId;
    }

    private function validarAplicacion(): int
    {
        $usuarioId = $this->option('usuario-id');
        if (! is_numeric($usuarioId)
            || (int) $usuarioId <= 0
            || ! DB::table('users')->where('id', (int) $usuarioId)->exists()) {
            throw new RuntimeException('Para aplicar indique --usuario-id con un usuario existente.');
        }

        if ($this->option('confirmar') !== self::CONFIRMACION) {
            throw new RuntimeException('La confirmación no coincide. Debe indicar --confirmar='.self::CONFIRMACION);
        }

        return (int) $usuarioId;
    }

    private function previsualizar(array $selecciones, PrestamoBancarioService $service): array
    {
        $filas = [];
        foreach ($selecciones as $seleccion) {
            $plan = $service->previsualizarCuotasSaldadasHistoricamente(
                $seleccion['prestamo_id'],
                $seleccion['hasta_cuota'],
            );
            $filas[] = $this->fila($plan, $seleccion['hasta_cuota']);
        }

        return ['filas' => $filas, 'actualizadas' => 0];
    }

    private function aplicar(array $selecciones, PrestamoBancarioService $service, int $sedeId, int $usuarioId): array
    {
        $filas = [];
        $actualizadas = 0;

        foreach ($selecciones as $seleccion) {
            $plan = $service->registrarCuotasSaldadasHistoricamente(
                PrestamoBancario::query()->findOrFail($seleccion['prestamo_id']),
                $seleccion['hasta_cuota'],
            );

            if (! $plan['ya_registradas']) {
                foreach ($plan['cuotas'] as $cuota) {
                    Log::registrar(
                        'ACTUALIZAR',
                        'CuotaPrestamoBancario',
                        (int) $cuota->CuotaPrestamoBancarioID,
                        ['Estado' => 'PENDIENTE', 'FechaPago' => $cuota->FechaPago?->toDateString()],
                        [
                            'Estado' => 'SALDADA_HISTORICA',
                            'FechaPago' => $cuota->FechaPago?->toDateString(),
                            'PrestamoBancarioID' => $seleccion['prestamo_id'],
                            'NumeroCuota' => (int) $cuota->Numero,
                            'Motivo' => 'Pago realizado fuera del sistema; sin movimiento de tesorería',
                        ],
                        $sedeId,
                        $usuarioId,
                    );
                    $actualizadas++;
                }
            }

            $filas[] = $this->fila($plan, $seleccion['hasta_cuota']);
        }

        return ['filas' => $filas, 'actualizadas' => $actualizadas];
    }

    private function fila(array $plan, int $hastaCuota): array
    {
        $prestamo = $plan['prestamo'];
        $cuotas = $plan['cuotas'];

        return [
            $prestamo->PrestamoBancarioID,
            $prestamo->Cliente,
            $prestamo->NombreBanco,
            'S/ '.number_format((float) $prestamo->MontoPrestamo, 2),
            $prestamo->FechaDesembolso?->format('d/m/Y'),
            '1-'.$hastaCuota.' ('.$cuotas->count().')',
            'S/ '.number_format((float) $cuotas->sum('MontoCuota'), 2),
            $plan['ya_registradas'] ? 'Ya estaban saldadas históricamente' : 'Pendientes; listas para registrar',
        ];
    }
}
