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

# Built-in dev server (alternative to XAMPP)
bin/cake server -p 8765
```

### Frontend (`webroot/react-frontend/`)

```bash
npm start          # CRA dev server on http://localhost:3000
npm run build      # production build via WEBPACK (not react-scripts) -> dist/main.js + dist/_redirects
npm test           # react-scripts (Jest) test runner
```

Note the split toolchain: the dev server is `react-scripts`, but the production bundle is built by the custom `webpack.config.js`. `webroot/react-frontend/dist/main.js` is the committed build artifact that Cloudflare Pages serves — rebuild and commit it when frontend source changes. Both `REACT_APP_API_URL` and `REACT_APP_GOOGLE_CLIENT_ID` are injected at build time via `DefinePlugin`.

Styling is Tailwind CSS (utility classes in JSX; no Bootstrap/react-bootstrap). `tailwind.config.js` and `postcss.config.js` live at the frontend root and are picked up by **both** toolchains: `react-scripts` auto-enables Tailwind's PostCSS plugin just by detecting `tailwind.config.js`, and the production `webpack.config.js` runs `postcss-loader` explicitly in its CSS rule — if that loader step is ever removed, the prod build silently ships unstyled markup. `src/index.css` is the Tailwind entry point (`@tailwind base/components/utilities` plus shared `.btn-*`/`.form-*`/`.card` classes under `@layer components`) and must stay imported from `src/index.js`. The brand color is aliased as `brand`/`brand-dark`/`brand-light` in `tailwind.config.js` (`#00D1B2`/`#009E86`/`#CCFFF7` — matches Bulma's default primary turquoise palette) — reuse that token rather than hardcoding the hex again. `src/components/ui/Modal.js` and `MobileMenu.js` are the shared dialog/offcanvas primitives that replaced `react-bootstrap`'s `Modal`/`Offcanvas`; `src/components/ui/Logo.js` is the CareerPass logo mark (teal tile, three ascending bars). The product is branded **CareerPass** (renamed from SkillSprint — see the note on the `skillsprint_user` localStorage key, which was deliberately left alone).

## Architecture

This is a **CakePHP 5.1** application (PHP 8.1+) running under XAMPP. It acts as a JSON API backend consumed by a React frontend at `http://localhost:3000`. The backend is deployable via Docker (to Railway); the frontend deploys to Cloudflare Pages.

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
| GET/POST `/api/topics` | `Api\TopicsController` | `index` / `add` |
| GET/PUT/DELETE `/api/topics/:id` | `Api\TopicsController` | `view` / `edit` / `delete` |
| GET/POST `/api/questions` | `Api\QuestionsController` | `index` / `add` |
| GET/PUT/DELETE `/api/questions/:id` | `Api\QuestionsController` | `view` / `edit` / `delete` |
| GET `/api/questions/practice` | `Api\QuestionsController` | `practice` |

The `practice` endpoint returns randomized questions with shuffled choices; accepts `?topic_id=` and `?limit=` (1–50, default 20). The route for it must be declared **before** `resources('Questions')` in `config/routes.php` to prevent `practice` being matched as an ID.

Controllers in the `Api\` namespace live under `src/Controller/Api/`. Auth/account routes (`login`, `register`, `logout`, `session`, `google-login`, `account/setup`, `account`) are handled by `UsersController` in the root namespace and connected explicitly (not via `resources()`). `updateAccount` (`POST /api/account`) patches profile fields only (name/username/email) for the logged-in user — never the password.

**Dead code — do not edit by mistake:** `src/Controller/Api/LoginController.php` (a legacy `sessionCheck`/`logout`/`options` stub using the removed `RequestHandler` component) and `src/Controller/ApiController.php` (empty) are unrouted leftovers. The live auth logic is entirely in `UsersController`.

### CORS & Middleware

CORS is handled by `CorsMiddleware` (`src/Middleware/CorsMiddleware.php`), added **first** in the `Application.php` middleware queue. It reads the allowed origin from the `FRONTEND_URL` env var (defaults to `http://localhost:3000`) and sets `Access-Control-Allow-Credentials: true`. Preflight `OPTIONS` requests are short-circuited inside the middleware before hitting routing.

`UsersController::login()` additionally slaps on its own `Access-Control-Allow-Origin: *` header — this is a leftover wart that conflicts with the credentialed CORS from the middleware; prefer the middleware and don't copy that pattern into new actions.

**No CSRF middleware is registered at all** — it was removed entirely (not merely skipped for `/api/`) to allow the cross-origin SPA to POST. The full queue is: `CorsMiddleware` → `ErrorHandlerMiddleware` → `AssetMiddleware` → `RoutingMiddleware` → `BodyParserMiddleware`.

**CSRF is instead enforced per-action** by `UsersController::requireAjaxHeader()`, called at the top of every state-changing auth action (`login`, `register`, `logout`, `googleLogin`, `setupAccount`). It 403s any request without `X-Requested-With: XMLHttpRequest`. The reasoning: a plain HTML `<form>` POST (the classic CSRF vector) can't set custom headers, and a cross-origin `fetch`/XHR that does set one triggers a CORS preflight that `CorsMiddleware` only allows for `FRONTEND_URL` — so the header requirement reuses the existing CORS policy as an effective CSRF check. Any new auth action must call this gate; any new frontend caller must go through the `api` axios instance in `webroot/react-frontend/src/api.js` (which sets that header + `withCredentials`), not a bare `axios`/`fetch`.

Session cookies are configured (`config/app.php` → `Session.ini`) as `SameSite=None; Secure; HttpOnly` — required for the cross-origin SPA to send them at all, which is also *why* the header gate above is needed.

### Authentication

Authentication is manual — no CakePHP Auth or Authentication plugin is used. All actions write/read the session key `Auth.User`.

- **`login()`** — `password_verify()` against the stored `user_pass` hash, then `session()->write('Auth.User', $user)`. If the matched user has `user_pass === null` (a Google-only account), it returns `200` with `{ success: false, needsGoogleSetup: true, account: {...} }` so the SPA can prompt "continue with Google to finish setup" instead of showing a generic error.
- **`googleLogin()`** — verifies a Google ID token (`credential` in the POST body) with `google/apiclient`'s `\Google_Client::verifyIdToken()`, using the `GOOGLE_CLIENT_ID` env var. User lookup order: by `google_id`, then by `email` (back-fills `google_id` on the existing row), then creates a new user with a de-duplicated `user_name` derived from the email local-part. On success writes `Auth.User` and returns `isNewUser` so the frontend can route brand-new sign-ups to account setup.
- **`setupAccount()`** — for a logged-in Google-only account (`user_pass` is null): sets a password for the first time (and optionally touches up `fname`/`lname`/`user_name`). 409s if a password already exists — this is first-time setup, **not** a change-password flow. Rewrites `Auth.User` on success.
- **`updateAccount()`** — `POST /api/account`, logged-in + AJAX-header gated. Patches `fname`/`lname`/`user_name`/`email` only (never `user_pass`), re-writes `Auth.User`. Backs the Settings page profile form.
- **`session()`** — returns `{ loggedIn, user }` and sends `Cache-Control: no-store` / `Pragma: no-cache` so the SPA never gets a stale login state.
- **`logout()`** — `session()->delete('Auth.User')` + `session()->renew()` (deliberately *not* `destroy()`, which caused issues in production).

The serialized `user` object **omits `user_pass` and `session_token`** (`$_hidden` on the `User` entity — `user_pass` used to leak the bcrypt hash into every response and into `localStorage`) and adds a virtual **`password_set`** boolean the Settings/AccountSetup UI uses to show account-completion state.

**Email + one-time codes.** `sendAuthCode()` sends via CakePHP's `Mailer` when `EMAIL_TRANSPORT_DEFAULT_URL` is a real SMTP DSN (and `EMAIL_FROM` for the sender); with neither set it **logs the code to `logs/error.log`** instead, so the flows work end-to-end before SMTP is wired. `issueCode()` / `consumeCode()` manage the `auth_codes` rows (6-digit, 10-min TTL, 5 attempts). **Forgot-password** is `POST /api/password/forgot` (always 200, never reveals whether the email exists) → emailed code → `POST /api/password/reset` (`email` + `code` + `password`), which sets the new hash and rotates `session_token` so every existing session is signed out.

**Single-device sessions.** `login()` and `googleLogin()` go through `startSession()`, which rotates `users.session_token` to a fresh random value and stores it in the PHP session as `Auth.token`. `activeUserId()` (used by `session()`, `setupAccount()`, `updateAccount()`) re-reads `session_token` from the DB on every call and returns null — clearing the stale session — when it no longer matches `Auth.token`, i.e. the account has since logged in elsewhere. `session()` then returns `{ loggedIn: false, reason: 'signed_in_elsewhere' }`; the SPA (`index.js`) also re-checks on tab focus/visibility so a superseded device signs out promptly, and shows a banner on the landing page. Sessions predating the column (`session_token` null on both sides) are grandfathered until their next login.

**Frontend auth state lives in `localStorage`** (key `skillsprint_user` — unchanged despite the SkillSprint→CareerPass rebrand; renaming it would silently log everyone out), not cookies — cross-origin session cookies between Cloudflare Pages and Railway were unreliable. `index.js` gates on the localStorage entry first, then confirms against `/api/session` with `withCredentials: true`. The React route `/account-setup` (`src/pages/AccountSetup.js`) is reachable regardless of login state and self-redirects to `/` when there's no stored user.

### Data Model

All tables use non-standard primary keys (not `id`):

- **`users`** — PK: `user_id`. Fields: `fname`/`lname` (max 45), `email` (max 35, unique rule), `user_name` (max 35, unique rule), `user_pass` (bcrypt hash, max 64; null for Google-only accounts), `google_id` (nullable, set by `googleLogin()`), `session_token` (`VARCHAR(64)` nullable — the single-device session guard, see Authentication). **There is no migration for this table** — `users` (and the `google_id` / `session_token` columns) is managed outside `config/Migrations/`; only topics/questions/choices are migrated. To add `session_token` to an existing DB: `ALTER TABLE users ADD COLUMN session_token VARCHAR(64) NULL;`
- **`topics`** — PK: `topic_id`. Fields: `name` (max 100), `description`. `hasMany` Questions.
- **`questions`** — PK: `question_id`. Fields: `topic_id` (FK → topics, CASCADE delete), `question_text`, `difficulty` (1=easy/2=medium/3=hard, constants on `QuestionsTable`), `explanation`. `belongsTo` Topics, `hasMany` Choices (sorted by `sort_order ASC`).
- **`choices`** — PK: `choice_id`. Fields: `question_id` (FK → questions, CASCADE delete), `choice_text`, `is_correct` (boolean), `sort_order`. `belongsTo` Questions.

- **`auth_codes`** — PK: `auth_code_id`. Fields: `user_id` (FK → users, CASCADE), `purpose` (`'password_reset'`, …), `code_hash` (bcrypt of the 6-digit code, `$_hidden`), `expires`, `attempts`. One-time emailed codes; a row is deleted on use, expiry or after 5 bad guesses. `AuthCodesTable` / `AuthCode` entity.
- **`login_events`** — PK: `login_event_id`. Fields: `user_id` (FK → users, CASCADE), `device_hash`, `created`. For the (not-yet-built) rapid-device-switch guard.

Migrations: topics/questions/choices in `20260529000000_CreateTopicsQuestionsChoices.php`; `auth_codes` + `login_events` in `20260907000000_CreateAuthCodes.php`. **`docker-start.sh` does not run migrations**, so on Railway apply new tables/columns by hand:
```sql
CREATE TABLE auth_codes (auth_code_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, purpose VARCHAR(30) NOT NULL, code_hash VARCHAR(255) NOT NULL, expires DATETIME NOT NULL, attempts INT NOT NULL DEFAULT 0, created DATETIME NULL, INDEX (user_id, purpose), FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE);
CREATE TABLE login_events (login_event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, device_hash VARCHAR(64) NOT NULL, created DATETIME NULL, INDEX (user_id, created), FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE);
```

### Configuration

- Backend env: `config/.env` (copy from `config/.env.example`). Key vars: `FRONTEND_URL`, `SECURITY_SALT`, `DATABASE_URL`, `GOOGLE_CLIENT_ID` (read by `googleLogin()`; **not** listed in `.env.example` — add it manually, and set it on Railway). Email is optional: `EMAIL_TRANSPORT_DEFAULT_URL` (an SMTP DSN like `smtp://user:pass@host:587`) + `EMAIL_FROM`; without them, `sendAuthCode()` logs codes to `logs/error.log` instead of sending.
- `config/app_local.php` (not committed — copy from `config/app_local.example.php`) sets the database connection under `Datasources.default`.
- Frontend env: `webroot/react-frontend/.env` (copy from `.env.example`). Key vars: `REACT_APP_API_URL` (defaults to `http://localhost/reviewer_app` in `src/api.js`) and `REACT_APP_GOOGLE_CLIENT_ID` (must match the backend's `GOOGLE_CLIENT_ID`; set it on Cloudflare Pages).
- Test suite defaults to SQLite (`sqlite://127.0.0.1/tmp/tests.sqlite`, set in `config/app_local.php`) unless `DATABASE_TEST_URL` overrides it.
- The default connection's `driver` is `App\Database\Driver\Mysql` (`src/Database/Driver/Mysql.php`), **not** CakePHP's core `Cake\Database\Driver\Mysql`. It's a one-line subclass that populates `RETRY_ERROR_CODES` (2002/2003/2006/2013) so CakePHP's built-in connection-retry actually fires — it absorbs the cold-start race against MySQL on Railway's scale-to-zero trial plan. Keep `config/app.php` pointed at the subclass.

### Testing

Tests live in `tests/TestCase/`, mirroring `src/`. Integration tests use `IntegrationTestTrait`. Fixtures in `tests/Fixture/` define the test schema (`tests/schema.sql` is an empty placeholder). Current coverage is thin: `UsersController`/`UsersTable` plus the default skeleton tests.

### Static Analysis & Code Style

- PHPStan at level 8, analyzing `src/` only (`phpstan.neon`)
- PHPCS uses the `CakePHP` ruleset, applied to `src/` and `tests/` (`phpcs.xml`); return type hints are excluded from controller files

### Deployment

The `Dockerfile` builds a `php:8.2-apache` image with `pdo_mysql`, sets `DocumentRoot` to `webroot/`, and installs Composer deps without dev packages. Intended for Railway (backend) + Cloudflare Pages (frontend) hosting.

`railway.toml` sets `deploy.sleepApplication = true` (trial plan requires scale-to-zero when idle). This is the reason for the retrying MySQL driver above: the first request after an idle period races the app's cold start against MySQL becoming reachable on Railway's private network.
