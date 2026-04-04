<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Mantenimiento extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Matenimiento';
    protected static ?int $navigationGroupSort = 10;
    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('page_Mantenimiento');
    }
}
