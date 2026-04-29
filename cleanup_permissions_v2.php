<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$keepNames = [
    'ver_todas_las_sedes',
    'abrir_dia_apertura',
    'cerrar_dia_apertura',
];

// 1. Obtener todos los permisos que NO empiezan con los prefijos estandar
// (estos son los "realmente" personalizados)
$customPermissions = Permission::where(function($query) {
    $query->where('name', 'not like', 'view_%')
          ->where('name', 'not like', 'view_any_%')
          ->where('name', 'not like', 'create_%')
          ->where('name', 'not like', 'update_%')
          ->where('name', 'not like', 'delete_%')
          ->where('name', 'not like', 'restore_%')
          ->where('name', 'not like', 'force_delete_%')
          ->where('name', 'not like', 'page_%')
          ->where('name', 'not like', 'widget_%');
})->get();

foreach ($customPermissions as $p) {
    if (!in_array($p->name, $keepNames)) {
        echo "Eliminando permiso personalizado no deseado: {$p->name}\n";
        $p->delete();
    }
}

// 2. Eliminar permisos de recursos excluidos (como CrearProposicionCreditoResource)
Permission::where('name', 'like', '%crear::proposicion::credito%')->delete();

// 3. Eliminar widgets antiguos o mal categorizados que aparecen en el screenshot
// (Muchos widgets en el screenshot parecen tener nombres duplicados o incorrectos)
// Si el usuario quiere limpiar la lista, eliminamos los que no estamos usando activamente ahora
$activeWidgets = [
    'widget_GenerarCreditosTotalWidget',
    'widget_GenerarCreditosCantidadWidget',
    'widget_CreditoGeneradoTotalWidget',
    'widget_CreditoGeneradoCantidadWidget',
    'widget_ProposicionCreditoStats',
    'widget_CustomAccountWidget',
    'widget_DashboardCobradoDiarioWidget',
    'widget_DashboardCreditosVencenHoyWidget',
    'widget_DashboardMiTotalPrestadoWidget',
    'widget_DashboardMisClientesActivosWidget',
    'widget_DashboardMisPrestamosActivosWidget',
    'widget_DashboardPagosCerradosHoyWidget',
    'widget_DashboardProposicionesHoyWidget',
];

$allWidgetsInDb = Permission::where('name', 'like', 'widget_%')->get();
foreach ($allWidgetsInDb as $w) {
    if (!in_array($w->name, $activeWidgets)) {
        echo "Eliminando widget antiguo o no deseado: {$w->name}\n";
        $w->delete();
    }
}

echo "Limpieza completada.\n";
