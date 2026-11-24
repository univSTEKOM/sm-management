<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Master\Actions\UpdateAppSetting;
use App\Domain\Master\Models\AppSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Halaman Setting (Superadmin, `master.manage`).
 *
 * @property-read Schema $form
 */
class PengaturanAplikasi extends Page
{
    protected static ?string $slug = 'pengaturan';

    protected static ?string $title = 'Pengaturan Aplikasi';

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 20;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manageSettings', AppSetting::class);
    }

    public function mount(): void
    {
        $this->form->fill([
            AppSetting::LOKASI_KAMPUS_PUSAT => AppSetting::get(AppSetting::LOKASI_KAMPUS_PUSAT),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Textarea::make(AppSetting::LOKASI_KAMPUS_PUSAT)
                    ->label('Lokasi kampus pusat')
                    ->helperText('Lokasi default Pelatihan Awal (selalu offline di kampus pusat).')
                    ->required()
                    ->maxLength(500),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Simpan')->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        try {
            app(UpdateAppSetting::class)->handle(auth()->user(), AppSetting::LOKASI_KAMPUS_PUSAT, $data[AppSetting::LOKASI_KAMPUS_PUSAT]);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Tidak dapat disimpan')->body(collect($e->errors())->flatten()->implode(' '))->send();

            return;
        }

        Notification::make()->success()->title('Pengaturan disimpan')->send();
    }
}
