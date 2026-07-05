<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Notifications;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Mailer\Sdk\Exception\ValidationException;
use Mailer\Sdk\Laravel\Events\MessageSuppressed;
use Mailer\Sdk\Laravel\Mail\IdempotencyKey;
use Mailer\Sdk\Laravel\Mail\MailerMessage;
use Mailer\Sdk\Laravel\Mail\PayloadMapper;
use Mailer\Sdk\MailerClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/**
 * Laravel notification channel that delivers through the Mailer platform /send
 * API. Registered as the "mailer" channel by the service provider, so a
 * notification's `via()` can return `['mailer']`.
 *
 * The notification must define `toMailer($notifiable)` returning one of: a
 * {@see MailerMessage} (full control: inline or template sends, explicit
 * recipient/idempotency key), an `Illuminate\Contracts\Mail\Mailable` (rendered
 * to subject + HTML only) or a plain `/send` payload array.
 *
 * Recipient routing precedence: an explicit {@see MailerMessage::to()} (or the
 * array's `to`) wins, then `routeNotificationFor('mailer')`, then
 * `routeNotificationFor('mail')`, then a public `$email` property on the
 * notifiable.
 *
 * Outcome semantics mirror the transport: a suppressed recipient is NOT a
 * failure (a {@see MessageSuppressed} event is dispatched and the send is
 * skipped); quota/domain/other SDK errors are rethrown so Laravel marks the
 * notification failed and can retry per its own policy.
 */
final class MailerChannel
{
    /**
     * Error code that is NOT a failure: the platform refused the recipient but
     * the send itself was accepted.
     */
    private const SUPPRESSED_CODE = 'recipient_suppressed';

    /**
     * @param array<string, mixed> $mailConfig The `mailer-sdk.mail` config block.
     */
    public function __construct(
        private readonly MailerClient $client,
        private readonly array $mailConfig = [],
        private readonly ?Dispatcher $events = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param mixed $notifiable
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toMailer')) {
            throw new \InvalidArgumentException(
                $notification::class.' must define a toMailer() method to use the "mailer" notification channel.',
            );
        }

        $message = $notification->toMailer($notifiable);

        [$payload, $explicitKey, $explicitTo] = $this->resolveMessage($message);

        $recipient = $this->resolveRecipient($notifiable, $notification, $explicitTo);

        if ($recipient === null) {
            throw new \InvalidArgumentException(
                'Could not resolve a recipient for the mailer notification channel. Provide '
                ."MailerMessage::to(), a routeNotificationFor('mailer'|'mail') method, or a public \$email "
                .'property on the notifiable.',
            );
        }

        $keyPayload = ['to' => $recipient] + $payload;
        $key = IdempotencyKey::compute($keyPayload, $this->mailConfig, $explicitKey);

        $requestPayload = ['to' => $recipient] + $payload;

        try {
            $this->client->send()->email($requestPayload, $key);
        } catch (ValidationException $e) {
            if ($e->getErrorCode() === self::SUPPRESSED_CODE) {
                $this->logger?->warning(
                    'Mailer SDK notification channel: recipient suppressed; skipping.',
                    ['recipient' => $recipient],
                );

                $this->events?->dispatch(new MessageSuppressed($recipient, $e->getErrorCode(), $e->getBody()));

                return;
            }

            throw $e;
        }
    }

    /**
     * Normalize the toMailer() return value into a content payload (no `to`/key),
     * the explicit idempotency key and the explicit recipient.
     *
     * A Mailable is rendered to its HTML body + subject only; attachments,
     * text-only Mailables and template headers set via withSymfonyMessage() are
     * not honored through the channel — return a MailerMessage for full control,
     * or a payload array to pass /send fields (including `attachments`) as-is.
     *
     * @param mixed $message
     *
     * @return array{0: array<string, mixed>, 1: ?string, 2: ?string}
     */
    private function resolveMessage($message): array
    {
        if ($message instanceof MailerMessage) {
            return [$message->toArray(), $message->explicitKey(), $message->recipient()];
        }

        if ($message instanceof Mailable) {
            $email = (new Email)->html($message->render());

            if (isset($message->subject)) {
                $email->subject((string) $message->subject);
            }

            $payload = PayloadMapper::fromEmail($email, '__placeholder__', $this->mailConfig, $this->logger);
            unset($payload['to']);

            return [$payload, null, null];
        }

        if (is_array($message)) {
            $payload = $message;

            $explicitTo = $payload['to'] ?? null;
            unset($payload['to']);

            $explicitKey = $payload['idempotency_key'] ?? null;
            unset($payload['idempotency_key']);

            return [$payload, $explicitKey, $explicitTo];
        }

        throw new \InvalidArgumentException(
            'Unsupported toMailer() return type ['.get_debug_type($message).']. Return a '
            .MailerMessage::class.', an Illuminate\\Contracts\\Mail\\Mailable, or a /send payload array.',
        );
    }

    /**
     * Resolve the recipient by precedence: explicit, then the mailer route, then
     * the mail route, then a public $email property on the notifiable.
     *
     * @param mixed $notifiable
     * @param mixed $explicitTo
     */
    private function resolveRecipient($notifiable, Notification $notification, $explicitTo): ?string
    {
        $recipient = $this->firstEmail($explicitTo);

        if ($recipient !== null) {
            return $recipient;
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $recipient = $this->firstEmail($notifiable->routeNotificationFor('mailer', $notification));

            if ($recipient !== null) {
                return $recipient;
            }

            $recipient = $this->firstEmail($notifiable->routeNotificationFor('mail', $notification));

            if ($recipient !== null) {
                return $recipient;
            }
        }

        return $this->firstEmail($notifiable->email ?? null);
    }

    /**
     * Reduce a notification route value (string, address=>name map, or list) to
     * a single email address.
     *
     * @param mixed $route
     */
    private function firstEmail(mixed $route): ?string
    {
        if (is_string($route)) {
            $route = trim($route);

            return $route === '' ? null : $route;
        }

        if (is_array($route)) {
            foreach ($route as $k => $v) {
                if (is_string($k) && str_contains($k, '@')) {
                    return $k;
                }

                if (is_string($v) && str_contains($v, '@')) {
                    $v = trim($v);

                    return $v === '' ? null : $v;
                }
            }

            foreach ($route as $v) {
                if (is_string($v)) {
                    $v = trim($v);

                    if ($v !== '') {
                        return $v;
                    }
                }
            }

            return null;
        }

        return null;
    }
}
