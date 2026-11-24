<?php

namespace App\Filament\Admin\Resources\Sekolahs\Pages;

use App\Domain\Sekolah\Actions\UpdateSekolah;
use App\Domain\Sekolah\Models\Sekolah;
use App\Filament\Admin\Resources\Sekolahs\SekolahResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSekolah extends EditRecord
{
    protected static string $resource = SekolahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Sekolah $sekolah */
        $sekolah = $this->getRecord();
        $data['kategori_ids'] = $sekolah->kategori()->pluck('kategori_pelatihan.id')->all();

        return $data;
    }

    /** Persisted by the domain action, never directly by Filament (risk F-R1). */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $version = isset($data['version']) ? (int) $data['version'] : null;
        unset($data['version'], $data['cabang_id']);

        /** @var Sekolah $record */
        return app(UpdateSekolah::class)->handle(auth()->user(), $record, $data, $version);
    }
}
