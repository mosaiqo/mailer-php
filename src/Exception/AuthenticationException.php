<?php

declare(strict_types=1);

namespace Mailer\Sdk\Exception;

/**
 * Thrown on HTTP 401 responses (missing/invalid/expired API token).
 */
class AuthenticationException extends MailerException
{
}
