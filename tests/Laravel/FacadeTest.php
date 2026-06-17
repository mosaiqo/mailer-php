<?php

declare(strict_types=1);

namespace Mailer\Sdk\Tests\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mailer\Sdk\Laravel\Facades\Mailer;
use Mailer\Sdk\MailerClient;
use Mailer\Sdk\Resources\ContactsResource;
use Psr\Http\Message\RequestInterface;

/**
 * Proves the Mailer facade resolves the container-bound MailerClient singleton
 * and proxies its resource accessors through to the real HTTP client.
 */
final class FacadeTest extends TestCase
{
    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Mailer' => Mailer::class];
    }

    public function test_facade_root_is_the_container_singleton(): void
    {
        $this->assertInstanceOf(MailerClient::class, Mailer::getFacadeRoot());
        $this->assertSame($this->app->make(MailerClient::class), Mailer::getFacadeRoot());
    }

    public function test_resource_accessor_is_memoized(): void
    {
        $contacts = Mailer::contacts();

        $this->assertInstanceOf(ContactsResource::class, $contacts);
        $this->assertSame($contacts, Mailer::contacts());
    }

    public function test_send_proxies_through_the_real_client(): void
    {
        $history = [];
        $this->bindMockClient([
            new Response(202, ['Content-Type' => 'application/json'], (string) json_encode([
                'data' => ['id' => 'msg-1', 'status' => 'queued'],
            ])),
        ], $history);

        Mailer::send()->email([
            'to' => 'jane@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
        ]);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://mailer.example.com/api/v1/send', (string) $request->getUri());

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $payload['to']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('<p>Hi</p>', $payload['body']);
    }

    /**
     * Re-bind the container's MailerClient singleton with one backed by a mock
     * HTTP handler, so the facade-resolved client uses it. The facade caches its
     * resolved root, so callers must clear it after rebinding.
     *
     * @param array<int, Response> $responses
     * @param array<int, array{request: RequestInterface}> $history
     */
    private function bindMockClient(array $responses, array &$history): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        $guzzle = new Client(['handler' => $stack]);

        $this->app->instance(
            MailerClient::class,
            new MailerClient('https://mailer.example.com/api/v1', 'test-token', $guzzle),
        );

        Mailer::clearResolvedInstance(MailerClient::class);
    }
}
