<?php

declare(strict_types=1);

namespace Mailer\Sdk\Resources;

use Mailer\Sdk\Dto\Message;
use Mailer\Sdk\Dto\Paginated;
use Mailer\Sdk\Http\HttpClient;

/**
 * The Messages resource (read-only).
 */
final readonly class MessagesResource
{
    public function __construct(private HttpClient $http)
    {
    }

    /**
     * List messages (GET /messages).
     *
     * @param array<string, mixed> $query status, source, campaign_id, search,
     *                                    per_page, page.
     *
     * @return Paginated<Message>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('messages', ['query' => $query]);

        return Paginated::fromArray($response, Message::fromArray(...));
    }

    /**
     * Fetch a single message with its event timeline (GET /messages/{uuid}).
     */
    public function get(string $uuid): Message
    {
        $response = $this->http->get('messages/'.rawurlencode($uuid));

        return Message::fromArray($response['data'] ?? []);
    }
}
