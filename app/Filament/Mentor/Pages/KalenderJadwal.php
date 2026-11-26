<?php

namespace App\Filament\Mentor\Pages;

use App\Domain\Shared\Enums\Role;
use App\Filament\Widgets\KalenderJadwalWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Calendar view for mentors (PRD 11.2). Rescheduling is limited to the mentor's own activities by Policy. */
class KalenderJadwal extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Kalender';

    protected static ?string $title = 'Kalender Jadwal';

    protected static ?string $slug = 'kalender-jadwal';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->aktif && $user->role === Role::Mentor;
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
