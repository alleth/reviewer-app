<?php
declare(strict_types=1);

namespace App\Log\Engine;

use Cake\Log\Engine\BaseLog;
use Stringable;

/**
 * Emits log lines through PHP's error_log().
 *
 * Under the php:*-apache image (see Dockerfile) Apache's ErrorLog is wired to
 * /proc/self/fd/2, so error_log() output shows up in `railway logs`. The stock
 * FileLog engines only write to logs/*.log *inside* the container, which Railway
 * never surfaces and which is wiped on every scale-to-zero cold start — so this
 * engine is the only way production warnings/errors (e.g. "[mail] send failed")
 * are actually visible without shelling into a live instance.
 */
class StderrLog extends BaseLog
{
    /**
     * @param mixed $level The severity level of the message being logged.
     * @param \Stringable|string $message The message to log.
     * @param array<string, mixed> $context Additional context for the message.
     * @return void
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $message = $this->interpolate($message, $context);
        error_log(sprintf('[%s] %s', (string)$level, $message));
    }
}
