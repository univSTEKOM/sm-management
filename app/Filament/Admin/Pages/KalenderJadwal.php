<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Filament\Widgets\KalenderJadwalWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Calendar view of the schedule (PRD 11.1). Same scope as the Jadwal list; read-only. */
class KalenderJadwal extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Jadwal';

    protected static ?string $navigationLabel = 'Kalender';

    protected static ?string $title = 'Kalender Jadwal';

    protected static ?string $slug = 'kalender-jadwal';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Kegiatan::class) ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [KalenderJadwalWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
