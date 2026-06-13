<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Settings\TwoFactor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_user_can_enable_confirm_and_disable_two_factor_authentication(): void
    {
        Livewire::test(TwoFactor::class)
            ->call('beginEnable')
            ->assertSet('step', 'confirming_enable');

        $this->user->refresh();
        $secret = $this->user->decryptedTwoFactorSecret();

        $this->assertNotNull($secret);
        $this->assertNotSame($secret, DB::table('users')->whereKey($this->user->id)->value('two_factor_secret'));
        $this->assertTrue($this->user->hasTwoFactorPending());

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        Livewire::test(TwoFactor::class)
            ->set('step', 'confirming_enable')
            ->set('verificationCode', $code)
            ->call('confirmEnable')
            ->assertHasNoErrors()
            ->assertSet('step', 'enabled');

        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(session('two_factor_verified'));

        Livewire::test(TwoFactor::class)
            ->set('confirmPassword', 'password')
            ->call('disable')
            ->assertHasNoErrors()
            ->assertSet('step', 'idle');

        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_recovery_codes_are_single_use(): void
    {
        $this->user->enableTwoFactor();
        $this->user->confirmTwoFactor();

        $code = $this->user->decryptedRecoveryCodes()[0];

        $this->assertTrue($this->user->useRecoveryCode($code));
        $this->assertFalse($this->user->fresh()->useRecoveryCode($code));
    }

    public function test_login_redirects_two_factor_users_to_challenge(): void
    {
        $this->user->enableTwoFactor();
        $this->user->confirmTwoFactor();
        auth()->logout();

        Livewire::test(Login::class)
            ->set('email', $this->user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertFalse(session('two_factor_verified'));
        $this->assertSame(route('dashboard'), session('two_factor_intended'));
    }

    public function test_two_factor_middleware_blocks_protected_routes_until_verified(): void
    {
        $this->user->enableTwoFactor();
        $this->user->confirmTwoFactor();

        $this->get(route('settings.index'))
            ->assertRedirect(route('two-factor.challenge'));

        session(['two_factor_verified' => true]);

        $this->get(route('settings.index'))->assertOk();
    }

    public function test_challenge_accepts_recovery_code_and_consumes_it(): void
    {
        $this->user->enableTwoFactor();
        $this->user->confirmTwoFactor();

        $code = $this->user->decryptedRecoveryCodes()[0];

        session(['two_factor_intended' => route('dashboard')]);

        Livewire::test(TwoFactorChallenge::class)
            ->call('toggleRecoveryCode')
            ->set('code', $code)
            ->call('verify')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(session('two_factor_verified'));
        $this->assertFalse($this->user->fresh()->useRecoveryCode($code));
    }

    public function test_unconfirmed_two_factor_secret_does_not_force_challenge(): void
    {
        $user = User::factory()->create();
        $user->enableTwoFactor();

        $this->actingAs($user);

        $this->get(route('settings.index'))->assertOk();
    }
}
