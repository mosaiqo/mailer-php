<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Mailer\Sdk\MailerClient;

/**
 * Facade proxying the container-bound MailerClient singleton, so an app can
 * reach the API through static calls (Mailer::contacts()->..., Mailer::send()
 * ->..., etc.) without injecting the client. It resolves the very same instance
 * you would receive by type-hinting MailerClient.
 *
 * @method static \Mailer\Sdk\Resources\SendResource send()
 * @method static \Mailer\Sdk\Resources\ContactsResource contacts()
 * @method static \Mailer\Sdk\Resources\ListsResource lists()
 * @method static \Mailer\Sdk\Resources\TagsResource tags()
 * @method static \Mailer\Sdk\Resources\TemplatesResource templates()
 * @method static \Mailer\Sdk\Resources\MessagesResource messages()
 * @method static \Mailer\Sdk\Resources\CampaignsResource campaigns()
 * @method static \Mailer\Sdk\Resources\NotificationsResource notifications()
 * @method static \Mailer\Sdk\Resources\PushTokensResource push()
 * @method static \Mailer\Sdk\Resources\SandboxResource sandbox()
 *
 * @see \Mailer\Sdk\MailerClient
 */
final class Mailer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MailerClient::class;
    }
}
