<?php

namespace App\Filament\Support;

use App\Domain\Master\Actions\SaveMasterData;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/** Single-page (modal) management of a master data type; creation goes through SaveMasterData. */
abstract class ManageMasterRecords extends ManageRecords
{
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->using(fn (array $data, CreateAction $action) => RunsDomainAction::attempt(
                    $action,
                    fn () => app(SaveMasterData::class)->handle(auth()->user(), static::getResource()::getModel(), $data),
                )),
        ];
    }
}
