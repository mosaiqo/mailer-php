# Changelog

All notable changes to `mosaiqo/mailer-php` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Laravel mail transport driver (`MAIL_MAILER=mailer`) — route the `Mail`
  facade, Mailables and queued mailers through the platform `/send` API, with
  documented behavior for attachments, suppressed recipients, quota / sending-
  domain rejections, From/Reply-To, multi-recipient batches, idempotency modes
  and the `X-Mailer-Template` header.
- Laravel notification channel (`mailer`) — deliver notifications via `via()` +
  `toMailer()` returning a `MailerMessage` (inline or stored template).
- `Mailer` facade proxying the container-bound `MailerClient` singleton.
- Automatic retries with exponential backoff (idempotency-safe) on the built-in
  HTTP client.
- Lazy pagination — `cursor()` generators that walk every page on demand
  (wrappable in a Laravel `LazyCollection`).
- Read-only campaigns resource (`campaigns()->list()` / `get()` with stats).

[Unreleased]: https://github.com/mosaiqo/mailer-php/commits/main
