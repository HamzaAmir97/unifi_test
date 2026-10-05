<?php

return [
    /*
     * The key every integration request must send as X-API-KEY.
     * An empty value refuses every request (never "no key = open").
     */
    'api_key' => env('UNIFI_MOCK_API_KEY', ''),

    'console_id' => env('UNIFI_MOCK_CONSOLE_ID', 'mock-console'),

    'app_version' => env('UNIFI_MOCK_APP_VERSION', '9.1.0'),

    /* D2: refuse unknown type/action values instead of warning. */
    'strict' => (bool) env('UNIFI_MOCK_STRICT', false),

    /* G11: "omit" or "null". */
    'optional_fields' => env('UNIFI_MOCK_OPTIONAL_FIELDS', 'omit'),

    /* R12: cap on an injected fault delay; the dev server is single-threaded. */
    'max_delay_ms' => (int) env('UNIFI_MOCK_MAX_DELAY_MS', 30000),

    /* FR-020: newest request-log rows kept. */
    'log_keep' => (int) env('UNIFI_MOCK_LOG_KEEP', 500),

    /* The prefix every integration route sits under (R4). */
    'integration_prefix' => 'v1/connector/consoles/{consoleId}/proxy/network/integration',
];
