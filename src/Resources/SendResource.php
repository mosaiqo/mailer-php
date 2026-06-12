<?php

declare(strict_types=1);

namespace Mailer\Sdk\Resources;

use Mailer\Sdk\Dto\BatchResult;
use Mailer\Sdk\Dto\SentMessage;
use Mailer\Sdk\Http\HttpClient;

/**
 * The Send resource: transactional sends, batch sends, event tracking and
 * contact subscription.
 */
final readonly class SendResource
{
    public function __construct(private HttpClient $http)
    {
    }

    /**
     * Send a single transactional email (POST /send).
     *
     * @param array<string, mixed> $payload `to` plus either `template` or
     *                                       `subject`+`body`, optional `text`,
     *                                       `variables`.
     */
    public function email(array $payload, ?string $idempotencyKey = null): SentMessage
    {
        $options = ['json' => $payload];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $response = $this->http->post('send', $options);

        return SentMessage::fromArray($response['data'] ?? []);
    }

    /**
     * Send a batch of transactional emails (POST /send/batch).
     *
     * @param array<int, array<string, mixed>> $messages 1-100 message payloads.
     */
    public function batch(array $messages, ?string $idempotencyKey = null): BatchResult
    {
        $options = ['json' => ['messages' => array_values($messages)]];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $response = $this->http->post('send/batch', $options);

        return BatchResult::fromArray($response['data'] ?? []);
    }

    /**
     * Record an event occurrence for a contact (POST /track).
     *
     * @param array<string, mixed> $data Optional event payload.
     *
     * @return array<string, mixed> The `data` block: id, event, email.
     */
    public function track(string $event, string $email, array $data = []): array
    {
        $payload = ['event' => $event, 'email' => $email];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        $response = $this->http->post('track', ['json' => $payload]);

        return $response['data'] ?? [];
    }

    /**
     * Subscribe a contact (POST /contacts/subscribe).
     *
     * @param array<string, mixed> $payload `email` plus optional first_name,
     *                                       last_name, locale, attributes,
     *                                       lists, tags.
     *
     * @return array<string, mixed> The `data` block: id, email, status.
     */
    public function subscribe(array $payload): array
    {
        $response = $this->http->post('contacts/subscribe', ['json' => $payload]);

        return $response['data'] ?? [];
    }
}
