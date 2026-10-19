<?php
declare(strict_types=1);

namespace App\Controller;

use App\Auth\GoogleTokenVerifierInterface;
use App\Auth\InvalidGoogleCredentialException;
use App\Http\ClientIp;
use App\Mailer\Transport\BrevoTransport;
use App\Model\Entity\User;
use App\Model\Table\UsersTable;
use App\Security\TurnstileVerifierInterface;
use App\Service\RateLimiter;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Mailer\Message;
use Cake\Mailer\Transport\SmtpTransport;
use Cake\Mailer\TransportFactory;
use Cake\Validation\Validation;
use LogicException;
use PDOException;
use Throwable;

/**
 * Sign-up, sign-in and account endpoints (see CLAUDE.md, Authentication).
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
    /** How long an emailed code stays valid, and how many guesses it allows. */
    private const CODE_TTL_MINUTES = 10;
    private const CODE_MAX_ATTEMPTS = 5;

    /** Minimum password length enforced on every path that sets a password. */
    private const MIN_PASSWORD_LENGTH = 8;

    /** bcrypt only hashes the first 72 bytes; reject longer so nothing is silently dropped. */
    private const MAX_PASSWORD_BYTES = 72;

    /** Distinct devices within 60s that trips the login-challenge guard. */
    private const DEVICE_SWITCH_LIMIT = 4;

    /**
     * Sign-in escalation (H4), on failures for the account (username and
     * email share one bucket) or, for an unknown name, the typed identifier:
     * from 5 in the last hour a 30 s wait after each failure; from 10 a
     * correct password also needs an emailed code (step-up); from 20 a 15 min
     * wait, and from 50 in a day an hour. Never a permanent lock.
     */
    private const LOGIN_DELAY_AFTER = 5;
    private const LOGIN_DELAY_SECONDS = 30;
    private const LOGIN_STEP_UP_AFTER = 10;
    private const LOGIN_LONG_WAIT_AFTER = 20;
    private const LOGIN_LONG_WAIT_SECONDS = 900;
    private const LOGIN_DAY_WAIT_AFTER = 50;
    private const LOGIN_DAY_WAIT_SECONDS = 3600;

    /**
     * Wrong guesses allowed per purpose and account (or, for an address with
     * no account, per address) across every code issued: a new code no longer
     * brings fresh guesses (H4).
     *
     * @var list<array{0: int, 1: int}>
     */
    private const CODE_GUESS_LIMITS = [[10, 3600], [50, 86400]];

    /**
     * Per-client-IP limits (H4). Log-only until RATE_LIMIT_IP_MODE=enforce.
     *
     * @var array<string, list<array{0: int, 1: int}>>
     */
    private const IP_LIMITS = [
        'login_fail_ip' => [[50, 900]],
        'forgot_ip' => [[10, 3600], [30, 86400]],
        'reset_ip' => [[30, 3600]],
        'resend_ip' => [[10, 3600]],
        'register_ip' => [[5, 3600], [20, 86400]],
        'google_ip' => [[30, 600]],
    ];

    /** Wrong current passwords while signed in (changePassword, changeEmail). */
    private const REAUTH_FAIL_LIMIT = 5;
    private const REAUTH_FAIL_WINDOW_SECONDS = 900;

    /**
     * Codes sent through sendLimitedCode(): email verification (B3-V) and
     * linking Google sign-in to an existing verified account (B3-L). Each
     * purpose's rows hold the code itself; `<purpose>_sent` rows hold no code,
     * one per email sent, and back the resend limits (a cooldown, and a daily
     * cap that bounds code guessing and email spam).
     */
    private const PURPOSE_VERIFY_EMAIL = 'verify_email';
    private const PURPOSE_LINK_GOOGLE = 'link_google';

    /**
     * Changing the account's email (H3): a code to the new address and, for an
     * account without a password, a re-authentication code to the current one
     * first. Both are bound to the new address (see issueCode()).
     */
    private const PURPOSE_CHANGE_EMAIL = 'change_email';
    private const PURPOSE_EMAIL_CHANGE_REAUTH = 'email_change_reauth';

    private const CODE_RESEND_COOLDOWN_SECONDS = 60;
    private const CODE_MAX_SENDS_PER_DAY = 10;

    /**
     * Daily send caps that differ from CODE_MAX_SENDS_PER_DAY. Password reset
     * is higher so that someone else can't easily use up the owner's
     * allowance; per-IP limits and Turnstile sit in front of it (H4).
     *
     * @var array<string, int>
     */
    private const CODE_SENDS_PER_DAY = ['password_reset' => 20];

    private ?RateLimiter $limiter = null;

    /** A hash that stays stable per browser/device (used by the guard). */
    private function deviceHash(): string
    {
        return hash('sha256', (string)$this->request->getHeaderLine('User-Agent'));
    }

    /**
     * Records a completed login for the rapid device-switch guard.
     */
    private function recordLoginEvent(int $userId): void
    {
        $events = $this->fetchTable('LoginEvents');
        $events->save($events->newEntity([
            'user_id' => $userId,
            'device_hash' => $this->deviceHash(),
            'created' => new DateTime(),
        ]));
    }

    /** Normalizes a submitted username/email into a throttle bucket key. */
    private function loginIdentifier(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }

    /**
     * The request's rate limiter, created on first use.
     */
    private function limiter(): RateLimiter
    {
        return $this->limiter ??= new RateLimiter();
    }

    /** The rate-limit key for an account. */
    private function accountKey(int $userId): string
    {
        return RateLimiter::key('u', (string)$userId);
    }

    /** The rate-limit key for this request's client IP. */
    private function ipKey(): string
    {
        return RateLimiter::key('ip', ClientIp::from($this->request));
    }

    /**
     * The standard 429: `reason: rate_limited` and `retryAfter` seconds, plus a
     * Retry-After header, so the SPA can count down.
     */
    private function tooMany(int $seconds): Response
    {
        $wait = $seconds < 120
            ? $seconds . ' second' . ($seconds === 1 ? '' : 's')
            : (int)ceil($seconds / 60) . ' minutes';

        return $this->json([
            'success' => false,
            'reason' => 'rate_limited',
            'retryAfter' => $seconds,
            'message' => "Too many attempts. Please try again in {$wait}.",
        ], 429)->withHeader('Retry-After', (string)$seconds);
    }

    /**
     * Per-IP limit for $bucket (see IP_LIMITS): checks, then counts this
     * request. A 429 when over, else null.
     */
    private function ipLimited(string $bucket): ?Response
    {
        // TEMPORARY staging-only diagnostic (H4): forwarding-header shape, no addresses.
        if (env('RAILWAY_ENVIRONMENT_NAME') === 'staging') {
            $d = ClientIp::diagnose($this->request);
            $yn = fn(bool $v): string => $v ? 'yes' : 'no';
            Log::info(sprintf(
                '[clientip diag] bucket=%s xff=%s xff_entries=%d x_real_ip=%s x_envoy_external_address=%s'
                . ' xff_left_equals_real_ip=%s xff_right_is_internal=%s remote_addr_fallback=%s source=%s',
                $bucket,
                $yn($d['xff']),
                $d['xff_entries'],
                $yn($d['x_real_ip']),
                $yn($d['x_envoy_external_address']),
                $yn($d['xff_left_equals_real_ip']),
                $yn($d['xff_right_is_internal']),
                $yn($d['remote_addr_fallback']),
                $d['source'],
            ), ['scope' => ['ratelimit']]);
        }

        $key = $this->ipKey();
        $wait = $this->limiter()->retryAfter($bucket, $key, self::IP_LIMITS[$bucket], true);
        if ($wait !== null) {
            return $this->tooMany($wait);
        }
        $this->limiter()->hit($bucket, $key, true);

        return null;
    }

    /**
     * Where sign-in stands for $key after its recent failures: how long to
     * wait (null = no wait) and whether a correct password also needs an
     * emailed code. Both follow RATE_LIMIT_MODE.
     *
     * @return array{wait: int|null, stepUp: bool}
     */
    private function loginEscalation(string $key): array
    {
        $limiter = $this->limiter();
        if (RateLimiter::mode() === RateLimiter::MODE_OFF) {
            return ['wait' => null, 'stepUp' => false];
        }

        $lastHour = $limiter->count('login_fail', $key, 3600);
        $lastDay = $limiter->count('login_fail', $key, 86400);
        $last = $limiter->lastHit('login_fail', $key);

        $delay = match (true) {
            $lastDay >= self::LOGIN_DAY_WAIT_AFTER => self::LOGIN_DAY_WAIT_SECONDS,
            $lastHour >= self::LOGIN_LONG_WAIT_AFTER => self::LOGIN_LONG_WAIT_SECONDS,
            $lastHour >= self::LOGIN_DELAY_AFTER => self::LOGIN_DELAY_SECONDS,
            default => 0,
        };
        $wait = null;
        if ($delay > 0 && $last !== null) {
            $remaining = $delay - ((new DateTime())->getTimestamp() - $last->getTimestamp());
            $wait = $remaining > 0 ? $remaining : null;
        }

        $enforce = RateLimiter::mode() === RateLimiter::MODE_ENFORCE;

        return [
            'wait' => $limiter->decide('login_fail', $key, $wait),
            'stepUp' => $enforce && $lastHour >= self::LOGIN_STEP_UP_AFTER,
        ];
    }

    /**
     * Checks a code against the guess budget for its purpose (H4). True when
     * right (and the budget is reset), false when wrong (and counted), or the
     * seconds to wait when the budget is used up (nothing is checked then).
     * `$budgetKey` is the account's key, or for an address with no account
     * the address's, so both answer alike.
     */
    private function checkCode(
        ?int $userId,
        string $purpose,
        string $code,
        string $budgetKey,
        string $binding = '',
        bool $consume = true,
    ): bool|int {
        $bucket = 'code_guess:' . $purpose;
        $wait = $this->limiter()->retryAfter($bucket, $budgetKey, self::CODE_GUESS_LIMITS);
        if ($wait !== null) {
            return $wait;
        }

        $ok = $userId !== null && $code !== '' && $this->consumeCode($userId, $purpose, $code, $binding, $consume);
        if ($ok) {
            $this->limiter()->clear($bucket, $budgetKey);
        } else {
            $this->limiter()->hit($bucket, $budgetKey);
        }

        return $ok;
    }

    /**
     * The budget key for a code tied to an email address: the account's if
     * there is one, else the address's (I5), so a missing account looks the
     * same as a wrong code.
     */
    private function codeBudgetKey(?User $user, string $email): string
    {
        return $user !== null
            ? $this->accountKey((int)$user->user_id)
            : RateLimiter::key('id', UsersTable::normalizeEmail($email));
    }

    /**
     * A 429 when this account has had too many wrong current passwords while
     * signed in (changePassword, changeEmail), else null.
     */
    private function reauthLimited(int $userId): ?Response
    {
        $wait = $this->limiter()->retryAfter(
            'reauth_fail',
            $this->accountKey($userId),
            [[self::REAUTH_FAIL_LIMIT, self::REAUTH_FAIL_WINDOW_SECONDS]],
        );

        return $wait !== null ? $this->tooMany($wait) : null;
    }

    /**
     * Counts a wrong current password while signed in. The attempt that
     * reaches the limit also emails the owner, at most once per window.
     */
    private function recordReauthFailure(User $user): void
    {
        $limiter = $this->limiter();
        $key = $this->accountKey((int)$user->user_id);
        $limiter->hit('reauth_fail', $key);

        if (
            $limiter->count('reauth_fail', $key, self::REAUTH_FAIL_WINDOW_SECONDS) >= self::REAUTH_FAIL_LIMIT
            && $limiter->count('reauth_notice', $key, self::REAUTH_FAIL_WINDOW_SECONDS) === 0
        ) {
            $limiter->hit('reauth_notice', $key);
            $this->sendMail(
                (string)$user->email,
                'Several wrong password attempts on your CareerPass account',
                'Several wrong password attempts were made while signed in to your CareerPass account. '
                . "If this wasn't you, sign out of other devices by changing your password.",
            );
        }
    }

    /**
     * The Turnstile check for register and forgot password (H4): 400 for a
     * missing or invalid token when a secret is configured; skipped without
     * one; allowed through (and logged) when Cloudflare can't be asked.
     */
    private function turnstileBlocked(TurnstileVerifierInterface $turnstile): ?Response
    {
        if (!$turnstile->isEnabled()) {
            return null;
        }

        $token = trim($this->textField($this->request->getData(), 'turnstile_token'));
        $valid = $token === '' ? false : $turnstile->verify($token, ClientIp::from($this->request));
        if ($valid === null) {
            Log::warning('[turnstile] verification unavailable; allowed (fail open)');

            return null;
        }
        if (!$valid) {
            return $this->json(
                ['success' => false, 'message' => 'Please complete the security check and try again.'],
                400,
            );
        }

        return null;
    }

    /**
     * The emailed-code check before a password sign-in: the rapid
     * device-switch guard, and (H4) the step-up after repeated failures when
     * `$force` is set. Returns a challenge response array (to send instead of
     * logging in), the seconds to wait when the code-guess budget is used up,
     * or null when the login may proceed. A correct `$code` clears the guard.
     *
     * @return array<string, mixed>|int|null
     */
    private function loginChallenge(User $user, string $code, bool $force = false): array|int|null
    {
        $userId = (int)$user->user_id;
        $events = $this->fetchTable('LoginEvents');
        // Count distinct devices in PHP: a DISTINCT/GROUP BY count subquery
        // trips MySQL's only_full_group_by, and the row set here is tiny.
        $recentHashes = $events->find()
            ->where(['user_id' => $userId, 'created >=' => (new DateTime())->modify('-60 seconds')])
            ->all()
            ->extract('device_hash')
            ->toList();
        $recentDevices = count(array_unique($recentHashes));

        $codes = $this->fetchTable('AuthCodes');
        $pending = $codes->find()
            ->where(['user_id' => $userId, 'purpose' => 'login_challenge', 'expires >' => new DateTime()])
            ->first();

        if (!$force && $recentDevices < self::DEVICE_SWITCH_LIMIT && $pending === null) {
            return null;
        }

        if ($code !== '') {
            $checked = $this->checkCode($userId, 'login_challenge', $code, $this->accountKey($userId));
            if (is_int($checked)) {
                return $checked;
            }
            if ($checked) {
                $events->deleteAll(['user_id' => $userId]);

                return null;
            }
        }

        if ($code === '' && !$pending) {
            $this->sendLimitedCode(
                $user,
                'login_challenge',
                $force
                    ? 'There were several unsuccessful attempts to sign in to your CareerPass account, '
                        . 'so we need to confirm this sign-in.'
                    : 'Your CareerPass account was accessed from several devices very quickly, '
                        . 'so we need to confirm this sign-in.',
            );
        }

        return [
            'success' => false,
            'needsCode' => true,
            'message' => $code !== ''
                ? 'That code is wrong or has expired.'
                : 'For your security, enter the 6-digit code we just emailed you.',
        ];
    }

    /**
     * Sends one email. Picks the transport straight from the environment rather
     * than the Mailer config so there's no config-wiring in the path: Brevo's
     * HTTP API when BREVO_API_KEY is set (Railway blocks outbound SMTP), else an
     * SMTP DSN in EMAIL_TRANSPORT_DEFAULT_URL. With neither, the message is
     * written to the log so email-dependent flows stay testable before mail is
     * wired.
     */
    private function sendMail(string $email, string $subject, string $body): void
    {
        $brevoKey = (string)env('BREVO_API_KEY');
        $smtpDsn = (string)env('EMAIL_TRANSPORT_DEFAULT_URL');

        if ($brevoKey === '' && $smtpDsn === '') {
            Log::warning("[mail] no transport - to $email | $subject | $body");

            return;
        }

        $transport = $brevoKey !== ''
            ? new BrevoTransport(['apiKey' => $brevoKey])
            : new SmtpTransport(TransportFactory::parseDsn($smtpDsn));

        try {
            $message = (new Message())
                ->setFrom(env('EMAIL_FROM', 'no-reply@careerpass.local'))
                ->setTo($email)
                ->setSubject($subject)
                ->setBodyText($body);
            $transport->send($message);
        } catch (Throwable $e) {
            Log::error("[mail] send failed for $email: {$e->getMessage()}");
        }
    }

    /**
     * Emails a one-time code, with $intro saying what it is for.
     */
    private function sendAuthCode(string $email, string $code, string $intro): void
    {
        $body = "$intro\n\nYour code is: $code\n\n"
            . 'It expires in ' . self::CODE_TTL_MINUTES . ' minutes. '
            . "If you didn't request this, you can ignore this email.";

        $this->sendMail($email, 'Your CareerPass verification code', $body);
    }

    /**
     * Generates a fresh 6-digit code for ($userId, $purpose), replacing any
     * earlier one, and returns the plaintext to email.
     *
     * A non-empty `$binding` (the normalized new address, for an email change)
     * is hashed in with the code, so the code only verifies together with that
     * same value: consumeCode() must be given it again.
     */
    private function issueCode(int $userId, string $purpose, string $binding = ''): string
    {
        $codes = $this->fetchTable('AuthCodes');
        $codes->deleteAll(['user_id' => $userId, 'purpose' => $purpose]);

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codes->save($codes->newEntity([
            'user_id' => $userId,
            'purpose' => $purpose,
            'code_hash' => password_hash($this->codeSecret($code, $binding), PASSWORD_DEFAULT),
            'expires' => (new DateTime())->modify('+' . self::CODE_TTL_MINUTES . ' minutes'),
            'attempts' => 0,
            'created' => new DateTime(),
        ]));

        return $code;
    }

    /**
     * What gets hashed for a code: the code alone, or the code plus a SHA-256 of
     * its binding. bcrypt reads only the first 72 bytes, so the binding is
     * digested (6 + 1 + 64 = 71 bytes) rather than appended as is; otherwise
     * the end of a long email address would be silently ignored.
     */
    private function codeSecret(string $code, string $binding): string
    {
        return $binding === '' ? $code : $code . '|' . hash('sha256', $binding);
    }

    /**
     * Checks a submitted code for ($userId, $purpose), with the same `$binding`
     * it was issued with. Consumes (deletes) the row on success unless
     * `$consume` is false; counts the attempt and deletes once exhausted on
     * failure.
     */
    private function consumeCode(
        int $userId,
        string $purpose,
        string $code,
        string $binding = '',
        bool $consume = true,
    ): bool {
        $codes = $this->fetchTable('AuthCodes');
        $row = $codes->find()
            ->where(['user_id' => $userId, 'purpose' => $purpose])
            ->orderBy(['auth_code_id' => 'DESC'])
            ->first();

        if (!$row) {
            return false;
        }

        if ($row->expires->isPast() || $row->attempts >= self::CODE_MAX_ATTEMPTS) {
            $codes->delete($row);

            return false;
        }

        if (!password_verify($this->codeSecret($code, $binding), $row->code_hash)) {
            $row->attempts += 1;
            if ($row->attempts >= self::CODE_MAX_ATTEMPTS) {
                $codes->delete($row);
            } else {
                $codes->save($row);
            }

            return false;
        }

        if ($consume) {
            $codes->delete($row);
        }

        return true;
    }

    /**
     * Emails a fresh `$purpose` code to the account — unless one went out in
     * the last CODE_RESEND_COOLDOWN_SECONDS, or the account has already been
     * sent its daily cap for this purpose in 24 hours (CODE_MAX_SENDS_PER_DAY,
     * or CODE_SENDS_PER_DAY for the purposes that differ). That bounds how
     * much mail anyone can make the inbox receive; guessing is bounded
     * separately by checkCode()'s budget. Every send leaves a code-less
     * `<purpose>_sent` row, so both limits survive issueCode() replacing the
     * code. Callers answer the same way whether or not a code went out.
     *
     * The code goes to `$to` when given (an email change sends to the new
     * address), else to the account's own address. `$binding` is passed on to
     * issueCode().
     */
    private function sendLimitedCode(
        User $user,
        string $purpose,
        string $intro,
        ?string $to = null,
        string $binding = '',
    ): bool {
        $userId = (int)$user->user_id;
        $sentPurpose = $purpose . '_sent';
        $codes = $this->fetchTable('AuthCodes');
        $now = new DateTime();

        $codes->deleteAll([
            'user_id' => $userId,
            'purpose' => $sentPurpose,
            'created <' => $now->modify('-1 day'),
        ]);
        $sent = $codes->find()
            ->where(['user_id' => $userId, 'purpose' => $sentPurpose])
            ->orderBy(['created' => 'DESC', 'auth_code_id' => 'DESC'])
            ->all();
        $last = $sent->first();
        $coolingDown = $last !== null
            && $last->created->greaterThan($now->modify('-' . self::CODE_RESEND_COOLDOWN_SECONDS . ' seconds'));
        $dailyCap = self::CODE_SENDS_PER_DAY[$purpose] ?? self::CODE_MAX_SENDS_PER_DAY;
        if ($coolingDown || $sent->count() >= $dailyCap) {
            return false;
        }

        $codes->saveOrFail($codes->newEntity([
            'user_id' => $userId,
            'purpose' => $sentPurpose,
            'code_hash' => '',
            'expires' => $now->modify('+1 day'),
            'attempts' => 0,
            'created' => $now,
        ]));
        $this->sendAuthCode($to ?? (string)$user->email, $this->issueCode($userId, $purpose, $binding), $intro);

        return true;
    }

    /**
     * B3-L: a Google login whose (Google-verified) email matches an existing
     * **verified** account that isn't linked to this Google account. Google's
     * "verified" flag says who controlled the address when it was checked, not
     * who controls it now, so the link needs an emailed code first:
     *
     * - the account is linked to a *different* Google account → 409, nothing
     *   sent or changed (relinking is never a side effect of signing in);
     * - no `$code` yet → email a `link_google` code (cooldown/cap permitting)
     *   and ask for it;
     * - a wrong/expired/used code → 400;
     * - the right code → attach `google_id`, send a "Google sign-in was added"
     *   notice, and return null so googleLogin() signs in as usual.
     *
     * The second step re-sends the Google credential, which googleLogin()
     * verifies again; nothing about the pending link is stored in the session.
     *
     * @return \Cake\Http\Response|null A response to send instead of signing in, or null once linked.
     */
    private function linkVerifiedAccount(User $user, string $googleId, string $code): ?Response
    {
        $userId = (int)$user->user_id;

        if ($user->google_id !== null && $user->google_id !== '') {
            Log::warning(
                "[users] Google sign-in matched user {$userId} by email, but the account is linked to "
                . 'a different Google account; refused (B3-L)',
            );

            return $this->json([
                'success' => false,
                'message' => 'This account is already connected to a different Google account. '
                    . 'Sign in with that Google account or with your password.',
            ], 409);
        }

        if ($code === '') {
            $this->sendLimitedCode(
                $user,
                self::PURPOSE_LINK_GOOGLE,
                'Someone is connecting Google sign-in to your CareerPass account. '
                . 'If that is you, enter this code to finish.',
            );

            return $this->json([
                'success' => false,
                'needsLinkCode' => true,
                'email' => $user->email,
                'message' => "You already have a CareerPass account with {$user->email}. "
                    . 'To connect Google sign-in, enter the 6-digit code we emailed you.',
            ]);
        }

        $checked = $this->checkCode($userId, self::PURPOSE_LINK_GOOGLE, $code, $this->accountKey($userId));
        if (is_int($checked)) {
            return $this->tooMany($checked);
        }
        if (!$checked) {
            return $this->json(
                ['success' => false, 'message' => 'That code is wrong or has expired. Request a new one.'],
                400,
            );
        }

        $user->google_id = $googleId;
        $this->Users->saveOrFail($user);
        $this->sendMail(
            (string)$user->email,
            'Google sign-in was added to your CareerPass account',
            "Google sign-in was just connected to your CareerPass account ({$user->email}). "
            . "You can now sign in with Google.\n\n"
            . "If this wasn't you, reset your password immediately using \"Forgot password\" on the sign-in screen.",
        );

        return null;
    }

    /**
     * The answer for an account that needs its email verified before it gets a
     * session: the SPA shows a code field and posts the code to /api/verify-email
     * with this `email`.
     *
     * @return array<string, mixed>
     */
    private function needsVerification(User $user, string $message): array
    {
        return [
            'success' => false,
            'needsVerification' => true,
            'email' => $user->email,
            'message' => $message,
        ];
    }

    /**
     * B3-V (d): a Google login whose (Google-verified) email matches an account
     * that never proved its address. Whoever registered that account didn't
     * control the inbox, and Google just showed that this person does, so the
     * account becomes theirs: marked verified and linked to this Google login,
     * with the unproven password, every session (token rotated), every emailed
     * code and the login events removed, and the names replaced by Google's when
     * Google gives them. All of it commits together or not at all.
     */
    private function takeOverUnverifiedAccount(User $user, string $googleId, string $fname, string $lname): void
    {
        $userId = (int)$user->user_id;

        $this->Users->getConnection()->transactional(
            function () use ($user, $userId, $googleId, $fname, $lname): bool {
                $this->fetchTable('AuthCodes')->deleteAll(['user_id' => $userId]);
                $this->fetchTable('LoginEvents')->deleteAll(['user_id' => $userId]);

                $user->email_verified_at = new DateTime();
                $user->user_pass = null;
                $user->session_token = bin2hex(random_bytes(20));
                $user->google_id = $googleId;
                if ($fname !== '') {
                    $user->fname = mb_substr($fname, 0, 50);
                }
                if ($lname !== '') {
                    $user->lname = mb_substr($lname, 0, 50);
                }
                // Throws (and so rolls back the deletes above) if the save fails.
                $this->Users->saveOrFail($user, ['atomic' => false]);

                return true;
            },
        );
    }

    /**
     * Establishes a fresh single-device session for $user: rotates the account's
     * session_token (which invalidates any session on another device), and
     * writes the user + token into this session.
     */
    private function startSession(User $user): void
    {
        // Backstop: every caller checks first, but an account that hasn't proven
        // its email must never get a session (B3-V), whatever path got here.
        if (!$user->isEmailVerified()) {
            throw new LogicException("Refusing to start a session for unverified user {$user->user_id}");
        }

        $token = bin2hex(random_bytes(20));
        $user->session_token = $token;
        $this->Users->save($user);

        $session = $this->request->getSession();
        $session->renew();
        $session->write('Auth.User', $user);
        $session->write('Auth.token', $token);
    }

    /**
     * Password sign-in by username or email. See CLAUDE.md (Authentication) for
     * the escalating delays and step-up (H4), the device-switch challenge and
     * the email-verification step.
     */
    public function login(): Response
    {
        $this->request->allowMethod(['post']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        try {
            // Automatically parses JSON or form data
            $data = $this->request->getData();

            $username = $this->textField($data, 'user_name');
            $password = $this->textField($data, 'user_pass');
            $identifier = $this->loginIdentifier($username);

            $ipWait = $this->limiter()->retryAfter(
                'login_fail_ip',
                $this->ipKey(),
                self::IP_LIMITS['login_fail_ip'],
                true,
            );
            if ($ipWait !== null) {
                return $this->tooMany($ipWait);
            }

            // The frontend advertises "Username or Email" on this field, so match
            // either. The email match ignores letter case (H3).
            $user = $this->Users->find()
                ->where(['user_name' => $username])
                ->first() ?? $this->Users->findByEmailAddress($username);

            // Failures count per account, whichever name was typed, or per
            // typed identifier when there's no such account (H4).
            $failKey = $user !== null
                ? $this->accountKey((int)$user->user_id)
                : RateLimiter::key('id', $identifier);
            $escalation = $this->loginEscalation($failKey);
            if ($escalation['wait'] !== null) {
                return $this->tooMany($escalation['wait']);
            }

            if ($user && $user->user_pass !== null && password_verify($password, $user->user_pass)) {
                // After repeated failures a correct password also needs the
                // emailed code (step-up). It only shows after a correct
                // password, so it reveals nothing about the account.
                $challenge = $user->isEmailVerified()
                    ? $this->loginChallenge(
                        $user,
                        trim($this->textField($data, 'code')),
                        $escalation['stepUp'],
                    )
                    : null;
                if (is_int($challenge)) {
                    return $this->tooMany($challenge);
                }
                if (!$user->isEmailVerified()) {
                    // No session until the email address is proven (B3-V).
                    $this->sendLimitedCode(
                        $user,
                        self::PURPOSE_VERIFY_EMAIL,
                        'Confirm your email address to finish signing in to CareerPass.',
                    );
                    $response = $this->needsVerification(
                        $user,
                        'Confirm your email address to finish signing in. '
                        . "Enter the 6-digit code we emailed to {$user->email}.",
                    );
                } elseif ($challenge !== null) {
                    $response = $challenge;
                } else {
                    // Upgrade the stored hash if PHP's default algorithm/cost has
                    // moved on since it was created. startSession() persists it.
                    if (password_needs_rehash((string)$user->user_pass, PASSWORD_DEFAULT)) {
                        $user->user_pass = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $this->recordLoginEvent((int)$user->user_id);
                    $this->startSession($user);
                    $this->limiter()->clear('login_fail', $failKey);
                    $response = ['success' => true, 'user' => $this->withPurchases($user)];
                }
            } elseif ($user && $user->user_pass === null) {
                // Google-only account: no password was ever set for it. Give the frontend
                // enough to render a personalized "continue with Google to finish setup"
                // prompt instead of a plain error.
                $response = [
                    'success' => false,
                    'needsGoogleSetup' => true,
                    'message' => 'For your security, this account needs a one-time setup. '
                        . 'Continue with Google to finish it.',
                    'account' => [
                        'email' => $user->email,
                        'fname' => $user->fname,
                        'lname' => $user->lname,
                    ],
                ];
            } else {
                // Wrong password, or unknown account: counts toward the
                // escalation and the per-IP limit.
                $this->limiter()->hit('login_fail', $failKey);
                $this->limiter()->hit('login_fail_ip', $this->ipKey(), true);
                $response = ['success' => false, 'message' => 'Invalid username or password'];
            }
        } catch (Throwable $e) {
            return $this->serverError($e);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    /**
     * Google sign-in: verifies the ID token, then finds the account by google_id,
     * then by email, or creates one. Only an email Google has verified is
     * accepted.
     */
    public function googleLogin(GoogleTokenVerifierInterface $verifier): Response
    {
        $this->request->allowMethod(['post']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $limited = $this->ipLimited('google_ip');
        if ($limited !== null) {
            return $limited;
        }

        try {
            $credential = $this->request->getData('credential');
            if (!is_string($credential) || $credential === '') {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Missing credential']));
            }

            $clientId = env('GOOGLE_CLIENT_ID');
            if (empty($clientId)) {
                return $this->response
                    ->withStatus(500)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Google client not configured']));
            }

            try {
                $payload = $verifier->verify($credential, (string)$clientId);
            } catch (InvalidGoogleCredentialException $e) {
                return $this->json(['success' => false, 'message' => 'Invalid Google credential'], 400);
            }

            $googleId = (string)($payload['sub'] ?? '');
            if ($payload === null || $googleId === '') {
                return $this->response
                    ->withStatus(401)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Invalid Google token']));
            }

            $email = trim((string)($payload['email'] ?? ''));
            $fname = trim((string)($payload['given_name'] ?? ''));
            $lname = trim((string)($payload['family_name'] ?? ''));

            // Only an address Google itself has verified can sign in to, create
            // or claim an account here (B3-V).
            if ($email === '' || ($payload['email_verified'] ?? null) !== true) {
                return $this->json([
                    'success' => false,
                    'message' => "Your Google account's email address isn't verified by Google. "
                        . 'Verify it with Google first, or sign up with your email and a password.',
                ], 403);
            }

            $user = $this->Users->find()->where(['google_id' => $googleId])->first();
            $isNewUser = false;

            if (!$user) {
                $user = $this->Users->findByEmailAddress($email);
                if ($user && !$user->isEmailVerified()) {
                    // Registered with this address but never verified: Google
                    // proves who owns it, so the account goes to them (B3-V (d)).
                    // It's left without a password, so treat it like a new
                    // sign-up and offer account setup.
                    $this->takeOverUnverifiedAccount($user, $googleId, $fname, $lname);
                    $isNewUser = true;
                } elseif ($user) {
                    // A verified account not linked to this Google account:
                    // link only after an emailed code (B3-L). Until then — or
                    // if it's linked to another Google account — answer
                    // without signing in.
                    $pending = $this->linkVerifiedAccount(
                        $user,
                        $googleId,
                        trim((string)$this->request->getData('code')),
                    );
                    if ($pending !== null) {
                        return $pending;
                    }
                }
            }

            if (!$user) {
                $userName = strstr($email, '@', true) ?: 'user' . substr($googleId, 0, 8);
                $suffix = 0;
                $baseName = $userName;
                while ($this->Users->find()->where(['user_name' => $userName])->count() > 0) {
                    $suffix++;
                    $userName = $baseName . $suffix;
                }

                $user = $this->Users->newEntity([
                    'fname' => $fname,
                    'lname' => $lname,
                    'email' => $email,
                    'user_name' => $userName,
                ]);
                $user->google_id = $googleId;
                // Google has verified this address (checked above).
                $user->email_verified_at = new DateTime();

                if (!$this->Users->save($user)) {
                    return $this->response
                        ->withStatus(422)
                        ->withType('application/json')
                        ->withStringBody(json_encode(['success' => false, 'errors' => $user->getErrors()]));
                }

                $isNewUser = true;
            }

            $this->startSession($user);
            // Google sign-ins are never challenged, but still count toward the guard.
            $this->recordLoginEvent((int)$user->user_id);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'user' => $this->withPurchases($user),
                    'isNewUser' => $isNewUser,
                ]));
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Lets a logged-in Google-only account (user_pass is null) set a password for the
     * first time, and touch up the profile fields Google login auto-derived. Refuses to
     * run once a password already exists — this is first-time setup, not a change-password
     * flow (which would need the current password).
     *
     * Two-step: the first call (no `code`) emails a verification code and returns
     * `needsCode: true`; the second call must include that `code`.
     */
    public function setupAccount(): Response
    {
        $this->request->allowMethod(['post']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $userId = $this->activeUserId();
        if (!$userId) {
            return $this->notLoggedIn();
        }

        try {
            $user = $this->Users->get($userId);

            if ($user->user_pass !== null) {
                return $this->response
                    ->withStatus(409)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'This account already has a password set.',
                    ]));
            }

            $data = $this->request->getData();
            $password = (string)($data['user_pass'] ?? '');
            $passwordError = $this->passwordError($password);
            if ($passwordError !== null) {
                return $this->json(['success' => false, 'message' => $passwordError], 422);
            }

            $code = trim($this->textField($data, 'code'));
            if ($code === '') {
                $this->sendLimitedCode(
                    $user,
                    'set_password',
                    "Confirm it's you before setting a password on your CareerPass account.",
                );

                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'needsCode' => true,
                        'message' => 'We emailed you a 6-digit code to confirm it\'s you.',
                    ]));
            }
            $checked = $this->checkCode($userId, 'set_password', $code, $this->accountKey($userId));
            if (is_int($checked)) {
                return $this->tooMany($checked);
            }
            if (!$checked) {
                return $this->json(
                    ['success' => false, 'message' => 'That code is wrong or has expired. Request a new one.'],
                    400,
                );
            }

            $patch = [];
            foreach (['fname', 'lname', 'user_name'] as $field) {
                if (!empty($data[$field])) {
                    $patch[$field] = $data[$field];
                }
            }

            $user = $this->Users->patchEntity($user, $patch);
            // Not mass-assignable (see User::$_accessible), so set it directly.
            $user->user_pass = password_hash($password, PASSWORD_DEFAULT);

            if (!$this->Users->save($user)) {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'errors' => $user->getErrors()]));
            }

            $this->request->getSession()->write('Auth.User', $user);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'user' => $user]));
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Updates the logged-in user's profile fields (name, username) from the
     * Settings page. Never the password (setupAccount() / changePassword()) and
     * never the email: a changed `email` gets a 422 pointing at changeEmail(),
     * which re-authenticates and verifies the new address (H3). Sending the
     * current email back unchanged is fine.
     */
    public function updateAccount(): Response
    {
        $this->request->allowMethod(['post', 'put', 'patch']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $userId = $this->activeUserId();
        if (!$userId) {
            return $this->notLoggedIn();
        }

        try {
            $user = $this->Users->get($userId);

            $data = $this->request->getData();
            if (
                array_key_exists('email', $data)
                && UsersTable::normalizeEmail($this->textField($data, 'email'))
                    !== UsersTable::normalizeEmail((string)$user->email)
            ) {
                return $this->json([
                    'success' => false,
                    'message' => "Use 'Change email' in Settings to update your email address.",
                ], 422);
            }

            $patch = [];
            foreach (['fname', 'lname', 'user_name'] as $field) {
                if (array_key_exists($field, $data)) {
                    $patch[$field] = trim($this->textField($data, $field));
                }
            }

            $user = $this->Users->patchEntity($user, $patch);

            if (!$this->Users->save($user)) {
                return $this->json(['success' => false, 'errors' => $user->getErrors()], 422);
            }

            $this->request->getSession()->write('Auth.User', $user);

            return $this->json(['success' => true, 'user' => $user]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Changes the signed-in account's email address (H3). Nothing is stored
     * between steps: every request carries `new_email`, and each code works
     * only for the address it was issued for (see issueCode()).
     *
     * 1. Re-authenticate and get a code at the new address (`needsCode`):
     *    `{new_email, current_password}`; or, for an account without a
     *    password, `{new_email}` (a code to the current address,
     *    `needsCurrentCode`) and then `{new_email, current_code}`.
     * 2. `{new_email, code}`: the new-address code. A `change_email` code is
     *    only ever issued after re-authentication, so it proves both. The
     *    address switches (and counts as verified), other devices are signed
     *    out, outstanding codes are dropped, and both addresses are told.
     */
    public function changeEmail(): Response
    {
        $this->request->allowMethod(['post']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $userId = $this->activeUserId();
        if (!$userId) {
            return $this->notLoggedIn();
        }

        try {
            $user = $this->Users->get($userId);
            $data = $this->request->getData();
            $newEmail = trim($this->textField($data, 'new_email'));

            $invalid = $this->newEmailError($user, $newEmail);
            if ($invalid !== null) {
                return $this->json(['success' => false, 'message' => $invalid], 422);
            }
            $binding = UsersTable::normalizeEmail($newEmail);

            $code = trim($this->textField($data, 'code'));
            if ($code !== '') {
                return $this->confirmEmailChange($user, $newEmail, $code);
            }

            if ($user->password_set) {
                $password = $this->textField($data, 'current_password');
                if ($password === '') {
                    return $this->json(['success' => false, 'message' => 'Enter your current password.'], 422);
                }
                $limited = $this->reauthLimited($userId);
                if ($limited !== null) {
                    return $limited;
                }
                if (!password_verify($password, (string)$user->user_pass)) {
                    $this->recordReauthFailure($user);

                    return $this->json(['success' => false, 'message' => 'Your current password is incorrect.'], 400);
                }
                $this->limiter()->clear('reauth_fail', $this->accountKey($userId));
            } else {
                $currentCode = trim($this->textField($data, 'current_code'));
                if ($currentCode === '') {
                    $this->sendLimitedCode(
                        $user,
                        self::PURPOSE_EMAIL_CHANGE_REAUTH,
                        'Someone (hopefully you) asked to change the email address on your CareerPass account to '
                        . $this->maskEmail($newEmail) . ". Enter this code to confirm it's you.",
                        null,
                        $binding,
                    );

                    $current = $this->maskEmail((string)$user->email);

                    return $this->json([
                        'success' => false,
                        'needsCurrentCode' => true,
                        'email' => $current,
                        'message' => "To confirm it's you, enter the 6-digit code we emailed to {$current}.",
                    ]);
                }
                // Checked, not used up: the user may still need "Send a new
                // code" for the new address, which repeats this request. It's
                // deleted with every other code once the change goes through.
                $checked = $this->checkCode(
                    $userId,
                    self::PURPOSE_EMAIL_CHANGE_REAUTH,
                    $currentCode,
                    $this->accountKey($userId),
                    $binding,
                    false,
                );
                if (is_int($checked)) {
                    return $this->tooMany($checked);
                }
                if (!$checked) {
                    return $this->json(
                        ['success' => false, 'message' => 'That code is wrong or has expired. Request a new one.'],
                        400,
                    );
                }
            }

            $this->sendLimitedCode(
                $user,
                self::PURPOSE_CHANGE_EMAIL,
                "Enter this code to confirm {$newEmail} as the email address for your CareerPass account.",
                $newEmail,
                $binding,
            );

            return $this->json([
                'success' => false,
                'needsCode' => true,
                'email' => $newEmail,
                'message' => "We emailed a 6-digit code to {$newEmail}. Enter it to confirm the change.",
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * The last step of changeEmail(): checks the new-address code, then switches
     * the address in one transaction: marked verified, session_token rotated
     * (other devices signed out; this one is kept), and every outstanding
     * actionable code deleted, e.g. a password-reset code already sent to the
     * old inbox. The `<purpose>_sent` rows (no code in them) stay, so the
     * resend limits still hold. Then both addresses are told.
     */
    private function confirmEmailChange(User $user, string $newEmail, string $code): Response
    {
        $userId = (int)$user->user_id;
        $binding = UsersTable::normalizeEmail($newEmail);

        $checked = $this->checkCode(
            $userId,
            self::PURPOSE_CHANGE_EMAIL,
            $code,
            $this->accountKey($userId),
            $binding,
        );
        if (is_int($checked)) {
            return $this->tooMany($checked);
        }
        if (!$checked) {
            return $this->json(
                ['success' => false, 'message' => 'That code is wrong or has expired. Request a new one.'],
                400,
            );
        }

        $oldEmail = (string)$user->email;
        $token = bin2hex(random_bytes(20));
        $user->email = $newEmail;
        $user->email_verified_at = new DateTime();
        $user->session_token = $token;

        try {
            $saved = $this->Users->getConnection()->transactional(function () use ($user, $userId): bool {
                $this->fetchTable('AuthCodes')->deleteAll(['user_id' => $userId, 'code_hash !=' => '']);

                return $this->Users->save($user, ['atomic' => false]) !== false;
            });
        } catch (PDOException $e) {
            // The unique index caught an account that took the address between
            // the checks and the save.
            if ((string)$e->getCode() !== '23000') {
                throw $e;
            }
            $saved = false;
        }

        if (!$saved) {
            return $this->json(['success' => false, 'message' => 'This email is already registered.'], 422);
        }

        $session = $this->request->getSession();
        $session->write('Auth.token', $token);
        $session->write('Auth.User', $user);

        $this->sendMail(
            $oldEmail,
            'Your CareerPass email address was changed',
            'The email address on your CareerPass account was just changed to '
            . $this->maskEmail($newEmail) . ", and other devices were signed out.\n\n"
            . "If this wasn't you, contact support@career-pass.org.",
        );
        $this->sendMail(
            $newEmail,
            'Your CareerPass email address was updated',
            'This is now the email address for your CareerPass account.',
        );

        return $this->json([
            'success' => true,
            'user' => $this->withPurchases($user),
            'message' => 'Email changed. Other devices were signed out.',
        ]);
    }

    /**
     * Why $newEmail can't become $user's address, or null when it can. Letter
     * case is ignored throughout (see UsersTable::normalizeEmail()).
     */
    private function newEmailError(User $user, string $newEmail): ?string
    {
        if ($newEmail === '' || !Validation::email($newEmail)) {
            return 'Enter a valid email address.';
        }
        if (mb_strlen($newEmail) > 100) {
            return 'Email must be 100 characters or fewer.';
        }
        if (UsersTable::normalizeEmail($newEmail) === UsersTable::normalizeEmail((string)$user->email)) {
            return "That's already your email address.";
        }
        if ($this->Users->emailTaken($newEmail, (int)$user->user_id)) {
            return 'This email is already registered.';
        }

        return null;
    }

    /**
     * `allethrey@gmail.com` → `a***@gmail.com`: enough to recognize, not to
     * read off a borrowed session or someone else's screen.
     */
    private function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1) . '***' . substr($email, $at);
    }

    /**
     * A request field as a string; anything that isn't a scalar (an array, say)
     * counts as empty rather than raising a conversion warning.
     *
     * @param array<mixed> $data Request data.
     */
    private function textField(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * Ends this session.
     */
    public function logout(): Response
    {
        $this->request->allowMethod(['post']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $session = $this->request->getSession();
        $session->delete('Auth.User');
        $session->renew();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true]));
    }

    /**
     * Reports whether this browser is signed in, and as whom.
     */
    public function session(): Response
    {
        $session = $this->request->getSession();
        $wasLoggedIn = $session->read('Auth.User') !== null;

        $userId = $this->activeUserId();
        $user = $userId ? $session->read('Auth.User') : null;
        if ($user) {
            $this->withPurchases($user);
        }
        // Set when this device had a session that is no longer usable, saying
        // why (`signed_in_elsewhere`, or `signed_out` e.g. for a deleted or
        // unverified account) — lets the SPA show the right message.
        $reason = $wasLoggedIn && $user === null ? $this->sessionDenialReason : null;

        return $this->response
            ->withType('application/json')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withStringBody(json_encode([
                'success' => true,
                'loggedIn' => $user !== null,
                'user' => $user,
                'reason' => $reason,
            ]));
    }

    /**
     * Password sign-up. The account starts unverified and gets no session until
     * the emailed code is entered at /api/verify-email. Behind Turnstile and a
     * per-IP limit on new accounts (H4); only accounts actually created count,
     * so a typo or a taken username doesn't use up the allowance.
     */
    public function register(TurnstileVerifierInterface $turnstile): Response
    {
        $this->request->allowMethod(['post']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $failed = $this->turnstileBlocked($turnstile);
        if ($failed !== null) {
            return $failed;
        }
        $ipWait = $this->limiter()->retryAfter('register_ip', $this->ipKey(), self::IP_LIMITS['register_ip'], true);
        if ($ipWait !== null) {
            return $this->tooMany($ipWait);
        }

        try {
            $data = $this->request->getData();

            $plainPassword = (string)($data['user_pass'] ?? '');
            $passwordError = $this->passwordError($plainPassword);
            if ($passwordError !== null) {
                return $this->json(['success' => false, 'message' => $passwordError], 422);
            }

            // Profile fields only — anything else in the request (google_id,
            // email_verified_at, …) is ignored. The account starts unverified.
            $user = $this->Users->newEntity([
                'fname' => $data['fname'] ?? '',
                'lname' => $data['lname'] ?? '',
                'email' => $data['email'] ?? '',
                'user_name' => $data['user_name'] ?? '',
            ]);
            $user->user_pass = password_hash($plainPassword, PASSWORD_DEFAULT);

            if ($this->Users->save($user)) {
                $this->limiter()->hit('register_ip', $this->ipKey(), true);
                // No session until the address is proven: the SPA asks for the
                // emailed code and posts it to /api/verify-email (B3-V).
                $this->sendLimitedCode(
                    $user,
                    self::PURPOSE_VERIFY_EMAIL,
                    'Welcome to CareerPass! Enter this code to confirm your email address '
                    . 'and finish creating your account.',
                );
                $response = $this->needsVerification(
                    $user,
                    "We emailed a 6-digit code to {$user->email}. Enter it to finish creating your account.",
                );
            } else {
                $response = ['success' => false, 'errors' => $user->getErrors()];
            }
        } catch (Throwable $e) {
            return $this->serverError($e);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    /**
     * Attaches the user's active access passes as `purchases` on the entity so
     * the SPA (getPurchases() in reviewers.js) has them without a separate call.
     */
    private function withPurchases(User $user): User
    {
        $user->set('purchases', $this->fetchTable('Passes')->activeForUser((int)$user->user_id));

        return $user;
    }

    /**
     * Validates a plaintext password for every path that sets one (register,
     * setupAccount, resetPassword, changePassword). Returns an error message, or
     * null when the password is acceptable.
     */
    private function passwordError(string $password): ?string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }
        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            return 'Password must be ' . self::MAX_PASSWORD_BYTES . ' characters or fewer.';
        }

        return null;
    }

    /**
     * Logs the real exception server-side and returns a generic 500. Never echo
     * getMessage() to the client — it leaks DB/schema internals.
     */
    private function serverError(Throwable $e): Response
    {
        Log::error('[users] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        return $this->json(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
    }

    /**
     * Step 1 of password recovery: emails a code to the address if it belongs to
     * an account. Always reports success so the endpoint can't be used to probe
     * which emails are registered. Behind Turnstile and a per-IP limit; the
     * code itself goes out at most once a minute and 20 times a day (H4).
     */
    public function forgotPassword(TurnstileVerifierInterface $turnstile): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $failed = $this->turnstileBlocked($turnstile);
        if ($failed !== null) {
            return $failed;
        }
        $limited = $this->ipLimited('forgot_ip');
        if ($limited !== null) {
            return $limited;
        }

        $email = trim($this->textField($this->request->getData(), 'email'));
        $user = $this->Users->findByEmailAddress($email);

        if ($user) {
            $this->sendLimitedCode($user, 'password_reset', 'We got a request to reset your CareerPass password.');
        }

        return $this->json([
            'success' => true,
            'message' => 'If that email has an account, a reset code is on its way.',
        ]);
    }

    /**
     * Step 2 of password recovery: verifies the emailed code and sets the new
     * password. Rotating session_token signs out every existing session.
     * Guesses count against the code-guess budget (an unknown email has its
     * own, so both answer alike) and a per-IP limit; a successful reset also
     * clears the account's sign-in failures, so recovery never leaves the
     * owner waiting (H4).
     */
    public function resetPassword(): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $data = $this->request->getData();
        $email = trim($this->textField($data, 'email'));
        $code = trim($this->textField($data, 'code'));
        $password = $this->textField($data, 'password');

        $passwordError = $this->passwordError($password);
        if ($passwordError !== null) {
            return $this->json(['success' => false, 'message' => $passwordError], 422);
        }

        $limited = $this->ipLimited('reset_ip');
        if ($limited !== null) {
            return $limited;
        }

        $user = $this->Users->findByEmailAddress($email);
        $checked = $this->checkCode(
            $user?->user_id,
            'password_reset',
            $code,
            $this->codeBudgetKey($user, $email),
        );
        if (is_int($checked)) {
            return $this->tooMany($checked);
        }

        if (!$user || !$checked) {
            return $this->json(
                ['success' => false, 'message' => 'That code is wrong or has expired. Request a new one.'],
                400,
            );
        }

        $user->user_pass = password_hash($password, PASSWORD_DEFAULT);
        $user->session_token = bin2hex(random_bytes(20));
        if (!$user->isEmailVerified()) {
            // The emailed reset code proves the address, same as a verify code.
            $user->email_verified_at = new DateTime();
            $this->fetchTable('AuthCodes')->deleteAll([
                'user_id' => (int)$user->user_id,
                'purpose' => self::PURPOSE_VERIFY_EMAIL,
            ]);
        }
        $this->Users->save($user);
        $accountKey = $this->accountKey((int)$user->user_id);
        $this->limiter()->clear('login_fail', $accountKey);
        $this->limiter()->clear('reauth_fail', $accountKey);

        return $this->json([
            'success' => true,
            'message' => 'Password updated. You can sign in now.',
        ]);
    }

    /**
     * Completes sign-up (or a sign-in that was held back) for an account that
     * hasn't proven its email yet: checks the emailed `verify_email` code, marks
     * the address verified and starts the session. A wrong, expired or used
     * code, an unknown email and an already-verified account all get the same
     * answer.
     */
    public function verifyEmail(): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $email = trim($this->textField($this->request->getData(), 'email'));
        $code = trim($this->textField($this->request->getData(), 'code'));

        try {
            $user = $this->Users->findByEmailAddress($email);

            // An already-verified account has no verify code, so it gets the
            // same wrong-code answer (and uses up the same budget).
            $checked = $this->checkCode(
                $user !== null && !$user->isEmailVerified() ? (int)$user->user_id : null,
                self::PURPOSE_VERIFY_EMAIL,
                $code,
                $this->codeBudgetKey($user, $email),
            );
            if (is_int($checked)) {
                return $this->tooMany($checked);
            }
            if ($user === null || !$checked) {
                return $this->json(
                    ['success' => false, 'message' => 'That code is wrong or has expired. Request a new one.'],
                    400,
                );
            }

            $user->email_verified_at = new DateTime();
            $this->recordLoginEvent((int)$user->user_id);
            // startSession() saves the user, persisting email_verified_at too.
            $this->startSession($user);
            $this->limiter()->clear('login_fail', $this->accountKey((int)$user->user_id));

            return $this->json(['success' => true, 'user' => $this->withPurchases($user)]);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Sends a new verification code (replacing the old one) to an account that
     * still needs one, subject to the cooldown and daily cap. Always gives the
     * same answer, so it can't be used to learn which emails are registered or
     * verified.
     */
    public function resendVerification(): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $limited = $this->ipLimited('resend_ip');
        if ($limited !== null) {
            return $limited;
        }

        $email = trim($this->textField($this->request->getData(), 'email'));

        try {
            $user = $this->Users->findByEmailAddress($email);

            if ($user && !$user->isEmailVerified()) {
                $this->sendLimitedCode(
                    $user,
                    self::PURPOSE_VERIFY_EMAIL,
                    'Here is a new code to confirm your email address for CareerPass.',
                );
            }
        } catch (Throwable $e) {
            return $this->serverError($e);
        }

        return $this->json([
            'success' => true,
            'message' => 'If that account still needs verifying, a new code is on its way. '
                . 'You can request a new code once a minute.',
        ]);
    }

    /**
     * Changes the password for a logged-in account that already has one. Needs
     * the current password. Rotates session_token to sign out other devices,
     * but keeps this one signed in, and emails a heads-up.
     */
    public function changePassword(): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $userId = $this->activeUserId();
        if (!$userId) {
            return $this->notLoggedIn();
        }

        $data = $this->request->getData();
        $current = $this->textField($data, 'current_password');
        $next = $this->textField($data, 'new_password');

        $passwordError = $this->passwordError($next);
        if ($passwordError !== null) {
            return $this->json(['success' => false, 'message' => $passwordError], 422);
        }

        $user = $this->Users->get($userId);

        if ($user->user_pass === null) {
            return $this->json(
                ['success' => false, 'message' => 'This account has no password yet — set one from account setup.'],
                409,
            );
        }
        $limited = $this->reauthLimited($userId);
        if ($limited !== null) {
            return $limited;
        }
        if (!password_verify($current, $user->user_pass)) {
            $this->recordReauthFailure($user);

            return $this->json(['success' => false, 'message' => 'Your current password is incorrect.'], 400);
        }
        $this->limiter()->clear('reauth_fail', $this->accountKey($userId));
        if (password_verify($next, $user->user_pass)) {
            return $this->json(
                ['success' => false, 'message' => 'Your new password must be different from the current one.'],
                422,
            );
        }

        $newToken = bin2hex(random_bytes(20));
        $user->user_pass = password_hash($next, PASSWORD_DEFAULT);
        $user->session_token = $newToken;
        $this->Users->save($user);

        // Keep this device signed in; every other session's token is now stale.
        $session = $this->request->getSession();
        $session->write('Auth.token', $newToken);
        $session->write('Auth.User', $user);

        $this->sendMail(
            (string)$user->email,
            'Your CareerPass password was changed',
            "Your CareerPass password was just changed and other devices were signed out.\n\n"
            . "If this wasn't you, reset your password immediately using \"Forgot password\" on the sign-in screen.",
        );

        return $this->json([
            'success' => true,
            'message' => 'Password changed. Other devices have been signed out.',
        ]);
    }
}
