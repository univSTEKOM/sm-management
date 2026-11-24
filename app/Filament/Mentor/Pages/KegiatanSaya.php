<?php

namespace App\Filament\Mentor\Pages;

use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Laporan\Actions\ReportKegiatan;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Filament\Support\RescheduleFormSchema;
use App\Filament\Support\RunsDomainAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Mentor work page (FR-MNT-04, FR-RPT-01/02, BR-ACT-05). Mobile-first cards.
 *
 * Records are loaded in computed properties (not public state) and every
 * action re-resolves its record through the scoped query, then the domain
 * action authorizes again (Implementation Plan 4.11).
 */
class KegiatanSaya extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Kegiatan Saya';

    protected static ?string $title = 'Kegiatan Saya';

    protected static ?string $slug = 'kegiatan-saya';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.mentor.pages.kegiatan-saya';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->aktif && $user->role === Role::Mentor;
    }

    /** Open activities whose date has passed (label "Perlu Pembaruan", FR-RPT-06). */
    #[Computed]
    public function perluPembaruan(): Collection
    {
        return $this->baseQuery()
            ->whereIn('status', [StatusKegiatan::BelumDimulai->value, StatusKegiatan::SedangBerlangsung->value])
            ->whereDate('tanggal', '<', today())
            ->orderBy('tanggal')
            ->get();
    }

    #[Computed]
    public function mendatang(): Collection
    {
        return $this->baseQuery()
            ->whereIn('status', [StatusKegiatan::BelumDimulai->value, StatusKegiatan::SedangBerlangsung->value])
            ->whereDate('tanggal', '>=', today())
            ->orderBy('tanggal')
            ->orderBy('waktu_mulai')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function riwayat(): Collection
    {
        return $this->baseQuery()
            ->whereIn('status', [StatusKegiatan::Selesai->value, StatusKegiatan::Dibatalkan->value])
            ->orderByDesc('tanggal')
            ->limit(10)
            ->get();
    }

    public function reportAction(): Action
    {
        return Action::make('report')
            ->label('Perbarui Status')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->modalHeading('Perbarui Status Kegiatan')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn (array $arguments) => [
                'status' => $this->resolve($arguments, 'kegiatan.report')->status->value,
                'version' => $this->resolve($arguments, 'kegiatan.report')->version,
            ])
            ->schema([
                ToggleButtons::make('status')
                    ->options(collect(StatusKegiatan::cases())->mapWithKeys(fn (StatusKegiatan $s) => [$s->value => $s->label()])->all())
                    ->colors([
                        StatusKegiatan::BelumDimulai->value => 'gray',
                        StatusKegiatan::SedangBerlangsung->value => 'info',
                        StatusKegiatan::Selesai->value => 'success',
                        StatusKegiatan::Dibatalkan->value => 'danger',
                    ])
                    ->columns(2)
                    ->required(),
                Textarea::make('catatan')->label('Catatan')->rows(3)->required(),
                Hidden::make('version'),
            ])
            ->action(function (Action $action, array $data, array $arguments): void {
                $kegiatan = $this->resolve($arguments, 'kegiatan.report');

                RunsDomainAction::run($action, fn () => app(ReportKegiatan::class)->handle(
                    auth()->user(),
                    $kegiatan,
                    StatusKegiatan::from($data['status']),
                    (string) $data['catatan'],
                    isset($data['version']) ? (int) $data['version'] : null,
                ), 'Status kegiatan diperbarui');

                unset($this->perluPembaruan, $this->mendatang, $this->riwayat);
            });
    }

    public function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Jadwal Ulang')
            ->icon(Heroicon::OutlinedCalendar)
            ->color('gray')
            ->modalHeading('Jadwal Ulang Kegiatan')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn (array $arguments) => RescheduleFormSchema::fill($this->resolve($arguments, 'kegiatan.reschedule')))
            ->schema(RescheduleFormSchema::components())
            ->action(function (Action $action, array $data, array $arguments): void {
                $kegiatan = $this->resolve($arguments, 'kegiatan.reschedule');
                $input = RescheduleFormSchema::input($data);

                RunsDomainAction::run($action, fn () => app(RescheduleKegiatan::class)->handle(
                    auth()->user(),
                    $kegiatan,
                    $input,
                    (string) ($input['alasan'] ?? ''),
                    isset($input['version']) ? (int) $input['version'] : null,
                ), 'Jadwal diperbarui');

                unset($this->perluPembaruan, $this->mendatang, $this->riwayat);
            });
    }

    /** Scoped lookup: records outside the mentor's assignment resolve to 404. */
    private function resolve(array $arguments, string $ability): Kegiatan
    {
        return Kegiatan::query()
            ->visibleTo(auth()->user(), $ability)
            ->with('sekolah')
            ->findOrFail((int) ($arguments['kegiatan'] ?? 0));
    }

    private function baseQuery()
    {
        return Kegiatan::query()
            ->visibleTo(auth()->user(), 'kegiatan.report')
            ->whereHas('sekolah', fn ($q) => $q->whereNull('diarsipkan_at'))
            ->with(['sekolah', 'metode']);
    }
}
