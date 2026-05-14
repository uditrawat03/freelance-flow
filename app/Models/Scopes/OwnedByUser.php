<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnedByUser implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if a user is authenticated
        // Prevents issues in seeders and artisan commands
        if (auth()->check()) {
            $builder->where($model->getTable() . '.user_id', auth()->id());
        }
    }
}