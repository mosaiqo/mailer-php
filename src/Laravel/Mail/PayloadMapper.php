<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Mail;

use Mailer\Sdk\Exception\UnsupportedFeatureException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/**
 * Maps a Symfony Email into the platform /send content payload. Either a
 * template payload (when the X-Mailer-Template header is present) or an inline
 * subject/body one. An optional PSR-3 logger receives the attachment-ignore
 * warning and the From-set debug log.
 */
final class PayloadMapper
{
    /**
     * Build the content payload shared by every recipient (no `to` key). Either
     * a template payload or an inline subject/body one.
     *
     * @param array<string, mixed> $mailConfig
     *
     * @return array<string, mixed>
     */
    public static function base(Email $email, array $mailConfig, ?LoggerInterface $logger = null): array
    {
        self::guardAttachments($email, $mailConfig, $logger);
        self::warnIfFromIsSet($email, $logger);

        $template = self::header($email, MailerHeaders::TEMPLATE);

        if ($template !== null) {
            return [
                'template' => $template,
                'variables' => self::variables($email),
            ];
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        $payload = [
            'subject' => (string) $email->getSubject(),
            // The API requires `body`; fall back to the text body when the
            // message is text-only so a plain Mail::raw() still goes out.
            'body' => self::stringBody($html) ?? self::stringBody($text) ?? '',
        ];

        $textBody = self::stringBody($text);

        if ($textBody !== null) {
            $payload['text'] = $textBody;
        }

        $variables = self::variables($email);

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        return $payload;
    }

    /**
     * Build the full /send payload for a single recipient: `to` first, then the
     * shared content payload.
     *
     * @param array<string, mixed> $mailConfig
     *
     * @return array<string, mixed>
     */
    public static function fromEmail(Email $email, string $recipient, array $mailConfig, ?LoggerInterface $logger = null): array
    {
        return ['to' => $recipient] + self::base($email, $mailConfig, $logger);
    }

    /**
     * @param array<string, mixed> $mailConfig
     */
    private static function guardAttachments(Email $email, array $mailConfig, ?LoggerInterface $logger): void
    {
        if ($email->getAttachments() === []) {
            return;
        }

        $mode = (string) ($mailConfig['attachments'] ?? 'fail');

        if ($mode === 'ignore') {
            $logger?->warning(
                'Mailer SDK transport: dropping attachments — the platform /send API does not support them.',
            );

            return;
        }

        throw new UnsupportedFeatureException(
            'The Mailer platform /send API does not support attachments. '
            .'Remove the attachment from the Mailable, or set mailer-sdk.mail.attachments to "ignore" '
            .'to send the message without it.',
        );
    }

    private static function warnIfFromIsSet(Email $email, ?LoggerInterface $logger): void
    {
        if ($email->getFrom() !== []) {
            $logger?->debug(
                'Mailer SDK transport: ignoring the message From address; the platform uses the '
                ."project's configured sender.",
            );
        }
    }

    /**
     * Decode the X-Mailer-Variables JSON header into an associative array.
     *
     * @return array<string, mixed>
     */
    private static function variables(Email $email): array
    {
        $raw = self::header($email, MailerHeaders::VARIABLES);

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function header(Email $email, string $name): ?string
    {
        $header = $email->getHeaders()->get($name);

        if ($header === null) {
            return null;
        }

        return $header->getBodyAsString();
    }

    /**
     * Symfony body parts can be strings or resources; normalize to a string.
     */
    private static function stringBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);

            return $contents === false ? null : $contents;
        }

        return (string) $body;
    }

    private function __construct()
    {
    }
}
