<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('layouts.auth')]
class TwoFactorChallenge extends Component
{
    #[Rule('required|string')]
    public string $code = '';

    public bool $usingRecoveryCode = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasTwoFactorEnabled(), 404);
    }

    public function toggleRecoveryCode(): void
    {
        $this->usingRecoveryCode = ! $this->usingRecoveryCode;
        $this->code = '';
        $this->resetValidation();
    }

    public function verify(): void
    {
        $this->validate();

        $user = auth()->user();
        $verified = $this->usingRecoveryCode
            ? $user->useRecoveryCode($this->code)
            : $user->verifyTwoFactorCode($this->code);

        if (! $verified) {
            $this->addError('code', $this->usingRecoveryCode
                ? __('auth.two_factor.invalid_recovery')
                : __('auth.two_factor.invalid_code'));

            return;
        }

        session(['two_factor_verified' => true]);

        $this->redirect(session()->pull('two_factor_intended', route('dashboard')), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge');
    }
}
