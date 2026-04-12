<?php

namespace App\Filament\Resources\GenerarCreditoResource\Pages;

use App\Filament\Resources\GenerarCreditoResource;
use App\Traits\BloquearPorDiaCerrado;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGenerarCredito extends EditRecord
{
    use BloquearPorDiaCerrado;

    protected static string $resource = GenerarCreditoResource::class;

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');
        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='{$url}' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Editar Crédito Generado</span>
            </div>
        ");
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
