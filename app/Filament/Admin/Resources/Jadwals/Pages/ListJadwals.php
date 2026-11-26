<?php

namespace App\Filament\Admin\Resources\Jadwals\Pages;

use App\Filament\Admin\Resources\Jadwals\JadwalResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListJadwals extends ListRecords
{
    protected static string $resource = JadwalResource::class;

    public function getTabs(): array
    {
        $tabs = [];

        foreach (JadwalResource::tahapTabs() as $tahap => $label) {
            $tabs[$tahap] = Tab::make($label)->modifyQueryUsing(fn (Builder $query) => $query->where('tahap', $tahap));
        }

        return $tabs;
    }
}
