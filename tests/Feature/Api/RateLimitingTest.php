<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithWorkspace;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;
    use WithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_token_creation_is_limited_by_ip_address(): void
    {
        config(['freelanceflow.rate_limits.token_creation.per_minute' => 2]);

        $user = User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => 'password',
        ]);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/api/v1/tokens/create', [
                'email' => $user->email,
                'password' => 'password',
                'device_name' => "Test Device {$attempt}",
            ])->assertCreated();
        }

        $this->postJson('/api/v1/tokens/create', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Blocked Device',
        ])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many requests.');
    }

    public function test_authenticated_api_write_limit_is_keyed_by_user(): void
    {
        config(['freelanceflow.rate_limits.api.authenticated_per_minute' => 3]);

        $firstUser = User::factory()->create();

        Sanctum::actingAs($firstUser, ['*']);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/clients', [])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/clients', [])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many requests.');

        $secondUser = User::factory()->create();

        Sanctum::actingAs($secondUser, ['*']);

        $this->postJson('/api/v1/clients', [])
            ->assertUnprocessable();
    }

    public function test_read_endpoints_use_the_separate_read_limit(): void
    {
        config([
            'freelanceflow.rate_limits.api.authenticated_per_minute' => 1,
            'freelanceflow.rate_limits.api_reads.authenticated_per_minute' => 3,
        ]);

        $this->setUpWorkspace();
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/clients', [])
            ->assertUnprocessable();

        $this->postJson('/api/v1/clients', [])
            ->assertTooManyRequests();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->getJson('/api/v1/clients')
                ->assertOk();
        }

        $this->getJson('/api/v1/clients')
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many requests.');
    }
}
