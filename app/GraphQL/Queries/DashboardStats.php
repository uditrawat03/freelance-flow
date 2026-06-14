<?php

namespace App\GraphQL\Queries;

use App\Services\DashboardService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class DashboardStats
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        return $this->dashboardService->stats();
    }
}
