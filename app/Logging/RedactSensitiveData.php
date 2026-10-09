<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Monolog tap that removes secrets and credentials from log context, e.g. if
 * a developer accidentally logs request input. Configured for the "single" and
 * "daily" channels in config/logging.php.
 */
class RedactSensitiveData
{
    /**
     * Context keys whose values are never written to the log (case-insensitive,
     * substring match).
     */
    private const SENSITIVE_KEYS = [
        'password', 'passwort', 'token', 'secret', 'recovery', 'code', 'cookie',
        'contact_name', 'contact_email', 'contact_phone', 'contact_message', 'enquiry', 'form_nonce',
        'session', 'authorization', 'api_key', 'apikey', 'credential', 'recipient',
    ];

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof \Monolog\Logger) {
            $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
                context: self::redact($record->context),
                extra: self::redact($record->extra),
            ));
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
