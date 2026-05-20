<?php

namespace App\Models;

use App\Models\Scopes\BelongsToWorkspace;
use App\Models\Scopes\OwnedByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // static::addGlobalScope(new OwnedByUser);
        static::addGlobalScope(new BelongsToWorkspace);

        // Auto-assign user_id on creation
        static::creating(function (self $model) {
            if (auth()->check() && ! $model->workspace_id) {
                $workspace = auth()->user()->currentWorkspace();
                $model->workspace_id = $workspace?->id;
            }
        });
    }

    protected $fillable = [
        'workspace_id', 'user_id',
        'name', 'email', 'phone', 'company', 'notes', 'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // --- Relationships ---
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // --- Scopes ---
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('status', 'inactive');
    }

    public function scopeLeads(Builder $query): void
    {
        $query->where('status', 'lead');
    }

    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeWithPhone(Builder $query): void
    {
        $query->whereNotNull('phone');
    }

    public function scopeWithCompany(Builder $query): void
    {
        $query->whereNotNull('company');
    }

    // --- Accessors ---
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company
                ? "{$this->name} ({$this->company})"
                : $this->name,
        );
    }

    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(explode(' ', $this->name))
                ->map(fn ($word) => strtoupper($word[0]))
                ->take(2)
                ->implode(''),
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'active'   => 'Active',
                'inactive' => 'Inactive',
                'lead'     => 'Lead',
                default    => ucfirst($this->status),
            },
        );
    }

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'active',
        );
    }

    // --- Mutators ---
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => str($value)->title()->toString(),
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => strtolower(trim($value)),
        );
    }

    protected function company(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: fn (?string $value) => $value ? trim($value) : null,
        );
    }
}