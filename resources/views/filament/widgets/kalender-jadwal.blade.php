@php
    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
    $cabangOptions = $this->cabangOptions();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="kal-filters mb-4 grid grid-cols-2 gap-3 md:grid-cols-4" data-testid="kalender-filter">
            <label class="grid gap-1 text-sm">
                <span class="font-medium">Jenis</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="tahap" aria-label="Filter jenis">
                        <option value="">Semua jenis</option>
                        @foreach ($this->tahapOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-medium">Status</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="status" aria-label="Filter status">
                        <option value="">Semua status</option>
                        @foreach ($this->statusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @if ($cabangOptions !== [])
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Cabang</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="cabang" aria-label="Filter cabang">
                            <option value="">Semua cabang</option>
                            @foreach ($cabangOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            @endif
            <label class="grid gap-1 text-sm">
                <span class="font-medium">Mentor</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="mentor" aria-label="Filter mentor">
                        <option value="">Semua mentor</option>
                        @foreach ($this->mentorOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>

        <div class="kal-legend mb-3 flex flex-wrap gap-4 text-xs">
            <span class="inline-flex items-center gap-1"><i class="kal-dot" style="background: {{ \App\Domain\Jadwal\Support\KalenderEvent::WARNA_AWAL }}"></i> Pelatihan Awal</span>
            <span class="inline-flex items-center gap-1"><i class="kal-dot" style="background: {{ \App\Domain\Jadwal\Support\KalenderEvent::WARNA_TL }}"></i> Tindak Lanjut</span>
            <span class="inline-flex items-center gap-1"><i class="kal-dot" style="background: {{ \App\Domain\Jadwal\Support\KalenderEvent::WARNA_BATAL }}"></i> Dibatalkan</span>
        </div>

        {{-- Narrow screens start on the agenda view (month grids are unreadable at 360px). --}}
        <div
            x-data
            x-init="
                if (window.innerWidth < 640) {
                    let tries = 0
                    const t = setInterval(() => {
                        if (document.querySelector('.filament-fullcalendar .fc') || ++tries > 50) {
                            clearInterval(t)
                            window.dispatchEvent(new CustomEvent('filament-fullcalendar--view', { detail: { view: 'listWeek' } }))
                        }
                    }, 100)
                }
            "
        ></div>

        <div wire:ignore x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('filament-fullcalendar-alpine', 'saade/filament-fullcalendar') }}"
            x-ignore x-data="fullcalendar({
                locale: @js($plugin->getLocale()),
                plugins: @js($plugin->getPlugins()),
                schedulerLicenseKey: @js($plugin->getSchedulerLicenseKey()),
                timeZone: @js($plugin->getTimezone()),
                config: @js($this->getConfig()),
                editable: false,
                selectable: false,
                eventClassNames: {!! htmlspecialchars($this->eventClassNames(), ENT_COMPAT) !!},
                eventContent: {!! htmlspecialchars($this->eventContent(), ENT_COMPAT) !!},
                eventDidMount: {!! htmlspecialchars($this->eventDidMount(), ENT_COMPAT) !!},
                eventWillUnmount: {!! htmlspecialchars($this->eventWillUnmount(), ENT_COMPAT) !!},
            })" class="filament-fullcalendar" data-testid="kalender"></div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
