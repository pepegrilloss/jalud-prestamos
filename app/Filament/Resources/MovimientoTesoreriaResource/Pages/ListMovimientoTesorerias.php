<?php

namespace App\Filament\Resources\MovimientoTesoreriaResource\Pages;

use App\Filament\Resources\MovimientoTesoreriaResource;
use App\Services\TesoreriaGerenciaService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMovimientoTesorerias extends ListRecords
{
    protected static string $resource = MovimientoTesoreriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('transferir')
                ->label('Nueva transferencia')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->form(MovimientoTesoreriaResource::formularioTransferencia())
                ->action(function (array $data): void {
                    app(TesoreriaGerenciaService::class)->transferir($data, auth()->id());
                    Notification::make()->success()->title('Transferencia registrada correctamente')->send();
                }),
        ];
    }
}
