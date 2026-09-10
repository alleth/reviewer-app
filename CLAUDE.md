# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
composer test
# or
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/TestCase/Controller/UsersControllerTest.php

# Run a single test method
vendor/bin/phpunit --filter testLogin tests/TestCase/Controller/UsersControllerTest.php

# Code style check / fix
composer cs-check
composer cs-fix

# Static analysis (PHPStan level 8)
composer stan

# Database migrations
bin/cake migrations migrate
# (this writes config/Migrations/schema-dump-default.lock — a generated cache
#  of the last-applied schema; it is not gitignored, leave it untracked)

# Code generation (bake)
bin/cake bake

# Bulk-import reviewer content from a CSV (template: resources/careerpass_question_bank_1000_v2.csv)
bin/cake import_questions path/to/questions.csv --dry-run   # validate only
bin/cake import_questions path/to/questions.csv             # append (skips exact dupes)
bin/cake import_questions path/to/questions.csv --fresh --force   # wipe questions+choices first

# Bulk-import per-topic study/explainer content from a CSV (columns: topic, content)
bin/cake import_topic_reviews path/to/topic-reviews.csv --dry-run   # validate only
bin/cake import_topic_reviews path/to/topic-reviews.csv             # upsert (re-running updates content)

# Built-in dev server (alternative to XAMPP)
bin/cake server -p 8765
```

The repo lives under `C:\xampp\htdocs\reviewer_app` and the dev shell is PowerShell. `bin/cake` and `vendor/bin/*` paths above are POSIX-style — on Windows invoke `bin\cake` (there is a `bin\cake.bat`) or `php bin\cake.php`.

### Frontend (`webroot/react-frontend/`)

```bash
npm start          # CRA dev server on http://localhost:3000
npm run build      # production build via WEBPACK (not react-scripts) -> dist/main.[contenthash].js + index.html + dist/_redirects
npm test           # react-scripts (Jest) test runner
```

Note the split toolchain: the dev server is `react-scripts`, but the production bundle is built by the custom `webpack.config.js`. **Cloudflare Pages runs its own `npm run build`** from the connected Git repo (`alleth/reviewer-app`, `master` branch) — the `dist/` folder is `.gitignore`d and *not* committed; whatever Pages builds is what ships. The build uses the **`REACT_APP_API_URL`** and **`REACT_APP_GOOGLE_CLIENT_ID`** values set in the Pages project's env vars (injected via `DefinePlugin`; webpack fallback for the API URL is `http://localhost/reviewer_app`). Production bundle filename is **content-hashed** (`main.[contenthash].js`), and `HtmlWebpackPlugin` writes the matching `<script>` into `dist/index.html` — this replaced a bare `main.js` that browser/ISP/CDN caches kept serving stale after deploys. A **git push to `master`** builds *and* promotes to production; the dashboard "Retry deployment" builds but does **not** promote. Prod: `REACT_APP_API_URL=https://api.career-pass.org` (Railway backend), site served at `https://career-pass.org`.

Styling is Tailwind CSS (utility classes in JSX; no Bootstrap/react-bootstrap). `tailwind.config.js` and `postcss.config.js` live at the frontend root and are picked up by **both** toolchains: `react-scripts` auto-enables Tailwind's PostCSS plugin just by detecting `tailwind.config.js`, and the production `webpack.config.js` runs `postcss-loader` explicitly in its CSS rule — if that loader step is ever removed, the prod build silently ships unstyled markup. `src/index.css` is the Tailwind entry point (`@tailwind base/components/utilities` plus shared `.btn-*`/`.form-*`/`.card` classes under `@layer components`) and must stay imported from `src/index.js`. The brand color is aliased as `brand`/`brand-dark`/`brand-light` in `tailwind.config.js` (`#00D1B2`/`#009E86`/`#CCFFF7` — matches Bulma's default primary turquoise palette) — reuse that token rather than hardcoding the hex again. Dark mode is `darkMode: 'class'`: `src/theme.js` is the micro-store that toggles the `dark` class on `<html>` (saved choice → system preference → light), an inline script in `public/index.html` applies the same resolution pre-paint to avoid a flash (keep the two in sync), and components read it via `useSyncExternalStore`. Style every surface with `dark:` variants. `src/components/ui/Modal.js` and `MobileMenu.js` are the shared dialog/offcanvas primitives that replaced `react-bootstrap`'s `Modal`/`Offcanvas`; `src/components/ui/Logo.js` is the CareerPass logo mark (teal tile, three ascending bars). The product is branded **CareerPass** (renamed from SkillSprint — see the note on the `skillsprint_user` localStorage key, which was deliberately left alone).

## Architecture

This is a **CakePHP 5.1** application (PHP 8.1+) running under XAMPP. It acts as a JSON API backend consumed by a React frontend at `http://localhost:3000`. The backend is deployed via Docker to **Railway** (prod API `https://api.career-pass.org`); the frontend deploys to **Cloudflare Pages** (prod site `https://career-pass.org`, `www.` 301s to apex). Frontend and API are **same-site** in prod (both under `career-pass.org`). See **Deployment** for domains/DNS.

### API Design

| Route | Controller | Action |
|-------|-----------|--------|
| POST `/api/login` | `UsersController` | `login` |
| POST `/api/register` | `UsersController` | `register` |
| GET `/api/session` | `UsersController` | `session` |
| POST `/api/logout` | `UsersController` | `logout` |
| POST `/api/google-login` | `UsersController` | `googleLogin` |
| POST `/api/account/setup` | `UsersController` | `setupAccount` |
| POST/PUT/PATCH `/api/account` | `UsersController` | `updateAccount` |
| POST `/api/password/forgot` | `UsersController` | `forgotPassword` |
| POST `/api/password/reset` | `UsersController` | `resetPassword` |
| POST `/api/password/change` | `UsersController` | `changePassword` |
| POST `/api/checkout` | `PaymentsController` | `checkout` |
| GET `/api/purchases` | `PaymentsController` | `myPasses` |
| GET `/api/billing/history` | `PaymentsController` | `history` |
| POST `/api/xendit/webhook` | `PaymentsController` | `webhook` |
| GET/POST `/api/topics` | `Api\TopicsController` | `index` / `add` |
| GET/PUT/DELETE `/api/topics/:id` | `Api\TopicsController` | `view` / `edit` / `delete` |
| GET/POST `/api/questions` | `Api\QuestionsController` | `index` / `add` |
| GET/PUT/DELETE `/api/questions/:id` | `Api\QuestionsController` | `view` / `edit` / `delete` |
| GET `/api/questions/practice` | `Api\QuestionsController` | `practice` |

The `practice` endpoint returns randomized questions with shuffled choices; accepts `?topic_id=` and `?limit=` (1–50, default 20). The route for it must be declared **before** `resources('Questions')` in `config/routes.php` to prevent `practice` being matched as an ID.

`Api\TopicsController` and `Api\QuestionsController` extend **`App\Controller\Api\AppController`** (a real base class, distinct from the empty dead `src/Controller/ApiController.php`). Its `beforeFilter` gates every request: **reads require the session user to hold an active `paid` pass** (the bank is paid content), and the **write actions `add`/`edit`/`delete` are disabled** (403 — content comes via `bin/cake import_questions`, no admin UI). `is_correct` is still in the practice payload (client-side scoring in the practice screen); a graded mock exam would need a server-side check endpoint. The frontend consumer is `src/pages/Practice.js` (`/practice`, topic picker → question → submit → reveal + explanation → score + review), routed in `Dashboard.js` and linked from `Review.js`.

`TopicsController::view()` (`GET /api/topics/:id`) also contains **`TopicReview`** (a topic's study/explainer write-up — see Data Model), so a topic's page always gets its content in one call. Frontend: `src/pages/TopicReview.js` (`/review/:reviewer/topics/:topicId`, routed in `Dashboard.js`) — the "how this topic is easy to answer" read that `Review.js`'s topic cards link to; it's deliberately *not* another quiz (that's still `/practice`, linked from a CTA at the bottom of the page). Topic content is plain paragraphs (blank-line-separated), not markdown — no markdown renderer is in the frontend's `package.json`.

Controllers in the `Api\` namespace live under `src/Controller/Api/`. Auth/account routes (`login`, `register`, `logout`, `session`, `google-login`, `account/setup`, `account`) are handled by `UsersController` in the root namespace and connected explicitly (not via `resources()`). `updateAccount` (`POST /api/account`) patches profile fields only (name/username/email) for the logged-in user — never the password.

**Dead code — do not edit by mistake:** `src/Controller/Api/LoginController.php` (a legacy `sessionCheck`/`logout`/`options` stub using the removed `RequestHandler` component) and `src/Controller/ApiController.php` (empty) are unrouted leftovers. The live auth logic is entirely in `UsersController`.

### CORS & Middleware

CORS is handled by `CorsMiddleware` (`src/Middleware/CorsMiddleware.php`), added **first** in the `Application.php` middleware queue. It reads **one** allowed origin from the `FRONTEND_URL` env var (dev default `http://localhost:3000`, prod `https://career-pass.org`) — echoed verbatim into `Access-Control-Allow-Origin`, so it must be scheme+host with **no trailing slash** and no path, and there is **no multi-origin support** (a second frontend domain would need a code change). Also sets `Access-Control-Allow-Credentials: true`. Preflight `OPTIONS` requests are short-circuited inside the middleware before hitting routing.

CORS for every action, auth included, comes solely from `CorsMiddleware` — do not add per-action `Access-Control-*` headers (`login()` used to force `Access-Control-Allow-Origin: *`, which conflicts with credentialed CORS; that was removed).

**No CSRF middleware is registered at all** — it was removed entirely (not merely skipped for `/api/`) to allow the cross-origin SPA to POST. The full queue is: `CorsMiddleware` → `ErrorHandlerMiddleware` → `AssetMiddleware` → `RoutingMiddleware` → `BodyParserMiddleware`.

**CSRF is instead enforced per-action** by `UsersController::requireAjaxHeader()`, called at the top of every state-changing auth/account action (`login`, `register`, `logout`, `googleLogin`, `setupAccount`, `updateAccount`, `forgotPassword`, `resetPassword`, `changePassword`). It 403s any request without `X-Requested-With: XMLHttpRequest`. The reasoning: a plain HTML `<form>` POST (the classic CSRF vector) can't set custom headers, and a cross-origin `fetch`/XHR that does set one triggers a CORS preflight that `CorsMiddleware` only allows for `FRONTEND_URL` — so the header requirement reuses the existing CORS policy as an effective CSRF check. Any new auth action must call this gate; any new frontend caller must go through the `api` axios instance in `webroot/react-frontend/src/api.js` (which sets that header + `withCredentials`), not a bare `axios`/`fetch`.

Session cookies are configured (`config/app.php` → `Session.ini`) as `SameSite=None; Secure; HttpOnly` — required for the cross-origin SPA to send them at all, which is also *why* the header gate above is needed.

### Authentication

Authentication is manual — no CakePHP Auth or Authentication plugin is used. All actions write/read the session key `Auth.User`.

- **`login()`** — `password_verify()` against the stored `user_pass` hash, then `session()->write('Auth.User', $user)`. On a successful verify it also runs `password_needs_rehash()` and re-stores the hash if PHP's `PASSWORD_DEFAULT` has since moved on (persisted by the `startSession()` save). If the matched user has `user_pass === null` (a Google-only account), it returns `200` with `{ success: false, needsGoogleSetup: true, account: {...} }` so the SPA can prompt "continue with Google to finish setup" instead of showing a generic error. **Brute-force throttle:** `loginThrottle()` runs before the password check — ≥ `LOGIN_MAX_FAILURES` (10) failed attempts for the same identifier (lowercased username/email) within `LOGIN_FAILURE_WINDOW_MINUTES` (15) returns `429` without checking the password. Each wrong password / unknown account writes a `login_attempts` row (`recordLoginFailure()`, which also prunes day-old rows); a correct password clears that identifier's rows (`clearLoginFailures()`). This is per-identifier only — no per-IP limit, since Railway's proxy makes `REMOTE_ADDR` unreliable without trusted-proxy config.
- **`googleLogin()`** — verifies a Google ID token (`credential` in the POST body) with `google/apiclient`'s `\Google_Client::verifyIdToken()`, using the `GOOGLE_CLIENT_ID` env var. User lookup order: by `google_id`, then by `email` (back-fills `google_id` on the existing row), then creates a new user with a de-duplicated `user_name` derived from the email local-part. On success writes `Auth.User` and returns `isNewUser` so the frontend can route brand-new sign-ups to account setup.
- **`setupAccount()`** — for a logged-in Google-only account (`user_pass` is null): sets a password for the first time (and optionally touches up `fname`/`lname`/`user_name`). 409s if a password already exists. **Two-step**: the first call (no `code`) emails a `set_password` code and returns `{ needsCode: true }`; the second call must include it. Rewrites `Auth.User` on success.
- **`changePassword()`** — `POST /api/password/change`, logged-in + AJAX-gated. Needs `current_password` + `new_password`, which must be different. Rotates `session_token` (signs out other devices) but updates `Auth.token` on the current session so this device stays in, and emails a "password was changed" notice.
- **`updateAccount()`** — `POST /api/account`, logged-in + AJAX-header gated. Patches `fname`/`lname`/`user_name`/`email` only (never `user_pass`), re-writes `Auth.User`. Backs the Settings page profile form.
- **`session()`** — returns `{ loggedIn, user }` and sends `Cache-Control: no-store` / `Pragma: no-cache` so the SPA never gets a stale login state.
- **`logout()`** — `session()->delete('Auth.User')` + `session()->renew()` (deliberately *not* `destroy()`, which caused issues in production).

The serialized `user` object **omits `user_pass` and `session_token`** (`$_hidden` on the `User` entity — `user_pass` used to leak the bcrypt hash into every response and into `localStorage`) and adds a virtual **`password_set`** boolean the Settings/AccountSetup UI uses to show account-completion state.

**Password rules.** Every path that sets a password (`register`, `setupAccount`, `resetPassword`, `changePassword`) runs `UsersController::passwordError()` — min 8, max 72 bytes (bcrypt's silent-truncation limit) — and hashes with `password_hash(PASSWORD_DEFAULT)` (bcrypt). Before this gate existed, `register()` accepted an empty password. The `user_pass` column / validator must stay at **`VARCHAR(255)`** even though bcrypt is 60 chars, so a future `PASSWORD_DEFAULT` (argon2id, ~95 chars) isn't silently truncated. Exception messages from the auth actions are logged server-side via `serverError()` and never returned to the client.

**Email + one-time codes.** `UsersController::sendMail()` picks its transport straight from the environment (not the `Mailer`/`EmailTransport` config, to keep the send path wiring-free): **Brevo's transactional HTTP API** when `BREVO_API_KEY` is set — via `src/Mailer/Transport/BrevoTransport.php`, which POSTs to `https://api.brevo.com/v3/smtp/email` because Railway blocks outbound SMTP — else an SMTP DSN in `EMAIL_TRANSPORT_DEFAULT_URL`, else (neither set) it **`Log::warning`s the message** (`[mail] no transport …`), so code flows stay testable before mail is wired. `EMAIL_FROM` is the sender in all cases. Warning-and-above logs are mirrored to stderr so Railway surfaces them. `config/app.php` also points `EmailTransport.default` at `BrevoTransport` when the key is present, but `sendMail()` does not depend on that. `sendAuthCode()` wraps `sendMail()` for the 6-digit codes; `issueCode()` / `consumeCode()` manage the `auth_codes` rows (6-digit, 10-min TTL, 5 attempts). **Forgot-password** is `POST /api/password/forgot` (always 200, never reveals whether the email exists) → emailed code → `POST /api/password/reset` (`email` + `code` + `password`), which sets the new hash and rotates `session_token` so every existing session is signed out.

**Rapid device-switch guard.** Every completed login writes a `login_events` row (`device_hash` = SHA-256 of the User-Agent). `loginChallenge()` runs on the password path of `login()`: if ≥ `DEVICE_SWITCH_LIMIT` (4) distinct devices logged in within the last 60 s, or a `login_challenge` code is already pending, it emails a code and returns `{ success: false, needsCode: true }` instead of starting a session — the SPA then shows a code field and resubmits `login` with `code`. A correct code clears the guard (deletes that user's `login_events`). `googleLogin()` records events but is never challenged.

**Single-device sessions.** `login()` and `googleLogin()` go through `startSession()`, which rotates `users.session_token` to a fresh random value and stores it in the PHP session as `Auth.token`. `activeUserId()` (used by `session()`, `setupAccount()`, `updateAccount()`) re-reads `session_token` from the DB on every call and returns null — clearing the stale session — when it no longer matches `Auth.token`, i.e. the account has since logged in elsewhere. `session()` then returns `{ loggedIn: false, reason: 'signed_in_elsewhere' }`; the SPA (`index.js`) also re-checks on tab focus/visibility so a superseded device signs out promptly, and shows a banner on the landing page. Sessions predating the column (`session_token` null on both sides) are grandfathered until their next login.

**Frontend auth state lives in `localStorage`** (key `skillsprint_user` — unchanged despite the SkillSprint→CareerPass rebrand; renaming it would silently log everyone out), not cookies. This dates from the old `*.pages.dev` ↔ `*.up.railway.app` setup where cross-**site** `SameSite=None` cookies were unreliable; now that both sides are under `career-pass.org` the session cookie *does* round-trip (login was verified working through it this way), but the localStorage gate is still the primary and `/api/session` is the confirmation. `index.js` gates on the localStorage entry first, then confirms against `/api/session` with `withCredentials: true`, and re-polls every 25s while the tab is visible plus on focus/`visibilitychange`. The React route `/account-setup` (`src/pages/AccountSetup.js`) is reachable regardless of login state and self-redirects to `/` when there's no stored user.

### Payments

Access passes (7 / 30 / 90 days, per **reviewer** — `civil-service` today) are bought through **Xendit's Invoice API** (`src/Payment/Xendit.php`, a thin `Cake\Http\Client` wrapper, Brevo-style). `PaymentsController` (root namespace, manual auth like `UsersController` — it copies `requireAjaxHeader()` / `activeUserId()`):

- **`checkout()`** (`POST /api/checkout`, logged-in + AJAX gate) — body `{ reviewer, plan_id }`. Amount is taken from `App\Payment\Plans` (server-side price list; the frontend `src/plans.js` is display-only and must be kept in sync), never the client. Creates a `pending` `passes` row, calls Xendit for a hosted invoice, returns `{ invoice_url }` for the SPA to redirect to.
- **`webhook()`** (`POST /api/xendit/webhook`, **no session** — only `hash_equals` on the `x-callback-token` header vs `XENDIT_WEBHOOK_TOKEN`) — on `PAID`/`SETTLED` marks the pass `paid` and sets `expires_at` = (existing active expiry for that reviewer, else now) + plan days, superseding the prior active pass so there's always **one active pass per (user, reviewer)**. Idempotent; always 200 except on a bad token.
- **`myPasses()`** (`GET /api/purchases`, logged-in) — the user's active passes, used by the `/checkout/success` poll.
- **`history()`** (`GET /api/billing/history`, logged-in) — every pass the user has paid for (`PassesTable::historyForUser()`). Backs the "Payment history" list in Settings and the printable receipt at `/billing/:reference` (`src/pages/Receipt.js`, full-bleed, `print:` variants; a payment confirmation, explicitly **not** a BIR Official Receipt).

`session()` / `login()` / `googleLogin()` attach a **`purchases`** array to the returned user (`UsersController::withPurchases()` → `PassesTable::activeForUser()`), which is what `getPurchases(user)` in `reviewers.js` reads. `index.js` now rewrites `localStorage['skillsprint_user']` from every `/api/session` poll so passes stay current app-wide. Frontend: `startCheckout()` in `src/api.js`; `src/pages/Checkout.js` is the post-redirect success (polls, then opens the reviewer) / cancel pages, routed in `Dashboard.js`.

Xendit account is in **Test Mode** until the business is verified — the whole flow works with test payment methods; going live is just swapping `XENDIT_SECRET_KEY`.

**SPA route split.** `src/index.js` → `AppWrapper` picks the whole route tree by auth state: logged-out renders `src/App.js` (guest routes only: `/`, `/login`, `/explore`, `/pricing`, everything else `<Navigate to="/">`), logged-in renders `src/pages/Dashboard.js` (which owns the authenticated route tree). `/account-setup` is wired above the split so it renders in both states. So a new guest page goes in `App.js`, a new authenticated page in `Dashboard.js`. `src/pages/*` are the authenticated screens (Settings, Profile, Review, MyLibrary, …); `src/components/*` the shared/landing pieces. `src/old_file/` (`index_old.js`, `App_old.js`) is dead pre-rewrite code — don't edit it.

### Data Model

All tables use non-standard primary keys (not `id`):

- **`users`** — PK: `user_id` (**`INT UNSIGNED`**). Fields: `fname`/`lname` (`VARCHAR(50) NOT NULL`), `email` (`VARCHAR(100) NOT NULL`, DB-level unique — `users_email`), `user_name` (`VARCHAR(50) NOT NULL`, DB-level unique — `users_user_name`), `user_pass` (`password_hash` digest, `VARCHAR(255)` nullable — null for Google-only accounts), `google_id` (`VARCHAR(255)` nullable, DB-level unique — `users_google_id` on Railway, `uniq_google_id` locally, same constraint; set by `googleLogin()`), `session_token` (`VARCHAR(64)` nullable — the single-device session guard, see Authentication). This is the schema **confirmed directly against Railway production** on 2026-09-10 — a prior version of this doc (and of local's actual table) had it wrong: signed `user_id`, nullable/narrower `fname`/`lname`/`email`/`user_name` with no DB-level uniqueness on the latter two, narrower `google_id`. Local was fixed to match production, not the other way around — see the FK signedness note below for how that surfaced. **`users` predates migrations** and already exists for real on every DB (local + Railway) — its migration, `20260528000000_CreateUsers.php`, exists **only** so the SQLite test schema (built from scratch, purely from migrations) has something for `users`-FK migrations (`auth_codes`, `passes`, `topic_reviews`) to reference; it must never actually run against a real database. If a fresh real DB ever needs it (a new environment, a rebuilt Railway MySQL service), run `bin/cake migrations mark_migrated --target 20260528000000 --only` instead of letting `migrate` create it.
- **`topics`** — PK: `topic_id`. Fields: `name` (max 100), `description`. `hasMany` Questions, `hasOne` TopicReviews (exposed on the entity as `$topic->topic_review`, singular — see note below on the association alias).
- **`questions`** — PK: `question_id`. Fields: `topic_id` (FK → topics, CASCADE delete), `question_text`, `difficulty` (1=easy/2=medium/3=hard, constants on `QuestionsTable`), `explanation`. `belongsTo` Topics, `hasMany` Choices (sorted by `sort_order ASC`).
- **`choices`** — PK: `choice_id`. Fields: `question_id` (FK → questions, CASCADE delete), `choice_text`, `is_correct` (boolean), `sort_order`. `belongsTo` Questions.

Content is loaded with **`bin/cake import_questions <file.csv>`** (`src/Command/ImportQuestionsCommand.php`): one row per question — `topic, question, difficulty (easy/medium/hard), explanation, choice_a…choice_e, answer (letter)`. Topics are created on demand; a row whose `(topic, question)` already exists is skipped, so re-running a file is safe. Validation is all-or-nothing. Template + examples: `resources/careerpass_question_bank_1000_v2.csv`.

- **`auth_codes`** — PK: `auth_code_id`. Fields: `user_id` (FK → users, CASCADE), `purpose` (`'password_reset'`, `'set_password'`, `'login_challenge'`), `code_hash` (bcrypt of the 6-digit code, `$_hidden`), `expires`, `attempts`. One-time emailed codes; a row is deleted on use, expiry or after 5 bad guesses. `AuthCodesTable` / `AuthCode` entity.
- **`login_events`** — PK: `login_event_id`. Fields: `user_id` (FK → users, CASCADE), `device_hash`, `created`. Backs the rapid device-switch guard (see Authentication → "Rapid device-switch guard"); a user's rows are deleted when a correct challenge code clears the guard. `LoginEventsTable` / `LoginEvent` entity.
- **`login_attempts`** — PK: `login_attempt_id`. Fields: `identifier` (lowercased submitted username/email — **no users FK**, a guess can name a non-existent account), `created`. Backs the password brute-force throttle in `login()`; rows are cleared on a correct password for that identifier and pruned after a day. `LoginAttemptsTable` / `LoginAttempt` entity.
- **`passes`** — PK: `pass_id`. Fields: `user_id` (FK → users, CASCADE), `reviewer` (`civil-service`), `plan_id` (`7-day`/`30-day`/`90-day`), `amount`, `currency`, `external_id` (unique — our Xendit ref), `xendit_invoice_id`, `status` (`pending`→`paid`/`expired`/`failed`, or `superseded` when a later pass extends it), `paid_at`, `expires_at`, `created`/`modified`. Backs Payments (see the Payments section). `PassesTable` (`activeForUser()`, `activeForReviewer()`) / `Pass` entity.
- **`topic_reviews`** — PK: `topic_review_id`. Fields: `topic_id` (FK → topics, CASCADE, **unique** — one review per topic in v1), `author_id` (nullable FK → users, `SET NULL`; unpopulated in v1 — all content is site-authored; exists now so attributing a write-up to an individual professional later doesn't need another migration — see the "Topics as Review Content" product note), `content` (the study/explainer text). Loaded with `bin/cake import_topic_reviews <file.csv>` (columns: `topic`, `content`; the topic must already exist — this command never creates one). Unlike `import_questions`, re-running the same file *updates* the row instead of skipping it. `TopicReviewsTable` / `TopicReview` entity; the association alias on `Topics` is **`TopicReviews`** (plural, matching this table's class), not `TopicReview` — CakePHP resolves the table name from the alias when no `className` is given, so a singular alias here would (and initially did) look for a nonexistent `topic_review` table.

Migrations: `users` in `20260528000000_CreateUsers.php` (test-schema-only — see the `users` bullet above, **never** run for real); topics/questions/choices in `20260529000000_CreateTopicsQuestionsChoices.php`; `auth_codes` + `login_events` in `20260907000000_CreateAuthCodes.php`; `login_attempts` in `20260908000000_CreateLoginAttempts.php`; `passes` in `20260909000000_CreatePasses.php`; `topic_reviews` in `20260910000000_CreateTopicReviews.php`. **`docker-start.sh` does not run migrations.** As of 2026-09-10 all six are applied on both local and Railway (Railway via `bin/cake migrations mark_migrated --target 20260909000000` for the five that already existed as hand-applied tables, then a real `bin/cake migrations migrate` for `topic_reviews` — verified: `migrations status` shows all `up`, and a live `TopicsTable` query with `contain(['TopicReviews'])` round-tripped correctly). **Before ever running `bin/cake migrations migrate` against a *new* real database for the first time**, run `bin/cake migrations mark_migrated --target 20260528000000 --only` first, or `migrate` will try to `CREATE TABLE users` against a database where it already exists and fail. If new tables/columns are ever hand-applied again instead of migrated (keeping with this app's established pattern), match production's real types — `INT UNSIGNED` for every FK to `users.user_id` (it's unsigned), e.g.:
```sql
CREATE TABLE topic_reviews (topic_review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, topic_id INT UNSIGNED NOT NULL, author_id INT UNSIGNED NULL, content TEXT NOT NULL, created DATETIME NULL, modified DATETIME NULL, UNIQUE KEY (topic_id), FOREIGN KEY (topic_id) REFERENCES topics(topic_id) ON DELETE CASCADE, FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE SET NULL);
```

**`user_id`/`author_id` FK signedness — the full story.** `users.user_id` is `INT UNSIGNED` on production (confirmed directly against Railway) — the *original* `auth_codes`/`login_events`/`passes` migrations, which declared their `user_id` FK column `INT UNSIGNED`, had this right all along. The bug was that **local's `users.user_id` had drifted to plain signed `int(11)`** (unrelated schema rot, not caused by any migration), so those migrations failed locally with `errno 150` ("Foreign key constraint is incorrectly formed") the first time they were actually run there. An earlier pass at fixing this made the FK columns signed instead — which "fixed" it locally but would have broken it against the real (unsigned) `users.user_id` everywhere it mattered, including `topic_reviews.author_id`. That was caught **before** applying anything to Railway (a routine read-only check of production's actual schema surfaced the mismatch) and reverted: all FK columns pointing at `users.user_id`, including `topic_reviews.author_id`, are `UNSIGNED` again; local's `users.user_id` (and the rest of its columns — see the `users` bullet above) was fixed to match production instead. **Lesson embedded here:** when a migration's FK to a legacy, unmigrated table fails, verify the legacy table's *real* definition (all real environments, not just the one at hand) before concluding which side is wrong — local can itself be the drifted one. **`login_attempts` was never affected** — it has no `users` FK by design (see its bullet above).

**`composer test` — fixed, now runs clean from scratch.** The SQLite test schema build (`tests/bootstrap.php` → `(new Migrator())->run()`) used to fail at `CreateAuthCodes` with `no such table: main.users` — a **separate** bug from the signedness one above (confirmed: identical error with or without the signedness fix). Cause: no migration created `users`, so any migration FK-ing to it had nothing to reference under a migrations-built SQLite test DB. Fixed by adding `20260528000000_CreateUsers.php` (see the `users` bullet above) — real databases never run it (`mark_migrated` instead), but the from-scratch SQLite test DB does. Separately, `tests/Fixture/UsersFixture.php` was stale — its columns (`b_day`, `user_location`, `user_type`, …) didn't match the real `users` schema — now uses real columns and a real bcrypt hash. `vendor/bin/phpunit` passes (`OK, but there were issues!` — 6 `markTestIncomplete()` bake stubs, no failures; two default-skeleton tests, `ApplicationTest::testMiddleware` and `PagesControllerTest`'s CSRF tests, were also updated/removed since they asserted the stock skeleton's middleware order and CSRF middleware, both of which this app deliberately changed — see "CORS & Middleware" above).

### Configuration

- Backend env: `config/.env` (copy from `config/.env.example`). Key vars: `FRONTEND_URL`, `SECURITY_SALT`, `DATABASE_URL`, `GOOGLE_CLIENT_ID` (read by `googleLogin()`; **not** listed in `.env.example` — add it manually, and set it on Railway). Email is optional and prefers `BREVO_API_KEY` (Brevo HTTP API — the Railway path, since SMTP is blocked there) over `EMAIL_TRANSPORT_DEFAULT_URL` (an SMTP DSN like `smtp://user:pass@host:587`); set `EMAIL_FROM` with either. With none set, `sendMail()` logs the message instead of sending. None of these are in `.env.example` — add manually and set on Railway.
- **Prod values on Railway:** `FRONTEND_URL=https://career-pass.org`, `EMAIL_FROM=no-reply@career-pass.org` (Brevo domain-authenticated: DKIM + DMARC records live in Cloudflare DNS for `career-pass.org`), `BREVO_API_KEY` set, `GOOGLE_CLIENT_ID` set, `XENDIT_SECRET_KEY` + `XENDIT_WEBHOOK_TOKEN` set (test keys for now). Brevo's *Authorized IPs* restriction (Settings → Security) is **off** for both API and SMTP keys — leave it off: it silently blocks sends when Railway's outbound IP changes, and Gmail's "send as `support@`" SMTP relay needs it off too.
- **Xendit** (`src/Payment/`): `XENDIT_SECRET_KEY` (an `xnd_development_...` key while in Test Mode) and `XENDIT_WEBHOOK_TOKEN` (Xendit dashboard → Settings → Webhooks → verification token). In that same Webhooks page, the *Invoices paid* and *Invoices expired* callback URL must be `https://api.career-pass.org/api/xendit/webhook`. Read via `env()` (Brevo pattern), not `config/app.php`.
- **Google OAuth** (Cloud project `skillsprint-498012`, client "SkillSprint Web Client"): Authorized JavaScript origins must list every frontend origin — currently `https://career-pass.org`, `https://reviewer-app.pages.dev`, `http://localhost:3000`. A new frontend domain that isn't added here gets a silent `403` / "origin not allowed" on the Google button.
- `config/app_local.php` (not committed — copy from `config/app_local.example.php`) sets the database connection under `Datasources.default`.
- Frontend env: local dev reads `webroot/react-frontend/.env` (copy from `.env.example`; `.env` is gitignored). **In prod the Cloudflare Pages project's env vars are the source of truth** (the Pages build injects them, not any committed file): `REACT_APP_API_URL=https://api.career-pass.org` and `REACT_APP_GOOGLE_CLIENT_ID` (must match the backend's `GOOGLE_CLIENT_ID`). `src/api.js` fallback is `http://localhost/reviewer_app`.
- Test suite defaults to SQLite (`sqlite://127.0.0.1/tmp/tests.sqlite`, set in `config/app_local.php`) unless `DATABASE_TEST_URL` overrides it.
- The default connection's `driver` is `App\Database\Driver\Mysql` (`src/Database/Driver/Mysql.php`), **not** CakePHP's core `Cake\Database\Driver\Mysql`. It's a one-line subclass that populates `RETRY_ERROR_CODES` (2002/2003/2006/2013) so CakePHP's built-in connection-retry actually fires — it absorbs the MySQL-not-yet-reachable race during a Railway deploy/restart. Still worth keeping now that scale-to-zero is off. Keep `config/app.php` pointed at the subclass.
- `Error.exceptionRenderer` is `App\Error\AppExceptionRenderer` (`src/Error/AppExceptionRenderer.php`), which catches the "database still asleep" case the retry driver couldn't ride out (a `MissingConnectionException` or a `[2002]/[2003]/[2006]/[2013]` / "Connection refused" / "server has gone away" message anywhere in the chain) and returns a friendly JSON `503` + `Retry-After: 5` instead of leaking a raw SQLSTATE string. All other errors fall through to `WebExceptionRenderer`.

### Testing

Tests live in `tests/TestCase/`, mirroring `src/`. Integration tests use `IntegrationTestTrait`. The test **schema** comes entirely from migrations — `tests/bootstrap.php` runs `(new Migrator())->run()` against SQLite before any test runs (`tests/schema.sql` is an empty placeholder, unused); this is why `users` needed its own migration (test-schema-only, see Data Model → `users`) even though it's otherwise unmigrated. Fixtures in `tests/Fixture/` (e.g. `UsersFixture`) supply **rows**, not schema — they introspect the already-migrated table for column types. Current coverage is thin: `UsersController`/`UsersTable` plus the default skeleton tests, most still `markTestIncomplete()` stubs.

### Static Analysis & Code Style

- PHPStan at level 8, analyzing `src/` only (`phpstan.neon`)
- PHPCS uses the `CakePHP` ruleset, applied to `src/` and `tests/` (`phpcs.xml`); return type hints are excluded from controller files

### Deployment

The `Dockerfile` builds a `php:8.2-apache` image with `pdo_mysql`, sets `DocumentRoot` to `webroot/`, and installs Composer deps without dev packages. `docker-start.sh` is the entrypoint (fixes the Apache MPM conflict → prefork, enables `mod_rewrite`). Railway hosts the backend + a MySQL service; Cloudflare Pages hosts the frontend.

**Railway.** `railway.toml` now sets `deploy.sleepApplication = false` (always-on — no scale-to-zero). This needs the project on a **paid plan** (Hobby+); on the trial's one-time credit, always-on drains it and then the project suspends. The MySQL service has its **own** sleep toggle in its Railway *Settings* (not in `railway.toml`) — that must be disabled too. `docker-start.sh` does **not** run migrations — apply new tables/columns by hand (see **Data Model**). The `AppExceptionRenderer` 503 + retrying MySQL driver still cover the brief window during a deploy/restart.

**Cloudflare.** `career-pass.org` is on **Cloudflare Registrar** (zone already on Cloudflare nameservers — no registrar step). SSL/TLS mode: Full. DNS records:
| Name | Type | Value | Proxy | Purpose |
|------|------|-------|-------|---------|
| `career-pass.org` (apex) | CNAME | `reviewer-app.pages.dev` | Proxied | frontend (Pages) |
| `www` | CNAME | `career-pass.org` | Proxied | a Redirect Rule 301s `www.*` → apex |
| `api` | CNAME | `<svc>.up.railway.app` (from Railway) | **DNS only** | backend; Railway terminates TLS, proxying breaks its Let's Encrypt renewal |
| `_railway-verify.api` | TXT | Railway domain-verification token | DNS only | Railway custom-domain check |
| `brevo1._domainkey`, `brevo2._domainkey` | CNAME | `b{1,2}.career-pass-org.dkim.brevo.com` | DNS only | Brevo DKIM (outbound `no-reply@`) |
| `_dmarc` | TXT | `v=DMARC1; p=none; rua=…` (Brevo) | — | DMARC policy |
| `@` | TXT | `brevo-code:…` **and** `v=spf1 include:_spf.mx.cloudflare.net ~all` | — | Brevo domain verification + SPF for Email Routing |
| `@` | MX ×3 | `route1/2/3.mx.cloudflare.net` | — | **Cloudflare Email Routing** — inbound, managed/locked by CF |

**Email**: outbound (`no-reply@career-pass.org`, verification codes/notices) goes through Brevo's API from Railway. Inbound `support@career-pass.org` is a **Cloudflare Email Routing** rule that forwards to a personal Gmail (which is also set up to *send as* `support@` via Brevo SMTP relay). The MX/SPF rows above are Email-Routing's and are inbound-only — they don't affect Brevo sending.

Cloudflare Pages: Git-connected to `alleth/reviewer-app` `master`, runs its own build (see **Frontend**). A push to `master` deploys + promotes; "Retry deployment" builds without promoting.
