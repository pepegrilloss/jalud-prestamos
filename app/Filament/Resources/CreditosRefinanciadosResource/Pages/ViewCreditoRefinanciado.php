<?php

namespace App\Filament\Resources\CreditosRefinanciadosResource\Pages;

use App\Filament\Resources\CreditosRefinanciadosResource;
use App\Filament\Resources\CreditoResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Actions;

class ViewCreditoRefinanciado extends ViewRecord
{
    protected static string $resource = CreditosRefinanciadosResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');
        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='{$url}' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Ver Crédito Refinanciado</span>
            </div>
        ");
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(CreditoResource::getInfolistSchema());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_pagos')
                ->label('Descargar Pagos (PDF)')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn() => route('descargar-pagos.pdf', $this->record->CreditoID))
                ->openUrlInNewTab(),
        ];
    }
}
