<?php

namespace App\Filament\Admin\Resources\Sekolahs\Pages;

use App\Domain\Sekolah\Actions\RegisterSekolah;
use App\Filament\Admin\Resources\Sekolahs\SekolahResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSekolah extends CreateRecord
{
    protected static string $resource = SekolahResource::class;

    /** Persisted by the domain action, never directly by Filament (risk F-R1). */
    protected function handleRecordCreation(array $data): Model
    {
        return app(RegisterSekolah::class)->handle(auth()->user(), $data);
    }
}
