<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToWorkspace implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $workspace = auth()->user()->currentWorkspace();

        if ($workspace) {
            $builder->where(
                $model->getTable() . '.workspace_id',
                $workspace->id
            );
        }
    }
}