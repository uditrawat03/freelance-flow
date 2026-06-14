<?php

namespace App\GraphQL\Queries;

use App\Models\Workspace;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CurrentWorkspace
{
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?Workspace
    {
        return $context->user()?->currentWorkspace();
    }
}
