<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'status',
        'budget',
        'deadline',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'deadline' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationship
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
                ->withPivot('tagged_at');
    }

    // Scopes
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    // Accessors
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn() => match ($this->status) {
                'draft' => 'Draft',
                'active' => 'Active',
                'on_hold' => 'On Hold',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                default => ucfirst($this->status),
            },
        );
    }

    protected function formattedBudget(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->budget
            ? '₹' . number_format($this->budget, 2)
            : 'No budget set',
        );
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->deadline
            && $this->deadline->isPast()
            && !in_array($this->status, ['completed', 'cancelled']),
        );
    }

    protected function daysUntilDeadline(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->deadline
            ? now()->diffInDays($this->deadline, false)
            : null,
        );
    }
}