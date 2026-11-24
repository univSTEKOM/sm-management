<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Domain\Pengguna\Actions\CreateUser;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Support\RunsDomainAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->using(fn (array $data, CreateAction $action) => RunsDomainAction::attempt(
                    $action,
                    fn () => app(CreateUser::class)->handle(auth()->user(), $data),
                )),
        ];
    }
}
