<?php

namespace App\Filament\Widgets;

use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Jadwal\Support\KalenderEvent;
use App\Domain\Jadwal\Support\KalenderQuery;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Admin\Resources\Sekolahs\SekolahResource;
use App\Filament\Support\RescheduleFormSchema;
use App\Filament\Support\RunsDomainAction;
use App\Filament\Support\ScopedOptions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

/**
 * Read-only schedule calendar (T-18, TD-13). It is a view over `kegiatan`: events come from
 * the scoped KalenderQuery, and the only way to change a schedule from here is the
 * "Jadwal Ulang" action, which runs RescheduleKegiatan (Policy, reason, history, audit).
 *
 * The package ships drag, resize and date-select handlers that open its edit/create
 * actions. They are neutralised below and the plugin list excludes `interaction`.
 */
class KalenderJadwalWidget extends FullCalendarWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.kalender-jadwal';

    public ?string $tahap = null;

    public ?string $status = null;

    public ?string $cabang = null;

    public ?string $mentor = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['tahap', 'status', 'cabang', 'mentor'], true)) {
            $this->refreshRecords();
        }
    }

    /** @param array{start: string, end: string, timezone: string} $info */
    public function fetchEvents(array $info): array
    {
        /** @var User $user */
        $user = auth()->user();

        return KalenderQuery::build($user, $info['start'], $info['end'], [
            'tahap' => $this->tahap,
            'status' => $this->status,
            'cabang' => $this->cabang,
            'mentor' => $this->mentor,
        ])
            ->limit(1000)
            ->get()
            ->map(fn (Kegiatan $kegiatan) => KalenderEvent::from($kegiatan))
            ->all();
    }

    public function config(): array
    {
        return [
            'initialView' => 'dayGridMonth',
            'eventDisplay' => 'block',
            'displayEventEnd' => true,
            'eventTimeFormat' => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
            'height' => 'auto',
            'dayMaxEvents' => 3,
            'firstDay' => 1,
            'nowIndicator' => true,
            'slotMinTime' => '06:00:00',
            'slotMaxTime' => '21:00:00',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,listWeek',
            ],
            'buttonText' => ['today' => 'Hari ini', 'month' => 'Bulan', 'week' => 'Minggu', 'list' => 'Agenda'],
        ];
    }

    /** Block content: school, time, stage, mentor and a spelled-out status. Text nodes only (no HTML injection). */
    public function eventContent(): string
    {
        return <<<'JS'
            function (info) {
                const p = info.event.extendedProps
                const box = document.createElement('div')
                box.className = 'kal-ev'
                const add = (cls, text) => {
                    const el = document.createElement('div')
                    el.className = cls
                    el.textContent = text
                    box.appendChild(el)
                }
                if (info.timeText) add('kal-time', info.timeText)
                add('kal-title', info.event.title)
                add('kal-meta', p.tahap + (p.mentor ? ' · ' + p.mentor : ''))
                add('kal-status', p.status)
                return { domNodes: [box] }
            }
        JS;
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
            function (info) {
                info.el.dataset.kegiatanId = info.event.id
                info.el.dataset.status = info.event.extendedProps.statusKey
            }
        JS;
    }

    /** @return array<string, string> */
    public function tahapOptions(): array
    {
        return collect(Tahap::cases())->mapWithKeys(fn (Tahap $t) => [$t->value => $t->label()])->all();
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return collect(StatusKegiatan::cases())->mapWithKeys(fn (StatusKegiatan $s) => [$s->value => $s->label()])->all();
    }

    /** @return array<int, string> */
    public function cabangOptions(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->role === Role::AdminCabang ? [] : ScopedOptions::cabang($user);
    }

    /** @return array<int, string> */
    public function mentorOptions(): array
    {
        return User::query()->where('role', Role::Mentor->value)->where('aktif', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [];
    }

    protected function viewAction(): Action
    {
        return Action::make('view')
            ->modalHeading(fn (array $arguments) => $this->resolve($arguments, 'kegiatan.view')->sekolah->nama)
            ->modalContent(function (array $arguments) {
                $kegiatan = $this->resolve($arguments, 'kegiatan.view');

                return view('filament.widgets.kalender-detail', [
                    'kegiatan' => $kegiatan,
                    'bolehLihatDetail' => Gate::allows('viewDetail', $kegiatan),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->extraModalFooterActions(function (array $arguments): array {
                $kegiatan = $this->resolve($arguments, 'kegiatan.view');
                $actions = [];

                if (Gate::allows('reschedule', $kegiatan)) {
                    $actions[] = Action::make('jadwalUlang')
                        ->label('Jadwal Ulang')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->action(fn () => $this->replaceMountedAction('reschedule', ['kegiatan' => $kegiatan->id]));
                }

                if (($url = $this->sekolahUrl($kegiatan)) !== null) {
                    $actions[] = Action::make('bukaSekolah')->label('Buka Sekolah')->color('gray')->url($url);
                }

                return $actions;
            });
    }

    /** The one write path of the calendar: the shared "Jadwal Ulang" form into RescheduleKegiatan. */
    public function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Jadwal Ulang')
            ->icon(Heroicon::OutlinedCalendar)
            ->modalHeading('Jadwal Ulang Kegiatan')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Simpan')
            ->record(fn (array $arguments) => $this->resolve($arguments, 'kegiatan.reschedule'))
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

                $this->refreshRecords();
            });
    }

    // Write paths of the package: drag, resize and date selection are inert (read-only calendar).
    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        return true;
    }

    public function onEventResize(array $event, array $oldEvent, array $relatedEvents, array $startDelta, array $endDelta): bool
    {
        return true;
    }

    public function onDateSelect(string $start, ?string $end, bool $allDay, ?array $view, ?array $resource): void {}

    /** Scoped lookup: a record outside the user's scope resolves to 404, whatever id the client sends. */
    private function resolve(array $arguments, string $ability): Kegiatan
    {
        $id = (int) ($arguments['kegiatan'] ?? ($arguments['event']['id'] ?? 0));

        return Kegiatan::query()
            ->visibleTo(auth()->user(), $ability)
            ->with(['sekolah.cabang', 'metode', 'mentorPelaksana'])
            ->findOrFail($id);
    }

    private function sekolahUrl(Kegiatan $kegiatan): ?string
    {
        $user = auth()->user();
        $panel = Filament::getCurrentPanel()?->getId();

        if ($user === null || ! Gate::forUser($user)->allows('view', $kegiatan->sekolah)) {
            return null;
        }

        $resource = match ($panel) {
            'admin' => SekolahResource::class,
            'mentor' => \App\Filament\Mentor\Resources\Sekolahs\SekolahResource::class,
            default => null,
        };

        return $resource === null ? null : $resource::getUrl('view', ['record' => $kegiatan->sekolah_id]);
    }
}
