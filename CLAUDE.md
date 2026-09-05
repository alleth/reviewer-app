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
| GET/POST `/api/topics` | `Api\TopicsController` | `index` / `add` |
| GET/PUT/DELETE `/api/topics/:id` | `Api\TopicsController` | `view` / `edit` / `delete` |
| GET/POST `/api/questions` | `Api\QuestionsController` | `index` / `add` |
| GET/PUT/DELETE `/api/questions/:id` | `Api\QuestionsController` | `view` / `edit` / `delete` |
| GET `/api/questions/practice` | `Api\QuestionsController` | `practice` |

The `practice` endpoint returns randomized questions with shuffled choices; accepts `?topic_id=` and `?limit=` (1–50, default 20). The route for it must be declared **before** `resources('Questions')` in `config/routes.php` to prevent `practice` being matched as an ID.

Controllers in the `Api\` namespace live under `src/Controller/Api/`. Auth routes (`login`, `register`, `logout`, `session`, `google-login`) are handled by `UsersController` in the root namespace and connected explicitly (not via `resources()`).

**Dead code — do not edit by mistake:** `src/Controller/Api/LoginController.php` (a legacy `sessionCheck`/`logout`/`options` stub using the removed `RequestHandler` component) and `src/Controller/ApiController.php` (empty) are unrouted leftovers. The live auth logic is entirely in `UsersController`.

### CORS & Middleware

CORS is handled by `CorsMiddleware` (`src/Middleware/CorsMiddleware.php`), added **first** in the `Application.php` middleware queue. It reads the allowed origin from the `FRONTEND_URL` env var (defaults to `http://localhost:3000`) and sets `Access-Control-Allow-Credentials: true`. Preflight `OPTIONS` requests are short-circuited inside the middleware before hitting routing.

`UsersController::login()` additionally slaps on its own `Access-Control-Allow-Origin: *` header — this is a leftover wart that conflicts with the credentialed CORS from the middleware; prefer the middleware and don't copy that pattern into new actions.

**No CSRF middleware is registered at all** — it was removed entirely (not merely skipped for `/api/`) to allow the cross-origin SPA to POST. The full queue is: `CorsMiddleware` → `ErrorHandlerMiddleware` → `AssetMiddleware` → `RoutingMiddleware` → `BodyParserMiddleware`.

### Authentication

Authentication is manual — no CakePHP Auth or Authentication plugin is used. All actions write/read the session key `Auth.User`.

- **`login()`** — `password_verify()` against the stored `user_pass` hash, then `session()->write('Auth.User', $user)`.
- **`googleLogin()`** — verifies a Google ID token (`credential` in the POST body) with `google/apiclient`'s `\Google_Client::verifyIdToken()`, using the `GOOGLE_CLIENT_ID` env var. User lookup order: by `google_id`, then by `email` (back-fills `google_id` on the existing row), then creates a new user with a de-duplicated `user_name` derived from the email local-part. On success writes `Auth.User`.
- **`session()`** — returns `{ loggedIn, user }` and sends `Cache-Control: no-store` / `Pragma: no-cache` so the SPA never gets a stale login state.
- **`logout()`** — `session()->delete('Auth.User')` + `session()->renew()` (deliberately *not* `destroy()`, which caused issues in production).

**Frontend auth state lives in `localStorage`** (key `skillsprint_user`), not cookies — cross-origin session cookies between Cloudflare Pages and Railway were unreliable. `index.js` gates on the localStorage entry first, then confirms against `/api/session` with `withCredentials: true`.

### Data Model

All tables use non-standard primary keys (not `id`):

- **`users`** — PK: `user_id`. Fields: `fname`/`lname` (max 45), `email` (max 35, unique rule), `user_name` (max 35, unique rule), `user_pass` (bcrypt hash, max 64; null for Google-only accounts), `google_id` (nullable, set by `googleLogin()`). **There is no migration for this table** — `users` (and the `google_id` column) is managed outside `config/Migrations/`; only topics/questions/choices are migrated.
- **`topics`** — PK: `topic_id`. Fields: `name` (max 100), `description`. `hasMany` Questions.
- **`questions`** — PK: `question_id`. Fields: `topic_id` (FK → topics, CASCADE delete), `question_text`, `difficulty` (1=easy/2=medium/3=hard, constants on `QuestionsTable`), `explanation`. `belongsTo` Topics, `hasMany` Choices (sorted by `sort_order ASC`).
- **`choices`** — PK: `choice_id`. Fields: `question_id` (FK → questions, CASCADE delete), `choice_text`, `is_correct` (boolean), `sort_order`. `belongsTo` Questions.

The migration for topics/questions/choices is `config/Migrations/20260529000000_CreateTopicsQuestionsChoices.php`.

### Configuration

- Backend env: `config/.env` (copy from `config/.env.example`). Key vars: `FRONTEND_URL`, `SECURITY_SALT`, `DATABASE_URL`, `GOOGLE_CLIENT_ID` (read by `googleLogin()`; **not** listed in `.env.example` — add it manually, and set it on Railway).
- `config/app_local.php` (not committed — copy from `config/app_local.example.php`) sets the database connection under `Datasources.default`.
- Frontend env: `webroot/react-frontend/.env` (copy from `.env.example`). Key vars: `REACT_APP_API_URL` (defaults to `http://localhost/reviewer_app` in `src/api.js`) and `REACT_APP_GOOGLE_CLIENT_ID` (must match the backend's `GOOGLE_CLIENT_ID`; set it on Cloudflare Pages).
- Test suite defaults to SQLite (`tmp/tests.sqlite`) via `DATABASE_TEST_URL` env var.

### Testing

Tests live in `tests/TestCase/`, mirroring `src/`. Integration tests use `IntegrationTestTrait`. Fixtures in `tests/Fixture/` define the test schema (`tests/schema.sql` is an empty placeholder). Current coverage is thin: `UsersController`/`UsersTable` plus the default skeleton tests.

### Static Analysis & Code Style

- PHPStan at level 8, analyzing `src/` only (`phpstan.neon`)
- PHPCS uses the `CakePHP` ruleset, applied to `src/` and `tests/` (`phpcs.xml`); return type hints are excluded from controller files

### Deployment

The `Dockerfile` builds a `php:8.2-apache` image with `pdo_mysql`, sets `DocumentRoot` to `webroot/`, and installs Composer deps without dev packages. Intended for Railway (backend) + Cloudflare Pages (frontend) hosting.
