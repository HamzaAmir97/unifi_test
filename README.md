# UniFi Network API — local mock

A local stand-in for the **UniFi Network Integration API** (APEX feature 089).

It answers all **73 requests in 13 groups** of the official Postman collection:

- at the same connector path, `/v1/connector/consoles/{consoleId}/proxy/network/integration/...`;
- with the same `X-API-KEY` authentication;
- with the same list envelope and per-list filters;
- with the documented status codes and content types.

It is stateful, and it has a control plane (`/__mock`) for scenarios, the clock, voucher redemption, faults and the request log.

**Moving to production is an environment switch only.** No request changes.

## 1. Install and run

You need PHP 8.3+ with `pdo_sqlite`, and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate
php artisan mock:reset default
php artisan serve --port=8091
```

Or run `composer setup`, which does all of that except starting the server.

- `curl http://127.0.0.1:8091/` describes the mock.
- `UNIFI_MOCK_API_KEY` in `.env` is the key the mock expects. It defaults to `local-dev-key`, which is also what the Postman mock environment sends. **Never put the real UniFi key here.**
- State lives in `database/database.sqlite` and **survives restarts**. Only a reset replaces it.

> **The dev server is single-threaded.** On Windows, `php artisan serve` handles one request at a time. While an injected delay sleeps, every other request waits, control-plane calls included. Delays are capped by `UNIFI_MOCK_MAX_DELAY_MS` (30 s by default).

## 2. Swagger

Open **http://127.0.0.1:8091/docs**:

1. Click **Authorize** and enter the API key (`local-dev-key`).
2. Call **List Local Sites** and copy its `id` into `siteId`.

The OpenAPI document (`/openapi.json`) is generated on every request from `resources/manifest.json`. That is the same manifest the routes, validation and filters use, so Swagger cannot describe a request the mock does not answer. Each operation shows its filterable properties. The **Mock control plane** tag drives `/__mock` too, without a key.

## 3. Postman

Import from `postman/`:

| File | What it is |
|---|---|
| `UniFi-Network-API.postman_collection.json` | The official collection with its two secrets removed. The API key became `{{apiKey}}` and the console id became `mock-console`. **Every request, parameter, description and example is otherwise unchanged** (`PostmanRequestsIntactTest`). |
| `UniFi-Mock-Control.postman_collection.json` | The `/__mock` control plane, kept separate so the integration collection stays identical to UniFi's. |
| `unifi-mock.postman_environment.json` | Select it to target the mock. |
| `unifi-production.postman_environment.json` | Template for the real API. `apiKey`, `consoleId` and `siteId` are empty. |

**How the unchanged collection runs against the mock.** The collection's requests hold placeholders (`:siteId = <uuid>`, `filter=<string>`, `"name": "<string>"`). Scripts added at collection level fill them at send time from the selected environment; no stored request is touched.

- Path variables come from environment variables of the same name.
- Optional placeholder filters are dropped.
- Body placeholders become valid values.
- On the mock only (`resetBeforeEach = true`, never set in production), every request first resets to the `default` scenario. The collection's own order, such as a delete before a get, therefore cannot break later requests.

Ids are deterministic, so the ids in the mock environment always match the default scenario.

Every request checks its documented status, content type and required fields. Shared checks cover the error envelope and the list envelope.

Run everything from Postman's **Collection Runner** with the `unifi-mock` environment. Or, with Newman (Postman's command-line runner) installed:

```bash
npx newman run postman/UniFi-Network-API.postman_collection.json -e postman/unifi-mock.postman_environment.json
```

**Switching to production:**

- Select `unifi-production` and fill in `apiKey`, `consoleId` and `siteId`.
- The folder **"Read-only checks (safe for production)"** holds every GET request that needs at most a `siteId`, with the same assertions. Any difference between the mock and the real API shows up as a named red test. **Run it only with owner approval (D4).**
- The mock environment also carries mock-only helpers (`controlBase`, `resetBeforeEach` and the scenario ids) that the production template deliberately lacks.

## 4. Scenarios

Reset with `php artisan mock:reset {scenario}` or `POST /__mock/reset?seed={scenario}`. A reset also returns the clock to real time, clears faults and restores the default settings.

| Scenario | Contents |
|---|---|
| `default` | One site with Default, Members, Guest and Lab networks; two WiFi broadcasts (the guest one is the **hotspot**); 6 clients; 5 vouchers (unused, active, full, expired, unlimited); 6 devices (gateway, an MC-LAG core pair, a switch stack, an AP) with derived LAGs; firewall zones and policies; ACL rules; DNS policies; traffic lists; reference data; one device pending adoption |
| `empty` | Nothing at all |
| `multi-site` | Two sites built like `default` |
| `large` | `default` with 300 vouchers, for paging |

All data is invented: RFC 5737 addresses (`192.0.2.x`) and locally administered MACs (`02:…`).

## 5. Control plane (`/__mock`, no key, 404 when `APP_ENV=production`)

| Request | Does |
|---|---|
| `POST /reset?seed=default` · `GET /state` | Load a scenario · read everything |
| `POST /hotspot/redeem {siteId, code}` | A guest types a voucher code at the captive portal. It activates the voucher, counts the guest and creates an authorized guest client. Unknown code 404; expired or full 409. |
| `POST /clock {advanceMinutes}` / `{now}` / `{reset: true}` | Move the clock. Voucher expiry is computed from it on every read. |
| `POST /clients/connect` · `/clients/disconnect` · `POST /devices/pending` | Things the network does on its own |
| `GET/POST/DELETE /faults` | `{match: "GET */hotspot/vouchers", status: 429, times: 2, delayMs?, timeout?}` fails the next N matching integration requests |
| `GET/DELETE /requests` | The request log: newest first, key masked, with warnings (`unknown_enum`, `not_in_collection`, `fault_injected`) |
| `POST /settings {strict?, optionalFields?}` | `strict`: refuse unknown `type`/`action` values instead of warning (D2). `optionalFields`: `omit` (default) or `null` for optional fields with no value (G11). |

**The hotspot lifecycle in eight steps:**

1. List WiFi broadcasts with `filter=hotspotConfiguration.type.eq('HOTSPOT')`.
2. Generate vouchers.
3. List them.
4. `POST /__mock/hotspot/redeem` with a code.
5. Read the voucher, which now has `activatedAt` and `expiresAt`, and the new guest client.
6. Redeem again until the guest limit refuses it.
7. `POST /__mock/clock {"advanceMinutes": 61}`; the voucher now reads `expired: true`.
8. Delete it. A delete without a filter is refused with 400 and deletes nothing.

## 6. What is assumed (read before trusting a green run)

The collection documents no error responses, no filter syntax and few enum values, and 22 of its 73 examples are truncated. Every behaviour chosen where it is silent is one named constant in `app/Mock/Unverified.php`:

```bash
php artisan mock:unverified
```

That prints the register: 35 assumptions with their gap numbers G1–G12. These include:

- the error envelope;
- the filter grammar (for example `and(enabled.eq(true), name.like('Gu*'))`);
- the default and maximum `limit`;
- the 10-digit voucher codes;
- that an unused voucher never expires;
- the console-mismatch status;
- the required and optional field sets, read from the collection's field order;
- the client `access` shape.

The register shrinks only when evidence arrives: the official OpenAPI file, or an approved read-only probe (D4).

## 7. Commands

| Command | Purpose |
|---|---|
| `mock:import-collection {path}` | Copy a collection into `postman/` with the key and console id removed, and write the request snapshot |
| `mock:build-manifest` | Regenerate `resources/manifest.json` from the collection |
| `mock:postman-tests` | (Re)apply the `pm.test` scripts and the read-only folder (idempotent) |
| `mock:build-mock-environment` | Regenerate the mock environment with the default scenario's ids |
| `mock:build-control-collection` | Regenerate the control collection |
| `mock:reset {scenario}` | Load a scenario |
| `mock:unverified` | List the assumptions |

## 8. Tests

```bash
php artisan test
vendor/bin/pint --test
```

The suite includes:

- **`CollectionCoverageTest`:** sends all 73 requests and checks the documented status and content type.
- **`RouteManifestParityTest`:** routes and manifest match one to one, and `/ordering` is declared before `{id}`.
- **`NoSecretsTest`:** proves by structure and shape, without containing either value, that the real key and console id are in no file.
- **`PostmanRequestsIntactTest`:** no request was changed.
- **`PersistenceTest`:** state survives a reconnect to the database file.

## 9. Layout

```
app/Mock/          pure logic: Unverified, Errors, Filter, Pagination, Manifest, Store, Clock,
                   Faults, Hotspot, Devices, Scenarios, Presenters, Validation, OpenApi, Postman
app/Http/          middleware (RecordRequest > ApplyMockClock > VerifyConsole > RequireApiKey >
                   InjectFault), manifest-driven FormRequests, Integration and Control controllers
routes/            integration.php (the 73 routes, explicit) · control.php · web.php (/, /docs)
database/seeders/Scenarios/   default, empty, multi-site, large
resources/manifest.json        generated from the collection, committed
postman/                       the two collections and two environments
```
