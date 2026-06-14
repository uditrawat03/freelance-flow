<?php

namespace App\Livewire\Settings;

use App\Support\FreelanceFlowConfig;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class LocaleSwitcher extends Component
{
    public string $locale = 'en';

    public function mount(): void
    {
        $this->locale = auth()->user()->getLocale();
    }

    public function save(): void
    {
        $this->validate([
            'locale' => ['required', 'string', Rule::in(FreelanceFlowConfig::supportedLocales())],
        ]);

        auth()->user()->setLocale($this->locale);

        app()->setLocale($this->locale);
        Carbon::setLocale($this->locale);

        $this->dispatch('notify', message: __('app.settings.language_saved'), type: 'success');
    }

    public function render()
    {
        return view('livewire.settings.locale-switcher', [
            'locales' => collect(FreelanceFlowConfig::supportedLocales())
                ->mapWithKeys(fn (string $locale): array => [$locale => FreelanceFlowConfig::localeName($locale)])
                ->all(),
        ]);
    }
}
