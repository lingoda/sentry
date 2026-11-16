# lingoda/sentry

Symfony bundle that sets up `sentry/sentry-symfony` with shared defaults: data protection, a scrubber
for credentials, a Monolog handler, and serializers for event context.

## Requirements

- PHP `^8.4`
- Symfony `^7.4 || ^8.0`
- `sentry/sentry-symfony` `^5.0`

## Installation

```bash
composer require lingoda/sentry
```

Register both bundles, usually for `prod` only:

```php
// config/bundles.php
Sentry\SentryBundle\SentryBundle::class => ['prod' => true],
Lingoda\SentryBundle\LingodaSentryBundle::class => ['prod' => true],
```

## Configuration

```yaml
# config/packages/prod/lingoda_sentry.yaml
lingoda_sentry:
    dsn: '%env(SENTRY_DSN)%'              # required
    environment: '%env(ENVIRONMENT)%'     # required
    namespace: ~                          # sent as the `namespace` tag
    release: 'none'
    traces_sample_rate: 0.0

    # Data sent with each event. See "What reaches Sentry".
    send_default_pii: false
    max_request_body_size: 'none'         # none, small, medium, always

    before_send: ~                        # service ID; replaces the default scrubber
    before_breadcrumb: ~                  # service ID

    json_serialize: []                    # classes and interfaces serialized as JSON in event context
    namespace_serialize: []               # namespaces whose objects are serialized as JSON

    scrubber:
        enabled: true
        query_param_names: [key, api_key, apikey, access_token, token, secret, password, auth, signature]
        extra_query_param_names: []
        value_patterns: []                # defaults: Google API keys, Stripe keys, Bearer tokens, JWTs
        extra_value_patterns: []
```

The bundle prepends this to the `sentry` configuration:

- `register_error_listener: false`, `messenger.enabled: true`, `capture_soft_fails: false`;
- `attach_stacktrace: true`;
- `RejectRedeliveredMessageException` is ignored.

Application config wins over the prepended values, so any `sentry.options` key can still be set
in the application.

## What reaches Sentry

By default an event carries no cookies, no client IP, no logged-in user and no request body.

- `send_default_pii: true` adds cookies (including the session cookie), the client IP and the user.
  A copied session cookie is a working login, so enable it only where nobody can misuse it.
- `max_request_body_size` controls the request body, independent of `send_default_pii`. Form posts
  and JSON bodies can hold passwords, one-time codes and personal data.

To opt in:

```yaml
lingoda_sentry:
    send_default_pii: true
    max_request_body_size: 'medium'
```

## Scrubber

`SensitiveDataScrubber` is the default `before_send`. It replaces credentials with `[Filtered]` in
URLs (by query parameter name), headers, exception messages, breadcrumbs and frame variables
(by value pattern). Extend the lists with `extra_query_param_names` and `extra_value_patterns`, or
decorate the service: the class is not final, and `scrub(string): string` is public.

Setting `before_send` replaces the scrubber.

## Monolog handler

`Lingoda\SentryBundle\Sentry\Handler` sends every log record as a breadcrumb, and only the latest of
the most severe records as an event. An event without an exception gets a fingerprint from the
message shape: messages with different shapes become separate issues, and ids or tokens inside one
shape do not split it into many.

```yaml
monolog:
    handlers:
        sentry:
            type: service
            id: Lingoda\SentryBundle\Sentry\Handler
            level: error
```

`LogReleaseProcessor` adds the `x-release-id` request header to log records as `release`.

## Upgrading to 5.0

- PHP 8.3 and Symfony 6.4 are no longer supported.
- `send_default_pii` is `false` and `max_request_body_size` is `none` by default. Events no longer
  carry cookies, IP, user or request body. Set both options to keep the old behaviour.

## Development

```bash
vendor/bin/phpunit
vendor/bin/phpspec run
vendor/bin/phpstan analyse -c phpstan.neon
vendor/bin/phpstan analyse -c phpstan.test.neon
```
