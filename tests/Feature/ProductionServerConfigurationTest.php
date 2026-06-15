<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionServerConfigurationTest extends TestCase
{
    public function test_reverb_scaling_uses_a_dedicated_redis_database_by_default(): void
    {
        $this->assertSame('2', (string) config('reverb.servers.reverb.scaling.server.database'));
        $this->assertNotSame(
            (string) config('database.redis.default.database'),
            (string) config('reverb.servers.reverb.scaling.server.database')
        );
    }

    public function test_env_example_documents_production_scaling_variables(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        foreach ([
            'REDIS_DB=0',
            'REDIS_CACHE_DB=1',
            'REDIS_REVERB_DB=2',
            'OCTANE_HOST=127.0.0.1',
            'OCTANE_PORT=8000',
            'HORIZON_DEFAULT_MAX_PROCESSES=5',
            'REVERB_SCALING_ENABLED=false',
            'REVERB_APP_RATE_LIMITING_ENABLED=true',
            'FILESYSTEM_DISK=local',
        ] as $expectedLine) {
            $this->assertStringContainsString($expectedLine, $envExample);
        }
    }
}
