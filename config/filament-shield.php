<?php

return [
    'shield_resource' => [
        'should_register_navigation' => true,
        'slug' => 'shield/roles',
        'navigation_sort' => -1,
        'navigation_badge' => true,
        'navigation_group' => true,
        'sub_navigation_position' => null,
        'is_globally_searchable' => false,
        'show_model_path' => true,
        'is_scoped_to_tenant' => true,
        'cluster' => null,
    ],

    'tenant_model' => null,

    'auth_provider_model' => [
        'fqcn' => 'App\\Models\\User',
    ],

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'define_via_gate' => false,
        'intercept_gate' => 'after',
    ],

    'panel_user' => [
        'enabled' => true,
        'name' => 'panel_user',
    ],

    'permission_prefixes' => [
        'resource' => [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
        ],

        'page' => 'page',
        'widget' => 'widget',
    ],

    'entities' => [
        'pages' => true,
        'widgets' => true,
        'resources' => true,
        'custom_permissions' => true,
    ],

    'custom_permissions' => [
        'ver_todas_las_sedes',
        'seleccionar_sedes_operativas',
        'ver_todos_los_pagos',
        'registrar_pagos_a_mayor',
        'registrar_pagos_a_mayor_por_mora',
        'abrir_dia_apertura',
        'cerrar_dia_apertura',
        'balance_diario',
        'descargar_excel_clientes',
        'descargar_pdf_clientes',
        'view_any_reporte::clientes::atraso',
        'view_any_reporte::clientes::inactivos',
        'registrar_pago_mora',
        'bloquear_pago_promotor',
        'reporte_creditos',
        'page_FacturasPendientes',
        'editar_capital_tasa',
        'aprobar_extornos',
        'aprobar_exoneraciones',
        'eliminar_credito',
    ],

    'generator' => [
        'option' => 'policies_and_permissions',
        'policy_directory' => 'Policies',
        'policy_namespace' => 'Policies',
    ],

    'exclude' => [
        'enabled' => true,

        'pages' => [
            'Dashboard',
            'SelectSede',
            'EvaluacionDeCredito',
            'Mantenimiento',
        ],

        'widgets' => [
            'AccountWidget',
            'FilamentInfoWidget',
        ],

        'resources' => [
            'CrearProposicionCreditoResource',
        ],
    ],

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    'register_role_policy' => [
        'enabled' => true,
    ],

];
