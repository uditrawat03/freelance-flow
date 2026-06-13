<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Logger;
use App\ValueObjects\SensitiveString;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EncryptionHashingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_notes_are_encrypted_at_rest_and_decrypted_on_read(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user);
        session(['current_workspace_id' => $workspace->id]);

        $client = Client::factory()->create([
            'notes' => 'Client prefers private billing notes.',
        ]);

        $rawNotes = DB::table('clients')->where('id', $client->id)->value('notes');

        $this->assertNotSame('Client prefers private billing notes.', $rawNotes);
        $this->assertSame('Client prefers private billing notes.', $client->fresh()->notes);
    }

    public function test_invoice_notes_are_encrypted_at_rest_and_decrypted_on_read(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user);
        session(['current_workspace_id' => $workspace->id]);

        $client = Client::factory()->create();
        $invoice = Invoice::factory()->for($client)->create([
            'notes' => 'Send payment reminder after seven days.',
        ]);

        $rawNotes = DB::table('invoices')->where('id', $invoice->id)->value('notes');

        $this->assertNotSame('Send payment reminder after seven days.', $rawNotes);
        $this->assertSame('Send payment reminder after seven days.', $invoice->fresh()->notes);
    }

    public function test_passwords_are_hashed_not_encrypted(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-horse-battery-staple',
        ]);

        $this->assertNotSame('correct-horse-battery-staple', $user->password);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
    }

    public function test_logger_redacts_sensitive_context_recursively(): void
    {
        Log::spy();

        app(Logger::class)->info('Incoming sensitive payload', [
            'notes' => 'plain client note',
            'payload' => [
                'api_token' => 'secret-token',
                'safe' => 'kept',
            ],
        ]);

        Log::shouldHaveReceived('info')->once()->with('Incoming sensitive payload', \Mockery::on(
            fn (array $context): bool => $context['notes'] === '[REDACTED]'
                && $context['payload']['api_token'] === '[REDACTED]'
                && $context['payload']['safe'] === 'kept'
        ));
    }

    public function test_sensitive_string_masks_debug_and_json_output(): void
    {
        $value = SensitiveString::from('private text');

        $this->assertSame('private text', $value->reveal());
        $this->assertSame('[REDACTED]', (string) $value);
        $this->assertSame('"[REDACTED]"', json_encode($value));
        $this->assertSame(['value' => '[REDACTED]'], $value->__debugInfo());
    }
}
