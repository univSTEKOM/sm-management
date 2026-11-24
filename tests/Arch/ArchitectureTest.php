<?php

/*
 * SP-05 / Implementation Plan 4.4: business logic is independent of the UI.
 */

arch('domain layer does not depend on Filament or Livewire')
    ->expect('App\Domain')
    ->not->toUse(['Filament', 'Livewire']);

arch('authorization and progress support do not depend on Filament or Livewire')
    ->expect(['App\Support\Authorization', 'App\Support\Progress', 'App\Support\Audit', 'App\Support\Concurrency'])
    ->not->toUse(['Filament', 'Livewire']);

arch('Filament layer does not run raw database queries')
    ->expect('App\Filament')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('domain actions are final classes with a handle method')
    ->expect('App\Domain\*\Actions')
    ->classes()
    ->toBeFinal();

arch('no debugging helpers left in application code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
