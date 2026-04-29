<?php

namespace App\Filament\Resources\ExcedenteResource\Pages;

use App\Filament\Resources\ExcedenteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListExcedentes extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ExcedenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\ExcedenteYapeStatsWidget::class,
            \App\Filament\Widgets\ExcedentePromotorStatsWidget::class,
            \App\Filament\Widgets\ExcedenteOficinaStatsWidget::class,
            \App\Filament\Widgets\ExcedenteTotalStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 4,
            'lg' => 4,
        ];
    }
}
