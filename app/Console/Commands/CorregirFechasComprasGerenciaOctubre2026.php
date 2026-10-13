<?php

namespace App\Console\Commands;

use App\Models\Log;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CorregirFechasComprasGerenciaOctubre2026 extends Command
{
    protected $signature = 'gerencia:corregir-fechas-compras-octubre-2026
                            {--compra=* : ID de cada compra a corregir}
                            {--usuario-id= : Usuario responsable, obligatorio para aplicar}
                            {--aplicar : Corrige la fecha contable de los egresos validados}
                            {--confirmar= : Confirmación literal APLICAR-COMPRAS-GERENCIA-2026-10-02}';

    protected $description = 'Previsualiza y corrige los egresos de compras de Gerencia registradas en la apertura del 02/10/2026';

    private const FECHA = '2026-10-02';

    private const CONFIRMACION = 'APLICAR-COMPRAS-GERENCIA-2026-10-02';

    private const COMPRAS = [
        '2026-09-24|351.88',
        '2026-09-22|590.08',
    ];

    public function handle(): int
    {
        try {
            $ids = collect($this->option('compra'));
            if ($ids->isEmpty() || $ids->contains(fn ($id) => ! ctype_digit((string) $id) || (int) $id <= 0)) {
                throw new RuntimeException('Indique los IDs de las compras con --compra=ID.');
            }

            $ids = $ids->map(fn ($id) => (int) $id)->unique()->values();
            if ($ids->count() !== 2) {
                throw new RuntimeException('Indique exactamente los dos IDs de las compras a corregir.');
            }

            $usuarioId = null;
            if ($this->option('aplicar')) {
                $usuarioId = $this->validarAplicacion();
            }

            $resultado = DB::transaction(function () use ($ids, $usuarioId): array {
                $sedeId = DB::table('Sede')
                    ->whereRaw('LOWER(Nombre) LIKE ?', ['%gerencia%'])
                    ->orderBy('SedeID')
                    ->value('SedeID');

                if (! $sedeId) {
                    throw new RuntimeException('No se encontró la sede Gerencia.');
                }

                $filas = [];
                $actualizados = 0;
                $comprobantes = [];

                foreach ($ids as $id) {
                    $compra = DB::table('Compra')->where('CompraID', $id);
                    if ($usuarioId) {
                        $compra->lockForUpdate();
                    }
                    $compra = $compra->first();

                    if (! $compra
                        || (int) $compra->SedeID !== (int) $sedeId
                        || Carbon::parse($compra->FechaCreacion)->toDateString() !== self::FECHA
                        || $compra->TipoCompra !== 'CONTADO'
                        || $compra->EstadoPago !== 'PAGADO'
                        || ! $compra->Activo
                        || ! in_array($compra->OrigenTesoreriaTipo, ['CAJA_GERENCIA', 'CUENTA_BANCARIA'], true)) {
                        throw new RuntimeException("La compra #{$id} no coincide con una compra pagada de Gerencia creada el 02/10/2026.");
                    }

                    $query = DB::table('tesoreria_movimientos')
                        ->where('CompraID', $id)
                        ->where('Tipo', 'EGRESO_COMPRA');
                    if ($usuarioId) {
                        $query->lockForUpdate();
                    }
                    $movimientos = $query->get();
                    if ($movimientos->count() !== 1) {
                        throw new RuntimeException("La compra #{$id} debe tener exactamente un egreso original.");
                    }

                    $movimiento = $movimientos->first();
                    $fechaEmision = Carbon::parse($compra->FechaEmision)->toDateString();
                    $comprobantes[] = $fechaEmision.'|'.number_format((float) $compra->Total, 2, '.', '');
                    if (round((float) $movimiento->Monto, 2) !== round((float) $compra->Total, 2)
                        || $movimiento->OrigenTipo !== $compra->OrigenTesoreriaTipo
                        || (int) $movimiento->CuentaOrigenID !== (int) $compra->CuentaTesoreriaID
                        || $movimiento->DestinoTipo !== 'COMPRA'
                        || ! in_array((string) $movimiento->FechaContable, [$fechaEmision, self::FECHA], true)) {
                        throw new RuntimeException("El egreso de la compra #{$id} no coincide con su comprobante y origen de pago.");
                    }

                    $fechaAnterior = (string) $movimiento->FechaContable;
                    $filas[] = [
                        $id, $compra->Numero, 'S/ '.number_format((float) $compra->Total, 2),
                        $fechaEmision, $movimiento->MovimientoTesoreriaID, $fechaAnterior, self::FECHA,
                    ];

                    if (! $usuarioId || $fechaAnterior === self::FECHA) {
                        continue;
                    }

                    $afectados = DB::table('tesoreria_movimientos')
                        ->where('MovimientoTesoreriaID', $movimiento->MovimientoTesoreriaID)
                        ->where('FechaContable', $fechaAnterior)
                        ->update(['FechaContable' => self::FECHA]);
                    if ($afectados !== 1) {
                        throw new RuntimeException("El egreso de la compra #{$id} cambió durante la corrección.");
                    }

                    Log::registrar(
                        'ACTUALIZAR', 'MovimientoTesoreria',
                        (int) $movimiento->MovimientoTesoreriaID,
                        ['FechaContable' => $fechaAnterior],
                        ['FechaContable' => self::FECHA],
                        (int) $sedeId, $usuarioId,
                    );
                    $actualizados++;
                }

                sort($comprobantes);
                $esperados = self::COMPRAS;
                sort($esperados);
                if ($comprobantes !== $esperados) {
                    throw new RuntimeException('Las compras seleccionadas no coinciden con los dos comprobantes y montos reportados.');
                }

                return [$filas, $actualizados];
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        [$filas, $actualizados] = $resultado;
        $this->table(['Compra', 'Número', 'Total', 'Emisión', 'Egreso', 'Fecha actual', 'Fecha objetivo'], $filas);
        $this->line('Solo cambia FechaContable del egreso. No modifica comprobantes, saldos ni FechaMovimiento.');

        if (! $this->option('aplicar')) {
            $this->warn('Vista previa: no se modificó ningún dato.');
            $this->line('Para aplicar, agregue --aplicar --usuario-id=ID --confirmar='.self::CONFIRMACION);
        } else {
            $this->info("Egresos actualizados y auditados: {$actualizados}.");
        }

        return self::SUCCESS;
    }

    private function validarAplicacion(): int
    {
        $usuarioId = $this->option('usuario-id');
        if (! ctype_digit((string) $usuarioId)
            || (int) $usuarioId <= 0
            || ! DB::table('users')->where('id', (int) $usuarioId)->exists()) {
            throw new RuntimeException('Indique --usuario-id con un usuario existente.');
        }

        if ($this->option('confirmar') !== self::CONFIRMACION) {
            throw new RuntimeException('La confirmación no coincide. Use --confirmar='.self::CONFIRMACION);
        }

        return (int) $usuarioId;
    }
}
