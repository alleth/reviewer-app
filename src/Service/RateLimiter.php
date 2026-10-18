<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Cake\Utility\Security;

/**
 * Sliding-window rate limits over the `rate_limits` table (H4).
 *
 * Every counted action is one row: (bucket, key_hash, created). A limit is a
 * list of [max, windowSeconds] pairs; an action is refused while any window
 * already holds `max` events, until the oldest of the counting ones leaves it.
 *
 * Keys are HMAC-SHA256 digests (keyed with the app's security salt) of
 * "<kind>:<value>" — an account id, a typed identifier, or a client IP — so no
 * IP address or email address is ever stored or logged (RA 10173).
 *
 * The RATE_LIMIT_MODE env var (`enforce`, default; `log`; `off`) switches all
 * limits; RATE_LIMIT_IP_MODE (`log`, default; `enforce`; `off`) the per-IP ones,
 * which stay log-only until the client-IP source is confirmed on Railway. In
 * `log` mode a limit that would refuse is logged but allowed; `off` neither
 * counts nor checks.
 */
class RateLimiter
{
    use LocatorAwareTrait;

    public const MODE_ENFORCE = 'enforce';
    public const MODE_LOG = 'log';
    public const MODE_OFF = 'off';

    /** Events are kept this long, then pruned on write. */
    private const RETENTION_SECONDS = 7 * 86400;

    private Table $events;

    /**
     * Uses the RateLimits table through the default table locator.
     */
    public function __construct()
    {
        $this->events = $this->fetchTable('RateLimits');
    }

    /**
     * The stored form of a key: an HMAC, never the raw value.
     */
    public static function key(string $kind, string $value): string
    {
        return hash_hmac('sha256', $kind . ':' . $value, Security::getSalt());
    }

    /**
     * The effective mode for a limit; per-IP limits can't be stricter than the
     * global mode.
     */
    public static function mode(bool $perIp = false): string
    {
        $global = self::parseMode((string)env('RATE_LIMIT_MODE'), self::MODE_ENFORCE);
        if (!$perIp || $global === self::MODE_OFF) {
            return $global;
        }
        $ip = self::parseMode((string)env('RATE_LIMIT_IP_MODE'), self::MODE_LOG);
        if ($global === self::MODE_LOG && $ip === self::MODE_ENFORCE) {
            return self::MODE_LOG;
        }

        return $ip;
    }

    /**
     * A mode from an env value, or $default when it isn't one of the three.
     */
    private static function parseMode(string $value, string $default): string
    {
        $value = strtolower(trim($value));

        return in_array($value, [self::MODE_ENFORCE, self::MODE_LOG, self::MODE_OFF], true) ? $value : $default;
    }

    /**
     * Records one event. Also prunes events past retention, so the table
     * stays bounded without a cron.
     */
    public function hit(string $bucket, string $key, bool $perIp = false): void
    {
        if (self::mode($perIp) === self::MODE_OFF) {
            return;
        }

        $now = new DateTime();
        $this->events->saveOrFail($this->events->newEntity([
            'bucket' => $bucket,
            'key_hash' => $key,
            'created' => $now,
        ]));
        $this->events->deleteAll(['created <' => $now->modify('-' . self::RETENTION_SECONDS . ' seconds')]);
    }

    /**
     * Forgets every event for (bucket, key): a successful sign-in, code or
     * recovery clears what it was guarding against.
     */
    public function clear(string $bucket, string $key): void
    {
        $this->events->deleteAll(['bucket' => $bucket, 'key_hash' => $key]);
    }

    /**
     * Events for (bucket, key) inside the last $windowSeconds.
     */
    public function count(string $bucket, string $key, int $windowSeconds): int
    {
        return $this->events->find()
            ->where([
                'bucket' => $bucket,
                'key_hash' => $key,
                'created >' => (new DateTime())->modify("-{$windowSeconds} seconds"),
            ])
            ->count();
    }

    /**
     * When the most recent event for (bucket, key) happened, or null.
     */
    public function lastHit(string $bucket, string $key): ?DateTime
    {
        /** @var \App\Model\Entity\RateLimit|null $row */
        $row = $this->events->find()
            ->where(['bucket' => $bucket, 'key_hash' => $key])
            ->orderBy(['created' => 'DESC', 'rate_limit_id' => 'DESC'])
            ->first();

        return $row?->created;
    }

    /**
     * Null when the next action is allowed, else the seconds until it is.
     * Honours the mode: in `log` a refusal is only logged, in `off` nothing is
     * checked.
     *
     * @param list<array{0: int, 1: int}> $limits [max, windowSeconds] pairs.
     */
    public function retryAfter(string $bucket, string $key, array $limits, bool $perIp = false): ?int
    {
        $mode = self::mode($perIp);
        if ($mode === self::MODE_OFF) {
            return null;
        }

        $wait = null;
        foreach ($limits as [$max, $window]) {
            $seconds = $this->windowWait($bucket, $key, $max, $window);
            if ($seconds !== null && ($wait === null || $seconds > $wait)) {
                $wait = $seconds;
            }
        }

        return $this->decide($bucket, $key, $wait, $mode);
    }

    /**
     * Applies the mode to a wait worked out by the caller (the sign-in
     * escalation, say): logs it, and returns it only when enforcing.
     */
    public function decide(string $bucket, string $key, ?int $wait, ?string $mode = null): ?int
    {
        $mode ??= self::mode();
        if ($wait === null || $mode === self::MODE_OFF) {
            return null;
        }

        $enforced = $mode === self::MODE_ENFORCE;
        Log::info(sprintf(
            '[ratelimit] %s %s: %s (retry after %ds)',
            $bucket,
            substr($key, 0, 12),
            $enforced ? 'refused' : 'would refuse (log mode)',
            $wait,
        ), ['scope' => ['ratelimit']]);

        return $enforced ? $wait : null;
    }

    /**
     * Seconds until one more event fits in this window, or null if it fits now.
     */
    private function windowWait(string $bucket, string $key, int $max, int $window): ?int
    {
        $now = new DateTime();
        $times = $this->events->find()
            ->select(['created'])
            ->where([
                'bucket' => $bucket,
                'key_hash' => $key,
                'created >' => $now->modify("-{$window} seconds"),
            ])
            ->orderBy(['created' => 'DESC', 'rate_limit_id' => 'DESC'])
            ->limit($max)
            ->all()
            ->extract('created')
            ->toList();

        if (count($times) < $max) {
            return null;
        }

        // The oldest of the newest $max events has to leave the window first.
        /** @var \Cake\I18n\DateTime $oldest */
        $oldest = end($times);

        return max(1, $window - ($now->getTimestamp() - $oldest->getTimestamp()));
    }
}
