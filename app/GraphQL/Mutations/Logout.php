<?php

namespace App\GraphQL\Mutations;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Logout
{
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): bool
    {
        $context->user()?->currentAccessToken()?->delete();

        return true;
    }
}
