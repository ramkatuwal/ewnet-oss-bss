<?php

namespace Tests\Feature\Integrations;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IntegrationCredentialLogTest extends TestCase
{
    use RefreshDatabase;

    private array $capturedLogs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Log::listen(function ($event) {
            $message = $event->message;
            if (is_array($event->context)) {
                $this->capturedLogs[] = $message.' '.json_encode($event->context);
            } else {
                $this->capturedLogs[] = (string) $message;
            }
        });
    }

    public function test_plaintext_credential_never_reaches_application_log(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('integrations.create');

        $secret = 'super-secret-api-token-'.uniqid();

        $response = $this->actingAs($user)->postJson('/api/v1/integrations', [
            'name' => 'Log Safety Test',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'configuration' => ['api_url' => 'https://nms.example.com/api/v0'],
            'credential_type' => 'api_token',
            'credential_value' => $secret,
        ]);

        $response->assertStatus(200);

        $responsePayload = json_encode($response->json());
        $this->assertStringNotContainsString($secret, $responsePayload);

        $logPayload = implode("\n", $this->capturedLogs);
        $this->assertStringNotContainsString($secret, $logPayload, 'Application log must not contain plaintext credentials');

        $auditMatches = DB::table('audit_logs')
            ->where('metadata', 'like', '%'.$secret.'%')
            ->count();
        $this->assertSame(0, $auditMatches, 'Audit logs must not contain plaintext credentials');
    }

    public function test_credential_encrypted_at_rest(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('integrations.create');

        $secret = 'encrypt-me-plainly-'.uniqid();

        $response = $this->actingAs($user)->postJson('/api/v1/integrations', [
            'name' => 'Encryption Test',
            'provider' => 'uisp',
            'type' => 'monitoring',
            'configuration' => ['api_url' => 'https://uisp.example.com/api'],
            'credential_type' => 'api_token',
            'credential_value' => $secret,
        ]);

        $response->assertStatus(200);

        $row = DB::table('integration_credentials')
            ->where('integration_id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($row);
        $this->assertStringNotContainsString($secret, $row->encrypted_value);
        $this->assertStringNotContainsString($secret, $row->masked_hint);
        $this->assertStringContainsString(
            substr($secret, -4),
            $row->masked_hint,
            'Masked hint should only expose the last 4 characters'
        );
    }
}
