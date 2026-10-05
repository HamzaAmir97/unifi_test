# UniFi Network API — local mock

A local stand-in for the **UniFi Network Integration API** (APEX feature 089). It answers the 73 requests of the official Postman collection at the same connector path, with the same `X-API-KEY` authentication and the same response shapes. Moving to production is an environment switch only.

> **Status:** setup (G0). The full guide is completed in G5.

## Install and run

Requires PHP 8.3+ with `pdo_sqlite`, and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate
php artisan serve --port=8091
```

`UNIFI_MOCK_API_KEY` in `.env` is the key the mock expects. Any local value works; **never put the real UniFi key here.**

`curl http://127.0.0.1:8091/` returns a JSON description of the mock.

> **Single-threaded server.** On Windows `php artisan serve` handles one request at a time. While an injected delay sleeps, every other request waits, including control-plane calls.

## Postman

Import from `postman/`:

- `UniFi-Network-API.postman_collection.json` — the official collection, with its secrets removed. The requests are otherwise byte-identical.
- `unifi-mock.postman_environment.json` — select it. Set `apiKey` to the `.env` value and `siteId` to the id returned by **List Local Sites**.
- `unifi-production.postman_environment.json` — a template with an empty key and console id, for later.

Environment variables override the collection's own `baseUrl` and `consoleId`, so selecting an environment is all it takes to switch target.
