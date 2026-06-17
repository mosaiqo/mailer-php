<?php

declare(strict_types=1);

namespace Mailer\Sdk\Tests\Notifications;

use Mailer\Sdk\Exception\ValidationException;
use Mailer\Sdk\Laravel\Events\MessageSuppressed;
use Mailer\Sdk\Laravel\Mail\MailerMessage;
use Mailer\Sdk\Laravel\Notifications\MailerChannel;
use Mailer\Sdk\MailerClient;
use Mailer\Sdk\Tests\Mail\Support\SpyDispatcher;
use Mailer\Sdk\Tests\Mail\Support\SpyLogger;
use Mailer\Sdk\Tests\TestCase;
use Psr\Http\Message\RequestInterface;

final class MailerChannelTest extends TestCase
{
    public function test_mailer_message_inline_posts_to_send_with_resolved_recipient_and_idempotency_header(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'm', 'status' => 'queued']]),
        ], $history);

        $channel = new MailerChannel($client, [], new SpyDispatcher(), new SpyLogger());

        $notifiable = $this->notifiable();
        $notifiable->mail = 'jane@example.com';

        $notification = $this->notification(
            (new MailerMessage)->subject('Hi')->html('<p>Hello</p>'),
        );

        $channel->send($notifiable, $notification);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://mailer.example.com/api/v1/send', (string) $request->getUri());

        $payload = $this->body($history, 0);
        $this->assertSame('jane@example.com', $payload['to']);
        $this->assertSame('Hi', $payload['subject']);
        $this->assertSame('<p>Hello</p>', $payload['body']);
        $this->assertNotSame('', $request->getHeaderLine('Idempotency-Key'));
    }

    public function test_template_mode_payload(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'm', 'status' => 'queued']]),
        ], $history);

        $channel = new MailerChannel($client);

        $notifiable = $this->notifiable();
        $notifiable->mail = 'jane@example.com';

        $notification = $this->notification(
            (new MailerMessage)->template('welcome')->variables(['first_name' => 'Jane']),
        );

        $channel->send($notifiable, $notification);

        $payload = $this->body($history, 0);
        $this->assertSame('welcome', $payload['template']);
        $this->assertSame(['first_name' => 'Jane'], $payload['variables']);
        $this->assertArrayNotHasKey('subject', $payload);
        $this->assertArrayNotHasKey('body', $payload);
        $this->assertArrayNotHasKey('text', $payload);
        $this->assertSame('jane@example.com', $payload['to']);
    }

    public function test_recipient_suppressed_does_not_throw_and_dispatches_event(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Recipient is suppressed.', 'code' => 'recipient_suppressed']),
        ], $history);

        $events = new SpyDispatcher();
        $channel = new MailerChannel($client, [], $events, new SpyLogger());

        $notifiable = $this->notifiable();
        $notifiable->mail = 'jane@example.com';

        $notification = $this->notification(
            (new MailerMessage)->subject('Hi')->html('<p>x</p>'),
        );

        $channel->send($notifiable, $notification);

        $suppressed = $events->ofType(MessageSuppressed::class);
        $this->assertCount(1, $suppressed);
        $this->assertSame('jane@example.com', $suppressed[0]->recipient);
    }

    public function test_quota_exceeded_rethrows_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Monthly quota exceeded.', 'code' => 'quota_exceeded']),
        ], $history);

        $channel = new MailerChannel($client);

        $notifiable = $this->notifiable();
        $notifiable->mail = 'jane@example.com';

        $notification = $this->notification(
            (new MailerMessage)->subject('Hi')->html('<p>x</p>'),
        );

        try {
            $channel->send($notifiable, $notification);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('quota_exceeded', $e->getErrorCode());
        }
    }

    public function test_explicit_to_overrides_route(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'm', 'status' => 'queued']]),
        ], $history);

        $channel = new MailerChannel($client);

        $notifiable = $this->notifiable();
        $notifiable->mail = 'jane@example.com';

        $notification = $this->notification(
            (new MailerMessage)->to('override@example.com')->subject('Hi')->html('<p>x</p>'),
        );

        $channel->send($notifiable, $notification);

        $payload = $this->body($history, 0);
        $this->assertSame('override@example.com', $payload['to']);
    }

    public function test_missing_recipient_throws_invalid_argument(): void
    {
        $history = [];
        $client = $this->clientWithResponses([], $history);

        $channel = new MailerChannel($client);

        $notifiable = new class {};

        $notification = $this->notification(
            (new MailerMessage)->subject('Hi')->html('<p>x</p>'),
        );

        $this->expectException(\InvalidArgumentException::class);

        $channel->send($notifiable, $notification);
    }

    public function test_routeNotificationFor_mailer_takes_precedence_over_mail(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'm', 'status' => 'queued']]),
        ], $history);

        $channel = new MailerChannel($client);

        $notifiable = $this->notifiable();
        $notifiable->mailer = 'ml@example.com';
        $notifiable->mail = 'ja@example.com';

        $notification = $this->notification(
            (new MailerMessage)->subject('Hi')->html('<p>x</p>'),
        );

        $channel->send($notifiable, $notification);

        $payload = $this->body($history, 0);
        $this->assertSame('ml@example.com', $payload['to']);
    }

    /**
     * A notifiable with the three resolution surfaces: routeNotificationFor()
     * for the mailer/mail drivers and a public $email fallback.
     */
    private function notifiable(): object
    {
        return new class
        {
            public mixed $mail = null;

            public mixed $mailer = null;

            public mixed $email = null;

            public function routeNotificationFor($driver, $notification = null): mixed
            {
                return $driver === 'mailer'
                    ? $this->mailer
                    : ($driver === 'mail' ? $this->mail : null);
            }
        };
    }

    /**
     * A notification whose toMailer() returns the given message.
     *
     * @param mixed $message
     */
    private function notification($message): \Illuminate\Notifications\Notification
    {
        $notification = new class extends \Illuminate\Notifications\Notification
        {
            public mixed $message = null;

            public function toMailer($notifiable): mixed
            {
                return $this->message;
            }
        };

        $notification->message = $message;

        return $notification;
    }

    /**
     * Decode the JSON body of the request captured at the given history index.
     *
     * @param array<int, array{request: RequestInterface}> $history
     *
     * @return array<string, mixed>
     */
    private function body(array $history, int $index): array
    {
        return json_decode((string) $history[$index]['request']->getBody(), true);
    }
}
