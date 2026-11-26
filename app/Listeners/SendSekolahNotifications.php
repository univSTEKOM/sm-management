<?php

namespace App\Listeners;

use App\Domain\Jadwal\Events\JadwalDibuat;
use App\Domain\Jadwal\Events\JadwalDiubah;
use App\Domain\Jadwal\Events\PelaksanaDitunjuk;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Mentor\Events\MentorDitugaskan;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Sekolah\Events\UsulanNarahubungDiajukan;
use App\Domain\Sekolah\Events\UsulanNarahubungDiputuskan;
use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;

/**
 * In-app notifications for Fase 3 events (FR-NTF-02 recommended triggers: new mentor
 * assignment, contact proposals). The actor never notifies themselves (BR-NTF-02).
 */
class SendSekolahNotifications
{
    public function usulanDiajukan(UsulanNarahubungDiajukan $event): void
    {
        $usulan = NarahubungUsulan::query()->with('sekolah', 'pengusul')->find($event->usulanId);

        if ($usulan === null) {
            return;
        }

        $recipients = User::query()
            ->where('aktif', true)
            ->where(fn ($q) => $q->where('role', Role::AdminPusat->value)
                ->orWhere(fn ($q) => $q->where('role', Role::AdminCabang->value)->where('cabang_id', $usulan->sekolah->cabang_id)))
            ->get();

        $this->send($recipients, $event->actorId, Notification::make()
            ->title('Usulan perubahan narahubung')
            ->body("{$usulan->pengusul->name} mengusulkan perubahan narahubung {$usulan->sekolah->nama}.")
            ->info());
    }

    public function usulanDiputuskan(UsulanNarahubungDiputuskan $event): void
    {
        $usulan = NarahubungUsulan::query()->with('sekolah', 'pengusul')->find($event->usulanId);

        if ($usulan === null) {
            return;
        }

        $disetujui = $usulan->status === NarahubungUsulan::DISETUJUI;

        $this->send(collect([$usulan->pengusul]), $event->actorId, Notification::make()
            ->title($disetujui ? 'Usulan narahubung disetujui' : 'Usulan narahubung ditolak')
            ->body($disetujui
                ? "Usulan Anda untuk {$usulan->sekolah->nama} sudah diterapkan."
                : "Usulan Anda untuk {$usulan->sekolah->nama} ditolak: {$usulan->alasan_penolakan}")
            ->status($disetujui ? 'success' : 'warning'));
    }

    public function mentorDitugaskan(MentorDitugaskan $event): void
    {
        $penugasan = PenugasanMentor::query()->with('sekolah', 'mentor')->find($event->penugasanId);

        if ($penugasan === null) {
            return;
        }

        $this->send(collect([$penugasan->mentor]), $event->actorId, Notification::make()
            ->title('Penugasan sekolah baru')
            ->body("Anda ditugaskan sebagai mentor penanggung jawab {$penugasan->sekolah->nama}.")
            ->info());
    }

    /** AC-JTL-10 / FR-JDA: new schedule goes to the executing mentor, Admin Pusat and the branch admin. */
    public function jadwalDibuat(JadwalDibuat $event): void
    {
        $kegiatan = Kegiatan::query()->with('sekolah', 'mentorPelaksana')->find($event->kegiatanId);

        if ($kegiatan === null) {
            return;
        }

        $this->send($this->adminRecipients($kegiatan)->push($this->pelaksana($kegiatan)), $event->actorId, Notification::make()
            ->title('Jadwal baru')
            ->body("{$kegiatan->nama_sesi} {$kegiatan->sekolah->nama} dijadwalkan {$kegiatan->tanggal->translatedFormat('d M Y')}.")
            ->info());
    }

    /** BR-ACT-07 / AC-JTL-08: a mentor's change reaches the admins; an admin's change reaches the executing mentor. */
    public function jadwalDiubah(JadwalDiubah $event): void
    {
        $kegiatan = Kegiatan::query()->with('sekolah', 'mentorPelaksana')->find($event->kegiatan->id);

        if ($kegiatan === null) {
            return;
        }

        $recipients = $event->actor->role === Role::Mentor
            ? $this->adminRecipients($kegiatan)
            : collect([$this->pelaksana($kegiatan)]);

        $this->send($recipients, $event->actor->id, Notification::make()
            ->title('Jadwal diubah')
            ->body("{$kegiatan->nama_sesi} {$kegiatan->sekolah->nama} dipindah ke {$kegiatan->tanggal->translatedFormat('d M Y')}.")
            ->warning());
    }

    public function pelaksanaDitunjuk(PelaksanaDitunjuk $event): void
    {
        $kegiatan = Kegiatan::query()->with('sekolah', 'mentorPelaksana')->find($event->kegiatanId);

        if ($kegiatan === null) {
            return;
        }

        $this->send(collect([$this->pelaksana($kegiatan)]), $event->actorId, Notification::make()
            ->title('Anda ditunjuk sebagai pelaksana')
            ->body("{$kegiatan->nama_sesi} {$kegiatan->sekolah->nama}, {$kegiatan->tanggal->translatedFormat('d M Y')}.")
            ->info());
    }

    private function pelaksana(Kegiatan $kegiatan): User
    {
        /** @var User $mentor */
        $mentor = $kegiatan->mentorPelaksana;

        return $mentor;
    }

    /** @return Collection<int, User> */
    private function adminRecipients(Kegiatan $kegiatan): Collection
    {
        return User::query()
            ->where('aktif', true)
            ->where(fn ($q) => $q->where('role', Role::AdminPusat->value)
                ->orWhere(fn ($q) => $q->where('role', Role::AdminCabang->value)->where('cabang_id', $kegiatan->sekolah->cabang_id)))
            ->get();
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UsulanNarahubungDiajukan::class => 'usulanDiajukan',
            UsulanNarahubungDiputuskan::class => 'usulanDiputuskan',
            MentorDitugaskan::class => 'mentorDitugaskan',
            JadwalDibuat::class => 'jadwalDibuat',
            JadwalDiubah::class => 'jadwalDiubah',
            PelaksanaDitunjuk::class => 'pelaksanaDitunjuk',
        ];
    }

    /** @param  Collection<int, User>  $recipients */
    private function send(Collection $recipients, int $actorId, Notification $notification): void
    {
        // One notification per person, never to the actor (BR-NTF-02).
        $recipients = $recipients->unique('id')->filter(fn (User $user) => $user->id !== $actorId && $user->aktif)->values();

        if ($recipients->isNotEmpty()) {
            $notification->sendToDatabase($recipients);
        }
    }
}
