<?php

namespace Tests\Unit\Integrations;

use App\Integrations\Providers\LibreNMS\LibreNMSProvider;
use PHPUnit\Framework\TestCase;

class LibreNMSProviderTest extends TestCase
{
    private LibreNMSProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new LibreNMSProvider();
    }

    public function test_identity_returns_librenms(): void
    {
        $this->assertSame('librenms', $this->provider->identity());
    }

    public function test_display_name_returns_librenms(): void
    {
        $this->assertSame('LibreNMS', $this->provider->displayName());
    }

    public function test_capabilities_returns_expected_array(): void
    {
        $capabilities = $this->provider->capabilities();
        $this->assertContains('device_discovery', $capabilities);
        $this->assertContains('interface_discovery', $capabilities);
        $this->assertContains('monitoring', $capabilities);
        $this->assertContains('alerts', $capabilities);
    }

    public function test_validate_configuration_accepts_valid_config(): void
    {
        $errors = $this->provider->validateConfiguration([
            'endpoint' => 'https://nms.example.com',
            'timeout' => 30,
        ]);
        $this->assertEmpty($errors);
    }

    public function test_validate_configuration_rejects_missing_endpoint(): void
    {
        $errors = $this->provider->validateConfiguration([]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Endpoint', $errors[0]);
    }

    public function test_validate_configuration_rejects_invalid_url(): void
    {
        $errors = $this->provider->validateConfiguration(['endpoint' => 'not-a-url']);
        $this->assertNotEmpty($errors);
    }

    public function test_validate_configuration_rejects_invalid_timeout(): void
    {
        $errors = $this->provider->validateConfiguration([
            'endpoint' => 'https://nms.example.com',
            'timeout' => 999,
        ]);
        $this->assertNotEmpty($errors);
    }
}
