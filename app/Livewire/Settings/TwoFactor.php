<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Rule;
use Livewire\Component;

class TwoFactor extends Component
{
    public string $step = 'idle';

    #[Rule('required|string')]
    public string $verificationCode = '';

    public string $confirmPassword = '';

    public bool $showRecoveryCodes = false;

    public function mount(): void
    {
        $this->step = auth()->user()->hasTwoFactorEnabled() ? 'enabled' : 'idle';
    }

    public function beginEnable(): void
    {
        auth()->user()->enableTwoFactor();

        $this->step = 'confirming_enable';
        $this->verificationCode = '';
        $this->resetValidation();
    }

    public function confirmEnable(): void
    {
        $this->validateOnly('verificationCode');

        if (! auth()->user()->fresh()->verifyTwoFactorCode($this->verificationCode)) {
            $this->addError('verificationCode', __('auth.two_factor.invalid_code'));

            return;
        }

        auth()->user()->fresh()->confirmTwoFactor();

        session(['two_factor_verified' => true]);

        $this->step = 'enabled';
        $this->showRecoveryCodes = true;
        $this->verificationCode = '';
        $this->dispatch('notify', message: 'Two-factor authentication enabled.', type: 'success');
    }

    public function cancelEnable(): void
    {
        auth()->user()->disableTwoFactor();

        $this->step = 'idle';
        $this->verificationCode = '';
        $this->resetValidation();
    }

    public function regenerateRecoveryCodes(): void
    {
        auth()->user()->regenerateRecoveryCodes();

        $this->showRecoveryCodes = true;
        $this->dispatch('notify', message: 'Recovery codes regenerated. Save them now.', type: 'warning');
    }

    public function disable(): void
    {
        if (! Hash::check($this->confirmPassword, auth()->user()->password)) {
            $this->addError('confirmPassword', __('auth.password'));

            return;
        }

        auth()->user()->disableTwoFactor();
        session()->forget('two_factor_verified');

        $this->step = 'idle';
        $this->confirmPassword = '';
        $this->showRecoveryCodes = false;
        $this->dispatch('notify', message: 'Two-factor authentication disabled.', type: 'info');
    }

    public function render()
    {
        $user = auth()->user()->fresh();

        return view('livewire.settings.two-factor', [
            'user' => $user,
            'qrCodeSvg' => $this->step === 'confirming_enable' ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $this->showRecoveryCodes ? $user->decryptedRecoveryCodes() : [],
            'secret' => $this->step === 'confirming_enable' ? $user->decryptedTwoFactorSecret() : null,
        ]);
    }
}
