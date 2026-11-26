<?php

/*
 * Dashboard: horizontal bar chart of the klasemen (the training categories chosen most).
 * PRD 13.8, FR-RNK-01/03, FR-DSH-04, BR-RNK-01..04, AC-RNK-01..07.
 */

use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Sekolah\Support\KlasemenKategori;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Widgets\KlasemenKategoriChart;
use App\Models\User;
use App\Support\Theme\BrandPalette;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create();
    $this->cabangB = Cabang::factory()->create();
    $this->sa = User::factory()->superadmin()->create();
    $this->ap = User::factory()->adminPusat()->create();
    $this->acA = User::factory()->adminCabang($this->cabangA)->create();
    $this->mentor = User::factory()->mentor()->create();

    $this->jasa = KategoriPelatihan::create(['nama' => 'Jasa Layanan Bisnis']);
    $this->barber = KategoriPelatihan::create(['nama' => 'Barbershop']);
    $this->akuntan = KategoriPelatihan::create(['nama' => 'Akuntan Bisnis']);
    $this->kosong = KategoriPelatihan::create(['nama' => 'Pengacara Bisnis']);

    $this->mitra = function (Cabang $cabang, array $kategori, array $attributes = []) {
        $sekolah = Fixtures::sekolah($cabang, null, ['tanggal_mitra' => now()->subMonths(3)->toDateString()] + $attributes);
        $sekolah->kategori()->attach(collect($kategori)->map->id->all());

        return $sekolah;
    };

    $this->klasemen = fn (?User $user = null) => KlasemenKategori::handle($user ?? $this->ap)->keyBy('nama');
});

describe('counting rules', function () {
    test('AC-RNK-01 a school in two categories counts once in each', function () {
        ($this->mitra)($this->cabangA, [$this->jasa, $this->barber]);

        $k = ($this->klasemen)();

        expect($k['Jasa Layanan Bisnis']['jumlah'])->toBe(1)->and($k['Barbershop']['jumlah'])->toBe(1)->and($k['Akuntan Bisnis']['jumlah'])->toBe(0);
    });

    test('AC-RNK-02 / BR-RNK-01 sessions do not multiply: five activities still count the school once', function () {
        $sekolah = ($this->mitra)($this->cabangA, [$this->jasa]);
        foreach (range(1, 5) as $i) {
            Fixtures::kegiatan($sekolah, $this->mentor, ['tahap' => Tahap::TindakLanjut, 'urutan' => $i, 'nama_sesi' => "Sesi {$i}"]);
        }

        expect(($this->klasemen)()['Jasa Layanan Bisnis']['jumlah'])->toBe(1);
    });

    test('AC-RNK-06 / BR-RNK-04 a candidate school (not yet an official partner) is not counted', function () {
        $calon = Fixtures::sekolah($this->cabangA, null, ['tanggal_mitra' => null]);
        $calon->kategori()->attach($this->jasa->id);
        ($this->mitra)($this->cabangA, [$this->jasa]);

        expect(($this->klasemen)()['Jasa Layanan Bisnis']['jumlah'])->toBe(1);
    });

    test('BR-RNK-04 an archived partner school is still counted', function () {
        ($this->mitra)($this->cabangA, [$this->jasa], ['diarsipkan_at' => now(), 'alasan_arsip' => 'Tidak aktif']);

        expect(($this->klasemen)()['Jasa Layanan Bisnis']['jumlah'])->toBe(1);
    });

    test('AC-RNK-05 a category without schools is listed with 0', function () {
        ($this->mitra)($this->cabangA, [$this->jasa]);

        expect(($this->klasemen)()['Pengacara Bisnis'])->toMatchArray(['jumlah' => 0]);
    });

    test('an inactive category without partners is hidden, one that partners still hold stays', function () {
        $lama = KategoriPelatihan::create(['nama' => 'Kategori Lama', 'aktif' => false]);
        $dipakai = KategoriPelatihan::create(['nama' => 'Kategori Dipakai', 'aktif' => false]);
        ($this->mitra)($this->cabangA, [$dipakai]);

        $k = ($this->klasemen)();

        expect($k->has($lama->nama))->toBeFalse()->and($k['Kategori Dipakai']['jumlah'])->toBe(1);
    });
});

describe('ranking', function () {
    test('AC-RNK-03 sorted by count descending; equal counts share a rank (dense ranking)', function () {
        foreach (range(1, 3) as $i) {
            ($this->mitra)($this->cabangA, [$this->jasa]);
        }
        ($this->mitra)($this->cabangB, [$this->barber]);
        ($this->mitra)($this->cabangB, [$this->akuntan]);

        $baris = KlasemenKategori::handle($this->ap);

        expect($baris->pluck('nama')->all())->toBe(['Jasa Layanan Bisnis', 'Akuntan Bisnis', 'Barbershop', 'Pengacara Bisnis'])
            ->and($baris->pluck('jumlah')->all())->toBe([3, 1, 1, 0])
            ->and($baris->pluck('peringkat')->all())->toBe([1, 2, 2, 3]);
    });
});

describe('scope and access', function () {
    test('AC-RNK-04 / AC-RNK-07 every role sees the same province-wide numbers, not its branch or assignment', function () {
        ($this->mitra)($this->cabangA, [$this->jasa]);
        ($this->mitra)($this->cabangB, [$this->jasa]);
        ($this->mitra)($this->cabangB, [$this->jasa]);

        foreach ([$this->sa, $this->ap, $this->acA, $this->mentor] as $user) {
            expect(($this->klasemen)($user)['Jasa Layanan Bisnis']['jumlah'])->toBe(3);
        }
    });

    test('an inactive user cannot read the klasemen', function () {
        $this->mentor->forceFill(['aktif' => false])->save();

        expect(fn () => KlasemenKategori::handle($this->mentor->refresh()))->toThrow(AuthorizationException::class);
    });

    test('the widget is shown to every active role and hidden from guests and inactive users', function () {
        foreach ([[$this->sa, 'admin'], [$this->ap, 'admin'], [$this->acA, 'admin'], [$this->mentor, 'mentor']] as [$user, $panel]) {
            $this->actingAs($user);
            Filament::setCurrentPanel($panel);
            expect(KlasemenKategoriChart::canView())->toBeTrue();
        }

        $this->mentor->forceFill(['aktif' => false])->save();
        $this->actingAs($this->mentor->refresh());
        expect(KlasemenKategoriChart::canView())->toBeFalse();

        auth()->logout();
        expect(KlasemenKategoriChart::canView())->toBeFalse();
    });
});

describe('the chart', function () {
    test('FR-RNK-03 it is a horizontal bar chart with the ranked data, whole numbers and brand reds', function () {
        ($this->mitra)($this->cabangA, [$this->jasa, $this->barber]);
        ($this->mitra)($this->cabangA, [$this->jasa]);
        $this->actingAs($this->ap);
        Filament::setCurrentPanel('admin');

        $widget = Livewire::test(KlasemenKategoriChart::class)->instance();
        $data = (fn () => $this->getData())->call($widget);
        $options = (fn () => $this->getOptions())->call($widget);
        $type = (fn () => $this->getType())->call($widget);

        expect($type)->toBe('bar')
            ->and($options['indexAxis'])->toBe('y')
            ->and($options['plugins']['legend']['display'])->toBeFalse()
            ->and($options['scales']['x']['ticks']['precision'])->toBe(0)
            ->and($options['scales']['x']['beginAtZero'])->toBeTrue()
            ->and($data['datasets'][0]['data'])->toBe([2, 1, 0, 0])
            // First place is the dark red, the others the brand red.
            ->and($data['datasets'][0]['backgroundColor'])->toBe([BrandPalette::DARK, BrandPalette::PRIMARY, BrandPalette::PRIMARY, BrandPalette::PRIMARY])
            // Each label shows rank, name and the count, so values are readable without hovering.
            ->and(implode(' ', $data['labels'][0]))->toContain('1. Jasa Layanan Bisnis', '2 sekolah')
            ->and($data['labels'][2][0])->toStartWith('3. ');
    });

    test('with no partner schools yet no bar is highlighted as the winner', function () {
        $this->actingAs($this->ap);
        Filament::setCurrentPanel('admin');

        $data = (fn () => $this->getData())->call(Livewire::test(KlasemenKategoriChart::class)->instance());

        expect($data['datasets'][0]['data'])->toBe([0, 0, 0, 0])
            ->and(array_unique($data['datasets'][0]['backgroundColor']))->toBe([BrandPalette::PRIMARY]);
    });

    test('long category names wrap into several label lines', function () {
        $panjang = KategoriPelatihan::create(['nama' => 'Konten Kreator, Live Sales, Musik AI + DJ dan Penyiaran Digital']);
        ($this->mitra)($this->cabangA, [$panjang]);
        $this->actingAs($this->ap);
        Filament::setCurrentPanel('admin');

        $data = (fn () => $this->getData())->call(Livewire::test(KlasemenKategoriChart::class)->instance());

        expect(collect($data['labels'][0])->count())->toBeGreaterThan(2)
            ->and(collect($data['labels'][0])->every(fn ($baris) => mb_strlen($baris) <= 20 || str_ends_with($baris, ' sekolah')))->toBeTrue();
    });
});
