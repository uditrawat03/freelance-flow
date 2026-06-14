<?php

namespace Tests\Feature;

use App\Livewire\Settings\LocaleSwitcher;
use App\Support\FreelanceFlowConfig;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_user_can_save_locale_preference(): void
    {
        Livewire::test(LocaleSwitcher::class)
            ->set('locale', 'hi')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertSame('hi', $this->user->fresh()->locale);
        $this->assertSame('hi', app()->getLocale());
    }

    public function test_locale_switcher_rejects_unsupported_locale(): void
    {
        Livewire::test(LocaleSwitcher::class)
            ->set('locale', 'de')
            ->call('save')
            ->assertHasErrors(['locale']);

        $this->assertSame('en', $this->user->fresh()->getLocale());
    }

    public function test_web_requests_use_authenticated_users_saved_locale(): void
    {
        $this->user->setLocale('hi');

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('भाषा');
    }

    public function test_locale_formatting_helpers_have_stable_fallbacks(): void
    {
        config(['freelanceflow.invoice.currency' => 'INR']);

        $currency = FreelanceFlowConfig::formatCurrency(1234.5, 'en');
        $date = FreelanceFlowConfig::formatDate(Carbon::parse('2026-01-05'), 'en');

        $this->assertNotSame('', $currency);
        $this->assertStringContainsString('1,234', $currency);
        $this->assertSame('January 5, 2026', $date);
    }
}
