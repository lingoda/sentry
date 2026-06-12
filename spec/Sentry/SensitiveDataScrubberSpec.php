<?php

declare(strict_types=1);

namespace spec\Lingoda\SentryBundle\Sentry;

use Lingoda\SentryBundle\DependencyInjection\Configuration;
use Lingoda\SentryBundle\Sentry\SensitiveDataScrubber;
use PhpSpec\ObjectBehavior;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;

class SensitiveDataScrubberSpec extends ObjectBehavior
{
    public function let(): void
    {
        $this->beConstructedWith(
            Configuration::DEFAULT_QUERY_PARAM_NAMES,
            Configuration::DEFAULT_VALUE_PATTERNS,
        );
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(SensitiveDataScrubber::class);
    }

    public function it_filters_query_string_secret_from_url(): void
    {
        $value = 'https://example.com/v1/foo?key=AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567&page=2#frag';

        $this->scrub($value)
            ->shouldBe('https://example.com/v1/foo?key=[Filtered]&page=2#frag');
    }

    public function it_filters_multiple_known_query_param_names(): void
    {
        foreach (['key', 'api_key', 'apikey', 'access_token', 'token', 'secret', 'password', 'auth', 'signature'] as $name) {
            $input = "https://example.com/?$name=hunter2";
            $this->scrub($input)
                ->shouldBe("https://example.com/?$name=[Filtered]");
        }
    }

    public function it_matches_query_param_names_case_insensitively(): void
    {
        $this->scrub('https://example.com/?Key=abc')
            ->shouldBe('https://example.com/?Key=[Filtered]');
    }

    public function it_leaves_non_sensitive_query_params_intact(): void
    {
        $value = 'https://example.com/v1/foo?page=2&order=desc&user_id=42';

        $this->scrub($value)->shouldBe($value);
    }

    public function it_preserves_url_scheme_host_path_and_fragment(): void
    {
        $value = 'https://user.example.com:8443/some/path?key=SECRET&other=ok#section';

        $this->scrub($value)
            ->shouldBe('https://user.example.com:8443/some/path?key=[Filtered]&other=ok#section');
    }

    public function it_filters_google_api_key_pattern_in_free_text(): void
    {
        $this->scrub('Failed with key AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567 in handler')
            ->shouldEndWith(' in handler');

        $this->scrub('AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567')
            ->shouldBe('[Filtered]');
    }

    public function it_filters_stripe_secret_key_pattern(): void
    {
        $this->scrub('sk_live_abcdefghijklmnop')->shouldBe('[Filtered]');
        $this->scrub('sk_test_ZYXWVUTSRQPONMLK')->shouldBe('[Filtered]');
    }

    public function it_filters_bearer_token_pattern(): void
    {
        $this->scrub('Bearer abc.DEF-ghi_jkl/mno+pqr=')->shouldBe('[Filtered]');
    }

    public function it_filters_jwt_pattern(): void
    {
        $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U';
        $this->scrub($jwt)->shouldBe('[Filtered]');
    }

    public function it_leaves_non_credential_strings_alone(): void
    {
        $this->scrub('the quick brown fox jumps over the lazy dog')
            ->shouldBe('the quick brown fox jumps over the lazy dog');

        $this->scrub('user@example.com')->shouldBe('user@example.com');
    }

    public function it_filters_known_query_params_from_exception_message(): void
    {
        $event = Event::createEvent();
        $message = 'HTTP/2 403 returned for "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567"';
        $event->setExceptions([new ExceptionDataBag(new RuntimeException($message))]);

        $this->__invoke($event)
            ->shouldHaveExceptionValue(
                'HTTP/2 403 returned for "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=[Filtered]"'
            );
    }

    public function it_filters_request_url_and_query_string(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example.com/v1/things?api_key=secret123&page=2',
            'query_string' => 'api_key=secret123&page=2',
        ]);

        $this->__invoke($event)->shouldHaveRequestKey('url', 'https://api.example.com/v1/things?api_key=[Filtered]&page=2');
        $this->__invoke($event)->shouldHaveRequestKey('query_string', 'api_key=[Filtered]&page=2');
    }

    public function it_filters_query_params_in_breadcrumb_message_and_url(): void
    {
        $event = Event::createEvent();
        $event->setBreadcrumb([
            new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_HTTP,
                'http',
                'GET https://api.example.com/?token=abc',
                ['url' => 'https://api.example.com/?token=abc'],
            ),
        ]);

        $this->__invoke($event)->shouldHaveBreadcrumbMessage(0, 'GET https://api.example.com/?token=[Filtered]');
        $this->__invoke($event)->shouldHaveBreadcrumbMetadata(0, 'url', 'https://api.example.com/?token=[Filtered]');
    }

    public function it_filters_string_values_in_frame_vars(): void
    {
        $event = Event::createEvent();
        $frame = new Frame(
            'callApi',
            'src/Foo.php',
            42,
            null,
            null,
            ['url' => 'https://example.com/?key=AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567', 'count' => 7],
        );
        $event->setExceptions([
            new ExceptionDataBag(new RuntimeException('boom'), new Stacktrace([$frame])),
        ]);

        $this->__invoke($event)->shouldHaveFrameVar(0, 'url', 'https://example.com/?key=[Filtered]');
        $this->__invoke($event)->shouldHaveFrameVar(0, 'count', 7);
    }

    public function it_does_not_recurse_into_frame_vars_past_max_depth(): void
    {
        $event = Event::createEvent();
        $secret = 'AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567';
        $deepVars = ['a' => ['b' => ['c' => ['d' => $secret]]]];
        $frame = new Frame('deep', 'src/Foo.php', 1, null, null, $deepVars);
        $event->setExceptions([
            new ExceptionDataBag(new RuntimeException('boom'), new Stacktrace([$frame])),
        ]);

        $this->__invoke($event)->shouldHaveDeepFrameValue($secret);
    }

    public function it_passes_through_when_event_is_empty(): void
    {
        $event = Event::createEvent();

        $this->__invoke($event)->shouldReturnAnInstanceOf(Event::class);
    }

    public function it_filters_event_message_set_via_capture_message(): void
    {
        $event = Event::createEvent();
        $event->setMessage(
            'Upstream call failed: https://api.example.com/v1/x?api_key=AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567',
            ['AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567'],
            'Upstream call failed: https://api.example.com/v1/x?api_key=AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567',
        );

        $scrubbed = $this->__invoke($event);
        $scrubbed->shouldHaveEventMessage('Upstream call failed: https://api.example.com/v1/x?api_key=[Filtered]');
        $scrubbed->shouldHaveEventMessageFormatted('Upstream call failed: https://api.example.com/v1/x?api_key=[Filtered]');
        $scrubbed->shouldHaveEventMessageParam(0, '[Filtered]');
    }

    public function it_filters_authorization_header_value(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example.com/v1/things',
            'headers' => [
                'Authorization' => ['Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U'],
                'X-Request-Id' => ['abc-123'],
            ],
        ]);

        $scrubbed = $this->__invoke($event);
        $scrubbed->shouldHaveRequestHeader('Authorization', 0, '[Filtered]');
        $scrubbed->shouldHaveRequestHeader('X-Request-Id', 0, 'abc-123');
    }

    public function it_filters_lowercase_bearer_in_header_dumps(): void
    {
        $message = 'Got: authorization: bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U';
        $this->scrub($message)
            ->shouldBe('Got: authorization: [Filtered]');
    }

    public function it_does_not_eat_english_text_containing_bearer(): void
    {
        $this->scrub('This is not a Bearer of tokens')
            ->shouldBe('This is not a Bearer of tokens');
    }

    public function it_filters_credentials_in_request_data_and_cookies(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'cookies' => ['session' => 'sk_live_abcdefghijklmnop'],
            'data' => ['payload' => ['inner' => 'AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567']],
        ]);

        $scrubbed = $this->__invoke($event);
        $scrubbed->shouldHaveRequestNested('cookies', ['session'], '[Filtered]');
        $scrubbed->shouldHaveRequestNested('data', ['payload', 'inner'], '[Filtered]');
    }

    public function it_filters_query_string_in_breadcrumb_http_query_metadata(): void
    {
        $event = Event::createEvent();
        $event->setBreadcrumb([
            new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_HTTP,
                'http',
                null,
                [
                    'url' => 'https://api.example.com/v1/items',
                    'http.query' => 'api_key=AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567&page=2',
                    'http.request.method' => 'GET',
                ],
            ),
        ]);

        $scrubbed = $this->__invoke($event);
        $scrubbed->shouldHaveBreadcrumbMetadata(0, 'http.query', 'api_key=[Filtered]&page=2');
        $scrubbed->shouldHaveBreadcrumbMetadata(0, 'http.request.method', 'GET');
    }

    public function it_rejects_invalid_value_patterns_at_construction(): void
    {
        $this->beConstructedWith(Configuration::DEFAULT_QUERY_PARAM_NAMES, ['#unterminated(#']);
        $this->shouldThrow(\Throwable::class)->duringInstantiation();
    }

    /**
     * @return array<string, callable>
     */
    public function getMatchers(): array
    {
        return [
            'haveExceptionValue' => static fn (Event $subject, string $expected): bool =>
                ($subject->getExceptions()[0] ?? null)?->getValue() === $expected,
            'haveEventMessage' => static fn (Event $subject, string $expected): bool =>
                $subject->getMessage() === $expected,
            'haveEventMessageFormatted' => static fn (Event $subject, string $expected): bool =>
                $subject->getMessageFormatted() === $expected,
            'haveEventMessageParam' => static fn (Event $subject, int $i, string $expected): bool =>
                ($subject->getMessageParams()[$i] ?? null) === $expected,
            'haveRequestKey' => static function (Event $subject, string $key, string $expected): bool {
                $request = $subject->getRequest();
                return ($request[$key] ?? null) === $expected;
            },
            'haveRequestHeader' => static function (Event $subject, string $name, int $i, string $expected): bool {
                $headers = $subject->getRequest()['headers'] ?? null;
                if (!is_array($headers) || !isset($headers[$name][$i])) {
                    return false;
                }
                return $headers[$name][$i] === $expected;
            },
            'haveRequestNested' => static function (Event $subject, string $top, array $path, mixed $expected): bool {
                $value = $subject->getRequest()[$top] ?? null;
                foreach ($path as $k) {
                    if (!is_array($value) || !array_key_exists($k, $value)) {
                        return false;
                    }
                    $value = $value[$k];
                }
                return $value === $expected;
            },
            'haveBreadcrumbMessage' => static function (Event $subject, int $i, string $expected): bool {
                $bc = $subject->getBreadcrumbs()[$i] ?? null;
                return $bc instanceof Breadcrumb && $bc->getMessage() === $expected;
            },
            'haveBreadcrumbMetadata' => static function (Event $subject, int $i, string $key, string $expected): bool {
                $bc = $subject->getBreadcrumbs()[$i] ?? null;
                if (!$bc instanceof Breadcrumb) {
                    return false;
                }
                return ($bc->getMetadata()[$key] ?? null) === $expected;
            },
            'haveFrameVar' => static function (Event $subject, int $i, string $key, mixed $expected): bool {
                $stacktrace = ($subject->getExceptions()[0] ?? null)?->getStacktrace();
                if ($stacktrace === null) {
                    return false;
                }
                $frame = $stacktrace->getFrames()[$i] ?? null;
                if (!$frame instanceof Frame) {
                    return false;
                }
                return ($frame->getVars()[$key] ?? null) === $expected;
            },
            'haveDeepFrameValue' => static function (Event $subject, string $expected): bool {
                $stacktrace = ($subject->getExceptions()[0] ?? null)?->getStacktrace();
                if ($stacktrace === null) {
                    return false;
                }
                $frame = $stacktrace->getFrames()[0] ?? null;
                if (!$frame instanceof Frame) {
                    return false;
                }
                $value = $frame->getVars();
                foreach (['a', 'b', 'c', 'd'] as $key) {
                    if (!is_array($value) || !array_key_exists($key, $value)) {
                        return $value === $expected;
                    }
                    $value = $value[$key];
                }
                return $value === $expected;
            },
        ];
    }
}
