<?php

namespace App\Console\Commands;

use App\Services\CalendarioLaboralService;
use App\Services\CreditoFechaRecalculationService;
use App\Services\FeriadoService;
use Illuminate\Console\Command;

class SincronizarFeriados extends Command
{
    protected $signature = 'feriados:sync {anio? : Año a sincronizar. Si se omite, sincroniza el año actual y el siguiente.}';

    protected $description = 'Sincroniza los feriados nacionales de Perú desde Calendarific hacia la base de datos local';

    public function handle(): int
    {
        $anio = (int) ($this->argument('anio') ?? now()->year);

        if ($this->argument('anio') === null) {
            $anios = [$anio, $anio + 1];
            $this->info('Sincronizando feriados de Perú para los años: '.implode(', ', $anios));
        } else {
            $anios = [$anio];
            $this->info("Sincronizando feriados de Perú para el año: {$anio}");
        }

        $total = 0;
        $fallos = 0;

        foreach ($anios as $a) {
            try {
                $sincronizados = FeriadoService::sincronizarAnio($a);

                if ($sincronizados > 0) {
                    $this->info("  [OK] {$a}: {$sincronizados} feriados sincronizados.");
                    $total += $sincronizados;

                    CalendarioLaboralService::clearCache();
                    $recalculo = CreditoFechaRecalculationService::recalcularAnio(
                        $a,
                        "Sincronizacion del calendario nacional de feriados {$a}"
                    );
                    $this->line(
                        "       Creditos auditados: {$recalculo['auditados']}; ".
                        "corregidos: {$recalculo['corregidos']}; errores: {$recalculo['errores']}."
                    );
                } else {
                    $existentes = count(FeriadoService::leerDeBD($a));

                    if ($existentes > 0) {
                        $this->warn("  [AVISO] {$a}: ya existen {$existentes} feriados en BD (no se volvió a consultar la API dentro de las 24 h o la API no devolvió datos).");
                    } else {
                        $this->error("  [ERROR] {$a}: no se pudieron sincronizar feriados (revise los logs y la API key).");
                        $fallos++;
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  [ERROR] {$a}: ".$e->getMessage());
                $fallos++;
            }
        }

        $this->newLine();

        if ($total > 0) {
            $this->info("Sincronización completada: {$total} feriado(s) almacenados.");
        }

        if ($fallos > 0) {
            $this->error("Hubo {$fallos} año(s) con errores. Revise el log del sistema.");

            return self::FAILURE;
        }

        if ($total === 0 && $fallos === 0) {
            $this->warn('No se sincronizó nada nuevo (la API ya fue consultada hoy o no devolvió datos).');
        }

        return self::SUCCESS;
    }
}
