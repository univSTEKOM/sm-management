<?php

/*
 * School registration rules checked again in Fase 3 (AC-SCH-01/02/03/14/20, BR-SCH-06).
 */

use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Sekolah\Actions\ArchiveSekolah;
use App\Domain\Sekolah\Actions\DuplicateSekolahGuard;
use App\Domain\Sekolah\Actions\RegisterSekolah;
use App\Domain\Sekolah\Models\NarahubungRiwayat;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create();
    $this->cabangB = Cabang::factory()->create();
    $this->kab = KabupatenKota::factory()->create();
    $this->ap = User::factory()->adminPusat()->create();
    $this->acA = User::factory()->adminCabang($this->cabangA)->create();
    $this->input = fn (array $extra = []) => $extra + [
        'nama' => 'SMAN 1 Semarang',
        'jenjang' => 'SMA',
        'kabupaten_kota_id' => $this->kab->id,
        'cabang_id' => $this->cabangA->id,
        'narahubung_utama' => ['nama' => 'Bu Sri', 'nomor_wa' => '0812 3456 7890'],
    ];
});

test('AC-SCH-01 a school without branch or regency is refused with a required-field message', function () {
    try {
        app(RegisterSekolah::class)->handle($this->ap, ($this->input)(['cabang_id' => null, 'kabupaten_kota_id' => null]));
        $this->fail('expected validation error');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['cabang_id', 'kabupaten_kota_id']);
    }
});

test('AC-SCH-02 a normalised duplicate name in the same regency and level is refused', function () {
    app(RegisterSekolah::class)->handle($this->ap, ($this->input)());

    expect(fn () => app(RegisterSekolah::class)->handle($this->ap, ($this->input)(['nama' => 'sman 1  semarang'])))
        ->toThrow(ValidationException::class);
});

test('AC-SCH-03 similar (not identical) names are offered as a warning', function () {
    app(RegisterSekolah::class)->handle($this->ap, ($this->input)());

    expect(DuplicateSekolahGuard::similarNames('SMAN 1 Semarag'))->toContain('SMAN 1 Semarang');
});

test('AC-SCH-14 admin cabang always registers into the own branch; admin pusat must choose', function () {
    $sekolah = app(RegisterSekolah::class)->handle($this->acA, ($this->input)(['cabang_id' => $this->cabangB->id]));
    expect($sekolah->cabang_id)->toBe($this->cabangA->id);

    expect(fn () => app(RegisterSekolah::class)->handle($this->ap, ($this->input)(['nama' => 'SMAN 2', 'cabang_id' => null])))
        ->toThrow(ValidationException::class);
});

test('AC-SCH-20 an archived school still blocks a duplicate registration', function () {
    $sekolah = app(RegisterSekolah::class)->handle($this->ap, ($this->input)());
    app(ArchiveSekolah::class)->handle($this->ap, $sekolah, 'Mundur');

    expect(fn () => app(RegisterSekolah::class)->handle($this->ap, ($this->input)()))->toThrow(ValidationException::class);
});

test('BR-SCH-06 the first primary contact is written to the history and the WhatsApp format is checked', function () {
    expect(fn () => app(RegisterSekolah::class)->handle($this->ap, ($this->input)(['narahubung_utama' => ['nama' => 'X', 'nomor_wa' => 'bukan nomor']])))
        ->toThrow(ValidationException::class);

    $sekolah = app(RegisterSekolah::class)->handle($this->ap, ($this->input)());

    $riwayat = NarahubungRiwayat::where('narahubung_id', $sekolah->narahubungUtama->id)->sole();
    expect($riwayat->data_sebelum)->toBeNull()->and($riwayat->data_sesudah['nama'])->toBe('Bu Sri');
});
