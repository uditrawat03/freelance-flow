<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    // Get the currently active workspace from the session
    public function currentWorkspace(): ?Workspace
    {
        $workspaceId = session('current_workspace_id');

        if ($workspaceId) {
            return $this->workspaces()->find($workspaceId);
        }

        // Default to the first workspace the user belongs to
        return $this->workspaces()->first();
    }

    // Switch the active workspace
    public function switchWorkspace(Workspace $workspace): void
    {
        if ($this->hasWorkspaceAccess($workspace)) {
            session(['current_workspace_id' => $workspace->id]);
        }
    }

    public function hasWorkspaceAccess(Workspace $workspace): bool
    {
        return $this->workspaces()->where('workspace_id', $workspace->id)->exists();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function hasTwoFactorPending(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at === null;
    }

    public function enableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => Crypt::encryptString(app(Google2FA::class)->generateSecretKey()),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->generateRecoveryCodes())),
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function confirmTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function decryptedTwoFactorSecret(): ?string
    {
        return filled($this->two_factor_secret)
            ? Crypt::decryptString($this->two_factor_secret)
            : null;
    }

    public function decryptedRecoveryCodes(): array
    {
        if (blank($this->two_factor_recovery_codes)) {
            return [];
        }

        $codes = json_decode(Crypt::decryptString($this->two_factor_recovery_codes), true);

        return is_array($codes) ? $codes : [];
    }

    public function generateRecoveryCodes(): array
    {
        return Collection::times(config('two_factor.recovery_code_count', 8), fn (): string => sprintf(
            '%s-%s',
            Str::upper(Str::random(5)),
            Str::upper(Str::random(5)),
        ))->all();
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->generateRecoveryCodes())),
        ])->save();
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        $secret = $this->decryptedTwoFactorSecret();

        if (blank($secret)) {
            return false;
        }

        return app(Google2FA::class)->verifyKey(
            $secret,
            preg_replace('/\s+/', '', $code) ?? '',
            config('two_factor.window', 1),
        );
    }

    public function useRecoveryCode(string $code): bool
    {
        $normalizedCode = Str::upper(trim($code));
        $codes = $this->decryptedRecoveryCodes();
        $matchingCode = collect($codes)->first(fn (string $storedCode): bool => hash_equals($storedCode, $normalizedCode));

        if ($matchingCode === null) {
            return false;
        }

        $remainingCodes = array_values(array_filter(
            $codes,
            fn (string $storedCode): bool => ! hash_equals($storedCode, $matchingCode),
        ));

        $this->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remainingCodes)),
        ])->save();

        return true;
    }

    public function twoFactorQrCodeSvg(): string
    {
        $url = app(Google2FA::class)->getQRCodeUrl(
            config('two_factor.issuer', config('app.name', 'FreelanceFlow')),
            $this->email,
            $this->decryptedTwoFactorSecret(),
        );

        return (new Writer(new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd,
        )))->writeString($url);
    }
}
