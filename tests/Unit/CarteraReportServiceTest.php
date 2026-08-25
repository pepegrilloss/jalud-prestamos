<?php

namespace Tests\Unit;

use App\Services\CarteraReportService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CarteraReportServiceTest extends TestCase
{
    #[DataProvider('fechasDeCartera')]
    public function test_clasifica_con_los_mismos_rangos_del_reporte_de_cartera(
        string $fechaVencimiento,
        string $esperado,
    ): void {
        $servicio = new CarteraReportService;

        $this->assertSame(
            $esperado,
            $servicio->clasificarFechaVencimiento(
                Carbon::parse($fechaVencimiento),
                Carbon::parse('2026-08-23'),
            ),
        );
    }

    public static function fechasDeCartera(): array
    {
        return [
            'vence en la fecha de corte' => ['2026-08-23', 'no_vencida'],
            'vence en el futuro' => ['2026-08-24', 'no_vencida'],
            'vencida un día' => ['2026-08-22', 'vencida'],
            'vencida siete días' => ['2026-08-16', 'vencida'],
            'morosa desde ocho días' => ['2026-08-15', 'morosa'],
            'morosa hasta ciento ochenta días' => ['2026-02-24', 'morosa'],
            'pesada desde ciento ochenta y un días' => ['2026-02-23', 'pesada'],
        ];
    }
}
