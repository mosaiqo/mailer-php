<?php

declare(strict_types=1);

namespace Mailer\Sdk\Laravel;

use Illuminate\Support\ServiceProvider;
use Mailer\Sdk\MailerClient;

/**
 * Laravel package service provider. Auto-discovered via composer's
 * extra.laravel.providers; this class is only ever loaded inside a Laravel
 * application, so the SDK itself stays usable in plain PHP without illuminate.
 */
final class MailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/mailer-sdk.php', 'mailer-sdk');

        $this->app->singleton(MailerClient::class, function ($app): MailerClient {
            /** @var array{base_url?: string, token?: string} $config */
            $config = $app['config']->get('mailer-sdk', []);

            return new MailerClient(
                (string) ($config['base_url'] ?? ''),
                (string) ($config['token'] ?? ''),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/mailer-sdk.php' => $this->app->configPath('mailer-sdk.php'),
            ], 'mailer-sdk-config');
        }
    }
}
