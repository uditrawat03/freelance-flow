<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'colour'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    // Auto-generate slug from name on save
    protected function name(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                $this->attributes['slug'] = str($value)->slug()->toString();
                return $value;
            },
        );
    }
}