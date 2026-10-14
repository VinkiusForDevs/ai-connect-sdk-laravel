<?php

namespace Vinkius\ConnectLaravel\Exceptions;

use Exception;
use Throwable;

class VinkiusException extends Exception
{
    protected ?int $status;

    protected ?string $requestId;

    protected ?string $errorCode;

    protected mixed $details;

    public function __construct(
        string $message = '',
        ?int $status = null,
        ?string $code = null,
        mixed $details = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
        $this->requestId = null;
        $this->errorCode = $code;
        $this->details = $details;
    }

    public function status(): ?int
    {
        return $this->status;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function code(): ?string
    {
        return $this->errorCode;
    }

    public function details(): mixed
    {
        return $this->details;
    }

    public static function forStatus(int $status, mixed $body, ?string $requestId = null, ?int $retryAfterMs = null): VinkiusException
    {
        $message = self::pickMessage($body, "Request failed with status {$status}");

        $e = match (true) {
            in_array($status, [401, 403], true) => new AuthenticationException($message, $status, $body),
            $status === 404 => new NotFoundException($message, $status, $body),
            $status === 422 => new ValidationException($message, $status, $body),
            $status === 402 => new OverageException($message, $status, $body),
            $status === 429 => self::isMcpShaped($body)
                ? new QuotaExceededException($message, $status, $body)
                : new RateLimitException($message, $status, $body, $retryAfterMs),
            default => new self($message, $status, 'api_error', $body),
        };

        $e->requestId = $requestId;

        if ($e instanceof ValidationException) {
            $b = is_array($body) || is_object($body) ? (array) $body : [];
            $e->fieldErrors = isset($b['errors']) && is_array($b['errors']) ? $b['errors'] : [];
        }

        if ($e instanceof QuotaExceededException || $e instanceof OverageException) {
            $b = is_array($body) || is_object($body) ? (array) $body : [];
            $e->upgradeUrl = isset($b['upgrade_url']) && is_string($b['upgrade_url']) ? $b['upgrade_url'] : null;
        }

        return $e;
    }

    private static function isMcpShaped(mixed $body): bool
    {
        if (!is_array($body) && !is_object($body)) {
            return false;
        }
        $b = (array) $body;

        return ($b['isError'] ?? null) === true
            || (isset($b['upgrade_url']) && is_string($b['upgrade_url']));
    }

    public static function pickMessage(mixed $body, string $fallback): string
    {
        if (is_array($body) || is_object($body)) {
            $b = (array) $body;
            if (isset($b['message']) && is_string($b['message']) && $b['message'] !== '') {
                return $b['message'];
            }
            if (isset($b['error']) && is_string($b['error'])) {
                return $b['error'];
            }
            if (isset($b['content']) && is_array($b['content'])) {
                foreach ($b['content'] as $entry) {
                    if (is_array($entry) && isset($entry['text']) && is_string($entry['text']) && $entry['text'] !== '') {
                        return $entry['text'];
                    }
                }
            }
        }
        if (is_string($body) && $body !== '') {
            return $body;
        }

        return $fallback;
    }
}
