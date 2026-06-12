<?php

declare(strict_types=1);

namespace Mailer\Sdk\Resources;

use Mailer\Sdk\Dto\Contact;
use Mailer\Sdk\Dto\ContactList;
use Mailer\Sdk\Dto\Paginated;
use Mailer\Sdk\Http\HttpClient;

/**
 * The Lists resource: list, create and manage list membership.
 */
final readonly class ListsResource
{
    public function __construct(private HttpClient $http)
    {
    }

    /**
     * List contact lists (GET /lists).
     *
     * @param array<string, mixed> $query
     *
     * @return Paginated<ContactList>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('lists', ['query' => $query]);

        return Paginated::fromArray($response, ContactList::fromArray(...));
    }

    /**
     * Create a contact list (POST /lists).
     */
    public function create(string $name, ?string $description = null): ContactList
    {
        $payload = ['name' => $name];

        if ($description !== null) {
            $payload['description'] = $description;
        }

        $response = $this->http->post('lists', ['json' => $payload]);

        return ContactList::fromArray($response['data'] ?? []);
    }

    /**
     * List a list's contacts (GET /lists/{id}/contacts).
     *
     * @param array<string, mixed> $query
     *
     * @return Paginated<Contact>
     */
    public function contacts(int $listId, array $query = []): Paginated
    {
        $response = $this->http->get('lists/'.$listId.'/contacts', ['query' => $query]);

        return Paginated::fromArray($response, Contact::fromArray(...));
    }

    /**
     * Attach a contact to a list (POST /lists/{id}/contacts).
     */
    public function attachContact(int $listId, string $email): Contact
    {
        $response = $this->http->post('lists/'.$listId.'/contacts', ['json' => ['email' => $email]]);

        return Contact::fromArray($response['data'] ?? []);
    }

    /**
     * Detach a contact from a list (DELETE /lists/{id}/contacts/{email}).
     */
    public function detachContact(int $listId, string $email): void
    {
        $this->http->delete('lists/'.$listId.'/contacts/'.rawurlencode($email));
    }
}
