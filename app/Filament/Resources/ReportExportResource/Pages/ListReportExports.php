<?php

namespace App\Filament\Resources\ReportExportResource\Pages;

use App\Filament\Resources\ReportExportResource;
use Filament\Resources\Pages\ListRecords;

class ListReportExports extends ListRecords
{
    protected static string $resource = ReportExportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
