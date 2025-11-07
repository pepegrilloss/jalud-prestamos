<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget as BaseAccountWidget;

class CustomAccountWidget extends BaseAccountWidget
{
    public function getColumnSpan(): array|int|string
    {
        return 2;
    }
}