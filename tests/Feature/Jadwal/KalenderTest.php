<?php

/*
 * T-18 / TD-13 / SP-07: the schedule calendar is a read-only view over `kegiatan`. Events come
 * from the scoped query, filters only narrow it, and the single write path is "Jadwal Ulang"
 * (RescheduleKegiatan).
 */

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Jadwal\Models\KegiatanJadwalRiwayat;
use App\Domain\Jadwal\Support\KalenderEvent;
use App\Domain\Master\Models\Cabang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Admin\Pages\KalenderJadwal as AdminKalender;
use App\Filament\Mentor\Pages\KalenderJadwal as MentorKalender;
use App\Filament\Widgets\KalenderJadwalWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create();
    $this->cabangB = Cabang::factory()->create();
    $this->sa = User::factory()->superadmin()->create();
    $this->ap = User::factory()->adminPusat()->create();
    $this->acA = User::factory()->adminCabang($this->cabangA)->create();
    $this->acB = User::factory()->adminCabang($this->cabangB)->create();
    $this->mentor = User::factory()->mentor()->create();
    $this->mentorLain = User::factory()->mentor()->create();
    $this->sekolahA = Fixtures::sekolah($this->cabangA, $this->mentor, ['posisi_tahap' => PosisiTahap::MitraBelumTl]);
    $this->sekolahB = Fixtures::sekolah($this->cabangB, $this->mentorLain);

    $this->panel = function (User $user, string $panel = 'admin') {
        test()->actingAs($user);
        Filament::setCurrentPanel($panel);
    };

    $this->dec = ['start' => '2026-12-01T00:00:00+07:00', 'end' => '2027-01-01T00:00:00+07:00', 'timezone' => 'Asia/Jakarta'];
    $this->jan = ['start' => '2027-01-01T00:00:00+07:00', 'end' => '2027-02-01T00:00:00+07:00', 'timezone' => 'Asia/Jakarta'];

    $this->awalA = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::Awal, 'urutan' => 1, 'nama_sesi' => 'Pelatihan Awal', 'tanggal' => '2026-12-15', 'waktu_mulai' => '09:00', 'waktu_selesai' => '11:00']);
    $this->tlB = Fixtures::kegiatan($this->sekolahB, $this->mentorLain, ['tahap' => Tahap::TindakLanjut, 'urutan' => 1, 'nama_sesi' => 'Sesi 1', 'tanggal' => '2026-12-16', 'waktu_mulai' => '13:30', 'waktu_selesai' => '15:00']);

    $this->events = function (User $user, ?array $info = null, array $filters = []) {
        ($this->panel)($user, $user->role->value === 'mentor' ? 'mentor' : 'admin');
        $widget = Livewire::test(KalenderJadwalWidget::class);
        foreach ($filters as $key => $value) {
            $widget->set($key, $value);
        }

        return collect($widget->instance()->fetchEvents($info ?? $this->dec));
    };
    $this->ids = fn ($events) => $events->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
});

describe('events: date, time, stage and status', function () {
    test('the event sits on its own date and time and shows school, stage, mentor and status', function () {
        $event = ($this->events)($this->ap)->firstWhere('id', (string) $this->awalA->id);

        expect($event['title'])->toBe($this->sekolahA->nama)
            ->and($event['start'])->toBe('2026-12-15T09:00:00')
            ->and($event['end'])->toBe('2026-12-15T11:00:00')
            ->and($event['extendedProps'])->toMatchArray([
                'tahap' => 'Pelatihan Awal',
                'sesi' => 'Pelatihan Awal',
                'mentor' => $this->mentor->name,
                'status' => 'Belum Dimulai',
            ]);
    });

    test('colour follows the stage: Awal and Tindak Lanjut differ', function () {
        $events = ($this->events)($this->ap);

        expect($events->firstWhere('id', (string) $this->awalA->id)['backgroundColor'])->toBe(KalenderEvent::WARNA_AWAL)
            ->and($events->firstWhere('id', (string) $this->tlB->id)['backgroundColor'])->toBe(KalenderEvent::WARNA_TL)
            ->and(KalenderEvent::WARNA_AWAL)->not->toBe(KalenderEvent::WARNA_TL);
    });

    test('events that cross a month or year boundary appear in the right visible range only', function () {
        $akhirTahun = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tanggal' => '2026-12-31', 'waktu_mulai' => '22:00', 'waktu_selesai' => '23:30']);
        $awalTahun = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tanggal' => '2027-01-01', 'waktu_mulai' => '00:30', 'waktu_selesai' => '02:00']);
        $minggu = ['start' => '2026-12-28T00:00:00+07:00', 'end' => '2027-01-04T00:00:00+07:00', 'timezone' => 'Asia/Jakarta'];

        $des = ($this->ids)(($this->events)($this->ap, $this->dec));
        $jan = ($this->ids)(($this->events)($this->ap, $this->jan));
        $lintas = ($this->ids)(($this->events)($this->ap, $minggu));

        expect($des)->toContain($akhirTahun->id)->not->toContain($awalTahun->id)
            ->and($jan)->toContain($awalTahun->id)->not->toContain($akhirTahun->id)
            ->and($lintas)->toContain($akhirTahun->id, $awalTahun->id);
    });

    test('the end of the visible range is exclusive', function () {
        $batas = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tanggal' => '2027-01-01']);

        expect(($this->ids)(($this->events)($this->ap, $this->dec)))->not->toContain($batas->id);
    });

    test('a cancelled activity stays on the calendar with a clear marker', function () {
        $batal = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tanggal' => '2026-12-20', 'status' => StatusKegiatan::Dibatalkan]);

        $event = ($this->events)($this->ap)->firstWhere('id', (string) $batal->id);

        expect($event)->not->toBeNull()
            ->and($event['classNames'])->toContain('kal-batal')
            ->and($event['extendedProps']['status'])->toBe('Dibatalkan')
            ->and($event['extendedProps']['statusKey'])->toBe('dibatalkan')
            ->and($event['backgroundColor'])->toBe(KalenderEvent::WARNA_BATAL);
    });

    test('a schedule change shows up on the very next fetch', function () {
        ($this->panel)($this->ap);
        $widget = Livewire::test(KalenderJadwalWidget::class);
        $sebelum = collect($widget->instance()->fetchEvents($this->dec))->firstWhere('id', (string) $this->awalA->id);

        $widget->callAction('reschedule', ['tanggal' => '2026-12-18', 'alasan' => 'Sekolah ujian'], ['kegiatan' => $this->awalA->id])->assertHasNoActionErrors();

        $sesudah = collect($widget->instance()->fetchEvents($this->dec))->firstWhere('id', (string) $this->awalA->id);
        expect($sebelum['start'])->toBe('2026-12-15T09:00:00')->and($sesudah['start'])->toBe('2026-12-18T09:00:00');
    });
});

describe('scope and filters', function () {
    test('AC-AUTH-04 Admin Cabang sees only its own branch; Admin Pusat and Superadmin see all', function () {
        expect(($this->ids)(($this->events)($this->acA)))->toBe([$this->awalA->id])
            ->and(($this->ids)(($this->events)($this->acB)))->toBe([$this->tlB->id])
            ->and(($this->ids)(($this->events)($this->ap)))->toBe(collect([$this->awalA->id, $this->tlB->id])->sort()->values()->all())
            ->and(($this->ids)(($this->events)($this->sa)))->toBe(collect([$this->awalA->id, $this->tlB->id])->sort()->values()->all());
    });

    test('filters cannot leak another branch: Admin Cabang forcing cabang, mentor or ids of another branch gets nothing', function () {
        expect(($this->events)($this->acA, null, ['cabang' => (string) $this->cabangB->id]))->toBeEmpty()
            ->and(($this->events)($this->acA, null, ['mentor' => (string) $this->mentorLain->id]))->toBeEmpty()
            ->and(($this->events)($this->acA, null, ['cabang' => (string) $this->cabangA->id, 'mentor' => (string) $this->mentorLain->id]))->toBeEmpty()
            ->and(($this->ids)(($this->events)($this->acA, null, ['cabang' => (string) $this->cabangA->id])))->toBe([$this->awalA->id]);
    });

    test('Admin Cabang gets no cabang filter options, Admin Pusat gets all branches', function () {
        ($this->panel)($this->acA);
        expect(Livewire::test(KalenderJadwalWidget::class)->instance()->cabangOptions())->toBe([]);

        ($this->panel)($this->ap);
        expect(array_keys(Livewire::test(KalenderJadwalWidget::class)->instance()->cabangOptions()))->toContain($this->cabangA->id, $this->cabangB->id);
    });

    test('each filter narrows by stage, branch, mentor and status', function () {
        $selesai = Fixtures::kegiatan($this->sekolahB, $this->mentorLain, ['tanggal' => '2026-12-22', 'status' => StatusKegiatan::Selesai]);
        $semua = (fn (array $f) => ($this->ids)(($this->events)($this->ap, null, $f)));

        expect($semua(['tahap' => 'awal']))->toBe([$this->awalA->id])
            ->and($semua(['tahap' => 'tindak_lanjut']))->toBe(collect([$this->tlB->id, $selesai->id])->sort()->values()->all())
            ->and($semua(['cabang' => (string) $this->cabangA->id]))->toBe([$this->awalA->id])
            ->and($semua(['mentor' => (string) $this->mentorLain->id]))->toBe(collect([$this->tlB->id, $selesai->id])->sort()->values()->all())
            ->and($semua(['status' => 'selesai']))->toBe([$selesai->id])
            ->and($semua(['tahap' => 'tindak_lanjut', 'status' => 'selesai', 'cabang' => (string) $this->cabangB->id, 'mentor' => (string) $this->mentorLain->id]))->toBe([$selesai->id]);
    });

    test('unknown filter values return nothing instead of ignoring the filter', function () {
        foreach ([['tahap' => 'x'], ['status' => 'x'], ['cabang' => 'x'], ['mentor' => 'x'], ['cabang' => '0 OR 1=1']] as $filters) {
            expect(($this->events)($this->ap, null, $filters))->toBeEmpty();
        }
    });

    test('a mentor sees the cross-school calendar but an inactive user sees nothing', function () {
        expect(($this->ids)(($this->events)($this->mentor)))->toBe(collect([$this->awalA->id, $this->tlB->id])->sort()->values()->all());

        $this->mentor->forceFill(['aktif' => false])->save();
        expect(($this->events)($this->mentor->refresh()))->toBeEmpty();
    });

    test('the pages are open to admins and mentors of their own panel, closed to guests', function () {
        ($this->panel)($this->ap);
        expect(AdminKalender::canAccess())->toBeTrue();
        ($this->panel)($this->acA);
        expect(AdminKalender::canAccess())->toBeTrue();
        ($this->panel)($this->mentor, 'mentor');
        expect(MentorKalender::canAccess())->toBeTrue();
        ($this->panel)($this->ap, 'mentor');
        expect(MentorKalender::canAccess())->toBeFalse();

        auth()->logout();
        expect(AdminKalender::canAccess())->toBeFalse();
        $this->get('/admin/kalender-jadwal')->assertRedirect();
        $this->get('/mentor/kalender-jadwal')->assertRedirect();
    });
});

describe('detail on click', function () {
    test('clicking an event mounts the view action for that very activity', function () {
        ($this->panel)($this->ap);

        $widget = Livewire::test(KalenderJadwalWidget::class)
            ->call('onEventClick', ['id' => (string) $this->tlB->id, 'title' => 'x'])
            ->assertActionMounted('view');

        expect($widget->instance()->mountedActions[0]['arguments']['event']['id'])->toBe((string) $this->tlB->id);
    });

    test('the detail of another branch resolves to 404 for Admin Cabang, whatever id is sent', function () {
        ($this->panel)($this->acA);

        Livewire::test(KalenderJadwalWidget::class)->call('onEventClick', ['id' => (string) $this->tlB->id])->assertStatus(404);
        Livewire::test(KalenderJadwalWidget::class)->call('onEventClick', ['id' => '999999'])->assertStatus(404);
    });

    test('the detail shows schedule, stage, mentor and status; location only when the policy allows', function () {
        $k = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tanggal' => '2026-12-21', 'lokasi' => 'Aula SMAN 1', 'tautan_pertemuan' => 'https://meet.example/abc', 'status' => StatusKegiatan::Dibatalkan]);

        $html = view('filament.widgets.kalender-detail', ['kegiatan' => $k->load('sekolah.cabang', 'metode', 'mentorPelaksana'), 'bolehLihatDetail' => true])->render();
        $tanpa = view('filament.widgets.kalender-detail', ['kegiatan' => $k, 'bolehLihatDetail' => false])->render();

        expect($html)->toContain('Kegiatan ini dibatalkan.', 'Aula SMAN 1', 'https://meet.example/abc', $this->mentor->name, 'Dibatalkan', '21 Desember 2026')
            ->and($tanpa)->not->toContain('Aula SMAN 1')->not->toContain('https://meet.example/abc')->toContain('Dibatalkan');
    });
});

describe('the calendar is read-only: Jadwal Ulang is the only write path', function () {
    test('drag, resize and date selection do not change data and open no create or edit form', function () {
        ($this->panel)($this->ap);
        $sebelum = [Kegiatan::query()->count(), KegiatanJadwalRiwayat::query()->count(), DB::table('audit_log')->count(), $this->awalA->fresh()->only(['tanggal', 'waktu_mulai', 'version'])];

        $widget = Livewire::test(KalenderJadwalWidget::class)
            ->call('onEventDrop', ['id' => (string) $this->awalA->id], ['id' => (string) $this->awalA->id], [], ['days' => 3], null, null)
            ->call('onEventResize', ['id' => (string) $this->awalA->id], ['id' => (string) $this->awalA->id], [], ['hours' => 1], ['hours' => 1])
            ->call('onDateSelect', '2026-12-30', null, true, null, null);

        expect($widget->instance()->mountedActions)->toBe([])
            ->and([Kegiatan::query()->count(), KegiatanJadwalRiwayat::query()->count(), DB::table('audit_log')->count(), $this->awalA->fresh()->only(['tanggal', 'waktu_mulai', 'version'])])->toEqual($sebelum);
    });

    test('the package create, edit and delete actions are unreachable', function () {
        ($this->panel)($this->ap);
        $sebelum = Kegiatan::query()->count();
        $versi = $this->awalA->fresh()->version;

        foreach (['create', 'edit', 'delete'] as $name) {
            $widget = Livewire::test(KalenderJadwalWidget::class)->call('mountAction', $name, ['event' => ['id' => (string) $this->awalA->id]]);

            expect($widget->instance()->mountedActions)->toBe([]);
        }

        expect(Kegiatan::query()->count())->toBe($sebelum)->and($this->awalA->fresh()->version)->toBe($versi);
    });

    test('only standard MIT plugins are registered: no interaction, no premium, no license key; not editable or selectable', function () {
        foreach (['admin', 'mentor'] as $id) {
            $plugin = Filament::getPanel($id)->getPlugin('filament-fullcalendar');

            expect($plugin)->toBeInstanceOf(FilamentFullCalendarPlugin::class)
                ->and($plugin->getPlugins())->toBe(['dayGrid', 'timeGrid', 'list'])
                ->and($plugin->getSchedulerLicenseKey())->toBeNull()
                ->and($plugin->isEditable())->toBeFalse()
                ->and($plugin->isSelectable())->toBeFalse();
        }

        $view = file_get_contents(resource_path('views/filament/widgets/kalender-jadwal.blade.php'));
        expect($view)->toContain('editable: false', 'selectable: false');
    });

    test('the widget source writes only through RescheduleKegiatan', function () {
        $source = file_get_contents(app_path('Filament/Widgets/KalenderJadwalWidget.php'));
        preg_match_all('/app\(([A-Za-z\\\\]+)::class\)/', $source, $domain);

        expect($domain[1])->toBe(['RescheduleKegiatan'])
            ->and($source)->not->toMatch('/->(save|update|delete|forceFill|create|insert)\(|::(create|insert|update)\(|DB::(table|statement|update|insert)/');
    });

    test('Jadwal Ulang from the calendar goes through the domain action: reason required, history and audit written', function () {
        ($this->panel)($this->acA);
        Livewire::test(KalenderJadwalWidget::class)->callAction('reschedule', ['tanggal' => '2026-12-18', 'alasan' => ''], ['kegiatan' => $this->awalA->id])->assertHasActionErrors(['alasan']);
        expect($this->awalA->fresh()->tanggal->toDateString())->toBe('2026-12-15');

        Livewire::test(KalenderJadwalWidget::class)->callAction('reschedule', ['tanggal' => '2026-12-18', 'alasan' => 'Permintaan sekolah'], ['kegiatan' => $this->awalA->id])->assertHasNoActionErrors();

        expect($this->awalA->fresh()->tanggal->toDateString())->toBe('2026-12-18')
            ->and(KegiatanJadwalRiwayat::query()->where('kegiatan_id', $this->awalA->id)->count())->toBe(1)
            ->and(DB::table('audit_log')->where('aksi', 'like', 'kegiatan.reschedule%')->count())->toBeGreaterThan(0);
    });

    test('Admin Cabang cannot reschedule another branch from the calendar (404, nothing changes)', function () {
        ($this->panel)($this->acA);

        Livewire::test(KalenderJadwalWidget::class)->call('mountAction', 'reschedule', ['kegiatan' => $this->tlB->id])->assertStatus(404);

        expect($this->tlB->fresh()->tanggal->toDateString())->toBe('2026-12-16')
            ->and(KegiatanJadwalRiwayat::query()->where('kegiatan_id', $this->tlB->id)->count())->toBe(0);
    });

    test('a mentor reschedules only own activities; another mentor activity resolves to 404', function () {
        ($this->panel)($this->mentor, 'mentor');

        Livewire::test(KalenderJadwalWidget::class)
            ->callAction('reschedule', ['tanggal' => '2026-12-19', 'alasan' => 'Bentrok'], ['kegiatan' => $this->awalA->id])->assertHasNoActionErrors();
        expect($this->awalA->fresh()->tanggal->toDateString())->toBe('2026-12-19');

        Livewire::test(KalenderJadwalWidget::class)->call('mountAction', 'reschedule', ['kegiatan' => $this->tlB->id])->assertStatus(404);
        expect($this->tlB->fresh()->tanggal->toDateString())->toBe('2026-12-16');
    });

    test('a finished or cancelled activity cannot be rescheduled from the calendar (Policy)', function () {
        $selesai = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tanggal' => '2026-12-22', 'status' => StatusKegiatan::Selesai]);
        ($this->panel)($this->acA);

        expect(Gate::forUser($this->acA)->allows('reschedule', $selesai))->toBeFalse();
        try {
            Livewire::test(KalenderJadwalWidget::class)->callAction('reschedule', ['tanggal' => '2026-12-23', 'alasan' => 'x'], ['kegiatan' => $selesai->id]);
        } catch (Throwable) {
            // The domain action refuses (403); what matters is that nothing was written.
        }

        expect($selesai->fresh()->tanggal->toDateString())->toBe('2026-12-22')
            ->and(KegiatanJadwalRiwayat::query()->where('kegiatan_id', $selesai->id)->count())->toBe(0);
    });
});
