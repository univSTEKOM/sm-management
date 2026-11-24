<?php

namespace App\Listeners;

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

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UsulanNarahubungDiajukan::class => 'usulanDiajukan',
            UsulanNarahubungDiputuskan::class => 'usulanDiputuskan',
            MentorDitugaskan::class => 'mentorDitugaskan',
        ];
    }

    /** @param  Collection<int, User>  $recipients */
    private function send(Collection $recipients, int $actorId, Notification $notification): void
    {
        $recipients = $recipients->filter(fn (User $user) => $user->id !== $actorId && $user->aktif)->values();

        if ($recipients->isNotEmpty()) {
            $notification->sendToDatabase($recipients);
        }
    }
}
