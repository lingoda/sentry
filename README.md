# lingoda/sentry

Symfony bundle that sets up `sentry/sentry-symfony` with shared defaults: data protection, a scrubber
for credentials, a Monolog handler, and serializers for event context.

## Requirements

- PHP `^8.4`
- Symfony `^7.4 || ^8.0`
- `sentry/sentry-symfony` `^5.14`

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
    send_default_pii: false               # the logged-in user
    send_ip: true                         # with send_default_pii: the client IP
    user_id_resolver: ~                   # service ID; see "Choosing the user id"
    max_request_body_size: 'none'         # none, small, medium, always

    before_send: ~                        # service ID; replaces the default scrubber
    before_breadcrumb: ~                  # service ID

    json_serialize: []                    # classes and interfaces serialized as JSON; entities in them as class and id
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

The bundle sets `sentry.options.data_collection` with every key explicit:

| Data | Sent | Option |
|---|---|---|
| Logged-in user (id) | only with `send_default_pii: true`; also on errors sent through Monolog after the request (see "Monolog handler") | `send_default_pii`, `user_id_resolver` |
| Client IP | with `send_default_pii: true`, unless `send_ip: false` | `send_ip` |
| Cookies | never | a copied session cookie is a working login |
| Request headers | yes, without sensitive ones (`Authorization`, `Cookie`, `*token*`, `*session*`, …) | |
| Request URL and method on errors sent through Monolog after the request | yes, the URL without the query string | |
| Incoming request body | only when `max_request_body_size` is not `none` | `max_request_body_size` |
| Outgoing HTTP bodies, database query values, AI inputs and outputs, queue data | never | |
| URL query parameters | yes, without sensitive ones | |
| Function arguments in stack frames | yes, without sensitive names | |
| Console arguments | yes, values of sensitive names filtered (`--password=[Filtered]`) | |

`max_request_body_size` is independent of `send_default_pii`: form posts and JSON bodies can hold
passwords, one-time codes and personal data.

To send the user and the IP:

```yaml
lingoda_sentry:
    send_default_pii: true
```

To send the user without the IP:

```yaml
lingoda_sentry:
    send_default_pii: true
    send_ip: false
```

`send_ip: false` is applied by the default scrubber. An application that replaces `before_send`
removes the IP itself.

Any `sentry.options.data_collection` key can still be set in the application; application config
wins over the prepended values. The legacy `sentry.options.send_default_pii` has no effect once
`data_collection` is set.

## Choosing the user id

By default the user id is the login identifier (`UserInterface::getUserIdentifier()`), which is
often the email address. To send something else, for example an internal id, implement
`UserIdResolver`:

```php
namespace App\Infrastructure\Sentry;

use App\Domain\User;
use Lingoda\SentryBundle\Sentry\UserIdResolver;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class InternalUserIdResolver implements UserIdResolver
{
    public function resolve(UserInterface $user): ?string
    {
        return $user instanceof User ? (string) $user->id() : null;
    }
}
```

```yaml
lingoda_sentry:
    send_default_pii: true
    user_id_resolver: App\Infrastructure\Sentry\InternalUserIdResolver
```

The resolver is an ordinary autowired service; no tag is needed. It runs once per request, and on
login. It only has an effect with `send_default_pii: true`. When it returns `null`, the event keeps
the login identifier.

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

`RequestContextProcessor` runs on every logger at log time, for warnings and above. It keeps the URL
(without the query string), the HTTP method and the Sentry user of the current request on the record.
Buffered handlers (`fingers_crossed`, `buffer`, `deduplication`) send records after the request has ended,
when sentry-php no longer finds the request or the user; the handler then puts the kept values on the
event. The user is kept only when `send_default_pii` allows it, and `send_ip: false` still removes the IP.
Other log handlers print the kept values as an empty object, so the user and the IP do not reach the logs.

## Upgrading to 5.1

- The bundle sets `sentry.options.data_collection` instead of the legacy options. `send_default_pii`
  now sends the user (and IP), never cookies.
- 5.0 applications that want the user back set `send_default_pii: true`.
- New: `send_ip` and `user_id_resolver`.
- New: `RequestContextProcessor`. Errors that Monolog buffers and sends after the request now carry the
  URL, the method and (with `send_default_pii`) the user. No configuration is needed.
- Requires `sentry/sentry-symfony` `^5.14`.

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
