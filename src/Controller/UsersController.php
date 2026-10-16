<?php
declare(strict_types=1);

namespace App\Controller;

use App\Auth\GoogleTokenVerifierInterface;
use App\Auth\InvalidGoogleCredentialException;
use App\Mailer\Transport\BrevoTransport;
use App\Model\Entity\User;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Mailer\Message;
use Cake\Mailer\Transport\SmtpTransport;
use Cake\Mailer\TransportFactory;
use LogicException;
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

    /** Failed password attempts per identifier within the window that trip the throttle. */
    private const LOGIN_MAX_FAILURES = 10;
    private const LOGIN_FAILURE_WINDOW_MINUTES = 15;

    /**
     * Email verification (B3-V). `verify_email` rows hold the code itself;
     * `verify_email_sent` rows hold no code, one per email sent, and back the
     * resend limits (a cooldown, and a daily cap that bounds code guessing).
     */
    private const PURPOSE_VERIFY_EMAIL = 'verify_email';
    private const PURPOSE_VERIFY_EMAIL_SENT = 'verify_email_sent';
    private const VERIFY_RESEND_COOLDOWN_SECONDS = 60;
    private const VERIFY_MAX_CODES_PER_DAY = 10;

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
     * Password brute-force throttle. Returns a 429 response when this identifier
     * has had too many failed attempts inside the window, else null. Runs before
     * the password is checked so a locked bucket costs one COUNT query.
     */
    private function loginThrottle(string $identifier): ?Response
    {
        if ($identifier === '') {
            return null;
        }

        $since = (new DateTime())->modify('-' . self::LOGIN_FAILURE_WINDOW_MINUTES . ' minutes');
        $recent = $this->fetchTable('LoginAttempts')->find()
            ->where(['identifier' => $identifier, 'created >=' => $since])
            ->count();

        if ($recent < self::LOGIN_MAX_FAILURES) {
            return null;
        }

        return $this->json([
            'success' => false,
            'message' => 'Too many failed sign-in attempts. Please wait about '
                . self::LOGIN_FAILURE_WINDOW_MINUTES
                . ' minutes and try again, or reset your password.',
        ], 429);
    }

    /** Records one failed login against the identifier and prunes day-old rows. */
    private function recordLoginFailure(string $identifier): void
    {
        if ($identifier === '') {
            return;
        }

        $attempts = $this->fetchTable('LoginAttempts');
        $attempts->save($attempts->newEntity([
            'identifier' => $identifier,
            'created' => new DateTime(),
        ]));
        // Opportunistic prune — keeps the table from growing unbounded without a cron.
        $attempts->deleteAll(['created <' => (new DateTime())->modify('-1 day')]);
    }

    /** Clears every failed-login row for the identifier (called on a correct password). */
    private function clearLoginFailures(string $identifier): void
    {
        if ($identifier === '') {
            return;
        }

        $this->fetchTable('LoginAttempts')->deleteAll(['identifier' => $identifier]);
    }

    /**
     * The rapid device-switch guard. Returns a challenge response array (to send
     * instead of logging in) when this account has been accessed from too many
     * devices in the last minute or already has a pending challenge; returns
     * null when the login may proceed. A correct `$code` clears the guard.
     *
     * @return array<string, mixed>|null
     */
    private function loginChallenge(int $userId, string $email, string $code): ?array
    {
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

        if ($recentDevices < self::DEVICE_SWITCH_LIMIT && $pending === null) {
            return null;
        }

        if ($code !== '' && $this->consumeCode($userId, 'login_challenge', $code)) {
            $events->deleteAll(['user_id' => $userId]);

            return null;
        }

        if ($code === '' && !$pending) {
            $this->sendAuthCode(
                $email,
                $this->issueCode($userId, 'login_challenge'),
                'Your CareerPass account was accessed from several devices very quickly, '
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
     */
    private function issueCode(int $userId, string $purpose): string
    {
        $codes = $this->fetchTable('AuthCodes');
        $codes->deleteAll(['user_id' => $userId, 'purpose' => $purpose]);

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codes->save($codes->newEntity([
            'user_id' => $userId,
            'purpose' => $purpose,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires' => (new DateTime())->modify('+' . self::CODE_TTL_MINUTES . ' minutes'),
            'attempts' => 0,
            'created' => new DateTime(),
        ]));

        return $code;
    }

    /**
     * Checks a submitted code for ($userId, $purpose). Consumes (deletes) the
     * row on success; counts the attempt and deletes once exhausted on failure.
     */
    private function consumeCode(int $userId, string $purpose, string $code): bool
    {
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

        if (!password_verify($code, $row->code_hash)) {
            $row->attempts += 1;
            if ($row->attempts >= self::CODE_MAX_ATTEMPTS) {
                $codes->delete($row);
            } else {
                $codes->save($row);
            }

            return false;
        }

        $codes->delete($row);

        return true;
    }

    /**
     * Emails a fresh `verify_email` code to an unverified account — unless one
     * went out in the last VERIFY_RESEND_COOLDOWN_SECONDS, or the account has
     * already been sent VERIFY_MAX_CODES_PER_DAY in 24 hours (with 5 guesses a
     * code, that caps guessing at 50 a day). Every send leaves a code-less
     * `verify_email_sent` row, so both limits survive issueCode() replacing the
     * code. Callers answer the same way whether or not a code went out.
     */
    private function sendVerificationCode(User $user, string $intro): bool
    {
        $userId = (int)$user->user_id;
        $codes = $this->fetchTable('AuthCodes');
        $now = new DateTime();

        $codes->deleteAll([
            'user_id' => $userId,
            'purpose' => self::PURPOSE_VERIFY_EMAIL_SENT,
            'created <' => $now->modify('-1 day'),
        ]);
        $sent = $codes->find()
            ->where(['user_id' => $userId, 'purpose' => self::PURPOSE_VERIFY_EMAIL_SENT])
            ->orderBy(['created' => 'DESC', 'auth_code_id' => 'DESC'])
            ->all();
        $last = $sent->first();
        $coolingDown = $last !== null
            && $last->created->greaterThan($now->modify('-' . self::VERIFY_RESEND_COOLDOWN_SECONDS . ' seconds'));
        if ($coolingDown || $sent->count() >= self::VERIFY_MAX_CODES_PER_DAY) {
            return false;
        }

        $codes->saveOrFail($codes->newEntity([
            'user_id' => $userId,
            'purpose' => self::PURPOSE_VERIFY_EMAIL_SENT,
            'code_hash' => '',
            'expires' => $now->modify('+1 day'),
            'attempts' => 0,
            'created' => $now,
        ]));
        $this->sendAuthCode((string)$user->email, $this->issueCode($userId, self::PURPOSE_VERIFY_EMAIL), $intro);

        return true;
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
     * Lightweight CSRF gate for the state-changing auth actions. This app has no CSRF
     * middleware (removed entirely so the cross-origin SPA can POST at all) and session
     * cookies are SameSite=None (required for the same reason) — so without this, any
     * other website could trigger these actions using a visitor's session cookie via a
     * plain <form> POST.
     *
     * A plain HTML form cannot set custom request headers, only fetch/XHR can — and a
     * cross-origin fetch/XHR triggers a CORS preflight, which CorsMiddleware only allows
     * for FRONTEND_URL. So requiring this header turns the CORS policy we already have
     * into an effective CSRF check, without needing a separate token scheme.
     *
     * @return \Cake\Http\Response|null A 403 response if the header is missing, null to continue.
     */
    private function requireAjaxHeader(): ?Response
    {
        if ($this->request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            return $this->response
                ->withStatus(403)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Missing required request header']));
        }

        return null;
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
     * The logged-in user's id, or null when there's no session — or when this
     * session has been superseded by a login on another device (in which case
     * the stale session is cleared here). Sessions created before session_token
     * existed are grandfathered in until their next login.
     */
    private function activeUserId(): ?int
    {
        $session = $this->request->getSession();
        $user = $session->read('Auth.User');
        if (!$user) {
            return null;
        }

        $sessionToken = $session->read('Auth.token');
        $current = $this->Users->find()
            ->select(['user_id', 'session_token'])
            ->where(['user_id' => $user->user_id])
            ->first();
        $dbToken = $current?->session_token;

        // Pre-rollout session (neither side has a token yet) — leave it be.
        if ($sessionToken === null && $dbToken === null) {
            return (int)$user->user_id;
        }

        if (!$current || $sessionToken === null || $sessionToken !== $dbToken) {
            $session->delete('Auth.User');
            $session->delete('Auth.token');
            $session->renew();

            return null;
        }

        return (int)$user->user_id;
    }

    /**
     * Password sign-in by username or email. See CLAUDE.md (Authentication) for
     * the throttle, the device-switch challenge and the email-verification step.
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

            $username = $data['user_name'] ?? '';
            $password = (string)($data['user_pass'] ?? '');
            $identifier = $this->loginIdentifier((string)$username);

            $throttled = $this->loginThrottle($identifier);
            if ($throttled !== null) {
                return $throttled;
            }

            // The frontend advertises "Username or Email" on this field, so match either.
            $user = $this->Users->find()
                ->where(['OR' => ['user_name' => $username, 'email' => $username]])
                ->first();

            if ($user && $user->user_pass !== null && password_verify($password, $user->user_pass)) {
                // Correct password — clear the guess counter for this identifier.
                $this->clearLoginFailures($identifier);
                $challenge = $user->isEmailVerified()
                    ? $this->loginChallenge(
                        (int)$user->user_id,
                        (string)$user->email,
                        trim((string)($data['code'] ?? '')),
                    )
                    : null;
                if (!$user->isEmailVerified()) {
                    // No session until the email address is proven (B3-V).
                    $this->sendVerificationCode(
                        $user,
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
                // Wrong password, or unknown account — count it against the identifier.
                $this->recordLoginFailure($identifier);
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
                $user = $this->Users->find()->where(['email' => $email])->first();
                if ($user && !$user->isEmailVerified()) {
                    // Registered with this address but never verified: Google
                    // proves who owns it, so the account goes to them (B3-V (d)).
                    // It's left without a password, so treat it like a new
                    // sign-up and offer account setup.
                    $this->takeOverUnverifiedAccount($user, $googleId, $fname, $lname);
                    $isNewUser = true;
                } elseif ($user) {
                    $user->google_id = $googleId;
                    $this->Users->save($user);
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
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Not logged in']));
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

            $code = trim((string)($data['code'] ?? ''));
            if ($code === '') {
                $this->sendAuthCode(
                    (string)$user->email,
                    $this->issueCode($userId, 'set_password'),
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
            if (!$this->consumeCode($userId, 'set_password', $code)) {
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
     * Updates the logged-in user's profile fields (name, username, email) from
     * the Settings page. Does not touch the password — that's setupAccount() for
     * first-time setup, and a dedicated change-password flow later.
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
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Not logged in']));
        }

        try {
            $user = $this->Users->get($userId);

            $data = $this->request->getData();
            $patch = [];
            foreach (['fname', 'lname', 'user_name', 'email'] as $field) {
                if (array_key_exists($field, $data)) {
                    $patch[$field] = trim((string)$data[$field]);
                }
            }

            $user = $this->Users->patchEntity($user, $patch);

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
        // Set when this device was signed out because the account signed in
        // somewhere else — lets the SPA show a message.
        $supersededElsewhere = $wasLoggedIn && $user === null;

        return $this->response
            ->withType('application/json')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withStringBody(json_encode([
                'success' => true,
                'loggedIn' => $user !== null,
                'user' => $user,
                'reason' => $supersededElsewhere ? 'signed_in_elsewhere' : null,
            ]));
    }

    /**
     * Password sign-up. The account starts unverified and gets no session until
     * the emailed code is entered at /api/verify-email.
     */
    public function register(): Response
    {
        $this->request->allowMethod(['post']);

        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
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
                // No session until the address is proven: the SPA asks for the
                // emailed code and posts it to /api/verify-email (B3-V).
                $this->sendVerificationCode(
                    $user,
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
     * @param array<string, mixed> $body
     */
    private function json(array $body, int $status = 200): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($body));
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
     * which emails are registered.
     */
    public function forgotPassword(): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $email = trim((string)$this->request->getData('email'));
        $user = $email !== ''
            ? $this->Users->find()->where(['email' => $email])->first()
            : null;

        if ($user) {
            $code = $this->issueCode((int)$user->user_id, 'password_reset');
            $this->sendAuthCode(
                $user->email,
                $code,
                'We got a request to reset your CareerPass password.',
            );
        }

        return $this->json([
            'success' => true,
            'message' => 'If that email has an account, a reset code is on its way.',
        ]);
    }

    /**
     * Step 2 of password recovery: verifies the emailed code and sets the new
     * password. Rotating session_token signs out every existing session.
     */
    public function resetPassword(): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $data = $this->request->getData();
        $email = trim((string)($data['email'] ?? ''));
        $code = trim((string)($data['code'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $passwordError = $this->passwordError($password);
        if ($passwordError !== null) {
            return $this->json(['success' => false, 'message' => $passwordError], 422);
        }

        $user = $email !== ''
            ? $this->Users->find()->where(['email' => $email])->first()
            : null;

        if (!$user || !$this->consumeCode((int)$user->user_id, 'password_reset', $code)) {
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

        $email = trim((string)$this->request->getData('email'));
        $code = trim((string)$this->request->getData('code'));

        try {
            $user = $email !== ''
                ? $this->Users->find()->where(['email' => $email])->first()
                : null;

            if (
                !$user
                || $user->isEmailVerified()
                || $code === ''
                || !$this->consumeCode((int)$user->user_id, self::PURPOSE_VERIFY_EMAIL, $code)
            ) {
                return $this->json(
                    ['success' => false, 'message' => 'That code is wrong or has expired. Request a new one.'],
                    400,
                );
            }

            $user->email_verified_at = new DateTime();
            $this->recordLoginEvent((int)$user->user_id);
            // startSession() saves the user, persisting email_verified_at too.
            $this->startSession($user);

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

        $email = trim((string)$this->request->getData('email'));

        try {
            $user = $email !== ''
                ? $this->Users->find()->where(['email' => $email])->first()
                : null;

            if ($user && !$user->isEmailVerified()) {
                $this->sendVerificationCode(
                    $user,
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
            return $this->json(['success' => false, 'message' => 'Not logged in'], 401);
        }

        $data = $this->request->getData();
        $current = (string)($data['current_password'] ?? '');
        $next = (string)($data['new_password'] ?? '');

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
        if (!password_verify($current, $user->user_pass)) {
            return $this->json(['success' => false, 'message' => 'Your current password is incorrect.'], 400);
        }
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
