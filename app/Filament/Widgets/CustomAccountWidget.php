<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\AccountWidget as BaseAccountWidget;

class CustomAccountWidget extends BaseAccountWidget
{
    use HasWidgetShield;

    protected int|string|array $columnSpan = 2;

    protected static string $view = 'filament.widgets.custom-account-widget';
}