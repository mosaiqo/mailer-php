<?php

declare(strict_types=1);

namespace Mailer\Sdk;

use GuzzleHttp\ClientInterface;
use Mailer\Sdk\Exception\MailerConfigurationException;
use Mailer\Sdk\Http\HttpClient;
use Mailer\Sdk\Resources\CampaignsResource;
use Mailer\Sdk\Resources\ContactsResource;
use Mailer\Sdk\Resources\ListsResource;
use Mailer\Sdk\Resources\MessagesResource;
use Mailer\Sdk\Resources\NotificationsResource;
use Mailer\Sdk\Resources\PushTokensResource;
use Mailer\Sdk\Resources\SandboxResource;
use Mailer\Sdk\Resources\SendResource;
use Mailer\Sdk\Resources\TagsResource;
use Mailer\Sdk\Resources\TemplatesResource;

/**
 * Entry point of the Mailer SDK. Build it with a base URL (the ".../api/v1"
 * root) and a project API token, then reach the API through memoized resource
 * accessors. A custom Guzzle client may be injected (used in tests).
 */
final class MailerClient
{
    /**
     * The placeholder host the SDK used to ship as a working-looking default.
     * A base URL pointing at it is treated as "not configured" so a consumer
     * who forgot to set MAILER_BASE_URL (or still has the old published
     * default) fails loudly instead of silently sending to a dead host.
     */
    public const string PLACEHOLDER_BASE_URL_HOST = 'api.mailer.test';

    /**
     * The hosted API's base URL, used as the default when MAILER_BASE_URL is
     * not set. Hosted consumers only need to configure MAILER_API_TOKEN;
     * self-hosted consumers override MAILER_BASE_URL with their own endpoint.
     */
    public const string DEFAULT_BASE_URL = 'https://mailer.mosaiqo.com/api/v1';

    private readonly HttpClient $http;

    private ?SendResource $send = null;

    private ?ContactsResource $contacts = null;

    private ?ListsResource $lists = null;

    private ?TagsResource $tags = null;

    private ?TemplatesResource $templates = null;

    private ?MessagesResource $messages = null;

    private ?CampaignsResource $campaigns = null;

    private ?NotificationsResource $notifications = null;

    private ?PushTokensResource $push = null;

    private ?SandboxResource $sandbox = null;

    /**
     * @param array<string, mixed> $options Transport/resilience options applied
     *                                       only when no client is injected:
     *                                       `retries`, `retry_base_delay`,
     *                                       `retry_max_delay`, `retry_on_status`,
     *                                       `timeout`, `connect_timeout`.
     */
    public function __construct(
        string $baseUrl,
        string $token,
        ?ClientInterface $httpClient = null,
        array $options = [],
    ) {
        self::assertConfigured($baseUrl, $token);

        $this->http = new HttpClient(rtrim($baseUrl, '/'), $token, $httpClient, $options);
    }

    /**
     * Fail loudly on missing/placeholder configuration before any request is
     * ever made, so a misconfigured consumer gets a clear error instead of
     * silently sending to a dead host (the old `api.mailer.test` default) or
     * with an empty Bearer token.
     *
     * @throws MailerConfigurationException
     */
    private static function assertConfigured(string $baseUrl, string $token): void
    {
        if (trim($baseUrl) === '') {
            throw new MailerConfigurationException(
                'MAILER_BASE_URL is not configured; set it to your mailer-app /api/v1 endpoint '
                .'(e.g. https://app.example.com/api/v1).',
            );
        }

        if (str_contains($baseUrl, self::PLACEHOLDER_BASE_URL_HOST)) {
            throw new MailerConfigurationException(
                'MAILER_BASE_URL is still set to the placeholder "'.self::PLACEHOLDER_BASE_URL_HOST.'"; '
                .'set it to your mailer-app /api/v1 endpoint (e.g. https://app.example.com/api/v1).',
            );
        }

        if (trim($token) === '') {
            throw new MailerConfigurationException(
                'MAILER_API_TOKEN is not configured; set it to a project API key '
                .'(mailer-app → Settings → API keys).',
            );
        }
    }

    public function send(): SendResource
    {
        return $this->send ??= new SendResource($this->http);
    }

    public function contacts(): ContactsResource
    {
        return $this->contacts ??= new ContactsResource($this->http);
    }

    public function lists(): ListsResource
    {
        return $this->lists ??= new ListsResource($this->http);
    }

    public function tags(): TagsResource
    {
        return $this->tags ??= new TagsResource($this->http);
    }

    public function templates(): TemplatesResource
    {
        return $this->templates ??= new TemplatesResource($this->http);
    }

    public function messages(): MessagesResource
    {
        return $this->messages ??= new MessagesResource($this->http);
    }

    public function campaigns(): CampaignsResource
    {
        return $this->campaigns ??= new CampaignsResource($this->http);
    }

    public function notifications(): NotificationsResource
    {
        return $this->notifications ??= new NotificationsResource($this->http);
    }

    public function push(): PushTokensResource
    {
        return $this->push ??= new PushTokensResource($this->http);
    }

    public function sandbox(): SandboxResource
    {
        return $this->sandbox ??= new SandboxResource($this->http);
    }
}
