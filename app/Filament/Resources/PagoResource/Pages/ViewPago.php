<?php

namespace App\Filament\Resources\PagoResource\Pages;

use App\Filament\Resources\PagoResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPago extends ViewRecord
{
    protected static string $resource = PagoResource::class;

    public function mount(int|string $record): void
    {
        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            abort(403);
        }

        parent::mount($record);
    }
}
