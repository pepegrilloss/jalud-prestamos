<?php

namespace App\Filament\Resources\LogResource\Pages;

use App\Filament\Resources\LogResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListLogs extends ListRecords
{
    protected static string $resource = LogResource::class;

    /**
     * La auditoría crece indefinidamente. La paginación simple evita ejecutar
     * COUNT(*) sobre toda la tabla cada vez que se abre o filtra el listado.
     */
    protected function paginateTableQuery(Builder $query): Paginator | CursorPaginator
    {
        $perPage = $this->getTableRecordsPerPage();

        return $query->simplePaginate(
            perPage: is_numeric($perPage) ? (int) $perPage : 25,
            columns: ['*'],
            pageName: $this->getTablePaginationPageName(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
