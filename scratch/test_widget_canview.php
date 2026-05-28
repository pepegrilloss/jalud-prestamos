<?php
require_once 'C:/xampp/htdocs/jalud-prestamos/vendor/autoload.php';
$app = require_once 'C:/xampp/htdocs/jalud-prestamos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test 1: Check the exact canView condition
$user = App\Models\User::find(1);
if ($user) {
    Auth::login($user);
    echo 'User: ' . $user->name . PHP_EOL;
    echo 'Panel: ' . (filament()->getCurrentPanel()?->getId() ?? 'null') . PHP_EOL;
    echo 'Has perm: ' . ($user->can('widget_MontoPropuestoHoyStatsWidget') ? 'YES' : 'NO') . PHP_EOL;
    echo 'class_basename: ' . class_basename(App\Filament\Widgets\MontoPropuestoHoyStatsWidget::class) . PHP_EOL;
    echo 'Full perm name: widget_' . class_basename(App\Filament\Widgets\MontoPropuestoHoyStatsWidget::class) . PHP_EOL;
    echo 'canView result: ' . (App\Filament\Widgets\MontoPropuestoHoyStatsWidget::canView() ? 'TRUE' : 'FALSE') . PHP_EOL;
}

// Test 2: Check if getHeaderWidgets works
echo PHP_EOL . '=== HEADER WIDGETS from page ===' . PHP_EOL;
$page = new App\Filament\Resources\CrearProposicionCreditoResource\Pages\ListCrearProposicionCreditos();
$widgets = $page->getHeaderWidgets();
echo 'Header widget count: ' . count($widgets) . PHP_EOL;
foreach ($widgets as $w) {
    echo '  Class: ' . (is_string($w) ? $w : get_class($w)) . PHP_EOL;
    echo '  canView: ' . ($w::canView() ? 'YES' : 'NO') . PHP_EOL;
}

// Test 3: Check visible header widgets
echo PHP_EOL . '=== VISIBLE HEADER WIDGETS ===' . PHP_EOL;
$visible = $page->getVisibleHeaderWidgets();
echo 'Visible count: ' . count($visible) . PHP_EOL;
foreach ($visible as $w) {
    echo '  Class: ' . (is_string($w) ? $w : get_class($w)) . PHP_EOL;
}
