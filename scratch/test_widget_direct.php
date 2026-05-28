<?php
require_once 'C:/xampp/htdocs/jalud-prestamos/vendor/autoload.php';
$app = require_once 'C:/xampp/htdocs/jalud-prestamos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Login as user 1
$user = App\Models\User::find(1);
Auth::login($user);

// Test the widget directly
echo '=== DIRECT WIDGET TEST ===' . PHP_EOL;
echo 'Widget class: ' . App\Filament\Widgets\MontoPropuestoHoyStatsWidget::class . PHP_EOL;
echo 'canView: ' . (App\Filament\Widgets\MontoPropuestoHoyStatsWidget::canView() ? 'YES' : 'NO') . PHP_EOL;

// Simulate getHeaderWidgets
$widgets = [
    \App\Filament\Widgets\MontoPropuestoHoyStatsWidget::class,
];
echo 'Widget in array: ' . (in_array(\App\Filament\Widgets\MontoPropuestoHoyStatsWidget::class, $widgets) ? 'YES' : 'NO') . PHP_EOL;

// Check filterVisibleWidgets manually
$filtered = array_filter($widgets, function($w) {
    return $w::canView();
});
echo 'Filtered count: ' . count($filtered) . PHP_EOL;

// Check if StatsOverviewWidget renders properly
echo PHP_EOL . '=== STATS RESULT ===' . PHP_EOL;
$widget = new App\Filament\Widgets\MontoPropuestoHoyStatsWidget();
$widget->mount(); // Some widgets need mounting
$stats = $widget->getStats();
foreach ($stats as $s) {
    echo 'Label: ' . $s->getLabel() . PHP_EOL;
    echo 'Value: ' . $s->getValue() . PHP_EOL;
}
