<?php

declare(strict_types=1);

namespace Lingoda\SentryBundle\Sentry;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Stacktrace;

/**
 * Default `before_send` callback. Redacts well-known credential shapes from
 * URLs, headers, exception messages, breadcrumbs and frame variables before
 * the event reaches Sentry's servers.
 *
 * Designed to be overridden by consumers via Symfony service decoration:
 * the class is non-final and the `scrub(string): string` entry point is
 * public so decorators can reuse the string-level rules.
 */
class SensitiveDataScrubber
{
    public const string FILTERED = '[Filtered]';
    private const int MAX_FRAME_VAR_DEPTH = 3;

    /**
     * @var list<string>
     */
    private array $valuePatterns;

    private string $urlQueryRegex;

    /**
     * @param list<string> $queryParamNames
     * @param list<string> $valuePatterns
     */
    public function __construct(
        array $queryParamNames,
        array $valuePatterns,
    ) {
        $this->validatePatterns($valuePatterns);
        $this->valuePatterns = array_values($valuePatterns);
        $this->urlQueryRegex = $this->buildUrlQueryRegex($this->normalizeNames($queryParamNames));
    }

    public function __invoke(Event $event, ?EventHint $hint = null): ?Event
    {
        $message = $event->getMessage();
        if ($message !== null) {
            $event->setMessage(
                $this->scrub($message),
                array_map(fn (string $p): string => $this->scrub($p), $event->getMessageParams()),
                $this->scrubNullable($event->getMessageFormatted()),
            );
        }

        $request = $event->getRequest();
        if ($request !== []) {
            $event->setRequest($this->scrubRequestArray($request));
        }

        $this->scrubExceptions($event);
        $this->scrubBreadcrumbs($event);

        $topStacktrace = $event->getStacktrace();
        if ($topStacktrace !== null) {
            $this->scrubStacktrace($topStacktrace);
        }

        return $event;
    }

    /**
     * Apply URL-query and value-pattern scrubbing to a single string.
     * Public so consumer decorators can reuse the rules.
     */
    public function scrub(string $value): string
    {
        return $this->scrubByPatterns($this->scrubUrlQuery($value));
    }

    private function scrubNullable(?string $value): ?string
    {
        return $value === null ? null : $this->scrub($value);
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function scrubRequestArray(array $request): array
    {
        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = $this->scrub($request['url']);
        }

        if (isset($request['query_string']) && is_string($request['query_string'])) {
            $request['query_string'] = $this->scrub($request['query_string']);
        }

        foreach (['headers', 'cookies', 'data', 'env'] as $key) {
            if (isset($request[$key]) && is_array($request[$key])) {
                $request[$key] = $this->scrubArrayRecursive($request[$key], 0);
            }
        }

        return $request;
    }

    private function scrubExceptions(Event $event): void
    {
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue($this->scrub($exception->getValue()));

            $stacktrace = $exception->getStacktrace();
            if ($stacktrace !== null) {
                $this->scrubStacktrace($stacktrace);
            }
        }
    }

    private function scrubBreadcrumbs(Event $event): void
    {
        $breadcrumbs = $event->getBreadcrumbs();
        if ($breadcrumbs === []) {
            return;
        }

        $event->setBreadcrumb(array_map(
            fn (Breadcrumb $b): Breadcrumb => $this->scrubBreadcrumb($b),
            $breadcrumbs,
        ));
    }

    private function scrubBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $message = $breadcrumb->getMessage();
        if ($message !== null) {
            $breadcrumb = $breadcrumb->withMessage($this->scrub($message));
        }

        foreach ($breadcrumb->getMetadata() as $key => $value) {
            if (is_string($value)) {
                $scrubbed = $this->scrub($value);
                if ($scrubbed !== $value) {
                    $breadcrumb = $breadcrumb->withMetadata($key, $scrubbed);
                }
            }
        }

        return $breadcrumb;
    }

    private function scrubStacktrace(Stacktrace $stacktrace): void
    {
        foreach ($stacktrace->getFrames() as $frame) {
            $vars = $frame->getVars();
            if ($vars === []) {
                continue;
            }
            $frame->setVars($this->scrubArrayRecursive($vars, 0));
        }
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function scrubArrayRecursive(array $values, int $depth): array
    {
        if ($depth >= self::MAX_FRAME_VAR_DEPTH) {
            return $values;
        }

        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $this->scrub($value);
            } elseif (is_array($value)) {
                $values[$key] = $this->scrubArrayRecursive($value, $depth + 1);
            }
        }

        return $values;
    }

    private function scrubUrlQuery(string $value): string
    {
        if ($this->urlQueryRegex === '') {
            return $value;
        }

        $result = preg_replace_callback(
            $this->urlQueryRegex,
            /** @param array<int, string> $m */
            static fn (array $m): string => $m[1] . $m[2] . '=' . self::FILTERED,
            $value,
        );

        return $result ?? $value;
    }

    private function scrubByPatterns(string $value): string
    {
        foreach ($this->valuePatterns as $pattern) {
            $result = preg_replace($pattern, self::FILTERED, $value);
            if (is_string($result)) {
                $value = $result;
            }
        }

        return $value;
    }

    /**
     * @param list<string> $patterns
     */
    private function validatePatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, '') === false) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid PCRE pattern in scrubber value_patterns: %s',
                    $pattern,
                ));
            }
        }
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function normalizeNames(array $names): array
    {
        return array_values(array_unique(array_map(
            static fn (string $n): string => strtolower($n),
            $names,
        )));
    }

    /**
     * @param list<string> $names
     */
    private function buildUrlQueryRegex(array $names): string
    {
        if ($names === []) {
            return '';
        }

        $alternatives = implode('|', array_map(
            static fn (string $n): string => preg_quote($n, '#'),
            $names,
        ));

        // (prefix)(name)=(value-until-delimiter)
        // prefix: start-of-string or `?` or `&`; value stops at `&`, `#`, whitespace, quote, `>`.
        // The `#` inside the character class is escaped because we use `#` as the PCRE delimiter.
        return '#(^|[?&])(' . $alternatives . ')=[^&\#\s"\'>]+#i';
    }
}
