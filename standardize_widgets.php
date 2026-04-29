<?php

$dir = __DIR__ . '/app/Filament/Widgets/';
$files = [
    'DashboardCobradoDiarioWidget.php',
    'DashboardCreditosRefinanciadosWidget.php',
    'DashboardCreditosVencenHoyWidget.php',
    'DashboardMiTotalPrestadoWidget.php',
    'DashboardMisClientesActivosWidget.php',
    'DashboardMisPrestamosActivosWidget.php',
    'DashboardPagosCerradosHoyWidget.php',
    'DashboardPendientesAprobacionWidget.php',
    'DashboardProposicionesHoyWidget.php'
];

foreach ($files as $file) {
    $path = $dir . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        // Ensure imports
        if (strpos($content, 'use Illuminate\Support\Facades\Auth;') === false) {
            $content = str_replace('use Filament\Widgets\StatsOverviewWidget\Stat;', "use Filament\Widgets\StatsOverviewWidget\Stat;\nuse Illuminate\Support\Facades\Auth;", $content);
        }
        
        // Remove existing canView if any
        $content = preg_replace('/public static function canView\(\): bool.*?\{.*?return parent::canView\(\);.*?\}/s', '', $content);
        
        // Add robust canView
        $canView = <<<PHP

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }

PHP;

        if (strpos($content, 'public static function canView(): bool') === false) {
             // Insert before getStats or last method
             $content = str_replace('protected function getStats(): array', $canView . '    protected function getStats(): array', $content);
        }

        file_put_contents($path, $content);
        echo "Standardized $file\n";
    }
}
