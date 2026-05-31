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

## Architecture

This is a **CakePHP 5.1** application (PHP 8.1+) running under XAMPP. It acts as a JSON API backend consumed by a React frontend at `http://localhost:3000`. The backend is deployable via Docker (to Railway); the frontend deploys to Cloudflare Pages.

### API Design

| Route | Controller | Action |
|-------|-----------|--------|
| POST `/api/login` | `UsersController` | `login` |
| POST `/api/register` | `UsersController` | `register` |
| GET `/api/session` | `UsersController` | `session` |
| POST `/api/logout` | `UsersController` | `logout` |
| GET/POST `/api/topics` | `Api\TopicsController` | `index` / `add` |
| GET/PUT/DELETE `/api/topics/:id` | `Api\TopicsController` | `view` / `edit` / `delete` |
| GET/POST `/api/questions` | `Api\QuestionsController` | `index` / `add` |
| GET/PUT/DELETE `/api/questions/:id` | `Api\QuestionsController` | `view` / `edit` / `delete` |
| GET `/api/questions/practice` | `Api\QuestionsController` | `practice` |

The `practice` endpoint returns randomized questions with shuffled choices; accepts `?topic_id=` and `?limit=` (1–50, default 20). The route for it must be declared **before** `resources('Questions')` in `config/routes.php` to prevent `practice` being matched as an ID.

Controllers in the `Api\` namespace live under `src/Controller/Api/`. Auth routes (`login`, `register`, `logout`, `session`) are handled by `UsersController` in the root namespace and connected explicitly (not via `resources()`).

### CORS & Middleware

CORS is handled by `CorsMiddleware` (`src/Middleware/CorsMiddleware.php`), which **is** registered in `Application.php`. It reads the allowed origin from the `FRONTEND_URL` env var (defaults to `http://localhost:3000`). Preflight `OPTIONS` requests are short-circuited inside the middleware before hitting routing.

CSRF protection is enabled globally but skipped for all paths starting with `/api/` via `skipCheckCallback` in `Application.php`.

### Authentication

Authentication is manual — no CakePHP Auth or Authentication plugin is used. `UsersController::login()` calls `password_verify()` against the stored hash and writes the user to `$this->request->getSession()->write('Auth.User', $user)`. `session()` reads that value to let the frontend check login state. `logout()` destroys the session.

### Data Model

All tables use non-standard primary keys (not `id`):

- **`users`** — PK: `user_id`. Fields: `fname`, `lname`, `email`, `user_name`, `user_pass` (bcrypt hash, max 64 chars in validation).
- **`topics`** — PK: `topic_id`. Fields: `name` (max 100), `description`. `hasMany` Questions.
- **`questions`** — PK: `question_id`. Fields: `topic_id` (FK → topics, CASCADE delete), `question_text`, `difficulty` (1=easy/2=medium/3=hard, constants on `QuestionsTable`), `explanation`. `belongsTo` Topics, `hasMany` Choices (sorted by `sort_order ASC`).
- **`choices`** — PK: `choice_id`. Fields: `question_id` (FK → questions, CASCADE delete), `choice_text`, `is_correct` (boolean), `sort_order`. `belongsTo` Questions.

The migration for topics/questions/choices is `config/Migrations/20260529000000_CreateTopicsQuestionsChoices.php`.

### Configuration

- Backend env: `config/.env` (copy from `config/.env.example`). Key vars: `FRONTEND_URL`, `SECURITY_SALT`, `DATABASE_URL`.
- `config/app_local.php` (not committed — copy from `config/app_local.example.php`) sets the database connection under `Datasources.default`.
- Frontend env: `webroot/react-frontend/.env` (copy from `.env.example`). Key var: `REACT_APP_API_URL` (defaults to `http://localhost/reviewer_app` in `src/api.js`).
- Test suite defaults to SQLite (`tmp/tests.sqlite`) via `DATABASE_TEST_URL` env var.

### Testing

Tests live in `tests/TestCase/`, mirroring `src/`. Integration tests use `IntegrationTestTrait`. Fixtures are in `tests/Fixture/`. The test schema is in `tests/schema.sql`.

### Static Analysis & Code Style

- PHPStan at level 8, analyzing `src/` only (`phpstan.neon`)
- PHPCS uses the `CakePHP` ruleset, applied to `src/` and `tests/` (`phpcs.xml`); return type hints are excluded from controller files

### Deployment

The `Dockerfile` builds a `php:8.2-apache` image with `pdo_mysql`, sets `DocumentRoot` to `webroot/`, and installs Composer deps without dev packages. Intended for Railway (backend) + Cloudflare Pages (frontend) hosting.
