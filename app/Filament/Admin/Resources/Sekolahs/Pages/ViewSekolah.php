<?php

namespace App\Filament\Admin\Resources\Sekolahs\Pages;

use App\Filament\Admin\Resources\Sekolahs\SekolahResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSekolah extends ViewRecord
{
    protected static string $resource = SekolahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
