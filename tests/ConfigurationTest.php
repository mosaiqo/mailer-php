<?php

declare(strict_types=1);

namespace Mailer\Sdk\Tests;

use Mailer\Sdk\Exception\MailerConfigurationException;
use Mailer\Sdk\Exception\MailerException;
use Mailer\Sdk\MailerClient;

/**
 * Pins the fail-loud configuration guard: the client refuses to construct with
 * a missing/empty/placeholder base URL or an empty API token, so a
 * misconfigured consumer never silently sends to a dead host.
 */
final class ConfigurationTest extends TestCase
{
    public function test_empty_base_url_throws_a_configuration_exception(): void
    {
        $this->expectException(MailerConfigurationException::class);
        $this->expectExceptionMessage('MAILER_BASE_URL is not configured');

        new MailerClient('', 'test-token');
    }

    public function test_whitespace_only_base_url_throws(): void
    {
        $this->expectException(MailerConfigurationException::class);

        new MailerClient('   ', 'test-token');
    }

    public function test_placeholder_base_url_throws(): void
    {
        $this->expectException(MailerConfigurationException::class);
        $this->expectExceptionMessage('placeholder');

        new MailerClient('https://'.MailerClient::PLACEHOLDER_BASE_URL_HOST.'/api/v1', 'test-token');
    }

    public function test_placeholder_base_url_without_scheme_also_throws(): void
    {
        $this->expectException(MailerConfigurationException::class);

        new MailerClient(MailerClient::PLACEHOLDER_BASE_URL_HOST.'/api/v1', 'test-token');
    }

    public function test_empty_token_throws_a_configuration_exception(): void
    {
        $this->expectException(MailerConfigurationException::class);
        $this->expectExceptionMessage('MAILER_API_TOKEN is not configured');

        new MailerClient('https://app.example.com/api/v1', '');
    }

    public function test_whitespace_only_token_throws(): void
    {
        $this->expectException(MailerConfigurationException::class);

        new MailerClient('https://app.example.com/api/v1', '   ');
    }

    public function test_configuration_exception_is_a_mailer_exception(): void
    {
        // Consumers catching the SDK base type still catch misconfiguration.
        $this->expectException(MailerException::class);

        new MailerClient('', 'test-token');
    }

    public function test_a_valid_configuration_constructs_without_throwing(): void
    {
        $client = new MailerClient('https://app.example.com/api/v1', 'test-token');

        $this->assertInstanceOf(MailerClient::class, $client);
    }
}
