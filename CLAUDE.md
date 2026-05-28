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

This is a **CakePHP 5.1** application (PHP 8.1+) running under XAMPP. It acts as a JSON API backend consumed by a React frontend at `http://localhost:3000`.

### API Design

The app exposes only two routes at present:

| Route | Controller | Action |
|-------|-----------|--------|
| POST `/api/login` | `UsersController` | `login` |
| POST `/api/register` | `UsersController` | `register` |

Routes are defined in `config/routes.php`. The `Api\LoginController` also exists under `src/Controller/Api/` but is not wired to any route yet.

### CORS

CORS is handled in `AppController::beforeFilter()`, **not** via `CorsMiddleware` (which exists in `src/Middleware/CorsMiddleware.php` but is not registered in `Application.php`). The allowed origin is hardcoded to `http://localhost:3000`. Preflight `OPTIONS` requests are short-circuited with `exit()` in `beforeFilter`.

### Authentication

Authentication is manual — no CakePHP Auth or Authentication plugin is used. `UsersController::login()` calls `password_verify()` against the stored hash and writes the user to `$this->request->getSession()->write('Auth.User', $user)`. Logout destroys the session.

### Data Model

The `users` table uses a non-standard primary key `user_id` (not `id`). Fields: `user_id`, `fname`, `lname`, `email`, `user_name`, `user_pass`. Passwords are stored as `password_hash(..., PASSWORD_DEFAULT)` hashes with a max length of 64 characters in the validation (note: bcrypt hashes are 60 chars).

### Configuration

- Environment-specific config: `config/app_local.php` (not committed — copy from `config/app_local.example.php`)
- Database connection is set in `config/app_local.php` under `Datasources.default`
- Test suite defaults to SQLite (`tmp/tests.sqlite`) via `DATABASE_TEST_URL` env var

### Testing

Tests live in `tests/TestCase/`, mirroring `src/`. Integration tests use `IntegrationTestTrait`. Fixtures are in `tests/Fixture/`. The test schema is in `tests/schema.sql`.

### Static Analysis & Code Style

- PHPStan at level 8, analyzing `src/` only (`phpstan.neon`)
- PHPCS uses the `CakePHP` ruleset, applied to `src/` and `tests/` (`phpcs.xml`); return type hints are excluded from controller files
